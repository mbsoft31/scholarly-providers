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

## Installation Requirements

- **PHP 8.3+** with extensions: `ext-json`, `ext-curl`
- **PSR-18 HTTP Client** (or use [Symfony HttpClient](https://symfony.com/doc/current/http_client.html) + [Nyholm PSR-7](https://github.com/Nyholm/psr7))
- **Composer** for package management

See [Architecture](architecture.md#requirements) for detailed PSR compatibility information.

Install
```bash
composer require scholarly/providers
```

## Complete Example: Research Workflow
```php
use Scholarly\Contracts\Query;
use Scholarly\Factory\AdapterFactory;

// Setup with caching
$factory = AdapterFactory::make([
    'cache' => new ArrayAdapter(), // PSR-6 cache
    'openalex' => ['mailto' => 'your-email@example.com']
]);

$openAlex = $factory->adapter('openalex');

// 1. Search for recent works on a topic
$recentWorks = $openAlex->searchWorks(Query::from([
    'q' => 'graph neural networks',
    'year' => '2023-2024',
    'openAccess' => true,
    'limit' => 50,
    'fields' => ['id', 'title', 'year', 'authors', 'counts']
]));

// 2. Build collaboration networks from results
$workIds = [];
foreach ($recentWorks as $work) {
    $workIds[] = $work['id'];
    if (count($workIds) >= 10) break; // Limit for demo
}

// 3. Generate citation graph
$exporter = $factory->graphExporter($openAlex);
$citationGraph = $exporter->buildWorkCitationGraph(
    $workIds,
    Query::from(['limit' => 100])
);

// 4. Export for analysis
$graphData = $exporter->exportToJson($citationGraph);
file_put_contents('citation_network.json', $graphData);

// 5. Run graph algorithms (requires mbsoft31/graph-algorithms)
use Mbsoft\Graph\Algorithms\Centrality\PageRank;
$influence = (new PageRank())->compute($citationGraph);
arsort($influence);
echo "Most influential work: " . array_key_first($influence) . "\n";

```

## Common Use Cases

### Academic Research Pipelines
- **Literature Discovery**: Cross-provider search with unified results
- **Citation Analysis**: Build and analyze scholarly networks
- **Author Collaboration**: Map research collaborations over time
- **Impact Assessment**: Calculate centrality metrics and influence scores

### Integration Patterns
- **Laravel Applications**: Use the [facade and service provider](laravel.md)
- **Symfony Projects**: Leverage PSR-compliant architecture
- **Data Processing**: Batch operations with built-in rate limiting
- **Visualization**: Export to [Cytoscape](https://cytoscape.org/), [Gephi](https://gephi.org/), or custom formats

## Troubleshooting

### Common Issues
- **Rate Limiting**: Package handles 429 responses automatically with backoff
- **API Keys**: See [provider-specific configuration](adapters/) for setup
- **Memory Usage**: Use pagination and batch processing for large datasets
- **Caching**: Configure [PSR-6/PSR-16 caches](architecture.md#caching) for performance

### Getting Help
- Check [GitHub Issues](https://github.com/mbsoft31/scholarly-providers/issues) for known problems
- Review [Architecture Documentation](architecture.md) for design decisions
- Consult [Provider Documentation](adapters/) for API-specific behavior

---

## Related Documentation

**Core Concepts**: [Contracts](contracts.md) | [Architecture](architecture.md) | [Getting Started](getting-started.md)
**Features**: [Graph Analytics](graph.md) | [Laravel Integration](laravel.md) | [Provider Adapters](providers.md)
**Development**: [Extending](extending.md) | [GitHub Repository](https://github.com/mbsoft31/scholarly-providers)

**External Resources**: [OpenAlex API](https://docs.openalex.org/) | [Semantic Scholar API](https://api.semanticscholar.org/) | [Crossref API](https://github.com/CrossRef/rest-api-doc)
