<?php

declare(strict_types=1);

namespace Scholarly\Adapters\OpenAlex;

use Generator;
use JsonException;
use Scholarly\Adapters\Traits\ArxivTrait;
use Scholarly\Adapters\Traits\DoiTrait;
use Scholarly\Adapters\Traits\OrcidTrait;
use Scholarly\Adapters\Traits\PubmedTrait;
use Scholarly\Contracts\Paginator;
use Scholarly\Contracts\Query;
use Scholarly\Contracts\ScholarlyDataSource;
use Scholarly\Core\Client;
use Scholarly\Core\Exceptions\NotFoundException;
use Scholarly\Core\Normalizer;
use Throwable;

use function array_filter;
use function array_map;
use function array_merge;
use function array_values;
use function implode;
use function is_array;
use function is_numeric;
use function rawurlencode;
use function sprintf;
use function str_starts_with;
use function strtolower;
use function substr;
use function trim;

final class DataSource implements ScholarlyDataSource
{
    use ArxivTrait;
    use DoiTrait;
    use OrcidTrait;
    use PubmedTrait;

    private const string BASE_URL       = 'https://api.openalex.org';
    private const int WORK_BATCH_SIZE   = 50;
    private const int AUTHOR_BATCH_SIZE = 50;

    /**
     * @var array<string, string>
     */
    private array $defaultHeaders;

    /**
     * @var array<string, string>
     */
    private array $defaultQuery;

    public function __construct(
        private readonly Client  $client,
        private readonly ?string $mailto = null,
        private readonly int     $maxPerPage = 200,
    ) {
        $userAgent = 'ScholarlyClient/1.0';

        $this->defaultHeaders = [
            'Accept'     => 'application/json',
            'User-Agent' => $userAgent,
        ];

        $this->defaultQuery = [];

        if ($this->mailto !== null && trim($this->mailto) !== '') {
            $this->defaultQuery['mailto']       = trim($this->mailto);
            $this->defaultHeaders['User-Agent'] = sprintf('%s (mailto:%s)', $userAgent, $this->mailto);
        }
    }

    protected function client(): Client
    {
        return $this->client;
    }

    public function searchWorks(Query $query): Paginator
    {
        $params = $this->withBaseParams([
            'cursor'   => $query->cursor ?? '*',
            'per-page' => min($query->limit, $this->maxPerPage),
        ]);

        if ($query->q !== null) {
            $params['search'] = $query->q;
        }

        $filters = $this->buildWorkFilters($query);
        if ($filters !== []) {
            $params['filter'] = implode(',', $filters);
        }

        if ($select = $this->selectForWorks($query)) {
            $params['select'] = $select;
        }

        if ($query->offset !== null) {
            $params['page'] = max(1, $query->offset);
        }

        if ($query->raw !== []) {
            $params = array_merge($params, $query->raw);
        }

        $response = $this->client->get(self::BASE_URL . '/works', $params, $this->defaultHeaders, 'search');

        return new OpenAlexPaginator(
            $this->client,
            self::BASE_URL . '/works',
            $this->withoutCursor($params),
            fn (array $item): array => $this->normalizeWork($item),
            $response,
        );
    }

