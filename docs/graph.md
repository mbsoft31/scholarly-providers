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


## Advanced Graph Operations

### Custom Progress Tracking
```php
$progressCallback = function(int $current, ?int $total, string $itemId, array $meta) {
    $type = $meta['type'] ?? 'unknown';
    $progress = $total ? round(($current / $total) * 100, 1) : $current;
    echo "Processing {$type} {$current}" . ($total ? "/{$total} ({$progress}%)" : "") . ": {$itemId}\n";
};

$graph = $exporter->buildWorkCitationGraph(
    ['openalex:W123', 'openalex:W456'],
    Query::from(['limit' => 100]),
    $progressCallback
);
```


### Graph Analysis Examples

#### Find Research Communities
```php
use Mbsoft\Graph\Algorithms\Community\LouvainCommunities;

$communities = (new LouvainCommunities())->detect($authorGraph);
    foreach ($communities as $communityId => $members) {
    echo "Community {$communityId}: " . count($members) . " researchers\n";
}
```


#### Identify Key Papers
```php
use Mbsoft\Graph\Algorithms\Centrality\BetweennessCentrality;

$betweenness = (new BetweennessCentrality())->compute($citationGraph);
$keyPapers = array_slice($betweenness, 0, 10, true);
foreach ($keyPapers as $workId => $score) {
    echo "Bridge paper {$workId}: {$score}\n";
}
```

## Export Formats & Tools

### Visualization Platforms
- **[Cytoscape](https://cytoscape.org/)**: Use `CytoscapeJsonExporter` for network analysis
- **[Gephi](https://gephi.org/)**: Export via `GraphMLExporter` for large-scale visualization
- **[NetworkX](https://networkx.org/) (Python)**: GraphML format for scientific computing
- **[igraph](https://igraph.org/) (R/Python)**: Compatible with GraphML exports

### Integration with Analysis Tools
```php
// Export for Python NetworkX
use Mbsoft\Graph\IO\GraphMLExporter;

$xml = (new GraphMLExporter())->export($graph);
file_put_contents('network.graphml', $xml);

// Export for web visualization (D3.js, vis.js)
use Mbsoft\Graph\IO\CytoscapeJsonExporter;
$json = (new CytoscapeJsonExporter())->export($graph);
file_put_contents('network.json', $json);
```

For more graph algorithms, see [mbsoft31/graph-algorithms documentation](https://github.com/mbsoft31/graph-algorithms).

---

## Related Documentation

**Core Concepts**: [Contracts](contracts.md) | [Architecture](architecture.md) | [Getting Started](getting-started.md)
**Features**: [Graph Analytics](graph.md) | [Laravel Integration](laravel.md) | [Provider Adapters](providers.md)
**Development**: [Extending](extending.md) | [GitHub Repository](https://github.com/mbsoft31/scholarly-providers)

**External Resources**: [OpenAlex API](https://docs.openalex.org/) | [Semantic Scholar API](https://api.semanticscholar.org/) | [Crossref API](https://github.com/CrossRef/rest-api-doc)
