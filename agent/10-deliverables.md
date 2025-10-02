# Task 10 — Finalization & Release Checklist

## Goal
Ensure the rebuilt package is production-ready, documented, and versioned for distribution.

## Deliverables
- Updated `README.md`, `docs/` content, `agent/` specs, and `CHANGELOG.md`
- Version bump in `composer.json`
- Release notes draft and packaging artifacts

## Instructions for Codex
1. **Documentation sweep**
   - Fill README sections: features, adapters overview, installation, configuration matrix, usage samples (factory + Laravel), graph exporter examples.
   - Update `docs/architecture.md` with diagrams or textual flow describing adapters, core services, and exporter.
   - Extend `docs/providers.md` summarizing each provider capabilities, rate limits, supported filters, and normalization quirks.
   - Sync `agent/` specs (existing + rebuild series) with final API shapes; cross-link tasks to ensure future agents can maintain parity.
2. **Configuration parity**
   - Confirm `.env.example` values match `config/scholarly.php` keys; add comments instructing developers to set `OPENALEX_MAILTO`, `S2_API_KEY`, `CROSSREF_MAILTO`, `SCHOLARLY_CACHE_STORE`, `SCHOLARLY_HTTP_TIMEOUT`.
   - Document rate limit defaults in `config/rate_limits.php` (create file if missing) and ensure they are referenced in README + docs.
3. **Quality verification**
   - Run `composer quality` and capture output summary for release notes.
   - Generate coverage report `composer test-coverage`; store HTML in `coverage/` (ignored by git) and note statement % in changelog.
4. **Versioning**
   - Update `composer.json` `version` field (e.g., `1.0.0`) and add `CHANGELOG.md` entry under `## [1.0.0] - YYYY-MM-DD` summarizing rebuild highlights.
   - Provide release notes template including install instructions, breaking change callouts (should be none if parity achieved).
5. **Distribution prep**
   - Verify `composer archive --format=zip` excludes tests (`archive.exclude` matches legacy list).
   - Ensure Packagist metadata (keywords, description) accurate.
6. **Next steps for maintainers**
   - Recommend publishing GitHub release, tagging version, running CI pipeline.
   - Outline monitoring tasks: watch provider API changelog, rotate API keys, review rate limit updates.

## Acceptance Criteria
- All docs render without TODO placeholders.
- Configuration files, env example, and README reference the same keys and defaults.
- Changelog ready for public release; version numbers aligned across files.
- Quality suite executed and results documented.
