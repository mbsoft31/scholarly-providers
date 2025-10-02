<?php

declare(strict_types=1);

namespace Scholarly\Adapters\OpenAlex;

use Closure;
use Scholarly\Contracts\Paginator;
use Scholarly\Core\Client;
use Throwable;
use Traversable;

use function is_array;
use function is_string;

class OpenAlexPaginator implements Paginator
{
    /**
     * @var array<string, mixed>
     */
    private array $payload;

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $payload
     */
    public function __construct(
        private readonly Client  $client,
        private readonly string  $url,
        private readonly array   $params,
        private readonly Closure $mapper,
        array                    $payload,
        private readonly string  $resultKey = 'results',
    ) {
        $this->payload = $payload;
    }

    /**
     * @return array{items: list<array<string, mixed>>, nextCursor: string|null}
     */
    public function page(): array
    {
        $items  = $this->mapItems($this->payload);
        $cursor = $this->payload['meta']['next_cursor'] ?? null;

        return [
            'items'      => $items,
            'nextCursor' => is_string($cursor) && $cursor !== '' ? $cursor : null,
        ];
    }

    /**
     * @return Traversable<array<string, mixed>>
     * @throws Throwable
     */
    public function getIterator(): Traversable
    {
        $payload = $this->payload;
        $visited = [];

        while (true) {
            foreach ($this->mapItems($payload) as $item) {
                yield $item;
            }

            $cursor = $payload['meta']['next_cursor'] ?? null;

            if (! is_string($cursor) || $cursor === '' || isset($visited[$cursor])) {
                break;
            }

            $visited[$cursor]     = true;
            $nextParams           = $this->params;
            $nextParams['cursor'] = $cursor;
            $payload              = $this->client->get($this->url, $nextParams, [], 'search');
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return list<array<string, mixed>>
     */
    private function mapItems(array $payload): array
    {
        $items = [];

        $results = $payload[$this->resultKey] ?? [];

        if (! is_array($results)) {
            return [];
        }

        foreach ($results as $result) {
            $mapped = ($this->mapper)($result);

            if (is_array($mapped)) {
                $items[] = $mapped;
            }
        }

        return $items;
    }
}
