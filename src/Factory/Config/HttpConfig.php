<?php

declare(strict_types=1);

namespace Scholarly\Factory\Config;

final readonly class HttpConfig
{
    /**
     * @param array{base?: float|int, max?: float|int, factor?: float|int} $backoff
     */
    public function __construct(
        public ?string $client = null,
        public ?float  $timeout = null,
        public ?string $userAgent = null,
        /** @var array{base?: float|int, max?: float|int, factor?: float|int} */
        public array   $backoff = [],
        public mixed   $logger = null,
    ) {
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        $backoff = $config['backoff'] ?? [];

        return new self(
            $config['client'] ?? null,
            isset($config['timeout']) ? (float) $config['timeout'] : null,
            $config['user_agent'] ?? null,
            is_array($backoff) ? $backoff : [],
            $config['logger'] ?? null,
        );
    }
}
