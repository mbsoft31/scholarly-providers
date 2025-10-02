<?php

declare(strict_types=1);

namespace Scholarly\Exporter\Graph;

use JsonException;
use Mbsoft\Graph\Domain\Graph;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Psr\SimpleCache\InvalidArgumentException;
use Scholarly\Contracts\Paginator;
use Scholarly\Contracts\Query;
use Scholarly\Contracts\ScholarlyDataSource;
use Scholarly\Core\CacheLayer;
use Scholarly\Exporter\Graph\Adapters\AlgorithmsHelper;
use Throwable;

use function array_diff;
use function array_filter;
use function array_flip;
use function array_key_exists;
use function array_keys;
use function array_unique;
use function array_values;
use function count;
use function explode;
use function is_array;
use function is_callable;
use function is_numeric;
use function json_encode;
use function max;
use function md5;
use function sleep;
use function trim;

final class GraphExporter
{
    private readonly LoggerInterface $logger;

    public function __construct(
        private readonly ScholarlyDataSource $dataSource,
        private readonly ?CacheLayer $cache = null,
        ?LoggerInterface $logger = null,
        private ?AlgorithmsHelper $algorithms = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
        $this->algorithms ??= new AlgorithmsHelper();
    }

    /**
     * @throws InvalidArgumentException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function buildWorkCitationGraph(array $workIds, Query $query, ?callable $progress = null): Graph
    {
        $graph = new Graph(true);
        $ids   = $this->normalizeIdentifiers($workIds);

        if ($ids === []) {
            return $graph;
        }

        $works = $this->collectWorks($ids, $query);

        $missing = array_diff($ids, array_keys($works));
        foreach ($missing as $id) {
            if ($work = $this->safeGetWork($id, $query)) {
                $works[$work['id']] = $work;
            }
        }

        $total = count($works);
        $index = 0;

        foreach ($works as $work) {
            ++$index;
            $workId = $this->resolveWorkId($work);

            if ($workId === null) {
                continue;
            }

            $this->addWorkNode($graph, $work);

            $references = $this->fetchReferences($workId, $query);
            foreach ($references as $reference) {
                $referenceId = $this->resolveWorkId($reference);
                if ($referenceId === null || $referenceId === $workId) {
                    continue;
                }

                $this->addWorkNode($graph, $reference);
                $this->addOrIncrementEdge($graph, $workId, $referenceId, ['type' => 'citation']);
            }

            $citations = $this->fetchCitations($workId, $query);
            foreach ($citations as $citation) {
                $citationId = $this->resolveWorkId($citation);
                if ($citationId === null || $citationId === $workId) {
                    continue;
                }

                $this->addWorkNode($graph, $citation);
                $this->addOrIncrementEdge($graph, $citationId, $workId, ['type' => 'citation']);
            }

            $this->maybeThrottle();

            if (is_callable($progress)) {
                $progress($index, $total, $workId, ['type' => 'work']);
            }
        }

        return $graph;
    }

    public function buildAuthorCollaborationGraph(array $authorIds, Query $query, ?callable $progress = null): Graph
    {
        $graph   = new Graph(false);
        $ids     = $this->normalizeIdentifiers($authorIds);
        $targets = array_flip($ids);

        if ($ids !== []) {
            $authors = $this->collectAuthors($ids, $query);
            foreach ($authors as $author) {
                $this->addAuthorNode($graph, $author);
            }
        }

        $works     = $this->resolveSourceWorks($query);
        $threshold = max(1, (int)($query->raw['min_collaborations'] ?? 1));

        $weights    = [];
        $workLookup = [];
        $processed  = 0;
        $limit      = (int)($query->raw['max_works'] ?? 0);

        foreach ($works as $work) {
            $workId  = $this->resolveWorkId($work) ?? 'work:' . md5(json_encode($work));
            $authors = $work['authors']            ?? [];

            if (! is_array($authors) || $authors === []) {
                continue;
            }

            $participants = [];
            foreach ($authors as $author) {
                if (! is_array($author)) {
                    continue;
                }

                $authorId = $author['id'] ?? null;
                if ($authorId === null || $authorId === '') {
                    continue;
                }

                $this->addAuthorNode($graph, $author);
                $participants[] = $authorId;
            }

            $participants = array_values(array_unique($participants));
            $count        = count($participants);

            if ($count < 2) {
                continue;
            }

            for ($i = 0; $i < $count; $i++) {
                for ($j = $i + 1; $j < $count; $j++) {
                    $a = $participants[$i];
                    $b = $participants[$j];

                    if ($a === $b) {
                        continue;
                    }

                    if ($targets !== [] && ! array_key_exists($a, $targets) && ! array_key_exists($b, $targets)) {
                        continue;
                    }

                    [$x, $y]                   = $this->edgeKey($a, $b);
                    $key                       = $x . '|' . $y;
                    $weights[$key]             = ($weights[$key] ?? 0) + 1;
                    $workLookup[$key][$workId] = true;
                }
            }

            ++$processed;
            if ($limit > 0 && $processed >= $limit) {
                break;
            }

            $this->maybeThrottle();

            if (is_callable($progress)) {
                $progress($processed, $limit > 0 ? $limit : null, $workId, ['type' => 'author']);
            }
        }

        foreach ($weights as $key => $weight) {
            if ($weight < $threshold) {
                continue;
            }

            [$a, $b]      = explode('|', $key, 2);
            $worksForEdge = array_keys($workLookup[$key] ?? []);

            $graph->addEdge($a, $b, [
                'weight' => $weight,
                'works'  => $worksForEdge,
            ]);
        }

        return $graph;
    }

    /**
     * @return array{directed: bool, nodes: list<array{id: string, attributes: array<string, mixed>}>, edges: list<array{from: string, to: string, attributes: array<string, mixed>}>}
     */
    public function exportToArray(Graph $graph): array
    {
        $nodes = [];
        foreach ($graph->nodes() as $id) {
            $nodes[] = [
                'id'         => $id,
                'attributes' => $graph->nodeAttrs($id),
            ];
        }

        $edges = [];
        foreach ($graph->edges() as $edge) {
            $edges[] = [
                'from'       => $edge->from,
                'to'         => $edge->to,
                'attributes' => $edge->attributes,
            ];
        }

        return [
            'directed' => $graph->isDirected(),
            'nodes'    => $nodes,
            'edges'    => $edges,
        ];
    }

