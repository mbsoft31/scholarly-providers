<?php

declare(strict_types=1);

namespace Scholarly\Factory\Config;

final readonly class GraphConfig
{
    public function __construct(
        public ?int $maxWorks = null,
        public ?int $minCollaborations = null,
    ) {
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            isset($config['max_works']) ? (int) $config['max_works'] : null,
            isset($config['min_collaborations']) ? (int) $config['min_collaborations'] : null,
        );
    }
}
