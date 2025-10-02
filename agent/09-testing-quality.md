# Task 09 — Testing & Quality Gates

## Goal
Reconstruct the automated test suites, fixtures, and quality tooling to guarantee feature parity and maintainability.

## Outputs
- `tests/bootstrap.php`
- Pest suites under `tests/Contract`, `tests/Unit`, `tests/Adapters`, `tests/Integration`, `tests/Exporter`, `tests/Feature`
- Fixtures under `tests/Fixtures/...`
- QA configs: `.php-cs-fixer.dist.php`, `phpstan.neon`, GitHub workflows (optional but recommended)

## Instructions for Codex
1. **Bootstrap**
   - Load Composer autoload, Dotenv (`vlucas/phpdotenv` optional), set default timezone.
   - Register helper functions for fixture loading, mock client factories, fake cache implementations.
2. **Contract tests**
   - Validate `Query` setters, array hydration, serialization.
   - Ensure `Paginator` implementations conform (use anonymous class mocks).
3. **Core tests**
   - `CacheLayerTest`: verify key uniqueness, TTL hints, PSR-16 + PSR-6 compatibility.
   - `ClientTest`: simulate retry/backoff using mock PSR-18 client; assert exceptions thrown for status codes, caching behavior, provider policy injection.
   - `IdentityTest`: coverage for DOI/ORCID/ArXiv/PMID normalization edge cases.
   - `NormalizerTest`: snapshot normalized outputs using fixture payloads for each provider.
4. **Adapter tests**
   - Use mocked `Client` returning fixture responses; assert parameter mapping, paginator flow, normalization, batch splitting.
   - Integration group can use real HTTP client when env var `SCHOLARLY_LIVE_TESTS=1` (skip otherwise).
5. **Graph exporter tests**
   - Build synthetic graphs from fixtures, assert node/edge counts, algorithm helper outputs (PageRank, betweenness) with deterministic tolerance.
6. **Laravel tests**
   - Leverage Orchestra Testbench to confirm service provider registration, facade access, config publishing.
7. **Static analysis & style**
   - Configure `phpstan.neon` level 8 with baseline support; ignore known vendor issues.
   - `.php-cs-fixer.dist.php` enforcing PSR-12 + trailing commas, single quotes.
8. **CI parity**
   - Add scripts or workflow docs describing running `composer quality`, `composer test -- --group=adapters`, coverage thresholds (≥90%).
   - Document how to record coverage reports in `coverage/` output.

## Acceptance Criteria
- `composer test` green with ≥90% statement coverage (`pest --coverage`).
- `composer stan` and `composer cs-check` pass without errors.
- Tests deterministic (use fake timers for backoff, seed randoms).
- Fixtures stored as JSON under `tests/Fixtures/{provider}/` mirroring API docs; update instructions for regeneration when APIs change.
