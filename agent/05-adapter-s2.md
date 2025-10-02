# Task 05 — Semantic Scholar (S2) Adapter

## Goal
Recreate the Semantic Scholar Graph API integration, including search, lookup, citations/references, and batching across papers and authors.

## Outputs
- `src/Adapters/S2/DataSource.php`
- `src/Adapters/S2/S2Paginator.php`
- `src/Adapters/S2/S2AuthorPaginator.php`

## Instructions for Codex
1. **Base configuration**
   - Base URL `https://api.semanticscholar.org/graph/v1`.
   - Constructor accepts `Client $client`, optional `?string $apiKey` (attach `x-api-key` header when present).
   - Default paper fields (when `Query::$fields` empty) should match: `paperId,title,abstract,year,publicationDate,venue,externalIds,authors,citationCount,referenceCount,openAccessPdf,url,tldr`.
   - Default author fields: `authorId,name,affiliations,paperCount,citationCount,hIndex,url`.
2. **Paginator classes**
   - Similar to OpenAlex paginator but S2 uses `next` offset numeric.
   - Keep track of `next` metadata (`offset + limit`). Convert into cursor string.
3. **Work endpoints**
   - `searchWorks` → GET `/paper/search` with params `query`, `limit`, `offset`, `fields`.
   - `getWorkById` → GET `/paper/{id}` normalizing ID (strip `s2:` prefix). Always request mapped fields.
   - `getWorkByDoi`/`Arxiv`/`Pubmed` use appropriate endpoints `/paper/DOI:{doi}`, `/paper/ARXIV:{id}`, `/paper/PMID:{id}`; ensure uppercase prefixes.
   - `listCitations`, `listReferences` hitting `/paper/{id}/citations` and `/paper/{id}/references`.
   - `batchWorksByIds` → POST `/paper/batch` with body `{ids: [...], fields: '...'}` (max 500 per call). Return generator yielding normalized works.
4. **Author endpoints**
   - `searchAuthors` → GET `/author/search` with `query`, `fields`, `limit`, `offset`.
   - `getAuthorById` → GET `/author/{id}`.
   - `getAuthorByOrcid` → GET `/author/ORCID:{orcid}` (normalized ORCID from `Identity`).
   - `batchAuthorsByIds` → POST `/author/batch` similar to works.
5. **Field mapping helpers**
   - `mapFieldsForPapers(array $fields)` ensures `paperId` always included.
   - `mapFieldsForAuthors(array $fields)` ensures `authorId` always included; expand `counts` to `paperCount`, `citationCount`, `hIndex`.
6. **Rate limiting**
   - Use response headers `x-ratelimit-remaining`, `x-ratelimit-reset`, `x-ratelimit-limit` if present.
   - `health()` can GET `/catalog` or `/paper/search` with `query=the` minimal limit to confirm service availability.
7. **Error handling**
   - Batch endpoints should catch `Throwable`, log, and continue yielding remaining items.
   - Return `null` on 404 for lookup methods.

## Acceptance Criteria
- Requests attach API key header when provided.
- Cursor-based pagination works with numeric offsets and respects `Query::$cursor`/`$offset`.
- Normalized results identical to legacy expectations (verify via fixtures once tests created).
- Batch iterables are lazy (generators) and respect limit chunking (<=500 IDs per call).
