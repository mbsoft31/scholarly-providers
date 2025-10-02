---
layout: default
title: Getting Started
nav_order: 2
---

# Getting Started

This guide shows how to search works and authors, fetch citations/references, and build graphs – without hand-rolling HTTP, pagination, and normalization for each provider.

Why not raw HTTP?
- One contract for all providers: write once against `ScholarlyDataSource`.
- Reliability built-in: retries with jittered backoff, `Retry-After` honoring, typed exceptions.
- Consistent data: `Normalizer` maps provider payloads to stable keys (works/authors/ids/counts).
- Easy pagination: iterate `Paginator` or call `page()` – no cursor/offset bookkeeping.
- Caching: drop-in PSR-16/PSR-6 to skip redundant HTTP.
- Graphs: build citation/collaboration graphs and export with mbsoft graph tools.

Install
```bash
composer require scholarly/providers
```

Quick win
```php
use Scholarly\Contracts\Query;
use Scholarly\Factory\AdapterFactory;

$factory = AdapterFactory::make();
$openAlex = $factory->adapter('openalex');

// Search works lazily (Paginator implements Traversable)
$paginator = $openAlex->searchWorks(Query::from(['q' => 'graph neural networks', 'limit' => 25]));
foreach ($paginator as $work) {
    echo $work['title'].' ('.$work['year']."\n");
}

// Build a citation graph around a seed set
$graph = $factory->graphExporter($openAlex)
    ->buildWorkCitationGraph(['openalex:W123'], Query::from(['limit' => 50]));
```

Core concepts
- `Query`: provider-agnostic filters (q, year, limit, cursor/offset) + arbitrary `raw` params.
- `Paginator`: `page()` returns items + nextCursor; `foreach` streams all items lazily.
- `ScholarlyDataSource`: one interface for search/get/list/batch for works and authors.

Next steps
- Understand the APIs you’ll call: contracts.md
- Learn graph export and algorithms: graph.md
- See provider specifics and limits: adapters/
- Add your own provider: extending.md
