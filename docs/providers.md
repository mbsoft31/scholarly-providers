# Provider Capabilities

### OpenAlex
- **Endpoints**: `/works`, `/authors`, paginator cursor (`meta.next_cursor`).
- **Filters**: `publication_year`, open access flag (`is_oa`), citation bounds, venue IDs (`host_venue.id`). Fluent helpers transform `Query` fields into `filter` + `select` parameters.
- **Identifiers**: `openalex:W...` for works, `openalex:A...` for authors. DOIs, PMID, arXiv ids returned under `external_ids`.
- **Rate limits**: headers `x-ratelimit-remaining`, `x-ratelimit-reset`. Configure polite traffic via `mailto` query parameter and custom `User-Agent`.
- **Normalization notes**: Abstracts reconstructed from OpenAlex inverted index; venues normalized under `venue` key.

### Semantic Scholar Graph (S2)
- **Endpoints**: `/paper/search`, `/paper/{id}`, `/author/search`, `/paper/{id}/citations`, `/paper/{id}/references`, `/paper/batch`, `/author/batch`.
- **Authentication**: Optional API key via `x-api-key`. `AdapterFactory` passes through from config/ENV.
- **Pagination**: Numeric `offset` and `next` token. Adapter converts cursors into offsets for PSR-friendly iteration.
- **Identifiers**: `s2:{id}` for works/authors; DOIs/arXiv/PMID available in `externalIds`.
- **Batching**: Up to 500 paper or author IDs per request; adapter handles chunking and error logging.
- **Normalization notes**: Open-access metadata mapped to `is_oa`/`oa_url`; publication venue merged from `publicationVenue` snapshot.

### Crossref
- **Endpoints**: `/works`, `/works/{doi}` with cursor pagination (`message.next-cursor`).
- **Politeness**: Supply `mailto` query parameter and `User-Agent` describing your application. Laravel config reuses `CROSSREF_MAILTO` or fallback `SCHOLARLY_MAILTO`.
- **Filters**: Supports year ranges (`from-pub-date`, `until-pub-date`), `has-license:true`, `container-title`, and DOI/reference searches. Citations emulated via `filter=reference:{doi}`.
- **Identifiers**: DOI namespaced as `crossref:{doi}`. Normalizer extracts author ORCIDs, ISSN/ISBN when present.
- **Limitations**: Direct author lookups unavailable; adapter aggregates unique authors from works search and skips unsupported operations like batch author retrieval.

## Common Behaviours
- All adapters respect `Query::limit`, `cursor`, `offset`, and `raw` overrides.
- `Core\Client` unifies retries (429/5xx) with exponential backoff and maps errors to domain exceptions.
- `Core\Normalizer` returns consistent keys for works (`id`, `title`, `year`, `authors`, `counts`, `external_ids`) and authors (`id`, `name`, `orcid`, `counts`, `affiliations`).
- `GraphExporter` consumes any adapter conforming to `ScholarlyDataSource` to build citation or collaboration graphs.
