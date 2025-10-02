<?php

declare(strict_types=1);

namespace Scholarly\Factory;

use InvalidArgumentException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Psr\SimpleCache\CacheInterface;
use RuntimeException;
use Scholarly\Adapters\Crossref\DataSource as CrossrefDataSource;
use Scholarly\Adapters\OpenAlex\DataSource as OpenAlexDataSource;
use Scholarly\Adapters\S2\DataSource as S2DataSource;
use Scholarly\Contracts\ScholarlyDataSource;
use Scholarly\Core\Backoff;
use Scholarly\Core\CacheLayer;
use Scholarly\Core\Client;
use Scholarly\Exporter\Graph\GraphExporter;
use Scholarly\Factory\Config\AdapterConfig;
use Scholarly\Factory\Config\CacheConfig;
use Scholarly\Factory\Config\HttpConfig;
use Scholarly\Factory\Config\RootConfig;
use Symfony\Component\HttpClient\Psr18Client;

use function class_exists;

final class AdapterFactory
{
    private const string PROVIDER_OPENALEX = 'openalex';
    private const string PROVIDER_S2       = 's2';
    private const string PROVIDER_CROSSREF = 'crossref';

    private static ?AdapterFactory $instance = null;

    /** @var array<string, ScholarlyDataSource> */
    private array $adapters = [];

    private function __construct(
        private readonly RootConfig $config,
        private readonly Client $client,
        private readonly CacheLayer $cacheLayer,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     */
    public static function make(array $config = [], ?ContainerInterface $container = null): self
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $root = RootConfig::fromArray($config);

        $client     = self::buildClient($root->http, $container);
        $cacheLayer = new CacheLayer(self::resolveCacheStore($root->cache, $container), self::resolveLogger($root->http, $container));
        $logger     = self::resolveLogger($root->http, $container);

        self::$instance = new self($root, $client, $cacheLayer, $logger);

        return self::$instance;
    }

    public function reset(): void
    {
        $this->adapters = [];
        self::$instance = null;
    }

    public function adapter(?string $name = null): ScholarlyDataSource
    {
        $name ??= $this->config->defaultAdapter;
        $name = strtolower($name);

        if (! isset($this->adapters[$name])) {
            $this->adapters[$name] = $this->createAdapter($name);
        }

        return $this->adapters[$name];
    }

    public function graphExporter(?ScholarlyDataSource $dataSource = null): GraphExporter
    {
        return new GraphExporter($dataSource ?? $this->adapter(), $this->cacheLayer, $this->logger);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function createAdapter(string $name, array $options = []): ScholarlyDataSource
    {
        return match ($name) {
            self::PROVIDER_OPENALEX => $this->createOpenAlexAdapter($options),
            self::PROVIDER_S2       => $this->createS2Adapter($options),
            self::PROVIDER_CROSSREF => $this->createCrossrefAdapter($options),
            default                 => throw new InvalidArgumentException("Unsupported adapter: $name"),
        };
    }

    /**
     * @param array<string, mixed> $options
     */
    private function createOpenAlexAdapter(array $options): ScholarlyDataSource
    {
        $config     = $this->config->providers[ self::PROVIDER_OPENALEX ] ?? new AdapterConfig();
        $mailto     = $options['mailto']                                  ?? $config->mailto;
        $maxPerPage = (int)($options['max_per_page'] ?? $config->maxPerPage ?? 200);

        return new OpenAlexDataSource($this->client, $mailto, $maxPerPage);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function createS2Adapter(array $options): ScholarlyDataSource
    {
        $config     = $this->config->providers[ self::PROVIDER_S2 ] ?? new AdapterConfig();
        $apiKey     = $options['api_key']                           ?? $config->apiKey;
        $maxPerPage = (int)($options['max_per_page'] ?? $config->maxPerPage ?? 100);

        return new S2DataSource($this->client, $apiKey, $maxPerPage);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function createCrossrefAdapter(array $options): ScholarlyDataSource
    {
        $config  = $this->config->providers[ self::PROVIDER_CROSSREF ] ?? new AdapterConfig();
        $mailto  = $options['mailto']                                  ?? $config->mailto;
        $maxRows = (int)($options['max_rows'] ?? $config->maxPerPage ?? 100);

        return new CrossrefDataSource($this->client, $mailto, $maxRows);
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    private static function buildClient(HttpConfig $httpConfig, ?ContainerInterface $container): Client
    {
        $psr17Factory = new Psr17Factory();

        $uriFactory     = self::resolve($container, UriFactoryInterface::class)     ?? $psr17Factory;
        $requestFactory = self::resolve($container, RequestFactoryInterface::class) ?? $psr17Factory;
        $streamFactory  = self::resolve($container, StreamFactoryInterface::class)  ?? $psr17Factory;
        $httpClient     = self::resolve($container, ClientInterface::class)         ?? self::makeHttpClient($container);

        $backoff = new Backoff(
            $httpConfig->backoff['base']   ?? 0.5,
            $httpConfig->backoff['max']    ?? 60,
            $httpConfig->backoff['factor'] ?? 2,
        );

        $logger = self::resolveLogger($httpConfig, $container);

        $policy = static function ($request) use ($httpConfig) {
            if ($httpConfig->timeout !== null && method_exists($request, 'withHeader')) {
                $request = $request->withHeader('timeout', (string) $httpConfig->timeout);
            }

            if ($httpConfig->userAgent !== null && method_exists($request, 'withHeader')) {
                $request = $request->withHeader('User-Agent', $httpConfig->userAgent);
            }

            return $request;
        };

        return new Client(
            $httpClient,
            $uriFactory,
            $requestFactory,
            $streamFactory,
            $policy,
            $logger,
            $backoff,
        );
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    private static function resolveLogger(HttpConfig|CacheConfig $config, ?ContainerInterface $container): LoggerInterface
    {
        $logger = $config->logger ?? null;

        if (is_string($logger) && $container && $container->has($logger)) {
            $service = $container->get($logger);
            if ($service instanceof LoggerInterface) {
                return $service;
            }
        }

        if ($logger instanceof LoggerInterface) {
            return $logger;
        }

        return self::resolve($container, LoggerInterface::class) ?? new NullLogger();
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    private static function resolveCacheStore(CacheConfig $config, ?ContainerInterface $container): CacheInterface|CacheItemPoolInterface|null
    {
        $store = $config->store;

        if ($store === null) {
            return null;
        }

        if (is_string($store) && $container && $container->has($store)) {
            $resolved = $container->get($store);
            if ($resolved instanceof CacheInterface || $resolved instanceof CacheItemPoolInterface) {
                return $resolved;
            }
        }

        if ($store instanceof CacheInterface || $store instanceof CacheItemPoolInterface) {
            return $store;
        }

        return self::resolve($container, CacheInterface::class)
            ?? self::resolve($container, CacheItemPoolInterface::class);
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    private static function makeHttpClient(?ContainerInterface $container): ClientInterface
    {
        if ($container && $container->has(ClientInterface::class)) {
            $client = $container->get(ClientInterface::class);
            if ($client instanceof ClientInterface) {
                return $client;
            }
        }

        if (class_exists(Psr18Client::class)) {
            return new Psr18Client();
        }

        throw new RuntimeException('No PSR-18 HTTP client available. Install symfony/http-client or bind your own.');
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    private static function resolve(?ContainerInterface $container, string $id): mixed
    {
        if ($container && $container->has($id)) {
            return $container->get($id);
        }

        return null;
    }
}
