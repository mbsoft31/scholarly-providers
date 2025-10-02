# Task 06 — Crossref Adapter

## Goal
Implement the Crossref REST adapter, focusing on polite usage requirements and DOI-centric workflows.

## Outputs
- `src/Adapters/Crossref/DataSource.php`
- `src/Adapters/Crossref/CrossrefPaginator.php`

## Instructions for Codex
1. **Constructor**: accept `Client $client`, optional `string $mailto`. Append `?mailto=$mailto` to queries and set `User-Agent: ScholarlyClient/1.0 (mailto:$mailto)` when provided.
2. **Paginator**
   - Crossref uses `next-cursor` from `message['next-cursor']`. Implement lazy paging via cursor parameter (`cursor=*` for first request).
   - `page()` returns items under `message['items']` and `nextCursor` when not empty.
3. **Works search**
   - Endpoint `/works` with params `query`, `filter`, `select`, `rows`, `cursor`, `sort=created`, `order=desc`.
   - Filters: year ranges via `from-pub-date`, `until-pub-date`; open access approximated via `has-license:true`; add venue filtering by DOIs if supplied (`container-title`, or use `filter=container-title:{value}` when `venueIds` provided with DOIs?). Since legacy limited, instruct to map `venueIds` to DOIs when available.
4. **Lookups**
   - `getWorkById` delegates to DOI-specific method after `Identity::normalizeDoi`.
   - `getWorkByDoi` fetches `/works/{doi}` (URL-encode). Normalize via `Normalizer::work($message['item'], 'crossref')`.
   - `getWorkByArxiv` and `getWorkByPubmed` call `/works?filter=arxiv:{id}` or `pubmed:{pmid}` then return first item.
5. **Citations & References**
   - Crossref lacks citations API; mimic legacy by reusing `/works` filtering on `doi` for citations (if `include` metadata) and returning empty paginator for references.
6. **Batch**
   - Crossref does not provide native batch API; legacy behavior iterated sequential fetch. Implement generator that yields per DOI, using cache for repeated calls.
7. **Authors**
   - `searchAuthors` uses `/works` with `query.author=$q` and aggregates authors from results (normalize unique). Document limitation in PHPDoc.
   - `getAuthorById` (Crossref lacks IDs) should return `null`.
   - `getAuthorByOrcid` query `/works?filter=orcid:{id}` and map first match.
   - `batchAuthorsByIds` returns empty generator (document unsupported) to maintain API contract.
8. **Meta**
   - `health()` ping `/works?rows=0` to validate service.
   - `rateLimitState()` returns array with `remaining`, `limit`, `reset` (Crossref rarely sets; default nulls).

## Acceptance Criteria
- Requests attach polite agent headers when `mailto` configured.
- Cursor pagination continues until `next-cursor` empty.
- Normalized works contain DOI-based `id` with namespace `crossref:10.xxxx/yyy`.
- Unsupported features documented through PHPDoc and return safe fallbacks (null/empty arrays).
