<?php

declare(strict_types=1);

namespace Scholarly\Adapters\OpenAlex;

final class AuthorPaginator extends OpenAlexPaginator
{
    /**
     * Return the current page of normalized authors without advancing the iterator.
     *
     * @return list<array<string, mixed>>
     */
    public function items(): array
    {
        return $this->page()['items'];
    }
}
