<?php

declare(strict_types=1);

namespace Scholarly\Adapters\S2;

final class S2AuthorPaginator extends S2Paginator
{
    /**
     * Convenience helper returning the current author page as an array.
     *
     * @return list<array<string, mixed>>
     */
    public function items(): array
    {
        return $this->page()['items'];
    }
}
