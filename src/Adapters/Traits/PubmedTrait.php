<?php

declare(strict_types=1);

namespace Scholarly\Adapters\Traits;

use Scholarly\Core\Client;
use Scholarly\Core\Exceptions\NotFoundException;
use Scholarly\Core\Identity;

trait PubmedTrait
{
    abstract protected function client(): Client;

    abstract protected function fetchWorkByPubmed(string $pmid): ?array;

    public function getWorkByPubmed(string $pmid): ?array
    {
        $normalized = Identity::normalizePmid($pmid);

        if ($normalized === null) {
            return null;
        }

        try {
            return $this->fetchWorkByPubmed($normalized);
        } catch (NotFoundException) {
            $this->client()->log('Work not found by PubMed identifier', ['pmid' => $normalized]);

            return null;
        }
    }
}
