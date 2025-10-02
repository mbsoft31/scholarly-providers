<?php

declare(strict_types=1);

namespace Scholarly\Contracts;

use IteratorAggregate;
use Traversable;

/**
 * Contract for lazy paginated collections provided by adapters.
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
     * @return Traversable<array<string, mixed>>
     */
    public function getIterator(): Traversable;
}
