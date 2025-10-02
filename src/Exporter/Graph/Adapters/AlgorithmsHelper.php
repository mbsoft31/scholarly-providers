<?php

declare(strict_types=1);

namespace Scholarly\Exporter\Graph\Adapters;

use Mbsoft\Graph\Algorithms\Centrality\Betweenness;
use Mbsoft\Graph\Algorithms\Centrality\PageRank;
use Mbsoft\Graph\Algorithms\Components\Connected;
use Mbsoft\Graph\Algorithms\Components\StronglyConnected;
use Mbsoft\Graph\Contracts\GraphInterface;

final class AlgorithmsHelper
{
    /**
     * @return array<string, float>
     */
    public function pageRank(
        GraphInterface $graph,
        float $dampingFactor = 0.85,
        int $maxIterations = 100,
        float $tolerance = 1e-6
    ): array {
        $algorithm = new PageRank($dampingFactor, $maxIterations, $tolerance);

        return $algorithm->compute($graph);
    }

    /**
     * @return array<string, float>
     */
    public function betweenness(GraphInterface $graph, bool $normalized = true): array
    {
        $algorithm = new Betweenness($normalized);

        return $algorithm->compute($graph);
    }

    /**
     * @return list<list<string>>
     */
    public function connectedComponents(GraphInterface $graph): array
    {
        $algorithm = new Connected();

        return $algorithm->findComponents($graph);
    }

    /**
     * @return list<list<string>>
     */
    public function stronglyConnectedComponents(GraphInterface $graph): array
    {
        $algorithm = new StronglyConnected();

        return $algorithm->findComponents($graph);
    }
}
