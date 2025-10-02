# Task 04 — OpenAlex Adapter

## Goal
Rebuild the OpenAlex implementation of `ScholarlyDataSource`, including paginator classes and identifier traits.

## Outputs
- `src/Adapters/Traits/{ArxivTrait,DoiTrait,OrcidTrait,PubmedTrait}.php`
- `src/Adapters/OpenAlex/DataSource.php`
- `src/Adapters/OpenAlex/OpenAlexPaginator.php`
- `src/Adapters/OpenAlex/AuthorPaginator.php`

## Instructions for Codex
1. **Shared traits**
   - Provide helper methods for fetching works/authors via DOI/ArXiv/ORCID/PMID using core client + normalizer.
   - Each trait should expect implementing class to define `client`, `mapFields...` helpers, and base URL patterns.
   - Implement methods by calling canonical adapter APIs and forwarding to `Normalizer`.
2. **`OpenAlexPaginator`**
   - Accept `Client $client`, `string $url`, `array $params`, initial response payload.
   - Implement `page()` returning current items + `meta['next_cursor']`.
   - Implement lazy iteration: when `nextCursor` present, fetch next page using same query with updated `cursor` param.
   - Capture `meta['count']` if present for tests.
3. **`AuthorPaginator`**
   - Similar to works paginator but adapted to `/authors` endpoint.
   - Provide convenience `public function items(): array` returning normalized authors.
4. **`DataSource`**
   - Base URL `https://api.openalex.org`.
   - Constructor accepts `Client $client`, optional `string $mailto` to attach `mailto=` query param and `User-Agent` header.
   - Methods to implement:
     - `searchWorks(Query $q)` — build params using filters (year range → `publication_year`, `is_oa`, citation bounds, venues). Map fields via `select` parameter with `mapFieldsForWorks()`.
     - `getWorkById`, `getWorkByDoi`, `getWorkByArxiv`, `getWorkByPubmed` — clean identifiers (`openalex:` prefix), call client, normalize work.
     - `listCitations` / `listReferences` — append `filter=cites:{id}` or `referenced_works:{id}`.
     - Batch endpoints: use POST `/works/batch` with `ids` list (max 50 per batch) and cache results.
     - Author methods (`searchAuthors`, `getAuthorById`, `getAuthorByOrcid`, `batchAuthorsByIds`). Map field selections with `mapFieldsForAuthors()`.
     - `health()` — call `/status` (or return true if 200) and catch exceptions gracefully.
     - `rateLimitState()` — parse headers `x-ratelimit-remaining`, `x-ratelimit-reset`, fallback to `['remaining' => null, ...]`.
   - Ensure all responses normalized via `Normalizer::work` / `Normalizer::author` and namespaced IDs using `Identity::ns('openalex', ...)`.
5. **Field mappers**
   - Provide arrays translating generic field names to OpenAlex API `select` parameter values (works + authors). Include defaults for `id`, `title`, `abstract`, `publication_year`, `authorships`, `ids`, `open_access`, etc.
6. **Error handling**
   - Catch `NotFoundException` and return `null` where appropriate.
   - Log errors via `$this->client->log()`.

## Acceptance Criteria
- Paginators support `foreach ($paginator as $item)` yielding normalized arrays.
- Search parameters respect `Query` properties and pass raw overrides.
- Batch lookup splits >50 IDs into multiple POST calls and merges results.
- Adapter methods remain pure (no stateful caching beyond `Client` layer).
- Unit tests (later) can mock client responses and verify normalization mapping.