    /**
     * @throws JsonException
     */
    public function exportToJson(Graph $graph): string
    {
        return json_encode($this->exportToArray($graph), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public function algorithms(): AlgorithmsHelper
    {
        return $this->algorithms ??= new AlgorithmsHelper();
    }

    private function normalizeIdentifiers(array $ids): array
    {
        $normalized = [];
        foreach ($ids as $id) {
            if (! is_string($id)) {
                continue;
            }

            $value = trim($id);
            if ($value !== '') {
                $normalized[] = $value;
            }
        }

        return array_values(array_unique($normalized));
    }

    private function collectWorks(array $ids, Query $query): array
    {
        $works      = [];
        $batchQuery = $this->cloneQuery($query);

        try {
            foreach ($this->dataSource->batchWorksByIds($ids, $batchQuery) as $work) {
                if (! is_array($work)) {
                    continue;
                }

                $workId = $this->resolveWorkId($work);
                if ($workId === null) {
                    continue;
                }

                $works[$workId] = $work;
            }
        } catch (Throwable $exception) {
            $this->logger->warning('Batch work retrieval failed', ['error' => $exception->getMessage()]);
        }

        return $works;
    }

    private function collectAuthors(array $ids, Query $query): array
    {
        $authors    = [];
        $batchQuery = $this->cloneQuery($query);

        try {
            foreach ($this->dataSource->batchAuthorsByIds($ids, $batchQuery) as $author) {
                if (! is_array($author)) {
                    continue;
                }

                $authorId = $author['id'] ?? null;
                if ($authorId === null || $authorId === '') {
                    continue;
                }

                $authors[$authorId] = $author;
            }
        } catch (Throwable $exception) {
            $this->logger->warning('Batch author retrieval failed', ['error' => $exception->getMessage()]);
        }

        return $authors;
    }

    private function safeGetWork(string $id, Query $query): ?array
    {
        try {
            $work = $this->dataSource->getWorkById($id);
        } catch (Throwable $exception) {
            $this->logger->warning('Failed to fetch work', ['id' => $id, 'error' => $exception->getMessage()]);

            return null;
        }

        if (! is_array($work)) {
            return null;
        }

        $work['id'] ??= $id;

        return $work;
    }

    /**
     * @throws InvalidArgumentException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    private function fetchReferences(string $workId, Query $query): array
    {
        $key = $this->cacheKey('refs:' . $workId, $query);

        $resolver = function () use ($workId, $query): array {
            $results        = [];
            $referenceQuery = $this->cloneQuery($query);
            $referenceQuery->cursor(null);
            $referenceQuery->offset(null);

            try {
                $paginator = $this->dataSource->listReferences($workId, $referenceQuery);
                $this->collectFromPaginator($paginator, $results);
            } catch (Throwable $exception) {
                $this->logger->warning('Failed to list references', ['id' => $workId, 'error' => $exception->getMessage()]);
            }

            return array_values($results);
        };

        return $this->remember($key, $resolver);
    }

    /**
     * @throws InvalidArgumentException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    private function fetchCitations(string $workId, Query $query): array
    {
        $key = $this->cacheKey('cites:' . $workId, $query);

        $resolver = function () use ($workId, $query): array {
            $results       = [];
            $citationQuery = $this->cloneQuery($query);
            $citationQuery->cursor(null);
            $citationQuery->offset(null);

            try {
                $paginator = $this->dataSource->listCitations($workId, $citationQuery);
                $this->collectFromPaginator($paginator, $results);
            } catch (Throwable $exception) {
                $this->logger->warning('Failed to list citations', ['id' => $workId, 'error' => $exception->getMessage()]);
            }

            return array_values($results);
        };

        return $this->remember($key, $resolver);
    }

    private function collectFromPaginator(Paginator $paginator, array &$bucket): void
    {
        foreach ($paginator as $item) {
            if (! is_array($item)) {
                continue;
            }

            $id = $this->resolveWorkId($item);
            if ($id === null) {
                continue;
            }

            $bucket[$id] = $item;
        }
    }

    private function resolveWorkId(array $work): ?string
    {
        $id = $work['id'] ?? null;

        if (is_string($id) && trim($id) !== '') {
            return trim($id);
        }

        $external = $work['external_ids'] ?? [];
        if (is_array($external)) {
            foreach (['doi', 's2', 'openalex'] as $key) {
                if (! empty($external[$key]) && is_string($external[$key])) {
                    return trim($external[$key]);
                }
            }
        }

        return null;
    }

    private function addWorkNode(Graph $graph, array $work): void
    {
        $id = $this->resolveWorkId($work);

        if ($id === null) {
            return;
        }

        $attrs = [
            'title'        => $work['title']        ?? null,
            'year'         => $work['year']         ?? null,
            'counts'       => $work['counts']       ?? null,
            'external_ids' => $work['external_ids'] ?? null,
            'is_oa'        => $work['is_oa']        ?? null,
        ];

        $graph->addNode($id, array_filter($attrs, static fn ($value) => $value !== null));
    }

    private function addAuthorNode(Graph $graph, array $author): void
    {
        $id = $author['id'] ?? null;
        if (! is_string($id) || trim($id) === '') {
            return;
        }

        $attrs = [
            'name'         => $author['name']         ?? null,
            'orcid'        => $author['orcid']        ?? null,
            'counts'       => $author['counts']       ?? null,
            'affiliations' => $author['affiliations'] ?? null,
        ];

        $graph->addNode(trim($id), array_filter($attrs, static fn ($value) => $value !== null));
    }

    private function addOrIncrementEdge(Graph $graph, string $from, string $to, array $attrs = []): void
    {
        $attrs['weight'] = $attrs['weight'] ?? 1;

        if ($graph->hasEdge($from, $to)) {
            $existing = $graph->edgeAttrs($from, $to);
            $attrs    = $existing + ['weight' => ($existing['weight'] ?? 1) + $attrs['weight']];
        }

        $graph->addEdge($from, $to, $attrs);
    }

    /**
     * @throws InvalidArgumentException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    private function remember(string $key, callable $resolver): array
    {
        if ($this->cache === null) {
            return $resolver();
        }

        return $this->cache->remember($key, $resolver(...), $this->cache->getTtlHint('metadata'));
    }

    private function cacheKey(string $prefix, Query $query): string
    {
        try {
            $payload = json_encode($query->toArray(), JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $payload = '{}';
        }

        return 'graph:' . md5($prefix . '|' . $payload);
    }

    private function cloneQuery(Query $query): Query
    {
        return Query::from($query->toArray());
    }

    private function maybeThrottle(): void
    {
        $state     = $this->dataSource->rateLimitState();
        $remaining = $state['remaining'] ?? null;
        $reset     = $state['reset']     ?? null;

        if (is_numeric($remaining) && (int) $remaining <= 1 && is_numeric($reset) && (int) $reset > 0) {
            $sleep = (int) $reset;
            $sleep = max(0, $sleep);

            if ($sleep > 0 && $sleep <= 5) {
                $this->logger->info('Rate limit reached – pausing graph export', ['sleep' => $sleep]);
                sleep($sleep);
            }
        }
    }

    private function resolveSourceWorks(Query $query): array
    {
        $works = [];
        $raw   = $query->raw['work_ids'] ?? null;

        if (is_array($raw) && $raw !== []) {
            $works = array_values($this->collectWorks($this->normalizeIdentifiers($raw), $query));
        } else {
            try {
                $paginator = $this->dataSource->searchWorks($query);
                foreach ($paginator as $work) {
                    if (is_array($work)) {
                        $works[] = $work;
                    }
                }
            } catch (Throwable $exception) {
                $this->logger->warning('Work search for collaboration graph failed', ['error' => $exception->getMessage()]);
            }
        }

        return $works;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function edgeKey(string $a, string $b): array
    {
        if (strcmp($a, $b) < 0) {
            return [$a, $b];
        }

        return [$b, $a];
    }
}
