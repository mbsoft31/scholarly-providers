---
layout: default
title: Laravel Integration
nav_order: 8
---

# Laravel Integration

Scholarly Providers ships with a first-class Laravel bridge that registers adapters, configures caching, and exposes a facade for quick access.

## Installation

```bash
composer require scholarly/providers
```

The service provider uses package discovery. If discovery is disabled, add the provider manually in `config/app.php`:

```php
'providers' => [
    // ...
    Scholarly\Laravel\ScholarlyServiceProvider::class,
],
```

## Configuration

Publish the configuration file to customise adapters, HTTP timeouts, and caching:

```bash
php artisan vendor:publish --tag=scholarly-config
```

The published file (`config/scholarly.php`) reads from the following environment variables:

- `SCHOLARLY_DEFAULT_ADAPTER` (default `openalex`)
- `SCHOLARLY_HTTP_TIMEOUT`, `SCHOLARLY_HTTP_USER_AGENT`
- `SCHOLARLY_CACHE_STORE`
- `OPENALEX_MAILTO`, `S2_API_KEY`, `CROSSREF_MAILTO`

## Usage

Resolve the factory or use the facade to obtain adapters:

```php
use Scholarly\Laravel\Facades\Scholarly;
use Scholarly\Contracts\Query;

$adapter = Scholarly::adapter('openalex');
$results = $adapter->searchWorks(Query::from(['q' => 'graph neural networks']));
```

Inject the factory or default adapter into your services:

```php
use Scholarly\Factory\AdapterFactory;

class WorkService
{
    public function __construct(private AdapterFactory $factory) {}

    public function openAlexWorks(): array
    {
        return $this->factory->adapter('openalex')->searchWorks(Query::from(['q' => 'vision']));
    }
}
```

The graph exporter is bound for convenience:

```php
$graph = Scholarly::graphExporter()->buildWorkCitationGraph(['openalex:W123'], Query::from(['limit' => 50]));

### Overriding PSR dependencies
Bind your own PSR-18 client, PSR-17 factories, cache store, or logger to integrate platform choices like Symfony HttpClient or Redis caching.

```php
use Psr\Http\Client\ClientInterface as Psr18;
use Psr\SimpleCache\CacheInterface as Psr16;
use Illuminate\Support\Facades\Cache;

$this->app->bind(Psr18::class, fn () => new MyHttpClient());
$this->app->bind(Psr16::class, fn () => Cache::store('redis'));
```

## Custom Clients & Caching

Bind PSR-18, PSR-17, cache, or logger implementations in your container to override defaults.

```php
$this->app->bind(ClientInterface::class, fn () => new Psr18Client());
$this->app->bind(CacheInterface::class, fn () => Cache::store('redis')->getRepository());
```

Set `SCHOLARLY_LOG_CHANNEL` to reuse an application log channel.

## Advanced Configuration

### Custom HTTP Client

```php
// In a service provider
use Psr\Http\Client\ClientInterface;
use Symfony\Component\HttpClient\Psr18Client;

public function register()
{
    $this->app->bind(ClientInterface::class, function() {
        return new Psr18Client(HttpClient::create([
            'timeout' => 60,
            'headers' => ['User-Agent' => 'MyApp/1.0'],
        ]));
    });
}
```


### Redis Caching Setup
```php
// config/scholarly.php
return [
    'cache_store' => 'redis',
    'cache_ttl' => [
        'metadata' => 3600, // 1 hour for works/authors
        'search' => 900, // 15 minutes for search results
        'graph' => 7200, // 2 hours for graph data
    ],
];
```

### Queue Integration
```php
use Scholarly\Laravel\Facades\Scholarly;

// In a queued job
class BuildCitationGraphJob implements ShouldQueue
{
    public function handle()
    {
        $exporter = Scholarly::graphExporter();
        $graph = $exporter->buildWorkCitationGraph(
            $this->workIds,
            Query::from(['limit' => 200])
        );
        // Process the graph...
    }
}
```

### Testing with Package
```php
// tests/Feature/ScholarlyTest.php
use Scholarly\Laravel\Facades\Scholarly;

class ScholarlyTest extends TestCase
{
    public function test_can_search_works()
    {
        $results = Scholarly::adapter('openalex')
            ->searchWorks(Query::from(['q' => 'test']));
        $this->assertInstanceOf(Paginator::class, $results);
    }
}
```

## Production Deployment

### Performance Considerations
- Enable **Redis caching** for production workloads
- Use **queue workers** for graph building operations
- Configure **rate limiting** to respect provider policies
- Monitor **memory usage** during large batch operations

### Monitoring & Logging
```php
// Custom logger for scholarly operations
Log::channel('scholarly')->info('Graph export completed', [
    'nodes' => $graph->nodeCount(),
    'edges' => $graph->edgeCount(),
    'duration' => $exportTime
]);
```

---

## Related Documentation

**Core Concepts**: [Contracts](contracts.md) | [Architecture](architecture.md) | [Getting Started](getting-started.md)
**Features**: [Graph Analytics](graph.md) | [Laravel Integration](laravel.md) | [Provider Adapters](providers.md)
**Development**: [Extending](extending.md) | [GitHub Repository](https://github.com/mbsoft31/scholarly-providers)

**External Resources**: [OpenAlex API](https://docs.openalex.org/) | [Semantic Scholar API](https://api.semanticscholar.org/) | [Crossref API](https://github.com/CrossRef/rest-api-doc)

