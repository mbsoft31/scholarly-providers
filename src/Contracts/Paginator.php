<?php

declare(strict_types=1);

namespace Scholarly\Contracts;

use IteratorAggregate;
use Traversable;

/**
 * Contract for lazy paginated collections provided by adapters.
 *
 * @extends IteratorAggregate<int, array<string, mixed>>
 */
interface Paginator extends IteratorAggregate
{
    /**
     * Fetch the current page payload.
     *
     * @return array{items: list<array<string, mixed>>, nextCursor: string|null}
     */
    public function page(): array;

    /**
     * Iterate over each item in the paginated collection.
     *
     * @return Traversable<int, array<string, mixed>>
     */
    public function getIterator(): Traversable;
}
