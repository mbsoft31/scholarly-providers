<?php

declare(strict_types=1);

namespace Scholarly\Core;

use JsonException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Random\RandomException;
use Scholarly\Core\Exceptions\ClientException;
use Scholarly\Core\Exceptions\DefaultException;
use Scholarly\Core\Exceptions\NotFoundException;
use Scholarly\Core\Exceptions\RateLimitException;
use Scholarly\Core\Exceptions\ServerException;
use Scholarly\Core\Exceptions\TransportException;
use Throwable;

use function array_change_key_case;
use function array_filter;
use function array_key_exists;
use function array_values;
use function ctype_digit;
use function http_build_query;
use function json_decode;
use function json_encode;
use function ksort;
use function parse_str;
use function strtolower;
use function strtotime;
use function time;
use function trim;

/**
 * Shared HTTP client wrapper handling retries, logging, and caching.
 */
class Client
{
    private const int MAX_ATTEMPTS = 3;

    /** @var callable|null */
    private $policy;

    private LoggerInterface $logger;

    /**
     * @var array<string, mixed>
     */
    private array $lastResponseHeaders = [];

    public function __construct(
        private readonly ClientInterface         $http,
        private readonly UriFactoryInterface     $uriFactory,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface  $streamFactory,
        ?callable                                $policy = null,
        ?LoggerInterface                         $logger = null,
        private ?Backoff                         $backoff = null,
        private readonly ?CacheLayer             $cache = null,
    ) {
        $this->policy  = $policy;
        $this->logger  = $logger  ?? new NullLogger();
        $this->backoff = $backoff ?? new Backoff();
    }

    /**
     * @param array<string, mixed> $query
     * @param array<string, string|string[]> $headers
     *
     * @return array<string, mixed>
     * @throws Throwable
     */
    public function get(string $url, array $query = [], array $headers = [], ?string $cacheContext = 'detail'): array
    {
        $hasAuth = $this->hasAuthHeader($headers);

        if ($cacheContext !== null && $this->cache?->isEnabled()) {
            $ttl = $this->cache->getTtlHint($cacheContext);
            $key = $this->cache->buildKey('GET', $url, $query, null, $hasAuth);

            return $this->cache->remember($key, function () use ($url, $query, $headers) {
                return $this->dispatch('GET', $url, $query, null, $headers);
            }, $ttl);
        }

        return $this->dispatch('GET', $url, $query, null, $headers);
    }

    /**
     * @param array<string, mixed>|string|null $payload
     * @param array<string, mixed> $query
     * @param array<string, string|string[]> $headers
     *
     * @return array<string, mixed>
     * @throws JsonException|Throwable
     */
    public function post(
        string $url,
        array|string|null $payload = null,
        array $query = [],
        array $headers = [],
        ?string $cacheContext = null,
    ): array {
        $hasAuth = $this->hasAuthHeader($headers);

        if ($cacheContext !== null && $this->cache?->isEnabled()) {
            $ttl = $this->cache->getTtlHint($cacheContext);
            $key = $this->cache->buildKey('POST', $url, $query, $payload, $hasAuth);

            return $this->cache->remember($key, function () use ($url, $payload, $query, $headers) {
                return $this->dispatch('POST', $url, $query, $payload, $headers);
            }, $ttl);
        }

        return $this->dispatch('POST', $url, $query, $payload, $headers);
    }

    public function getCache(): ?CacheLayer
    {
        return $this->cache;
    }

    /**
     * @return array<string, mixed>
     */
    public function lastResponseHeaders(): array
    {
        return $this->lastResponseHeaders;
    }

    public function log(string $message, array $context = []): void
    {
        $this->logger->info($message, $context);
    }

    /**
     * @param array<string, mixed>|string|null $payload
     * @param array<string, mixed> $query
     * @param array<string, string|string[]> $headers
     *
     * @return array<string, mixed>
     * @throws Throwable
     */
    private function dispatch(string $method, string $url, array $query, array|string|null $payload, array $headers): array
    {
        $attempt       = 0;
        $lastException = null;
        $this->storeResponse(null);

        while ($attempt < self::MAX_ATTEMPTS) {
            try {
                $request  = $this->prepareRequest($method, $url, $query, $payload, $headers);
                $response = $this->http->sendRequest($request);
                $this->storeResponse($response);

                $status = $response->getStatusCode();

                if ($status >= 200 && $status < 300) {
                    return $this->decodeResponse($response);
                }

                $exception = $this->mapException($response);

                if (! $this->shouldRetry($status, $attempt)) {
                    throw $exception;
                }

                $lastException = $exception;
                $this->sleepBeforeRetry($exception, $attempt + 1);
                $attempt++;
                continue;
            } catch (RateLimitException $exception) {
                $lastException = $exception;

                if (! $this->shouldRetry(429, $attempt)) {
                    throw $exception;
                }

                $this->sleepBeforeRetry($exception, $attempt + 1);
                $attempt++;
            } catch (ServerException $exception) {
                $lastException = $exception;

                if (! $this->shouldRetry(500, $attempt)) {
                    throw $exception;
                }

                $this->sleepBeforeRetry($exception, $attempt + 1);
                $attempt++;
            } catch (ClientExceptionInterface $exception) {
                $this->storeResponse(null);

                throw new TransportException($exception->getMessage(), $exception->getCode(), null, $exception);
            }
        }

        if ($lastException instanceof Throwable) {
            throw $lastException;
        }

        throw new DefaultException('Request failed after retries.');
    }

