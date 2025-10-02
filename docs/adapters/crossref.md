# Crossref Adapter

Implements `ScholarlyDataSource` for Crossref’s REST API. Cursor pagination for `/works`, and DOI-based lookups.

Highlights
- Cursor pagination via `message.next-cursor`
- Politeness: include `mailto` param and a descriptive `User-Agent`
- Search filters: year ranges, `container-title`, license flags
- Identifiers: DOIs normalized; author ORCIDs extracted when present

Search
```php
$results = $crossref->searchWorks(Query::from([
    'q' => 'causal inference',
    'year' => '2015-2020',
    'limit' => 100,
]));
```

Lookup by DOI
```php
$crossref->getWorkByDoi('10.1038/nature14539');
```

References/Citations
- References are available in work payloads when present; the adapter exposes `listReferences()` with provider support or emulation.
- Citations are limited in Crossref; the adapter provides best-effort lookups where feasible.

Configuration
- Set `CROSSREF_MAILTO` or a global mailto in `scholarly.http.user_agent`/`providers.crossref.mailto`.

Notes
- Crossref does not support author batch endpoints; the adapter no-ops unsupported features gracefully.

