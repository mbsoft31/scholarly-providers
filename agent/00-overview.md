# Rebuild Blueprint — Scholarly Providers

## Purpose
Provide Codex with an end-to-end script to recreate the Scholarly Providers package from an empty repository. Follow this playbook sequentially; do not assume existing PHP sources beyond configuration scaffolding.

## Non-Negotiables
- Target PHP 8.2+ with strict types.
- Obey PSR-12 formatting, PSR-4 autoloading, and explicit return types on public methods.
- Preserve public API signatures documented in the original package (`Scholarly\Contracts\*`, adapter method names, paginator semantics).
- Maintain provider support parity: OpenAlex, Semantic Scholar Graph (S2), Crossref.
- Keep caching, rate limiting, and normalization behaviors feature-equivalent.
- Recreate Pest test coverage (unit + integration) and ensure `composer quality` passes.

## Reimplementation Roadmap
1. Establish a fresh Composer project skeleton and shared tooling (coding standards, QA scripts, bootstrap config).
2. Define contracts and value objects consumed by adapters and exporters.
3. Implement core infrastructure (HTTP client wrapper, caching, backoff, identifier utilities, normalizer).
4. Build provider adapters with paginator implementations and field mapping.
5. Restore graph exporter module and helper abstractions.
6. Implement Laravel bridge (service provider, facade, config).
7. Recreate documentation, configuration stubs, and example usage.
8. Author exhaustive Pest test suites with fixtures mirroring provider responses.
9. Validate quality gates (`composer test`, `composer stan`, `composer cs-check`).

## How to Use These Files
- Execute the numbered tasks in order; later steps rely on earlier artifacts.
- Each file contains Codex-ready prompts, expected outputs, and acceptance checks.
- When rebuilding incrementally, commit after each major module once tests pass.

## Deliverable Definition
A freshly generated Scholarly Providers package that:
- Ships identical public APIs and namespaces.
- Supports the same configuration surface in `config/` and `.env.example`.
- Includes documentation parity in `README.md`, `docs/`, and `agent/` specs.
- Achieves ≥90% statement coverage and green `composer quality`.
- Provides release metadata (CHANGELOG stub, version placeholders) ready for Packagist.
