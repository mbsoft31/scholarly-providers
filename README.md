# scholarly/providers

[![PHP Version](https://img.shields.io/badge/php-%5E8.3-blue.svg)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-brightgreen.svg)](LICENSE)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/scholarly/providers.svg?style=flat-square)](https://packagist.org/packages/scholarly/providers)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/mbsoft31/scholarly-providers/ci.yml?branch=main&style=flat-square)](https://github.com/mbsoft31/scholarly-providers/actions)
[![Total Downloads](https://img.shields.io/packagist/dt/scholarly/providers.svg?style=flat-square)](https://packagist.org/packages/scholarly/providers)

Unified PHP SDK for scholarly data providers (OpenAlex, Semantic Scholar, Crossref) with a clean contracts layer, robust HTTP client with retries and caching, and a graph exporter powered by mbsoft/graph-core and mbsoft/graph-algorithms.

## ✨ Features

- 🔌 Provider adapters: OpenAlex, Semantic Scholar (S2), Crossref
- 🧩 Clean contracts: `Query`, `Paginator`, `ScholarlyDataSource`
- 🔁 Resilient HTTP: jittered backoff, retry-after handling, 4xx/5xx classification
- 🧠 Normalization utils: DOI, arXiv, PubMed, ORCID helpers
- 🗃️ Caching: PSR-16 and PSR-6 support via a simple `CacheLayer`
- 📈 Graph export: citation and collaboration graphs as mbsoft/graph `Graph`
- 🧪 Well-tested: Pest test suite, static analysis with PHPStan
- 🧰 Framework-friendly: Laravel service provider + facade

## 📋 Requirements

- PHP 8.3+
- ext-json, ext-curl
- PSR-18 HTTP client and PSR-17 factories (or use Symfony HttpClient + Nyholm PSR-7)

## 📦 Installation

Install via Composer:

```bash
composer require scholarly/providers
```

Optional (Laravel): package discovery registers `Scholarly\Laravel\ScholarlyServiceProvider`. If you disable discovery, register it manually in `config/app.php`.

## 🚀 Quick Start

```php
use Scholarly\Contracts\Query;
use Scholarly\Factory\AdapterFactory;

$factory  = AdapterFactory::make();
$openAlex = $factory->adapter('openalex');

// Search works
$results = $openAlex->searchWorks(Query::from(['q' => 'graph neural networks', 'limit' => 25]));
foreach ($results as $work) {
    // each $work is array<string, mixed>
}

// Build a citation graph around a seed set
$graph = $factory
    ->graphExporter($openAlex)
    ->buildWorkCitationGraph(['openalex:W123'], Query::from(['limit' => 50]));

// Export using mbsoft/graph exporters (e.g., Cytoscape JSON)
// use Mbsoft\Graph\IO\CytoscapeJsonExporter;
// $json = (new CytoscapeJsonExporter())->export($graph);
```

PSR-first design: inject your own PSR-18 client, PSR-17 factories, PSR-16/PSR-6 cache, or PSR-3 logger via `AdapterFactory::make([...], $container)`.

## 🔍 Advanced Features

- Caching: wrap a PSR-16 or PSR-6 store with `CacheLayer` and pass to the exporter to reuse reference/citation lookups between runs.
- Retries & backoff: the client honors `Retry-After` headers and applies jittered exponential backoff for transient errors.
- Batching & pagination: iterate `Paginator` instances (`searchWorks`, `listReferences`, `listCitations`) or use `batchWorksByIds`.
- Identifier helpers: `getWorkByDoi`, `getWorkByArxiv`, `getWorkByPubmed`, `getAuthorByOrcid` normalize inputs and handle not-found paths.

## 🧱 Architecture

- `src/Contracts`: `Query` value object, `Paginator`, `ScholarlyDataSource` contract
- `src/Core`: HTTP client, backoff, cache layer, identity normalization
- `src/Adapters`: OpenAlex, S2, Crossref implementations and paginators
- `src/Exporter`: Graph exporter building mbsoft/graph `Graph` instances
- `src/Factory`: Adapter factory, configuration objects, Laravel bindings
- `src/Laravel`: Service provider and facade

See `docs/` for architecture notes, provider specifics, and Laravel usage.

## ⚙️ Configuration

Environment variables (or publish `config/scholarly.php` in Laravel):

- `SCHOLARLY_DEFAULT_ADAPTER` (e.g., `openalex`)
- `SCHOLARLY_CACHE_STORE` (Laravel) or provide `CacheInterface`/`CacheItemPoolInterface`
- `SCHOLARLY_HTTP_TIMEOUT` (seconds)
- Provider settings: `OPENALEX_MAILTO`, `S2_API_KEY`, `CROSSREF_MAILTO`

Graph exporter query flags:

- `work_ids` (seed set), `limit` (page sizes), `max_works` (author graph cap), `min_collaborations` (edge threshold)

## 🧰 Laravel Usage

```php
use Scholarly\Laravel\Facades\Scholarly;

// Resolve the default provider (configured via scholarly.default)
$adapter = Scholarly::adapter();

// Export graphs
$exporter = Scholarly::graphExporter();
$graph    = $exporter->buildWorkCitationGraph(['openalex:W1'], Query::from(['limit' => 50]));
```

Publish config if needed:

```bash
php artisan vendor:publish --tag=scholarly-config
```

## 🧪 Testing

Run the test suite:

```bash
composer test
```

With coverage:

```bash
composer test-coverage
```

Static analysis and coding style:

```bash
composer stan
composer cs-check
```

Fixtures for provider responses live under `tests/Fixtures/`. Guard any live API tests with `SCHOLARLY_LIVE_TESTS=1`.

## 📚 Documentation

- Start here: `docs/index.md`
- Quick start guide: `docs/getting-started.md`
- Contracts reference: `docs/contracts.md`
- Graphs & algorithms: `docs/graph.md`
- Adapters: `docs/adapters/openalex.md`, `docs/adapters/s2.md`, `docs/adapters/crossref.md`
- Extending: `docs/extending.md`

Hosted docs (GitHub Pages): https://mbsoft31.github.io/scholarly-providers/

## 🎯 Use Cases

- Research discovery and enrichment workflows
- Building literature citation graphs for analysis/visualization
- Author collaboration network analysis
- Data pipelines integrating multiple scholarly sources via one API

## 🤝 Contributing

Contributions are welcome! Please open an issue or PR.

1. Fork the repository
2. Create your feature branch (`git checkout -b feat/awesome`)
3. Ensure quality gates pass (`composer quality`)
4. Push and open a Pull Request

## 📝 License

This library is open-sourced software licensed under the MIT license.

## 🔗 See Also

- [mbsoft/graph-core](https://github.com/mbsoft31/graph-core)
- [mbsoft/graph-algorithms](https://github.com/mbsoft31/graph-algorithms)
