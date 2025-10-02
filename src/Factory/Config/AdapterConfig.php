<?php

declare(strict_types=1);

namespace Scholarly\Factory\Config;

final readonly class AdapterConfig
{
    public function __construct(
        public ?string $mailto = null,
        public ?string $apiKey = null,
        public ?int    $maxPerPage = null,
    ) {
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            $config['mailto']  ?? null,
            $config['api_key'] ?? null,
            isset($config['max_per_page']) ? (int) $config['max_per_page'] : null,
        );
    }
}