    /**
     * @param array<string, mixed>|string|null $payload
     * @param array<string, mixed> $query
     * @param array<string, string|string[]> $headers
     */
    private function prepareRequest(string $method, string $url, array $query, array|string|null $payload, array $headers): RequestInterface
    {
        $uri     = $this->buildUri($url, $query);
        $request = $this->requestFactory->createRequest($method, $uri);

        foreach ($this->normalizeHeaders($headers) as $name => $values) {
            $request = $request->withHeader($name, $values);
        }

        if ($payload !== null) {
            if (is_array($payload)) {
                try {
                    $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
                } catch (JsonException $exception) {
                    throw new TransportException('Failed encoding JSON payload: ' . $exception->getMessage(), 0, null, $exception);
                }
            } else {
                $body = $payload;
            }

            $request = $request->withBody($this->streamFactory->createStream($body));

            if (is_array($payload) && ! $request->hasHeader('Content-Type')) {
                $request = $request->withHeader('Content-Type', 'application/json');
            }
        }

        if ($this->policy !== null) {
            $request = ($this->policy)($request);
        }

        return $request;
    }

    /**
     * @param array<string, mixed> $query
     */
    private function buildUri(string $url, array $query): string
    {
        $uri      = $this->uriFactory->createUri($url);
        $existing = [];

        if ($uri->getQuery() !== '') {
            parse_str($uri->getQuery(), $existing);
        }

        $merged = [];

        foreach (array_filter($existing, static fn ($value) => $value !== null && $value !== '') as $key => $value) {
            $merged[$key] = $value;
        }

        foreach ($query as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $merged[$key] = $value;
        }

        ksort($merged);

        return $uri->withQuery(
            http_build_query($merged, '', '&', PHP_QUERY_RFC3986)
        )->__toString();
    }

    private function decodeResponse(ResponseInterface $response): array
    {
        $body = (string) $response->getBody();

        if ($body === '') {
            return [];
        }

        try {
            return json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new TransportException('Failed decoding JSON payload: ' . $exception->getMessage(), 0, $response, $exception);
        }
    }

    private function mapException(ResponseInterface $response): Throwable
    {
        $status  = $response->getStatusCode();
        $message = $response->getReasonPhrase();

        return match (true) {
            $status === 404                 => new NotFoundException($message ?: 'Not Found', $status, $response),
            $status === 429                 => new RateLimitException($message ?: 'Too Many Requests', $status, $this->extractRetryAfter($response), $response),
            $status >= 400 && $status < 500 => new ClientException($message ?: 'Client Error', $status, $response),
            $status >= 500                  => new ServerException($message ?: 'Server Error', $status, $response),
            default                         => new DefaultException($message ?: 'Unexpected response status', $status, $response),
        };
    }

    private function extractRetryAfter(ResponseInterface $response): ?int
    {
        $values = $response->getHeader('Retry-After');

        if ($values === []) {
            return null;
        }

        $raw = trim($values[0]);

        if ($raw === '') {
            return null;
        }

        if (ctype_digit($raw)) {
            return (int) $raw;
        }

        $timestamp = strtotime($raw);

        if ($timestamp === false) {
            return null;
        }

        return max(0, $timestamp - time());
    }

    private function shouldRetry(int $status, int $attempt): bool
    {
        if ($attempt >= self::MAX_ATTEMPTS - 1) {
            return false;
        }

        return $status === 429 || $status >= 500;
    }

    /**
     * @throws RandomException
     */
    private function sleepBeforeRetry(Throwable $exception, int $attempt): void
    {
        $delay = $this->backoff?->duration($attempt) ?? 0.0;

        if ($exception instanceof RateLimitException && $exception->getRetryAfter() !== null) {
            $delay = max($delay, (float) $exception->getRetryAfter());
        }

        if ($delay > 0 && $this->backoff !== null) {
            $this->backoff->sleep($delay);
        }
    }

    private function storeResponse(?ResponseInterface $response): void
    {
        if ($response === null) {
            $this->lastResponseHeaders = [];

            return;
        }

        $headers = [
            'status' => $response->getStatusCode(),
        ];

        foreach ($response->getHeaders() as $name => $values) {
            $headers[strtolower($name)] = $values;
        }

        $this->lastResponseHeaders = $headers;
    }

    /**
     * @param array<string, string|string[]> $headers
     *
     * @return array<string, string[]>
     */
    private function normalizeHeaders(array $headers): array
    {
        $normalized = [];

        foreach ($headers as $name => $value) {
            $values            = is_array($value) ? array_values($value) : [$value];
            $normalized[$name] = array_map(static fn ($item) => (string) $item, $values);
        }

        return $normalized;
    }

    /**
     * @param array<string, string|string[]> $headers
     */
    private function hasAuthHeader(array $headers): bool
    {
        $headers = array_change_key_case($headers);

        return array_key_exists('authorization', $headers)
            || array_key_exists('x-api-key', $headers)
            || array_key_exists('api-key', $headers)
            || array_key_exists('x-auth-token', $headers);
    }
}
