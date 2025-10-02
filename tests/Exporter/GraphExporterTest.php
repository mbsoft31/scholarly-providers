<?php

declare(strict_types=1);

use Scholarly\Contracts\Paginator;
use Scholarly\Contracts\Query;
use Scholarly\Contracts\ScholarlyDataSource;
use Scholarly\Exporter\Graph\GraphExporter;

final class ArrayPaginatorStub implements Paginator
{
    /** @param list<array<string, mixed>> $items */
    public function __construct(private readonly array $items)
    {
    }

    /**
     * @return array{items: list<array<string, mixed>>, nextCursor: ?string}
     */
    public function page(): array
    {
        return ['items' => $this->items, 'nextCursor' => null];
    }

    /**
     * @return \Traversable<array<string, mixed>>
     */
    public function getIterator(): \Traversable
    {
        foreach ($this->items as $item) {
            yield $item;
        }
    }
}

final class FakeDataSource implements ScholarlyDataSource
{
    /**
     * @param array<string, array<string, mixed>>              $works
     * @param array<string, list<array<string, mixed>>>        $references
     * @param array<string, list<array<string, mixed>>>        $citations
     * @param array<string, array<string, mixed>>              $authors
     */
    public function __construct(
        private readonly array $works,
        private readonly array $references = [],
        private readonly array $citations = [],
        private readonly array $authors = [],
    ) {
    }

    /**
     * @return Paginator<array<string, mixed>>
     */
    public function searchWorks(Query $query): Paginator
    {
        return new ArrayPaginatorStub(array_values($this->works));
    }

    public function getWorkById(string $id): ?array
    {
        return $this->works[$id] ?? null;
    }

    public function getWorkByDoi(string $doi): ?array
    {
        foreach ($this->works as $work) {
            if (($work['external_ids']['doi'] ?? null) === $doi) {
                return $work;
            }
        }

        return null;
    }

    public function getWorkByArxiv(string $arxivId): ?array
    {
        return null;
    }

    public function getWorkByPubmed(string $pmid): ?array
    {
        return null;
    }

    /**
     * @return Paginator<array<string, mixed>>
     */
    public function listCitations(string $id, Query $query): Paginator
    {
        return new ArrayPaginatorStub($this->citations[$id] ?? []);
    }

    /**
     * @return Paginator<array<string, mixed>>
     */
    public function listReferences(string $id, Query $query): Paginator
    {
        return new ArrayPaginatorStub($this->references[$id] ?? []);
    }

    /**
     * @param iterable<string> $ids
     * @return iterable<array<string, mixed>>
     */
    public function batchWorksByIds(iterable $ids, Query $query): iterable
    {
        foreach ($ids as $id) {
            if (isset($this->works[$id])) {
                yield $this->works[$id];
            }
        }
    }

    /**
     * @return Paginator<array<string, mixed>>
     */
    public function searchAuthors(Query $query): Paginator
    {
        return new ArrayPaginatorStub(array_values($this->authors));
    }

    public function getAuthorById(string $id): ?array
    {
        return $this->authors[$id] ?? null;
    }

    public function getAuthorByOrcid(string $orcid): ?array
    {
        return null;
    }

    /**
     * @param iterable<string> $ids
     * @return iterable<array<string, mixed>>
     */
    public function batchAuthorsByIds(iterable $ids, Query $query): iterable
    {
        foreach ($ids as $id) {
            if (isset($this->works[$id])) {
                yield $this->works[$id];
            }
        }
    }

    public function health(): bool
    {
        return true;
    }

    public function rateLimitState(): array
    {
        return [];
    }
}

it('builds a citation graph with edges for references and citations', function () {
    $works = [
        'openalex:W1' => [
            'id' => 'openalex:W1',
            'title' => 'Paper 1',
            'authors' => [],
            'external_ids' => ['doi' => '10.1/1'],
        ],
        'openalex:W2' => [
            'id' => 'openalex:W2',
            'title' => 'Paper 2',
            'authors' => [],
        ],
    ];

    $references = [
        'openalex:W1' => [
            ['id' => 'openalex:W2', 'title' => 'Paper 2'],
        ],
    ];

    $citations = [
        'openalex:W1' => [
            ['id' => 'openalex:W3', 'title' => 'Paper 3'],
        ],
    ];

    $dataSource = new FakeDataSource($works, $references, $citations);
    $exporter = new GraphExporter($dataSource);

    $graph = $exporter->buildWorkCitationGraph(['openalex:W1'], Query::from(['limit' => 10]));

    expect($graph->nodes())->toContain('openalex:W1', 'openalex:W2', 'openalex:W3')
        ->and($graph->hasEdge('openalex:W1', 'openalex:W2'))->toBeTrue()
        ->and($graph->hasEdge('openalex:W3', 'openalex:W1'))->toBeTrue();

    $array = $exporter->exportToArray($graph);
    expect($array['directed'])->toBeTrue()
        ->and($array['edges'])->not->toBeEmpty();
});

it('builds author collaboration graph with weighted edges', function () {
    $works = [
        'openalex:W1' => [
            'id' => 'openalex:W1',
            'title' => 'Joint Work',
            'authors' => [
                ['id' => 'openalex:A1', 'name' => 'Ada'],
                ['id' => 'openalex:A2', 'name' => 'Grace'],
            ],
        ],
    ];

    $authors = [
        'openalex:A1' => ['id' => 'openalex:A1', 'name' => 'Ada'],
        'openalex:A2' => ['id' => 'openalex:A2', 'name' => 'Grace'],
    ];

    $dataSource = new FakeDataSource($works, authors: $authors);
    $exporter = new GraphExporter($dataSource);

    $query = Query::from([
        'raw' => ['work_ids' => ['openalex:W1']],
    ]);

    $graph = $exporter->buildAuthorCollaborationGraph(['openalex:A1', 'openalex:A2'], $query);

    expect($graph->nodes())->toContain('openalex:A1', 'openalex:A2')
        ->and($graph->hasEdge('openalex:A1', 'openalex:A2'))->toBeTrue();

    $edgeAttrs = $graph->edgeAttrs('openalex:A1', 'openalex:A2');
    expect($edgeAttrs['weight'])->toBe(1)
        ->and($edgeAttrs['works'])->toContain('openalex:W1');
});
