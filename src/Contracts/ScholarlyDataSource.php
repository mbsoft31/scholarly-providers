<?php

declare(strict_types=1);

namespace Scholarly\Contracts;

use Throwable;

/**
 * Shared contract implemented by all scholarly data provider adapters.
 */
interface ScholarlyDataSource
{
    /**
     * Execute a works search request.
     *
     * @throws Throwable When the underlying HTTP client fails or the provider responds with an error.
     */
    public function searchWorks(Query $query): Paginator;

    /**
     * Retrieve a single work by provider specific identifier.
     *
     * @return array<string, mixed>|null Normalized work payload or null when not found.
     *
     * @throws Throwable
     */
    public function getWorkById(string $id): ?array;

    /**
     * Retrieve a single work by DOI.
     *
     * @return array<string, mixed>|null
     *
     * @throws Throwable
     */
    public function getWorkByDoi(string $doi): ?array;

    /**
     * Retrieve a single work by arXiv identifier.
     *
     * @return array<string, mixed>|null
     *
     * @throws Throwable
     */
    public function getWorkByArxiv(string $arxivId): ?array;

    /**
     * Retrieve a single work by PubMed identifier.
     *
     * @return array<string, mixed>|null
     *
     * @throws Throwable
     */
    public function getWorkByPubmed(string $pmid): ?array;

    /**
     * List citation relationships for a given work identifier.
     *
     * @throws Throwable
     */
    public function listCitations(string $id, Query $query): Paginator;

    /**
     * List referenced works for a given work identifier.
     *
     * @throws Throwable
     */
    public function listReferences(string $id, Query $query): Paginator;

    /**
     * Batch fetch works by identifier. Implementations should yield normalized arrays lazily.
     *
     * @param iterable<int, string> $ids
     * @return iterable<int, array<string, mixed>>
     *
     * @throws Throwable
     */
    public function batchWorksByIds(iterable $ids, Query $query): iterable;

    /**
     * Execute an author search request.
     *
     * @throws Throwable
     */
    public function searchAuthors(Query $query): Paginator;

    /**
     * Retrieve a single author by provider specific identifier.
     *
     * @return array<string, mixed>|null
     *
     * @throws Throwable
     */
    public function getAuthorById(string $id): ?array;

    /**
     * Retrieve a single author by ORCID identifier.
     *
     * @return array<string, mixed>|null
     *
     * @throws Throwable
     */
    public function getAuthorByOrcid(string $orcid): ?array;

    /**
     * Batch fetch authors by identifier.
     *
     * @param iterable<int, string> $ids
     * @return iterable<int, array<string, mixed>>
     *
     * @throws Throwable
     */
    public function batchAuthorsByIds(iterable $ids, Query $query): iterable;

    /**
     * Check provider health. Should swallow transient exceptions and return false when the provider appears down.
     */
    public function health(): bool;

    /**
     * Provide current rate limit state as an associative array of remaining quota, reset timestamps, etc.
     *
     * @return array<string, int|null>
     */
    public function rateLimitState(): array;
}
