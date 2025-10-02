---
layout: default
title: Provider Capabilities
nav_order: 5
---

# Provider Capabilities

Deep dives for each adapter live under `docs/adapters/`:
- OpenAlex: adapters/openalex.md
- Semantic Scholar (S2): adapters/s2.md
- Crossref: adapters/crossref.md

Common Behaviours
- `Query` fields map to provider filters; `raw` passes through extras when needed.
- Retries and backoff (429/5xx) handled by `Core\Client`, including `Retry-After`.
- Normalization aligns keys across providers for works and authors.
- `GraphExporter` works with any adapter implementing `ScholarlyDataSource`.
