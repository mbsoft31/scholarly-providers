# Task 03 — Core Platform Services

## Goal
Implement the reusable services that power every adapter: HTTP client wrapper, caching layer, retry/backoff logic, identifier helpers, normalization utilities, and exception hierarchy.

## Outputs
- `src/Core/Exceptions/*.php`
- `src/Core/Backoff.php`
- `src/Core/CacheLayer.php`
- `src/Core/Client.php`
- `src/Core/Identity.php`
- `src/Core/Normalizer.php`

## Instructions for Codex
1. **Exception set (`Scholarly\Core\Exceptions`)**
   - Create base `Error extends \RuntimeException` storing optional `ResponseInterface`.
   - Derive `ClientException`, `ServerException`, `RateLimitException` (add `?int $retryAfter`), `TransportException`, `NotFoundException`, `DefaultException`.
   - Provide getters for HTTP response and retry hints.
2. **`Backoff` strategy**
   - Support exponential backoff with jitter: base delay 0.5s, cap 60s, factor 2, jitter using randomization.
   - Expose `public function duration(int $attempt): float` and `public function sleep(float $seconds): void` (calls `usleep`).
   - Allow overriding defaults via constructor.
3. **`CacheLayer`**
   - Accept PSR-16 or PSR-6 implementation plus optional logger.
   - Provide `remember`, `buildKey`, `getTtlHint`, `disable`, `enable`, `isEnabled`, `clear`.
   - TTL heuristics: search=3600s, detail=604800s, batch=21600s, metadata=2592000s.
   - `buildKey` must hash method+host+path+sorted query+payload hash+auth presence indicator.
4. **`Client` wrapper**
   - Constructor dependencies: PSR-18 client, PSR-17 factories, optional provider policy callable, logger, backoff, cache.
   - Methods `get`, `post` delegate to `executeGet` / `executePost` handling retries for 429/5xx up to 3 attempts.
   - Integrate cache layer for idempotent calls. Cache key built via `CacheLayer`.
   - Apply provider policy callable before sending (e.g., add headers/query defaults).
   - Parse JSON using `json_decode(..., JSON_THROW_ON_ERROR)` and throw `TransportException` on failure.
   - Expose `log(string $message): void` to forward to logger (info level) and `getCache(): ?CacheLayer`.
   - Map HTTP error codes to exception classes (404→`NotFoundException`, 429→`RateLimitException`, 4xx→`ClientException`, 5xx→`ServerException`, fallback→`DefaultException`).
5. **`Identity` utilities**
   - Implement helpers: `normalizeDoi`, `doiToUrl`, `normalizeOrcid`, `orcidToUrl`, `normalizeArxiv`, `arxivToUrl`, `normalizePmid`, `pmidToUrl`, `ns`, `parseNs`, `extractIds` (dot-notation support), etc.
   - Ensure regex and canonical formatting match original behavior (lowercase DOI, hyphenated ORCID, trimmed ArXiv version, numeric PMID).
6. **`Normalizer`**
   - Provide static `work(array $raw, string $provider)` and `author(...)` returning canonical maps.
   - Implement provider-specific private methods for `openalex`, `s2`, `crossref`, plus generic fallback.
   - Normalize fields: `id`, `title`, `abstract`, `year`, `publication_date`, `venue`, `external_ids` (using `Identity`), `is_oa`, `oa_url`, `url`, `counts`, `authors`, `references_count`, `tldr`, `type`, `language`.
   - Ensure authors arrays contain `id`, `name`, `orcid` where available.
7. **Logging**
   - Default logger should be `NullLogger` when none provided.

## Acceptance Criteria
- Static analysis: `composer stan` passes on `src/Core`.
- Unit tests (later tasks) can simulate retries and caching reliably.
- `CacheLayer` deterministic key generation verified via tests (same request → same key, query reorder safe).
- `Identity` handles lower/upper case inputs and returns null for invalid identifiers.
- `Normalizer` shapes match arrays produced by legacy package (write snapshot tests once implemented).
