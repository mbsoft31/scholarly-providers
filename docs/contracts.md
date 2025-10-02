# Contracts Reference

This package is contract-first. Implementations (adapters) and higher-level features (graphs) speak the same language.

## Query

Represents a provider-agnostic search/filter request with sensible defaults and validation.

Key fields
- `q: ?string` full-text search
- `year: ?string` YYYY or ranges like `2018-` or `2019-2021`
- `openAccess: ?bool`
- `minCitations: ?int`, `maxCitations: ?int`
- `venueIds: ?list<string>` provider-specific venue identifiers
- `fields: list<string>` normalized selection of fields, case-insensitive
- `limit: int` page size (default 25)
- `cursor: ?string`, `offset: ?int` pagination controls
- `raw: array<string, mixed>` for anything provider-specific

Construction
```php
use Scholarly\Contracts\Query;

$q = Query::from([
    'q' => 'deep learning',
    'year' => '2018-2022',
    'openAccess' => true,
    'limit' => 50,
    'fields' => ['id','title','year','authors'],
]);

// Or fluent
$q = (new Query())
    ->q('deep learning')
    ->year('2018-')
    ->openAccess(true)
    ->limit(50)
    ->addField('id')->addField('title');
```

Serialization
```php
$asArray = $q->toArray();
```

Validation
- Empty `year` → InvalidArgumentException
- `minCitations` / `maxCitations` must be ≥ 0
- `limit` ≥ 1, `offset` ≥ 0

## Paginator

Lazy, typed pagination for provider responses.

API
- `page(): array{items: list<array<string,mixed>>, nextCursor: ?string}`
- `getIterator(): Traversable<int, array<string, mixed>>`

Usage
```php
$paginator = $adapter->searchWorks($q);

// Pull a page
[$items, $cursor] = [$paginator->page()['items'], $paginator->page()['nextCursor']];

// Or stream lazily
foreach ($paginator as $row) {
    // $row is normalized
}
```

## ScholarlyDataSource

Unified interface across all adapters. Work and author endpoints mirror common capabilities.

Works
- `searchWorks(Query): Paginator`
- `getWorkById(string): ?array`
- `getWorkByDoi(string): ?array`
- `getWorkByArxiv(string): ?array`
- `getWorkByPubmed(string): ?array`
- `listCitations(string, Query): Paginator`
- `listReferences(string, Query): Paginator`
- `batchWorksByIds(iterable<string>, Query): iterable<array>`

Authors
- `searchAuthors(Query): Paginator`
- `getAuthorById(string): ?array`
- `getAuthorByOrcid(string): ?array`
- `batchAuthorsByIds(iterable<string>, Query): iterable<array>`

Meta
- `health(): bool` should handle transient errors internally
- `rateLimitState(): array{string:int|null}`

Normalized shapes (typical)
- Work: `['id','title','year','venue','authors','counts'=>['citations'=>int], 'external_ids'=>['doi'=>?, 'arxiv'=>?, 'pmid'=>?]]`
- Author: `['id','name','orcid'=>?, 'affiliations'=>list<string>, 'counts'=>['works'=>int,'citations'=>int]]`

Exceptions
- Adapters throw specialized exceptions under `Scholarly\Core\Exceptions` (e.g., `NotFoundException`, `RateLimitException`, `ClientException`).

