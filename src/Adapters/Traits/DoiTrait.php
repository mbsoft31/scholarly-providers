<?php

declare(strict_types=1);

namespace Scholarly\Adapters\Traits;

use Scholarly\Core\Client;
use Scholarly\Core\Exceptions\NotFoundException;
use Scholarly\Core\Identity;

trait DoiTrait
{
    abstract protected function client(): Client;

    /**
     * Perform the provider specific DOI lookup.
     *
     * @return array<string, mixed>|null
     */
    abstract protected function fetchWorkByDoi(string $normalizedDoi): ?array;

    /**
     * @return array<string, mixed>|null
     */
    public function getWorkByDoi(string $doi): ?array
    {
        $normalized = Identity::normalizeDoi($doi);

        if ($normalized === null) {
            return null;
        }

        try {
            return $this->fetchWorkByDoi($normalized);
        } catch (NotFoundException) {
            $this->client()->log('Work not found by DOI', ['doi' => $normalized]);

            return null;
        }
    }
}
