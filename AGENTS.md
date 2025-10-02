# Repository Guidelines

## Project Structure & Module Organization
- `src/` contains production PHP code organised by domain (e.g., `Contracts`, `Core`, `Adapters`).
- `tests/` mirrors the runtime namespaces with Pest suites under `Unit`, `Integration`, `Adapters`, and `Exporter`; fixtures live in `tests/Fixtures/`.
- `config/`, `docs/`, and `agent/` hold configuration defaults, long-form documentation, and task briefs respectively; `build/` hosts tooling scripts, while `coverage/` and `logs/` store generated artefacts.

## Build, Test, and Development Commands
- `composer install` pulls dependencies and copies `.env.example` to `.env` via the post-install script.
- `composer test` runs the Pest suite; append `--group=adapters` to target provider-specific cases.
- `composer stan` executes PHPStan at level 8; `composer cs-check` and `composer cs-fix` enforce PSR-12 via PHP-CS-Fixer.
- `composer quality` bundles `test`, `stan`, and `cs-check` for pre-PR validation.

## Coding Style & Naming Conventions
- Follow PSR-12 with strict types and 4-space indentation; favour short array syntax and single quotes.
- Namespace classes under `Scholarly\…` reflecting their directory path; keep filenames singular (`GraphExporter.php`).
- Tests use descriptive `it_can_*` or `it_handles_*` function names inside Pest closures.

## Testing Guidelines
- Pest (`vendor/bin/pest`) is the primary framework; target ≥90% statement coverage via `composer test-coverage`.
- Store HTTP fixtures as JSON under `tests/Fixtures/{provider}/`; prefer deterministic seeds for faker-driven data.
- Integration tests hitting live APIs must guard with `if (env('SCHOLARLY_LIVE_TESTS') !== '1') { $this->markTestSkipped(); }`.

## Commit & Pull Request Guidelines
- Use Conventional Commits (e.g., `feat(adapter): add openalex pagination`) to ease changelog generation.
- Scope commits logically (contracts, core services, adapter) and ensure `composer quality` passes before pushing.
- Pull requests should include a summary, linked issue, manual verification notes, and screenshots for documentation updates.

## Agent Workflow Notes
- Execute `agent/*.md` tasks sequentially; complete prerequisites (contracts, core, adapters) before downstream docs/tests.
- Regenerate documentation (`README.md`, `docs/`) and stubs after substantive API changes to keep briefs accurate.
