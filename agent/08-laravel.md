# Task 08 — Laravel Bridge

## Goal
Reintroduce first-class Laravel integration so applications can configure Scholarly Providers via service container bindings and facade access.

## Outputs
- `src/Laravel/ScholarlyServiceProvider.php`
- `src/Laravel/Facades/Scholarly.php`
- `config/scholarly.php`
- `docs/laravel.md` (complete documentation)

## Instructions for Codex
1. **Service provider**
   - Publishable config (`php artisan vendor:publish --tag=scholarly-config`).
   - Register singleton `Scholarly\Factory\AdapterFactory` configured using Laravel config values (`providers.openalex`, `providers.s2`, `providers.crossref`, `http`, `cache`).
   - Bind interface `Scholarly\Contracts\ScholarlyDataSource` to closures returning adapter instances on demand.
   - Provide helper bindings for `graph` exporter and allow `$app['scholarly']` to resolve the factory.
   - Hook into Laravel cache + log via PSR-3/PSR-16 adapters (use `Cache::store()` and `Log::channel()` where available).
2. **Facade**
   - Extend `Illuminate\Support\Facades\Facade`, `protected static function getFacadeAccessor(): string` returning `'scholarly'`.
   - Document usage in PHPDoc (`@method static ScholarlyDataSource adapter(string $name, array $options = [])`).
3. **Configuration file** `config/scholarly.php`
   - Define sections:
     - `default` adapter (string).
     - `http` for mailto, timeout, retries, user agent, backoff config.
     - `cache` for driver, ttl overrides, enabled flag.
     - `providers` array with openalex (mailto, max_per_page), s2 (api_key, max_per_page), crossref (mailto).
     - `graph` options (batch sizes, algorithm defaults).
   - Pull `.env` keys: `SCHOLARLY_DEFAULT_ADAPTER`, `OPENALEX_MAILTO`, `S2_API_KEY`, `CROSSREF_MAILTO`, `SCHOLARLY_CACHE_STORE`, `SCHOLARLY_HTTP_TIMEOUT`.
4. **Documentation (`docs/laravel.md`)**
   - Show installation via Composer + provider auto-discovery.
   - Describe publishing config, using facade, injecting factory via dependency injection.
   - Provide examples for customizing HTTP client (binding PSR-18) and cache store.
   - Include artisan command snippet for testing connection (optional, referencing upcoming features).

## Acceptance Criteria
- Running `php artisan vendor:publish --tag=scholarly-config` copies config file.
- Laravel tests (later) resolve facade and service container bindings.
- Config values default to environment placeholders and handle missing keys gracefully.
- Documentation includes working code snippets and annotation of required env keys.
