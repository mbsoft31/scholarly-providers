---
layout: default
title: Extending / Build Adapters
nav_order: 7
---

# Extending: Build Your Own Adapter

Adapters map `ScholarlyDataSource` to a concrete provider. Follow this step-by-step to add a new provider with reliable HTTP, pagination, and normalization.

1) Create the namespace and class
```
src/Adapters/YourApi/DataSource.php
src/Adapters/YourApi/YourApiPaginator.php
```

2) Implement ScholarlyDataSource
```php
namespace Scholarly\Adapters\YourApi;

use Scholarly\Contracts\{ScholarlyDataSource, Query, Paginator};
use Scholarly\Core\Client;

final class DataSource implements ScholarlyDataSource
{
    public function __construct(private Client $client, private array $config = []) {}

    public function searchWorks(Query $query): Paginator { /* return new YourApiPaginator(...) */ }
    public function getWorkById(string $id): ?array { /* GET /works/{id} */ }
    public function getWorkByDoi(string $doi): ?array { /* optional */ }
    public function getWorkByArxiv(string $arxivId): ?array { /* optional */ }
    public function getWorkByPubmed(string $pmid): ?array { /* optional */ }
    public function listCitations(string $id, Query $query): Paginator { /* /citations */ }
    public function listReferences(string $id, Query $query): Paginator { /* /references */ }
    public function batchWorksByIds(iterable $ids, Query $query): iterable { /* chunk + yield */ }
    public function searchAuthors(Query $query): Paginator { /* /authors */ }
    public function getAuthorById(string $id): ?array { /* /authors/{id} */ }
    public function getAuthorByOrcid(string $orcid): ?array { /* optional */ }
    public function batchAuthorsByIds(iterable $ids, Query $query): iterable { /* chunk + yield */ }
    public function health(): bool { /* lightweight GET */ }
    public function rateLimitState(): array { /* from headers or local counters */ }
}
```

3) Use the core HTTP client
```php
$response = $this->client->get('https://api.yourapi.io/works', [
    'query' => $this->buildWorkSearchParams($query),
]);

// The client handles:
// - retries with jittered backoff for 429/5xx
// - Retry-After parsing
// - mapping errors to domain exceptions
// - logging
```

4) Implement pagination
```php
final class YourApiPaginator implements Paginator
{
    public function __construct(private Client $client, private string $url, private array $params) {}

    public function page(): array { /* call, normalize items, return nextCursor */ }
    public function getIterator(): \Traversable { /* loop until nextCursor is null */ }
}
```

5) Normalize responses consistently
Use `Core\Identity` helpers to normalize external IDs (DOI, arXiv, PMID, ORCID). Map provider fields to the canonical shape used across adapters.

Example mapping
```php
private function mapWork(array $row): array
{
    return [
        'id' => 'yourapi:'.$row['id'],
        'title' => $row['title'] ?? '',
        'year' => (int)($row['year'] ?? 0),
        'venue' => $row['venue']['name'] ?? null,
        'authors' => array_map(fn($a) => [
            'id' => 'yourapi:'.$a['id'],
            'name' => trim(($a['given'] ?? '').' '.($a['family'] ?? '')),
            'orcid' => $a['orcid'] ?? null,
        ], $row['authors'] ?? []),
        'counts' => ['citations' => (int)($row['citationCount'] ?? 0)],
        'external_ids' => [
            'doi' => $row['doi'] ?? null,
            'arxiv' => $row['arxivId'] ?? null,
            'pmid' => $row['pmid'] ?? null,
        ],
    ];
}
```

6) Handle batching
- Chunk ID lists to provider maximums (e.g., 100/200/500 per request).
- Yield normalized items lazily to keep memory usage low.
- Log errors per batch item; skip or return partial results rather than failing the entire batch.

7) Rate limiting & politeness
- Respect `Retry-After` on 429/503 (the core client already does).
- Expose `rateLimitState()` for dashboards.
- Add polite identifiers (e.g., `mailto`, custom `User-Agent`) where providers ask for them.

8) Wire the factory
Add a config block under `Factory/Config` and map a key in `AdapterFactory`.

9) Tests & fixtures
- Add `tests/Adapters/YourApiDataSourceTest.php`.
- Use `php-http/mock-client` and JSON fixtures under `tests/Fixtures/yourapi/`.
- Cover search, pagination, lists, batches, and error paths.

10) Ship docs
Create `docs/adapters/yourapi.md` describing endpoints, limits, and examples.

Tips
- Reuse traits in `src/Adapters/Traits` for DOI/arXiv/PMID/ORCID lookups.
- Consider adding health checks using a lightweight, cacheable endpoint.
- Normalize aggressively; it pays off when exporting graphs or mixing providers.