    public function getWorkById(string $id): ?array
    {
        $identifier = $this->formatWorkId($id);

        if ($identifier === null) {
            return null;
        }

        try {
            $response = $this->client->get(
                self::BASE_URL . '/works/' . rawurlencode($identifier),
                $this->withSelectForWorks(),
                $this->defaultHeaders
            );
        } catch (NotFoundException) {
            return null;
        }

        return $this->normalizeWork($response);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function fetchWorkByDoi(string $normalizedDoi): ?array
    {
        try {
            $response = $this->client->get(
                self::BASE_URL . '/works/doi:' . rawurlencode($normalizedDoi),
                $this->withSelectForWorks(),
                $this->defaultHeaders
            );
        } catch (NotFoundException|Throwable) {
            return null;
        }

        return $this->normalizeWork($response);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function fetchWorkByArxiv(string $normalizedId): ?array
    {
        try {
            $response = $this->client->get(
                self::BASE_URL . '/works/arxiv:' . rawurlencode($normalizedId),
                $this->withSelectForWorks(),
                $this->defaultHeaders
            );
        } catch (NotFoundException|Throwable) {
            return null;
        }

        return $this->normalizeWork($response);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function fetchWorkByPubmed(string $pmid): ?array
    {
        try {
            $response = $this->client->get(
                self::BASE_URL . '/works/pmid:' . rawurlencode($pmid),
                $this->withSelectForWorks(),
                $this->defaultHeaders
            );
        } catch (NotFoundException|Throwable) {
            return null;
        }

        return $this->normalizeWork($response);
    }

    public function listCitations(string $id, Query $query): Paginator
    {
        $identifier = $this->formatWorkId($id);

        if ($identifier === null) {
            return new OpenAlexPaginator($this->client, self::BASE_URL . '/works', [], static fn (): ?array => null, ['results' => []]);
        }

        $url    = self::BASE_URL . '/works/' . rawurlencode($identifier) . '/citations';
        $params = $this->withBaseParams([
            'cursor'   => $query->cursor ?? '*',
            'per-page' => min($query->limit, $this->maxPerPage),
        ]);

        $response = $this->client->get($url, $params, $this->defaultHeaders, 'search');

        return new OpenAlexPaginator(
            $this->client,
            $url,
            $this->withoutCursor($params),
            fn (array $item): ?array => is_array($item['citing_work'] ?? null) ? $this->normalizeWork($item['citing_work']) : null,
            $response,
        );
    }

    public function listReferences(string $id, Query $query): Paginator
    {
        $identifier = $this->formatWorkId($id);

        if ($identifier === null) {
            return new OpenAlexPaginator($this->client, self::BASE_URL . '/works', [], static fn (): ?array => null, ['results' => []]);
        }

        $url    = self::BASE_URL . '/works/' . rawurlencode($identifier) . '/references';
        $params = $this->withBaseParams([
            'cursor'   => $query->cursor ?? '*',
            'per-page' => min($query->limit, $this->maxPerPage),
        ]);

        $response = $this->client->get($url, $params, $this->defaultHeaders, 'search');

        return new OpenAlexPaginator(
            $this->client,
            $url,
            $this->withoutCursor($params),
            fn (array $item): ?array => is_array($item['referenced_work'] ?? null) ? $this->normalizeWork($item['referenced_work']) : null,
            $response,
        );
    }

    public function batchWorksByIds(iterable $ids, Query $query): iterable
    {
        $batch = [];

        foreach ($ids as $id) {
            $formatted = $this->formatWorkId($id);

            if ($formatted === null) {
                continue;
            }

            $batch[] = $formatted;

            if (count($batch) >= self::WORK_BATCH_SIZE) {
                yield from $this->sendWorkBatch($batch, $query);
                $batch = [];
            }
        }

        if ($batch !== []) {
            yield from $this->sendWorkBatch($batch, $query);
        }
    }

    public function searchAuthors(Query $query): Paginator
    {
        $params = $this->withBaseParams([
            'cursor'   => $query->cursor ?? '*',
            'per-page' => min($query->limit, $this->maxPerPage),
        ]);

        if ($query->q !== null) {
            $params['search'] = $query->q;
        }

        if ($select = $this->selectForAuthors($query)) {
            $params['select'] = $select;
        }

        if ($query->raw !== []) {
            $params = array_merge($params, $query->raw);
        }

        $response = $this->client->get(self::BASE_URL . '/authors', $params, $this->defaultHeaders, 'search');

        return new AuthorPaginator(
            $this->client,
            self::BASE_URL . '/authors',
            $this->withoutCursor($params),
            fn (array $item): array => $this->normalizeAuthor($item),
            $response,
        );
    }

    public function getAuthorById(string $id): ?array
    {
        $identifier = $this->formatAuthorId($id);

        if ($identifier === null) {
            return null;
        }

        try {
            $response = $this->client->get(
                self::BASE_URL . '/authors/' . rawurlencode($identifier),
                $this->withSelectForAuthors(),
                $this->defaultHeaders
            );
        } catch (NotFoundException) {
            return null;
        }

        return $this->normalizeAuthor($response);
    }

    /**
     * @return array<string, mixed>|null
     *
     * @throws Throwable
     */
    protected function fetchAuthorByOrcid(string $normalizedOrcid): ?array
    {
        try {
            $response = $this->client->get(
                self::BASE_URL . '/authors/orcid:' . rawurlencode($normalizedOrcid),
                $this->withSelectForAuthors(),
                $this->defaultHeaders
            );
        } catch (NotFoundException) {
            return null;
        }

        return $this->normalizeAuthor($response);
    }

    public function batchAuthorsByIds(iterable $ids, Query $query): iterable
    {
        $batch = [];

        foreach ($ids as $id) {
            $formatted = $this->formatAuthorId($id);

            if ($formatted === null) {
                continue;
            }

            $batch[] = $formatted;

            if (count($batch) >= self::AUTHOR_BATCH_SIZE) {
                yield from $this->sendAuthorBatch($batch, $query);
                $batch = [];
            }
        }

        if ($batch !== []) {
            yield from $this->sendAuthorBatch($batch, $query);
        }
    }

    public function health(): bool
    {
        try {
            $this->client->get(self::BASE_URL . '/status', $this->withBaseParams(), $this->defaultHeaders, 'metadata');

            return true;
        } catch (Throwable $exception) {
            $this->client->log('OpenAlex health check failed', ['error' => $exception->getMessage()]);

            return false;
        }
    }

    /**
     * @return array{remaining: int|null, limit: int|null, reset: int|null}
     */
    public function rateLimitState(): array
    {
        $headers = $this->client->lastResponseHeaders();

        $remaining = null;
        $limit     = null;
        $reset     = null;

        if (isset($headers['x-ratelimit-remaining'][0]) && is_numeric($headers['x-ratelimit-remaining'][0])) {
            $remaining = (int) $headers['x-ratelimit-remaining'][0];
        }

        if (isset($headers['x-ratelimit-limit'][0]) && is_numeric($headers['x-ratelimit-limit'][0])) {
            $limit = (int) $headers['x-ratelimit-limit'][0];
        }

        if (isset($headers['x-ratelimit-reset'][0]) && is_numeric($headers['x-ratelimit-reset'][0])) {
            $reset = (int) $headers['x-ratelimit-reset'][0];
        }

        return [
            'remaining' => $remaining,
            'limit'     => $limit,
            'reset'     => $reset,
        ];
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function withBaseParams(array $params = []): array
    {
        return array_merge($this->defaultQuery, $params);
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function withoutCursor(array $params): array
    {
        unset($params['cursor']);

        return $params;
    }

    private function selectForWorks(Query $query): ?string
    {
        $map = [
            'id'               => ['id'],
            'title'            => ['display_name'],
            'abstract'         => ['abstract', 'abstract_inverted_index'],
            'year'             => ['publication_year'],
            'publication_date' => ['publication_date'],
            'venue'            => ['host_venue', 'primary_location'],
            'external_ids'     => ['ids'],
            'is_oa'            => ['open_access'],
            'counts'           => ['cited_by_count', 'referenced_works_count'],
            'authors'          => ['authorships'],
            'language'         => ['language'],
            'tldr'             => ['summary'],
            'type'             => ['type'],
            'url'              => ['primary_location'],
            'references'       => ['referenced_works'],
        ];

        $requested = $query->fields !== [] ? $query->fields : array_keys($map);

        $select = [];
        foreach ($requested as $field) {
            $field = strtolower(trim((string) $field));
            if (isset($map[$field])) {
                $select = array_merge($select, $map[$field]);
            }
        }

        $select = array_values(array_unique($select));

        return $select !== [] ? implode(',', $select) : null;
    }

    private function selectForAuthors(?Query $query = null): ?string
    {
        $map = [
            'id'           => ['id'],
            'name'         => ['display_name'],
            'orcid'        => ['orcid'],
            'counts'       => ['works_count', 'cited_by_count', 'summary_stats'],
            'affiliations' => ['last_known_institution'],
            'url'          => ['homepage_url'],
        ];

        $requested = $query?->fields ?? array_keys($map);

        $select = [];
        foreach ($requested as $field) {
            $field = strtolower(trim((string) $field));
            if (isset($map[$field])) {
                $select = array_merge($select, $map[$field]);
            }
        }

        $select = array_values(array_unique($select));

        return $select !== [] ? implode(',', $select) : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function withSelectForWorks(): array
    {
        $select = $this->selectForWorks(new Query());

        return $select ? $this->withBaseParams(['select' => $select]) : $this->withBaseParams();
    }

    /**
     * @return array<string, mixed>
     */
    private function withSelectForAuthors(): array
    {
        $select = $this->selectForAuthors();

        return $select ? $this->withBaseParams(['select' => $select]) : $this->withBaseParams();
    }

    /**
     * @return list<string>
     */
    private function buildWorkFilters(Query $query): array
    {
        $filters = [];

        if ($query->year !== null) {
            $filters[] = 'publication_year:' . trim($query->year);
        }

        if ($query->openAccess !== null) {
            $filters[] = 'is_oa:' . ($query->openAccess ? 'true' : 'false');
        }

        if ($query->minCitations !== null) {
            $filters[] = 'cited_by_count:>' . $query->minCitations;
        }

        if ($query->maxCitations !== null) {
            $filters[] = 'cited_by_count:<' . $query->maxCitations;
        }

        if ($query->venueIds !== null && $query->venueIds !== []) {
            $venues = array_map([$this, 'formatVenueId'], $query->venueIds);
            $venues = array_filter($venues, static fn ($value) => $value !== null);

            if ($venues !== []) {
                $filters[] = 'host_venue.id:' . implode('|', $venues);
            }
        }

        return array_values(array_filter($filters));
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function normalizeWork(array $payload): array
    {
        return Normalizer::work($payload, 'openalex');
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function normalizeAuthor(array $payload): array
    {
        return Normalizer::author($payload, 'openalex');
    }

    private function formatWorkId(string $id): ?string
    {
        $id = trim($id);

        if ($id === '') {
            return null;
        }

        if (str_starts_with($id, 'https://openalex.org/')) {
            $id = substr($id, strlen('https://openalex.org/'));
        }

        return $id;
    }

    private function formatAuthorId(string $id): ?string
    {
        $id = trim($id);

        if ($id === '') {
            return null;
        }

        if (str_starts_with($id, 'https://openalex.org/')) {
            $id = substr($id, strlen('https://openalex.org/'));
        }

        return $id;
    }

    private function formatVenueId(string $id): ?string
    {
        $id = trim($id);

        if ($id === '') {
            return null;
        }

        if (str_starts_with($id, 'https://openalex.org/')) {
            return $id;
        }

        if (str_starts_with($id, 'V')) {
            return 'https://openalex.org/' . $id;
        }

        return null;
    }

    /**
     * @throws Throwable
     * @throws JsonException
     */
    /**
     * @param list<string> $ids
     * @return Generator<int, array<string, mixed>>
     *
     * @throws Throwable
     * @throws JsonException
     */
    private function sendWorkBatch(array $ids, Query $query): Generator
    {
        $payload = [
            'ids' => array_map(static fn (string $id): string => 'https://openalex.org/' . $id, $ids),
        ];

        if ($select = $this->selectForWorks($query)) {
            $payload['select'] = $select;
        }

        $response = $this->client->post(
            self::BASE_URL . '/works/batch',
            $payload,
            $this->withBaseParams(),
            $this->defaultHeaders,
            'batch'
        );

        if (! isset($response['results']) || ! is_array($response['results'])) {
            return;
        }

        foreach ($response['results'] as $item) {
            if (is_array($item)) {
                yield $this->normalizeWork($item);
            }
        }
    }

    /**
     * @throws Throwable
     * @throws JsonException
     */
    /**
     * @param list<string> $ids
     * @return Generator<int, array<string, mixed>>
     *
     * @throws Throwable
     * @throws JsonException
     */
    private function sendAuthorBatch(array $ids, Query $query): Generator
    {
        $payload = [
            'ids' => array_map(static fn (string $id): string => 'https://openalex.org/' . $id, $ids),
        ];

        if ($select = $this->selectForAuthors($query)) {
            $payload['select'] = $select;
        }

        $response = $this->client->post(
            self::BASE_URL . '/authors/batch',
            $payload,
            $this->withBaseParams(),
            $this->defaultHeaders,
            'batch'
        );

        if (! isset($response['results']) || ! is_array($response['results'])) {
            return;
        }

        foreach ($response['results'] as $item) {
            if (is_array($item)) {
                yield $this->normalizeAuthor($item);
            }
        }
    }
}
