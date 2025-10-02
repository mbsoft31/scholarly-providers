<?php

declare(strict_types=1);

namespace Scholarly\Adapters\S2;

use Generator;
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

use function array_merge;
use function array_unique;
use function array_values;
use function implode;
use function is_array;
use function is_numeric;
use function max;
use function rawurlencode;
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

    private const string BASE_URL              = 'https://api.semanticscholar.org/graph/v1';
    private const string DEFAULT_PAPER_FIELDS  = 'paperId,title,abstract,year,publicationDate,venue,externalIds,authors,citationCount,referenceCount,openAccessPdf,url,tldr,isOpenAccess';
    private const string DEFAULT_AUTHOR_FIELDS = 'authorId,name,affiliations,paperCount,citationCount,hIndex,url,orcid';
    private const int    PAPER_BATCH_LIMIT     = 500;
    private const int    AUTHOR_BATCH_LIMIT    = 500;

    /**
     * @var array<string, string>
     */
    private array $defaultHeaders;

    public function __construct(
        private readonly Client  $client,
        private readonly ?string $apiKey = null,
        private readonly int     $maxPerPage = 100,
    ) {
        $this->defaultHeaders = [
            'Accept'     => 'application/json',
            'User-Agent' => 'ScholarlyClient/1.0',
        ];

        if ($this->apiKey !== null && trim($this->apiKey) !== '') {
            $this->defaultHeaders['x-api-key'] = trim($this->apiKey);
        }
    }

    protected function client(): Client
    {
        return $this->client;
    }

    public function searchWorks(Query $query): Paginator
    {
        $params = [
            'limit'  => min($query->limit, $this->maxPerPage),
            'offset' => $this->resolveOffset($query),
            'fields' => $this->mapFieldsForPapers($query->fields),
        ];

        if ($query->q !== null) {
            $params['query'] = $query->q;
        }

        if ($query->raw !== []) {
            $params = array_merge($params, $query->raw);
        }

        $response = $this->client->get(self::BASE_URL . '/paper/search', $params, $this->headers(), 'search');

        return new S2Paginator(
            $this->client,
            self::BASE_URL . '/paper/search',
            $params,
            fn (array $item): ?array => $this->normalizeWork($item),
            $response,
        );
    }

    public function getWorkById(string $id): ?array
    {
        $paperId = $this->formatPaperId($id);

        if ($paperId === null) {
            return null;
        }

        try {
            $response = $this->client->get(
                self::BASE_URL . '/paper/' . rawurlencode($paperId),
                ['fields' => $this->mapFieldsForPapers([])],
                $this->headers(),
            );
        } catch (NotFoundException) {
            return null;
        }

        return $this->normalizeWork($response);
    }

    protected function fetchWorkByDoi(string $normalizedDoi): ?array
    {
        try {
            $response = $this->client->get(
                self::BASE_URL . '/paper/DOI:' . rawurlencode($normalizedDoi),
                ['fields' => $this->mapFieldsForPapers([])],
                $this->headers(),
            );
        } catch (NotFoundException|Throwable) {
            return null;
        }

        return $this->normalizeWork($response);
    }

    protected function fetchWorkByArxiv(string $normalizedId): ?array
    {
        try {
            $response = $this->client->get(
                self::BASE_URL . '/paper/ARXIV:' . rawurlencode(strtoupper($normalizedId)),
                ['fields' => $this->mapFieldsForPapers([])],
                $this->headers(),
            );
        } catch (NotFoundException|Throwable) {
            return null;
        }

        return $this->normalizeWork($response);
    }

    protected function fetchWorkByPubmed(string $pmid): ?array
    {
        try {
            $response = $this->client->get(
                self::BASE_URL . '/paper/PMID:' . rawurlencode($pmid),
                ['fields' => $this->mapFieldsForPapers([])],
                $this->headers()
            );
        } catch (NotFoundException|Throwable) {
            return null;
        }

        return $this->normalizeWork($response);
    }

    public function listCitations(string $id, Query $query): Paginator
    {
        $paperId = $this->formatPaperId($id);

        if ($paperId === null) {
            return new S2Paginator($this->client, self::BASE_URL . '/paper/' . $id . '/citations', [], static fn (): ?array => null, ['data' => []]);
        }

        $params = [
            'limit'  => min($query->limit, $this->maxPerPage),
            'offset' => $this->resolveOffset($query),
            'fields' => $this->mapFieldsForPapers($query->fields),
        ];

        $response = $this->client->get(
            self::BASE_URL . '/paper/' . rawurlencode($paperId) . '/citations',
            $params,
            $this->headers(),
            'search'
        );

        return new S2Paginator(
            $this->client,
            self::BASE_URL . '/paper/' . rawurlencode($paperId) . '/citations',
            $params,
            fn (array $item): ?array => is_array($item['citingPaper'] ?? null) ? $this->normalizeWork($item['citingPaper']) : null,
            $response,
        );
    }

    public function listReferences(string $id, Query $query): Paginator
    {
        $paperId = $this->formatPaperId($id);

        if ($paperId === null) {
            return new S2Paginator($this->client, self::BASE_URL . '/paper/' . $id . '/references', [], static fn (): ?array => null, ['data' => []]);
        }

        $params = [
            'limit'  => min($query->limit, $this->maxPerPage),
            'offset' => $this->resolveOffset($query),
            'fields' => $this->mapFieldsForPapers($query->fields),
        ];

        $response = $this->client->get(
            self::BASE_URL . '/paper/' . rawurlencode($paperId) . '/references',
            $params,
            $this->headers(),
            'search'
        );

        return new S2Paginator(
            $this->client,
            self::BASE_URL . '/paper/' . rawurlencode($paperId) . '/references',
            $params,
            fn (array $item): ?array => is_array($item['citedPaper'] ?? null) ? $this->normalizeWork($item['citedPaper']) : null,
            $response,
        );
    }

    public function batchWorksByIds(iterable $ids, Query $query): iterable
    {
        $buffer = [];

        foreach ($ids as $id) {
            $paperId = $this->formatPaperId((string) $id);

            if ($paperId === null) {
                continue;
            }

            $buffer[] = $paperId;

            if (count($buffer) >= self::PAPER_BATCH_LIMIT) {
                yield from $this->sendWorkBatch($buffer, $query);
                $buffer = [];
            }
        }

        if ($buffer !== []) {
            yield from $this->sendWorkBatch($buffer, $query);
        }
    }

    public function searchAuthors(Query $query): Paginator
    {
        $params = [
            'limit'  => min($query->limit, $this->maxPerPage),
            'offset' => $this->resolveOffset($query),
            'fields' => $this->mapFieldsForAuthors($query->fields),
        ];

        if ($query->q !== null) {
            $params['query'] = $query->q;
        }

        if ($query->raw !== []) {
            $params = array_merge($params, $query->raw);
        }

        $response = $this->client->get(self::BASE_URL . '/author/search', $params, $this->headers(), 'search');

        return new S2AuthorPaginator(
            $this->client,
            self::BASE_URL . '/author/search',
            $params,
            fn (array $item): ?array => $this->normalizeAuthor($item),
            $response,
        );
    }

    public function getAuthorById(string $id): ?array
    {
        $authorId = $this->formatAuthorId($id);

        if ($authorId === null) {
            return null;
        }

        try {
            $response = $this->client->get(
                self::BASE_URL . '/author/' . rawurlencode($authorId),
                ['fields' => $this->mapFieldsForAuthors([])],
                $this->headers(),
            );
        } catch (NotFoundException) {
            return null;
        }

        return $this->normalizeAuthor($response);
    }

    protected function fetchAuthorByOrcid(string $normalizedOrcid): ?array
    {
        try {
            $response = $this->client->get(
                self::BASE_URL . '/author/ORCID:' . rawurlencode($normalizedOrcid),
                ['fields' => $this->mapFieldsForAuthors([])],
                $this->headers(),
            );
        } catch (NotFoundException|Throwable) {
            return null;
        }

        return $this->normalizeAuthor($response);
    }

    public function batchAuthorsByIds(iterable $ids, Query $query): iterable
    {
        $buffer = [];

        foreach ($ids as $id) {
            $authorId = $this->formatAuthorId((string) $id);

            if ($authorId === null) {
                continue;
            }

            $buffer[] = $authorId;

            if (count($buffer) >= self::AUTHOR_BATCH_LIMIT) {
                yield from $this->sendAuthorBatch($buffer, $query);
                $buffer = [];
            }
        }

        if ($buffer !== []) {
            yield from $this->sendAuthorBatch($buffer, $query);
        }
    }

    public function health(): bool
    {
        try {
            $this->client->get(
                self::BASE_URL . '/paper/search',
                [
                    'query'  => 'the',
                    'limit'  => 1,
                    'fields' => 'paperId',
                ],
                $this->headers(),
                'metadata'
            );

            return true;
        } catch (Throwable $exception) {
            $this->client->log('S2 health check failed', ['error' => $exception->getMessage()]);

            return false;
        }
    }

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

    private function headers(): array
    {
        return $this->defaultHeaders;
    }

    private function resolveOffset(Query $query): int
    {
        if ($query->cursor !== null && $query->cursor !== '') {
            return (int) $query->cursor;
        }

        if ($query->offset !== null) {
            return max(0, $query->offset);
        }

        return 0;
    }

    /**
     * @param list<string> $fields
     */
    private function mapFieldsForPapers(array $fields): string
    {
        if ($fields === []) {
            return self::DEFAULT_PAPER_FIELDS;
        }

        $map = [
            'id'               => ['paperId'],
            'title'            => ['title'],
            'abstract'         => ['abstract', 'abstractText'],
            'year'             => ['year'],
            'publication_date' => ['publicationDate'],
            'venue'            => ['venue', 'publicationVenue'],
            'external_ids'     => ['externalIds'],
            'counts'           => ['citationCount', 'referenceCount'],
            'authors'          => ['authors'],
            'is_oa'            => ['isOpenAccess', 'openAccessPdf'],
            'oa_url'           => ['openAccessPdf'],
            'url'              => ['url'],
            'tldr'             => ['tldr'],
        ];

        $selected = ['paperId'];

        foreach ($fields as $field) {
            $field = strtolower(trim((string) $field));
            if (isset($map[$field])) {
                $selected = array_merge($selected, $map[$field]);
            }
        }

        $selected = array_values(array_unique($selected));

        return implode(',', $selected);
    }

    /**
     * @param list<string> $fields
     */
    private function mapFieldsForAuthors(array $fields): string
    {
        if ($fields === []) {
            return self::DEFAULT_AUTHOR_FIELDS;
        }

        $map = [
            'id'           => ['authorId'],
            'name'         => ['name'],
            'orcid'        => ['orcid'],
            'counts'       => ['paperCount', 'citationCount', 'hIndex'],
            'url'          => ['url'],
            'affiliations' => ['affiliations'],
        ];

        $selected = ['authorId'];

        foreach ($fields as $field) {
            $field = strtolower(trim((string) $field));
            if (isset($map[$field])) {
                $selected = array_merge($selected, $map[$field]);
            }
        }

        $selected = array_values(array_unique($selected));

        return implode(',', $selected);
    }

    private function normalizeWork(array $payload): array
    {
        return Normalizer::work($payload, 's2');
    }

    private function normalizeAuthor(array $payload): array
    {
        return Normalizer::author($payload, 's2');
    }

    private function formatPaperId(string $id): ?string
    {
        $id = trim($id);

        if ($id === '') {
            return null;
        }

        if (str_starts_with($id, 's2:')) {
            $id = substr($id, 3);
        }

        return $id;
    }

    private function formatAuthorId(string $id): ?string
    {
        $id = trim($id);

        if ($id === '') {
            return null;
        }

        if (str_starts_with($id, 's2:')) {
            $id = substr($id, 3);
        }

        return $id;
    }

    /**
     * @param list<string> $ids
     */
    private function sendWorkBatch(array $ids, Query $query): Generator
    {
        $payload = [
            'ids'    => array_values($ids),
            'fields' => $this->mapFieldsForPapers($query->fields),
        ];

        try {
            $response = $this->client->post(
                self::BASE_URL . '/paper/batch',
                $payload,
                [],
                $this->headers(),
                'batch'
            );
        } catch (Throwable $exception) {
            $this->client->log('S2 batch works request failed', ['error' => $exception->getMessage()]);

            return;
        }

        if (! is_array($response)) {
            return;
        }

        foreach ($response as $item) {
            if (is_array($item)) {
                yield $this->normalizeWork($item);
            }
        }
    }

    /**
     * @param list<string> $ids
     */
    private function sendAuthorBatch(array $ids, Query $query): Generator
    {
        $payload = [
            'ids'    => array_values($ids),
            'fields' => $this->mapFieldsForAuthors($query->fields),
        ];

        try {
            $response = $this->client->post(
                self::BASE_URL . '/author/batch',
                $payload,
                [],
                $this->headers(),
                'batch'
            );
        } catch (Throwable $exception) {
            $this->client->log('S2 batch authors request failed', ['error' => $exception->getMessage()]);

            return;
        }

        if (! is_array($response)) {
            return;
        }

        foreach ($response as $item) {
            if (is_array($item)) {
                yield $this->normalizeAuthor($item);
            }
        }
    }
}
