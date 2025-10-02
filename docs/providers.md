---
layout: default
title: Provider Capabilities
nav_order: 5
---

# Provider Capabilities

Each adapter implements the unified [`ScholarlyDataSource`](contracts#scholarlydatasource) interface while handling provider-specific features and limitations.

## Supported Providers

| Provider | Works Search | Author Search | Citations | References | Batch APIs | Rate Limits |
|----------|:------------:|:-------------:|:---------:|:----------:|:----------:|:-----------:|
| **[OpenAlex](adapters/openalex)** | ✅ Cursor | ✅ Cursor | ✅ | ✅ | ✅ 50/req | Polite† |
| **[Semantic Scholar](adapters/s2)** | ✅ Offset | ✅ Offset | ✅ | ✅ | ✅ 500/req | 100/min* |
| **[Crossref](adapters/crossref)** | ✅ Cursor | ❌ | ⚠️ Limited | ✅ | ❌ | Polite† |

† *Polite usage encouraged with `mailto` parameter*
* *API key required for higher limits*

## Quick Provider Selection

### Choose **OpenAlex** for:
- Comprehensive academic coverage (100M+ works)
- Rich metadata and citation networks
- Open access status and venue information
- No API key required

### Choose **Semantic Scholar** for:
- AI/ML/Computer Science focus
- High-quality author disambiguation
- Advanced search capabilities
- Bulk data processing (500 IDs per batch)

### Choose **Crossref** for:
- DOI-based workflows
- Publisher metadata and licensing
- Journal-specific searches
- Reliable bibliographic data

## Common Behaviors

All adapters share these standardized features:

- **[Query Interface](contracts#query)**: Unified filters for search, pagination, and field selection
- **[Reliable HTTP](architecture)**: Automatic retries with jittered backoff and `Retry-After` compliance
- **[Data Normalization](architecture)**: Consistent field mapping across providers
- **[Graph Compatibility](graph)**: Direct integration with `GraphExporter` for network analysis
- **[Caching Support](architecture)**: PSR-6/PSR-16 compatible request-level caching
- **[Error Handling](contracts#exceptions)**: Typed exceptions for different failure modes

## Configuration Examples

### Environment Variables (Laravel)
```env
# Provider credentials
OPENALEX_MAILTO=researcher@university.edu
S2_API_KEY=your-semantic-scholar-key
CROSSREF_MAILTO=researcher@university.edu

# Global settings
SCHOLARLY_DEFAULT_ADAPTER=openalex
SCHOLARLY_HTTP_TIMEOUT=30
SCHOLARLY_CACHE_STORE=redis
```

### Direct Configuration
```php
$factory = AdapterFactory::make([
    'default' => 'openalex',
    'cache' => new ArrayAdapter(),
    'openalex' => ['mailto' => 'you@example.com'],
    's2' => ['api_key' => 'your-key'],
    'crossref' => ['mailto' => 'you@example.com']
]);
```

For detailed provider setup and capabilities, see the individual adapter guides:
- **[OpenAlex Configuration & Examples](adapters/openalex)**
- **[Semantic Scholar Setup & Limits](adapters/s2)**
- **[Crossref Integration Guide](adapters/crossref)**

---

## Related Documentation

**Core Concepts**: [Contracts](contracts) | [Architecture](architecture) | [Getting Started](getting-started)
**Features**: [Graph Analytics](graph) | [Laravel Integration](laravel) | [Provider Adapters](providers)
**Development**: [Extending](extending.md) | [GitHub Repository](https://github.com/mbsoft31/scholarly-providers)

**External Resources**: [OpenAlex API](https://docs.openalex.org/) | [Semantic Scholar API](https://api.semanticscholar.org/) | [Crossref API](https://github.com/CrossRef/rest-api-doc)
