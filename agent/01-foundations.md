# Task 01 — Foundation & Tooling

## Goal
Bootstrap a clean Scholarly Providers workspace so Codex can rebuild features on top of a consistent toolchain.

## Inputs
- Empty Git repository (or remove all PHP sources before starting).
- PHP 8.2 runtime with Composer available.

## Steps for Codex
1. **Initialize Composer package**
   - `composer init` with name `scholarly/providers`, MIT license, PSR-4 autoload `"Scholarly\\": "src/"`.
   - Require runtime deps: `psr/http-client`, `psr/http-factory`, `psr/http-message`, `psr/log`, `psr/cache`, `psr/simple-cache`, `ext-json`, `ext-curl`, `mbsoft31/graph-core`, `mbsoft31/graph-algorithms`.
   - Require-dev: `pestphp/pest`, `pestphp/pest-plugin-type-coverage`, `pestphp/pest-plugin-mutate`, `phpstan/phpstan`, `friendsofphp/php-cs-fixer`, `symfony/var-dumper`, `nyholm/psr7`, `symfony/http-client`, `php-http/mock-client`, `mockery/mockery`, `fakerphp/faker`.
   - Configure scripts identical to legacy repo (`test`, `test-coverage`, `test-parallel`, `test-type-coverage`, `stan`, `cs-fix`, `cs-check`, `quality`, `post-install-cmd`).
   - Enable plugin allowlist for `php-http/discovery` and `pestphp/pest-plugin`.
2. **Scaffold directories**: `src/`, `tests/`, `docs/`, `config/`, `agent/`, `build/`, `coverage/`, `logs/` (empty `.gitkeep` where needed).
3. **Configuration files**
   - Copy `.env.example` baseline with placeholders for provider credentials (`OPENALEX_MAILTO`, `S2_API_KEY`, `CROSSREF_MAILTO`, `CACHE_DRIVER`).
   - Create `config/scholarly.php` with keys for adapters, cache, rate limiting (structure detailed in Task 07).
   - Add `phpunit.xml` mirroring Pest bootstrap expectations (`tests/bootstrap.php`).
   - Prepare `phpstan.neon`, `.php-cs-fixer.dist.php`, and `.editorconfig` with PSR-12 + 4 space indent.
4. **Docs placeholders**
   - Generate `README.md` skeleton (project summary, install, usage, adapters table, testing instructions).
   - Add `docs/architecture.md`, `docs/providers.md`, and `docs/laravel.md` stubs referencing sections to be completed later.
   - Seed `CHANGELOG.md` with `## [Unreleased]` section.
5. **Testing harness**
   - Install Pest via `./vendor/bin/pest --init`.
   - Create `tests/bootstrap.php` to wire autoload, load `.env` if present, and register shared fixtures (PSR mock factories placeholder).
6. **Quality hooks**
   - Configure `.github/workflows/tests.yml` and `code-style.yml` replicating composer commands (optional when offline; include instructions for future commit).

## Acceptance Criteria
- `composer validate` succeeds without warnings.
- Running `composer install` produces `.env` from `.env.example` via post-install script.
- `./vendor/bin/pest --version` works.
- Repo tree matches high-level layout required by later tasks.
