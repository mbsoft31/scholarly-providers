<?php

declare(strict_types=1);

use Scholarly\Contracts\Paginator;

final class ArrayPaginator implements Paginator
{
    /** @param list<list<array<string, mixed>>> $pages */
    public function __construct(private array $pages)
    {
    }

    /**
     * @return array{items: list<array<string, mixed>>, nextCursor: ?string}
     */
    public function page(): array
    {
        return [
            'items'      => $this->pages[0] ?? [],
            'nextCursor' => null,
        ];
    }

    public function getIterator(): \Traversable
    {
        foreach ($this->pages as $page) {
            foreach ($page as $item) {
                yield $item;
            }
        }
    }
}

it('iterates over all items lazily', function () {
    $paginator = new ArrayPaginator([
        [['id' => 1], ['id' => 2]],
        [['id' => 3]],
    ]);

    $items = iterator_to_array($paginator);

    expect($items)->toHaveCount(3)
        ->and($items[0]['id'])->toBe(1)
        ->and($items[1]['id'])->toBe(2)
        ->and($items[2]['id'])->toBe(3);
});
