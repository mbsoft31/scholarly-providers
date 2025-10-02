# Architecture Overview

Scholarly Providers is organised into layered modules that build on shared contracts:

- **Contracts (`src/Contracts`)** define the public surface used by every adapter (`Query`, `Paginator`, `ScholarlyDataSource`).
- **Core (`src/Core`)** contains reusable services: `Client` wraps PSR-18 HTTP clients with retries, caching, and logging; `CacheLayer` unifies PSR-6/16 stores; `Identity` and `Normalizer` convert provider payloads into canonical arrays; custom exceptions live under `Core\Exceptions`.
- **Adapters (`src/Adapters`)** implement provider-specific logic. Each adapter uses the core `Client` for HTTP access, provides paginator implementations to stream responses lazily, and maps provider fields into the normalized schema returned by `Normalizer`.
- **Factory (`src/Factory`)** builds adapters based on configuration. `AdapterFactory` centralises PSR dependency wiring, caching, and exposes `graphExporter()` for downstream modules. Laravel integration reuses the factory.
- **Exporter (`src/Exporter`)** consumes normalized data to build citation or collaboration graphs using `mbsoft31/graph-core`. `GraphExporter` orchestrates data fetches via `ScholarlyDataSource`, applies optional caching, and serializes graphs to arrays/JSON. `Adapters\AlgorithmsHelper` wraps `mbsoft31/graph-algorithms` utilities.
- **Laravel (`src/Laravel`)** ships a service provider that publishes configuration, registers the factory, and exposes a facade for convenient consumption inside applications.

## Data Flow

1. Client code requests an adapter from `AdapterFactory` (directly or via Laravel bindings).
2. The adapter constructs HTTP requests through `Core\Client`, which applies retry/backoff and optional caching via `CacheLayer`.
3. Provider responses are transformed by `Core\Normalizer` into canonical arrays shared across adapters.
4. Higher-level modules (tests, exporter, Laravel services) operate on the normalized contract with no provider-specific branching.

## Extensibility

- New providers implement `ScholarlyDataSource` and reuse `Core\Client`, `CacheLayer`, `Normalizer`, and shared traits.
- Configuration objects inside `Factory\Config` isolate adapter-specific settings and are serializable from arrays/Laravel config.
- Graph exporter accepts any `ScholarlyDataSource`, making it easy to plug in additional adapters without modifying exporter internals.

For detailed provider capabilities and tips, see `docs/providers.md`.
