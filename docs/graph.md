---
layout: default
title: Graphs & Algorithms
nav_order: 4
---

# Graphs & Algorithms

Turn search results and identifiers into rich knowledge graphs, then export or analyze with mbsoft graph packages.

What you get
- Citation graphs: edges capture citing → cited relationships with optional weights.
- Collaboration graphs: undirected edges connect co-authors; weights reflect joint works.
- Export pipelines: convert to Cytoscape JSON, GraphML, GEXF using mbsoft/graph-core exporters.
- Algorithms: run PageRank, components, pathfinding, and more via mbsoft/graph-algorithms.

Build graphs
```php
use Scholarly\Contracts\Query;
use Scholarly\Factory\AdapterFactory;

$factory = AdapterFactory::make();
$src = $factory->adapter('openalex');
$exporter = $factory->graphExporter($src);

// Citation graph
$workGraph = $exporter->buildWorkCitationGraph(
    ['openalex:W1','openalex:W2'],
    Query::from(['limit' => 50])
);

// Collaboration graph
$authorGraph = $exporter->buildAuthorCollaborationGraph(
    ['openalex:A1','openalex:A2'],
    Query::from(['max_works' => 100, 'min_collaborations' => 2])
);
```

Caching
```php
use Scholarly\Core\CacheLayer;
use Symfony\Component\Cache\Adapter\ArrayAdapter; // PSR-6 example

$cache = new CacheLayer(new ArrayAdapter());
$exporter = new Scholarly\Exporter\Graph\GraphExporter($src, $cache);

// Re-runs reuse references/citations from cache
$graph = $exporter->buildWorkCitationGraph(['openalex:W1'], Query::from(['limit' => 50]));
```

Exports
```php
use Mbsoft\Graph\IO\CytoscapeJsonExporter;
use Mbsoft\Graph\IO\GraphMLExporter;

$json = (new CytoscapeJsonExporter())->export($workGraph);
$xml  = (new GraphMLExporter())->export($authorGraph);
```

Algorithms
```php
use Mbsoft\Graph\Algorithms\Centrality\PageRank;

$scores = (new PageRank())->compute($workGraph);
arsort($scores); // top influential works
```

Progress & throttling
- `GraphExporter` accepts an optional progress callback `(index, total|null, id, meta)`.
- It respects polite rate limiting (sleep when needed) and honors `Retry-After` via the core client.
