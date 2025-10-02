# Scholarly Providers


## Installation

```bash
composer require scholarly/providers
```

To enable the Laravel bridge, ensure package discovery is enabled or register `Scholarly\Laravel\ScholarlyServiceProvider` manually.

## Quick Start

```php
use Scholarly\Contracts\Query;
use Scholarly\Factory\AdapterFactory;

$factory = AdapterFactory::make();
$openAlex = $factory->adapter('openalex');
$results = $openAlex->searchWorks(Query::from(['q' => 'graph neural networks', 'limit' => 25]));

$graph = $factory
    ->graphExporter($openAlex)
    ->buildWorkCitationGraph(['openalex:W123'], Query::from(['limit' => 50]));
```

Ad-hoc usage is PSR-first: inject your own PSR-18 client, PSR-17 factories, PSR-16/PSR-6 cache, or PSR-3 logger via `AdapterFactory::make([...], $container)`.

## Modules

| Area          | Description |
|---------------|-------------|
| `src/Contracts` | Query value object, paginator interface, and core provider contract. |
| `src/Core`       | HTTP client wrapper, cache facade, identifier utilities, and normalization helpers. |
| `src/Adapters`   | Provider implementations for OpenAlex, Semantic Scholar (S2), and Crossref, including paginator classes. |
| `src/Exporter`   | Citation and collaboration graph exporter plus algorithm helpers. |
| `src/Factory`    | Adapter factory, configuration objects, and Laravel bindings. |
| `src/Laravel`    | Service provider and facade for framework integration. |

Documentation lives in `docs/` (architecture, provider notes, Laravel usage), agent briefs under `agent/`, and tests under `tests/` (Pest).

## Configuration Highlights

- Set `OPENALEX_MAILTO`, `S2_API_KEY`, `CROSSREF_MAILTO`, `SCHOLARLY_DEFAULT_ADAPTER`, `SCHOLARLY_CACHE_STORE`, and `SCHOLARLY_HTTP_TIMEOUT` in your environment.
- Graph exporter accepts optional raw query flags such as `work_ids`, `max_works`, and `min_collaborations`.
- When using Laravel, publish `config/scholarly.php` via `php artisan vendor:publish --tag=scholarly-config` and use the `Scholarly` facade or inject `AdapterFactory`/`ScholarlyDataSource`.

## Development Workflow

```bash
# run automated tests
composer test

# optional static analysis / style checks (requires PHPStan & PHP-CS-Fixer configured)
composer stan
composer cs-check

# convenience quality target
composer quality
```

Fixtures for provider responses live under `tests/Fixtures/`. Integration tests that hit live APIs should be guarded by `if (! getenv('SCHOLARLY_LIVE_TESTS')) { $this->markTestSkipped(); }`.

## Release Notes

See `CHANGELOG.md` for version history. Run `composer archive --format=zip` to produce a Packagist-friendly artifact.
