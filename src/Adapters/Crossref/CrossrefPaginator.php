<?php

declare(strict_types=1);

namespace Scholarly\Adapters\Crossref;

use Closure;
use Scholarly\Contracts\Paginator;
use Scholarly\Core\Client;
use Throwable;
use Traversable;

use function array_is_list;
use function is_array;
use function is_string;

final readonly class CrossrefPaginator implements Paginator
{
    /**
     * @var array<string, mixed>
     */
    private array $payload;

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $headers
     * @param array<string, mixed> $payload
     */
    public function __construct(
        private Client $client,
        private string $url,
        private array $params,
        private array $headers,
        private Closure $mapper,
        array $payload,
    ) {
        $this->payload = $payload;
    }

    /**
     * @return array{items: list<array<string, mixed>>, nextCursor: string|null}
     */
    public function page(): array
    {
        $items  = $this->mapItems($this->payload);
        $cursor = $this->payload['message']['next-cursor'] ?? null;

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
        $params  = $this->params;
        $visited = [];

        while (true) {
            foreach ($this->mapItems($payload) as $item) {
                yield $item;
            }

            $cursor = $payload['message']['next-cursor'] ?? null;

            if (! is_string($cursor) || $cursor === '' || isset($visited[$cursor])) {
                break;
            }

            $visited[$cursor] = true;
            $params['cursor'] = $cursor;

            $payload = $this->client->get($this->url, $params, $this->headers, 'search');
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return list<array<string, mixed>>
     */
    private function mapItems(array $payload): array
    {
        $items   = [];
        $message = $payload['message'] ?? [];
        $data    = $message['items']   ?? [];

        if (! is_array($data)) {
            return [];
        }

        foreach ($data as $row) {
            $mapped = ($this->mapper)($row);

            if ($mapped === null) {
                continue;
            }

            if (is_array($mapped) && $this->isAssociative($mapped)) {
                $items[] = $mapped;

                continue;
            }

            if (is_array($mapped) && array_is_list($mapped)) {
                foreach ($mapped as $sub) {
                    if (is_array($sub)) {
                        $items[] = $sub;
                    }
                }
            }
        }

        return $items;
    }

    /**
     * @param array $value
     * @return bool
     */
    /**
     * @param array<int|string, mixed> $value
     */
    private function isAssociative(array $value): bool
    {
        return ! array_is_list($value);
    }
}

