<?php

declare(strict_types=1);

namespace Scholarly\Core;

use Closure;
use JsonException;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Psr\SimpleCache\CacheInterface;

/**
 * Thin wrapper adding deterministic cache keys and shared TTL heuristics.
 */
class CacheLayer
{
    private bool $enabled = true;

    public function __construct(
        private readonly CacheInterface|CacheItemPoolInterface|null $store,
        private ?LoggerInterface                                    $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Cache the outcome of the resolver.
     *
     * @template TValue
     * @param string $key
     * @param Closure():TValue $resolver
     * @param int|null $ttlSeconds
     * @return TValue
     * @throws InvalidArgumentException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function remember(string $key, Closure $resolver, ?int $ttlSeconds = null)
    {
        if (! $this->enabled || $this->store === null) {
            return $resolver();
        }

        if ($this->store instanceof CacheInterface) {
            if ($this->store->has($key)) {
                return $this->store->get($key);
            }

            $value = $resolver();
            $this->store->set($key, $value, $ttlSeconds);

            return $value;
        }

        $item = $this->store->getItem($key);

        if ($item->isHit()) {
            return $item->get();
        }

        $value = $resolver();
        $item->set($value);

        if ($ttlSeconds !== null) {
            $item->expiresAfter($ttlSeconds);
        }

        $this->store->save($item);

        return $value;
    }

    /**
     * Generate a deterministic cache key for an HTTP request.
     *
     * @param array<string, mixed> $query
     * @param array<string, mixed>|string|null $payload
     * @throws JsonException
     */
    public function buildKey(string $method, string $url, array $query = [], array|string|null $payload = null, bool $hasAuth = false): string
    {
        $method = strtoupper($method);
        $parts  = parse_url($url) ?: [];
        $host   = $parts['host'] ?? '';
        $path   = $parts['path'] ?? '';

        $queryParams = [];

        if (isset($parts['query'])) {
            parse_str($parts['query'], $queryParams);
        }

        foreach ($query as $key => $value) {
            $queryParams[$key] = $value;
        }

        ksort($queryParams);

        $payloadHash = '';

        if ($payload !== null) {
            $payloadNormalized = is_array($payload)
                ? json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)
                : $payload;

            $payloadHash = hash('sha256', $payloadNormalized);
        }

        $segments = [
            $method,
            $host,
            $path,
            json_encode($queryParams, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            $payloadHash,
            $hasAuth ? 'auth' : 'anon',
        ];

        return hash('sha256', implode('|', $segments));
    }

    public function getTtlHint(string $context): int
    {
        return match ($context) {
            'search'   => 3600,
            'detail'   => 604_800,
            'batch'    => 21_600,
            'metadata' => 2_592_000,
            default    => 3600,
        };
    }

    public function disable(): void
    {
        $this->enabled = false;
    }

    public function enable(): void
    {
        $this->enabled = true;
    }

    public function isEnabled(): bool
    {
        return $this->enabled && $this->store !== null;
    }

    public function clear(): void
    {
        if ($this->store instanceof CacheInterface) {
            $this->store->clear();
        } elseif ($this->store instanceof CacheItemPoolInterface) {
            $this->store->clear();
        }
    }

    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }
}
