<?php

declare(strict_types=1);

namespace Scholarly\Adapters\S2;

use Closure;
use Scholarly\Contracts\Paginator;
use Scholarly\Core\Client;
use Throwable;
use Traversable;

use function is_array;
use function is_numeric;
use function is_string;

class S2Paginator implements Paginator
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
        private readonly string  $dataKey = 'data',
    ) {
        $this->payload = $payload;
    }

    /**
     * @return array{items: list<array<string, mixed>>, nextCursor: string|null}
     */
    public function page(): array
    {
        $items  = $this->mapItems($this->payload);
        $cursor = $this->payload['next'] ?? null;

        return [
            'items'      => $items,
            'nextCursor' => $cursor === null || $cursor === '' ? null : (string) $cursor,
        ];
    }

    /**
     * @return Traversable<array<string, mixed>>
     * @throws Throwable
     */
    public function getIterator(): Traversable
    {
        $payload = $this->payload;
        $params  = $this->params;
        $visited = [];

        while (true) {
            foreach ($this->mapItems($payload) as $item) {
                yield $item;
            }

            $cursor = $payload['next'] ?? null;

            if (! is_string($cursor) && ! is_numeric($cursor)) {
                break;
            }

            $cursorKey = (string) $cursor;

            if ($cursorKey === '' || isset($visited[$cursorKey])) {
                break;
            }

            $visited[$cursorKey] = true;
            $params['offset']    = is_numeric($cursor) ? (int) $cursor : $cursorKey;

            $payload = $this->client->get($this->url, $params, [], 'search');
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return list<array<string, mixed>>
     */
    private function mapItems(array $payload): array
    {
        $items = [];
        $data  = $payload[$this->dataKey] ?? [];

        if (! is_array($data)) {
            return [];
        }

        foreach ($data as $row) {
            $mapped = ($this->mapper)($row);

            if (is_array($mapped)) {
                $items[] = $mapped;
            }
        }

        return $items;
    }
}
