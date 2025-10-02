---
layout: default
title: Scholarly Providers
nav_order: 1
---

# Scholarly Providers Documentation

Build reliable scholarly features without juggling raw HTTP for [OpenAlex](https://openalex.org), [Semantic Scholar](https://www.semanticscholar.org/product/api) (S2), and [Crossref](https://www.crossref.org/services/metadata-delivery/rest-api/). This package unifies provider contracts, normalizes payloads, adds retries + caching, and exports rich graphs ready for analysis or visualization.

## Quick Navigation

### 🚀 **Getting Started**
- **[Getting Started](getting-started)** - Quick wins and why not raw HTTP
- **[Installation & First Steps](getting-started#install)** - Get up and running in minutes

### 📖 **Core Documentation**
- **[Contracts Reference](contracts)** - `Query`, `Paginator`, `ScholarlyDataSource` APIs
- **[Architecture Overview](architecture)** - System design and component relationships
- **[Graphs & Algorithms](graph)** - Citation/collaboration graphs + exporters

### 🔌 **Provider Integrations**
- **[Provider Capabilities](providers)** - Common behaviors across adapters
- **[Adapters Documentation](adapters/)** - OpenAlex, S2, Crossref specifics
    - [OpenAlex Adapter](adapters/openalex)
    - [Semantic Scholar (S2) Adapter](adapters/s2)
    - [Crossref Adapter](adapters/crossref)

### ⚡ **Advanced Features**
- **[Extending](extending)** - Build your own adapter and add features
- **[Laravel Integration](laravel)** - Framework integration, config, and facade

## Learning Path

**New to the package?** Follow this recommended sequence:
1. Start with **[Getting Started](getting-started)** for immediate wins
2. Review **[Contracts Reference](contracts)** to understand the API surface
3. Explore **[Graph Capabilities](graph)** for advanced analytics
4. Check **[Provider-Specific Guides](adapters/)** for implementation details

## External Resources

- **[GitHub Repository](https://github.com/mbsoft31/scholarly-providers)** - Source code and issues
- **[Packagist](https://packagist.org/packages/scholarly/providers)** - Composer package
- **[mbsoft31/graph-core](https://github.com/mbsoft31/graph-core)** - Graph data structures
- **[mbsoft31/graph-algorithms](https://github.com/mbsoft31/graph-algorithms)** - Graph analysis tools

---

## Related Documentation

**Core Concepts**: [Contracts](contracts) | [Architecture](architecture) | [Getting Started](getting-started)
**Features**: [Graph Analytics](graph) | [Laravel Integration](laravel) | [Provider Adapters](providers)
**Development**: [Extending](extending.md) | [GitHub Repository](https://github.com/mbsoft31/scholarly-providers)

**External Resources**: [OpenAlex API](https://docs.openalex.org/) | [Semantic Scholar API](https://api.semanticscholar.org/) | [Crossref API](https://github.com/CrossRef/rest-api-doc)
