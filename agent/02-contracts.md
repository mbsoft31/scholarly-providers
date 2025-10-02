# Task 02 — Core Contracts

## Goal
Recreate provider-agnostic contracts used throughout the package. These are the bedrock primitives every adapter, exporter, and integration must honour.

## Outputs
- `src/Contracts/Query.php`
- `src/Contracts/Paginator.php`
- `src/Contracts/ScholarlyDataSource.php`

## Instructions for Codex
1. **Define `Query` value object**
   - Immutable or mutable DTO with public typed properties:
     - `?string $q`, `?string $year`, `?bool $openAccess`, `?int $minCitations`, `?int $maxCitations`, `?array $venueIds`, `array $fields = []`, `int $limit = 25`, `?string $cursor`, `?int $offset`, `array $raw = []`.
   - Provide fluent setters returning `$this` for convenience (e.g., `public function year(string $value): self`).
   - Add helper `public static function from(array $payload): self` to map arrays into query object.
   - Include PHPDoc for each method, describe expected formats (e.g., `year` accepts `YYYY`, `YYYY-YYYY`, `YYYY-`).
2. **Define `Paginator` interface**
   - Extends `IteratorAggregate`.
   - Requires `public function page(): array` returning associative array with keys `items` (list<array>) and `nextCursor` (`?string`).
   - `public function getIterator(): Traversable` to iterate pages lazily.
3. **Define `ScholarlyDataSource` interface**
   - Methods exactly matching original signatures:
     - Works: `searchWorks`, `getWorkById`, `getWorkByDoi`, `getWorkByArxiv`, `getWorkByPubmed`, `listCitations`, `listReferences`, `batchWorksByIds`.
     - Authors: `searchAuthors`, `getAuthorById`, `getAuthorByOrcid`, `batchAuthorsByIds`.
     - Meta: `health`, `rateLimitState`.
   - Document expected return shapes and nullability. For batch methods, specify that iterables yield normalized arrays.
   - Add throw tags for `Throwable` where network failures may bubble up.
4. **Namespace and strict types**: `declare(strict_types=1);` at file top, namespace `Scholarly\Contracts`.

## Acceptance Criteria
- `composer dump-autoload` completes without errors.
- `vendor/bin/pest --group=contract` (later) can reference interfaces successfully.
- Public API identical to legacy version to ensure downstream compatibility.
