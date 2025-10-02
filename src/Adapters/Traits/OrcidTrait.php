<?php

declare(strict_types=1);

namespace Scholarly\Adapters\Traits;

use Scholarly\Core\Client;
use Scholarly\Core\Exceptions\NotFoundException;
use Scholarly\Core\Identity;

trait OrcidTrait
{
    abstract protected function client(): Client;

    abstract protected function fetchAuthorByOrcid(string $normalizedOrcid): ?array;

    public function getAuthorByOrcid(string $orcid): ?array
    {
        $normalized = Identity::normalizeOrcid($orcid);

        if ($normalized === null) {
            return null;
        }

        try {
            return $this->fetchAuthorByOrcid($normalized);
        } catch (NotFoundException) {
            $this->client()->log('Author not found by ORCID', ['orcid' => $normalized]);

            return null;
        }
    }
}
