<?php

declare(strict_types=1);

namespace Scholarly\Adapters\Crossref;

use Scholarly\Contracts\Paginator;
use Scholarly\Contracts\Query;
use Scholarly\Contracts\ScholarlyDataSource;
use Scholarly\Core\Client;
use Scholarly\Core\Exceptions\NotFoundException;
use Scholarly\Core\Identity;
use Scholarly\Core\Normalizer;
use Throwable;

use function array_filter;
use function array_map;
use function array_merge;
use function array_unique;
use function array_values;
use function explode;
use function implode;
use function is_array;
use function is_numeric;
use function max;
use function rawurlencode;
use function sprintf;
use function str_contains;
use function str_starts_with;
use function strtolower;
use function substr;
use function trim;

final class DataSource implements ScholarlyDataSource
{
    private const string BASE_URL  = 'https://api.crossref.org';
    private const int DEFAULT_ROWS = 100;

    /**
     * @var array<string, string>
     */
    private array $defaultHeaders;

    /**
     * @var array<string, string>
     */
    private array $defaultQuery;

    public function __construct(
        private readonly Client $client,
        private readonly ?string $mailto = null,
        private readonly int $maxRows = self::DEFAULT_ROWS,
    ) {
        $userAgent = 'ScholarlyClient/1.0';

        $this->defaultHeaders = [
            'Accept'     => 'application/json',
            'User-Agent' => $userAgent,
        ];

        $this->defaultQuery = [];

        if ($this->mailto !== null && trim($this->mailto) !== '') {
            $mailto                             = trim($this->mailto);
            $this->defaultQuery['mailto']       = $mailto;
            $this->defaultHeaders['User-Agent'] = sprintf('%s (mailto:%s)', $userAgent, $mailto);
        }
    }

    public function searchWorks(Query $query): Paginator
    {
        $params = $this->withBaseParams([
            'rows'   => min($query->limit, $this->maxRows),
            'cursor' => $query->cursor ?? '*',
            'sort'   => 'created',
            'order'  => 'desc',
        ]);

        if ($query->q !== null) {
            $params['query'] = $query->q;
        }

        if ($query->offset !== null) {
            $params['offset'] = max(0, $query->offset);
        }

        if ($filters = $this->buildWorkFilters($query)) {
            $params['filter'] = implode(',', $filters);
        }

        if ($select = $this->selectForWorks($query)) {
            $params['select'] = $select;
        }

        if ($query->raw !== []) {
            $params = array_merge($params, $query->raw);
        }

        $response = $this->client->get(self::BASE_URL . '/works', $params, $this->headers(), 'search');

        return new CrossrefPaginator(
            $this->client,
            self::BASE_URL . '/works',
            $this->withoutCursor($params),
            $this->headers(),
            fn (array $item): array => $this->normalizeWork($item),
            $response,
        );
    }

    public function getWorkById(string $id): ?array
    {
        return $this->getWorkByDoi($id);
    }

    public function getWorkByDoi(string $doi): ?array
    {
        $normalized = $this->formatDoi($doi);

        if ($normalized === null) {
            return null;
        }

        try {
            $response = $this->client->get(
                self::BASE_URL . '/works/' . rawurlencode($normalized),
                [],
                $this->headers()
            );
        } catch (NotFoundException) {
            return null;
        }

        $message = $response['message'] ?? null;

        if (! is_array($message)) {
            return null;
        }

        return $this->normalizeWork($message);
    }

    public function getWorkByArxiv(string $arxivId): ?array
    {
        $payload = $this->fetchSingleByFilter('arxiv:' . trim($arxivId));

        return $payload !== null ? $this->normalizeWork($payload) : null;
    }

    public function getWorkByPubmed(string $pmid): ?array
    {
        $payload = $this->fetchSingleByFilter('pubmed:' . trim($pmid));

        return $payload !== null ? $this->normalizeWork($payload) : null;
    }

