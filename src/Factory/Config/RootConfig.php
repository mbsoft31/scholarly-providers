<?php

declare(strict_types=1);

namespace Scholarly\Factory\Config;

use InvalidArgumentException;

final readonly class RootConfig
{
    /** @var array<string, AdapterConfig> */
    public array $providers;

    /**
     * @param array<string, AdapterConfig> $providers
     */
    public function __construct(
        public string      $defaultAdapter,
        public HttpConfig  $http,
        public CacheConfig $cache,
        public GraphConfig $graph,
        array              $providers,
    ) {
        $this->providers = $providers;
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        $default = $config['default'] ?? 'openalex';
        $http    = HttpConfig::fromArray($config['http'] ?? []);
        $cache   = CacheConfig::fromArray($config['cache'] ?? []);
        $graph   = GraphConfig::fromArray($config['graph'] ?? []);

        $providers = [];
        foreach (['openalex', 's2', 'crossref'] as $provider) {
            $providers[$provider] = AdapterConfig::fromArray($config['providers'][$provider] ?? []);
        }

        return new self($default, $http, $cache, $graph, $providers);
    }

    public function validate(): void
    {
        if (! isset($this->providers[$this->defaultAdapter])) {
            throw new InvalidArgumentException("Unknown default adapter: $this->defaultAdapter");
        }
    }
}
