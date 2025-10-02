<?php

declare(strict_types=1);

namespace Scholarly\Factory\Config;

final readonly class CacheConfig
{
    public function __construct(
        public mixed   $store = null,
        public ?string $logger = null,
    ) {
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            $config['store']  ?? null,
            $config['logger'] ?? null,
        );
    }
}
