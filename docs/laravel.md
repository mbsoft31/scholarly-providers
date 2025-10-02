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
```

## Custom Clients & Caching

Bind PSR-18, PSR-17, cache, or logger implementations in your container to override defaults.

```php
$this->app->bind(ClientInterface::class, fn () => new Psr18Client());
$this->app->bind(CacheInterface::class, fn () => Cache::store('redis')->getRepository());
```

Set `SCHOLARLY_LOG_CHANNEL` to reuse an application log channel.
