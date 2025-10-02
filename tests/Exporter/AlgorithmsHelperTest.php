<?php

declare(strict_types=1);

use Mbsoft\Graph\Domain\Graph;
use Scholarly\Exporter\Graph\Adapters\AlgorithmsHelper;

it('computes centrality metrics for directed graphs', function (): void {
    $graph = new Graph(true);
    $graph->addEdge('a', 'b');
    $graph->addEdge('b', 'c');
    $graph->addEdge('c', 'a');

    $helper   = new AlgorithmsHelper();
    $pageRank = $helper->pageRank($graph);
    $between  = $helper->betweenness($graph, false);

    expect($pageRank)
        ->toHaveKeys(['a', 'b', 'c'])
        ->and($pageRank['a'])->toBeGreaterThan(0.0);

    expect($between)->toBeArray();

    $strong     = $helper->stronglyConnectedComponents($graph);
    $normalized = array_map(static function (array $component): string {
        sort($component);
        return implode(',', $component);
    }, $strong);
    sort($normalized);

    expect($normalized)->toBe(['a,b,c']);
});

it('finds connected components in undirected graphs', function (): void {
    $graph = new Graph(false);
    $graph->addEdge('n1', 'n2');
    $graph->addNode('isolated');

    $helper     = new AlgorithmsHelper();
    $components = $helper->connectedComponents($graph);

    expect($components)->toBeArray()->toBeEmpty();
});
