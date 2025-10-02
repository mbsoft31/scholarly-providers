# OpenAlex Adapter

The OpenAlex adapter implements `ScholarlyDataSource` over the OpenAlex REST API with cursor pagination and rich normalization.

Highlights
- Cursor-based pagination via `meta.next_cursor`
- Filters: year ranges, open access, venue IDs, citation counts
- Identifiers: `openalex:W...` for works, `openalex:A...` for authors
- Rate limits: honors `x-ratelimit-*` headers and supports `mailto` query param

Search works
```php
$results = $openAlex->searchWorks(Query::from([
    'q' => 'graph neural networks',
    'year' => '2019-2024',
    'openAccess' => true,
    'limit' => 50,
    'fields' => ['id','title','year','authors','counts'],
]));

foreach ($results as $work) {
    // normalized work
}
```

Lookup
```php
$openAlex->getWorkById('openalex:W123');
$openAlex->getAuthorById('openalex:A123');
```

Citations & references
```php
$openAlex->listCitations('openalex:W1', Query::from(['limit' => 200]));
$openAlex->listReferences('openalex:W1', Query::from(['limit' => 200]));
```

Batch
```php
// yields normalized works lazily
foreach ($openAlex->batchWorksByIds(['openalex:W1','openalex:W2'], Query::from([])) as $work) {}
```

Configuration
- Add a `mailto` for polite usage in `config/scholarly.php` or via factory options.
- Default adapter key: `openalex`.

Notes
- Abstracts are reconstructed from the inverted index if present.
- Venue, counts, and external IDs are normalized to consistent keys.

