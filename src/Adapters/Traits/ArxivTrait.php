<?php

declare(strict_types=1);

namespace Scholarly\Adapters\Traits;

use Scholarly\Core\Client;
use Scholarly\Core\Exceptions\NotFoundException;
use Scholarly\Core\Identity;

trait ArxivTrait
{
    abstract protected function client(): Client;

    abstract protected function fetchWorkByArxiv(string $normalizedId): ?array;

    public function getWorkByArxiv(string $arxivId): ?array
    {
        $normalized = Identity::normalizeArxiv($arxivId);

        if ($normalized === null) {
            return null;
        }

        try {
            return $this->fetchWorkByArxiv($normalized);
        } catch (NotFoundException) {
            $this->client()->log('Work not found by arXiv identifier', ['arxiv' => $normalized]);

            return null;
        }
    }
}