    public function listCitations(string $id, Query $query): Paginator
    {
        $doi = $this->formatDoi($id);

        if ($doi === null) {
            return $this->emptyPaginator();
        }

        $params = $this->withBaseParams([
            'rows'   => min($query->limit, $this->maxRows),
            'cursor' => $query->cursor ?? '*',
            'filter' => 'reference:' . $doi,
            'sort'   => 'created',
            'order'  => 'desc',
        ]);

        if ($select = $this->selectForWorks($query)) {
            $params['select'] = $select;
        }

        $response = $this->client->get(self::BASE_URL . '/works', $params, $this->headers(), 'search');

        return new CrossrefPaginator(
            $this->client,
            self::BASE_URL . '/works',
            $this->withoutCursor($params),
            $this->headers(),
            fn (array $item): array => $this->normalizeWork($item),
            $response,
        );
    }

    public function listReferences(string $id, Query $query): Paginator
    {
        return $this->emptyPaginator();
    }

    public function batchWorksByIds(iterable $ids, Query $query): iterable
    {
        foreach ($ids as $id) {
            $work = $this->getWorkById((string) $id);

            if ($work !== null) {
                yield $work;
            }
        }
    }

    public function searchAuthors(Query $query): Paginator
    {
        $params = $this->withBaseParams([
            'rows'   => min($query->limit, $this->maxRows),
            'cursor' => $query->cursor ?? '*',
            'select' => 'author,DOI',
        ]);

        if ($query->q !== null) {
            $params['query.author'] = $query->q;
        }

        if ($query->raw !== []) {
            $params = array_merge($params, $query->raw);
        }

        $response = $this->client->get(self::BASE_URL . '/works', $params, $this->headers(), 'search');
        $seen     = [];

        return new CrossrefPaginator(
            $this->client,
            self::BASE_URL . '/works',
            $this->withoutCursor($params),
            $this->headers(),
            function (array $item) use (&$seen): array {
                $authors    = [];
                $rawAuthors = $item['author'] ?? [];

                if (! is_array($rawAuthors)) {
                    return [];
                }

                foreach ($rawAuthors as $rawAuthor) {
                    if (! is_array($rawAuthor)) {
                        continue;
                    }

                    $normalized = Normalizer::author($rawAuthor, 'crossref');
                    $key        = ($normalized['id'] ?? '') . '|' . ($normalized['name'] ?? '');

                    if ($key === '|' || isset($seen[$key])) {
                        continue;
                    }

                    $seen[$key] = true;
                    $authors[]  = $normalized;
                }

                return $authors;
            },
            $response,
        );
    }

    public function getAuthorById(string $id): ?array
    {
        return null;
    }

    public function getAuthorByOrcid(string $orcid): ?array
    {
        $normalized = Identity::normalizeOrcid($orcid);

        if ($normalized === null) {
            return null;
        }

        $payload = $this->fetchSingleByFilter('orcid:' . $normalized);

        if ($payload === null) {
            return null;
        }

        $authors = $payload['author'] ?? [];

        if (! is_array($authors)) {
            return null;
        }

        foreach ($authors as $rawAuthor) {
            if (! is_array($rawAuthor)) {
                continue;
            }

            if (isset($rawAuthor['ORCID']) && Identity::normalizeOrcid((string) $rawAuthor['ORCID']) === $normalized) {
                return Normalizer::author($rawAuthor, 'crossref');
            }
        }

        return null;
    }

    public function batchAuthorsByIds(iterable $ids, Query $query): iterable
    {
        yield from [];
    }

    public function health(): bool
    {
        try {
            $this->client->get(
                self::BASE_URL . '/works',
                $this->withBaseParams(['rows' => 0]),
                $this->headers(),
                'metadata'
            );

            return true;
        } catch (Throwable $exception) {
            $this->client->log('Crossref health check failed', ['error' => $exception->getMessage()]);

            return false;
        }
    }

    public function rateLimitState(): array
    {
        $headers = $this->client->lastResponseHeaders();

        $remaining = null;
        $limit     = null;
        $reset     = null;

        if (isset($headers['x-rate-limit-remaining'][0]) && is_numeric($headers['x-rate-limit-remaining'][0])) {
            $remaining = (int) $headers['x-rate-limit-remaining'][0];
        }

        if (isset($headers['x-rate-limit-limit'][0]) && is_numeric($headers['x-rate-limit-limit'][0])) {
            $limit = (int) $headers['x-rate-limit-limit'][0];
        }

        if (isset($headers['x-rate-limit-reset'][0]) && is_numeric($headers['x-rate-limit-reset'][0])) {
            $reset = (int) $headers['x-rate-limit-reset'][0];
        }

        return [
            'remaining' => $remaining,
            'limit'     => $limit,
            'reset'     => $reset,
        ];
    }

    private function headers(): array
    {
        return $this->defaultHeaders;
    }

    private function withBaseParams(array $params = []): array
    {
        return array_merge($this->defaultQuery, $params);
    }

    private function withoutCursor(array $params): array
    {
        unset($params['cursor']);

        return $params;
    }

    private function selectForWorks(Query $query): ?string
    {
        $map = [
            'id'               => ['DOI'],
            'title'            => ['title'],
            'abstract'         => ['abstract'],
            'year'             => ['issued'],
            'publication_date' => ['issued'],
            'venue'            => ['container-title', 'type'],
            'external_ids'     => ['DOI', 'ISBN', 'ISSN'],
            'counts'           => ['is-referenced-by-count', 'references-count'],
            'authors'          => ['author'],
            'language'         => ['language'],
            'url'              => ['URL'],
        ];

        $requested = $query->fields !== [] ? $query->fields : array_keys($map);
        $select    = [];

        foreach ($requested as $field) {
            $field = strtolower(trim((string) $field));
            if (isset($map[$field])) {
                $select = array_merge($select, $map[$field]);
            }
        }

        $select = array_values(array_unique($select));

        return $select !== [] ? implode(',', $select) : null;
    }

    private function buildWorkFilters(Query $query): array
    {
        $filters = [];

        if ($query->year !== null) {
            $filters = array_merge($filters, $this->parseYearFilter($query->year));
        }

        if ($query->openAccess === true) {
            $filters[] = 'has-license:true';
        }

        if ($query->venueIds !== null && $query->venueIds !== []) {
            $titles = array_filter(array_map([$this, 'formatVenueFilter'], $query->venueIds));
            if ($titles !== []) {
                $filters[] = 'container-title:' . implode('|', $titles);
            }
        }

        return array_values(array_filter($filters));
    }

    private function parseYearFilter(string $value): array
    {
        $value = trim($value);

        if ($value === '') {
            return [];
        }

        if (! str_contains($value, '-')) {
            return [
                'from-pub-date:' . $value,
                'until-pub-date:' . $value,
            ];
        }

        [$start, $end] = array_pad(explode('-', $value, 2), 2, '');
        $start         = trim($start);
        $end           = trim($end);

        $filters = [];

        if ($start !== '') {
            $filters[] = 'from-pub-date:' . $start;
        }

        if ($end !== '') {
            $filters[] = 'until-pub-date:' . $end;
        }

        return $filters;
    }

    private function normalizeWork(array $payload): array
    {
        return Normalizer::work($payload, 'crossref');
    }

    private function formatDoi(string $value): ?string
    {
        return Identity::normalizeDoi($value);
    }

    private function formatVenueFilter(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, 'crossref:')) {
            $value = substr($value, strlen('crossref:'));
        }

        return $value;
    }

    /**
     * @throws Throwable
     */
    private function fetchSingleByFilter(string $filter): ?array
    {
        $response = $this->client->get(
            self::BASE_URL . '/works',
            $this->withBaseParams([
                'filter' => $filter,
                'rows'   => 1,
            ]),
            $this->headers()
        );

        $message = $response['message'] ?? [];
        $items   = $message['items']    ?? [];

        if (! is_array($items) || $items === []) {
            return null;
        }

        $first = $items[0];

        return is_array($first) ? $first : null;
    }

    private function emptyPaginator(): Paginator
    {
        return new CrossrefPaginator(
            $this->client,
            self::BASE_URL . '/works',
            [],
            $this->headers(),
            static fn (): ?array => null,
            ['message' => ['items' => [], 'next-cursor' => null]],
        );
    }
}


