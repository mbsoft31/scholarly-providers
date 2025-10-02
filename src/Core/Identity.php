<?php

declare(strict_types=1);

namespace Scholarly\Core;

use function array_key_exists;
use function is_array;
use function is_scalar;

class Identity
{
    public static function normalizeDoi(?string $doi): ?string
    {
        if ($doi === null) {
            return null;
        }

        $doi = trim($doi);

        if ($doi === '') {
            return null;
        }

        $doi = preg_replace('~^https?://(dx\.)?doi.org/~i', '', $doi) ?? $doi;
        $doi = preg_replace('~^doi:~i', '', $doi)                     ?? $doi;
        $doi = strtolower($doi);

        return $doi !== '' ? $doi : null;
    }

    public static function doiToUrl(?string $doi): ?string
    {
        $normalized = self::normalizeDoi($doi);

        return $normalized === null ? null : 'https://doi.org/' . $normalized;
    }

    public static function normalizeOrcid(?string $orcid): ?string
    {
        if ($orcid === null) {
            return null;
        }

        $orcid = preg_replace('~https?://orcid.org/~i', '', trim($orcid)) ?? '';
        $orcid = str_replace([' ', '-'], '', $orcid);

        if (! preg_match('/^\d{15}[\dXx]$/', $orcid)) {
            return null;
        }

        $orcid = strtoupper($orcid);

        return implode('-', [
            substr($orcid, 0, 4),
            substr($orcid, 4, 4),
            substr($orcid, 8, 4),
            substr($orcid, 12, 4),
        ]);
    }

    public static function orcidToUrl(?string $orcid): ?string
    {
        $normalized = self::normalizeOrcid($orcid);

        return $normalized === null ? null : 'https://orcid.org/' . $normalized;
    }

    public static function normalizeArxiv(?string $identifier): ?string
    {
        if ($identifier === null) {
            return null;
        }

        $identifier = trim($identifier);

        if ($identifier === '') {
            return null;
        }

        $identifier = preg_replace('~^https?://arxiv.org/(abs|pdf)/~i', '', $identifier) ?? $identifier;
        $identifier = preg_replace('~^arxiv:~i', '', $identifier)                        ?? $identifier;
        $identifier = preg_replace('~\.pdf$~i', '', $identifier)                         ?? $identifier;
        $identifier = strtolower($identifier);
        $identifier = preg_replace('~v\d+$~', '', $identifier) ?? $identifier;

        return $identifier !== '' ? $identifier : null;
    }

    public static function arxivToUrl(?string $identifier): ?string
    {
        $normalized = self::normalizeArxiv($identifier);

        return $normalized === null ? null : 'https://arxiv.org/abs/' . $normalized;
    }

    public static function normalizePmid(?string $pmid): ?string
    {
        if ($pmid === null) {
            return null;
        }

        $pmid = preg_replace('/\D+/', '', $pmid) ?? '';

        return $pmid !== '' ? $pmid : null;
    }

    public static function pmidToUrl(?string $pmid): ?string
    {
        $normalized = self::normalizePmid($pmid);

        return $normalized === null ? null : 'https://pubmed.ncbi.nlm.nih.gov/' . $normalized . '/';
    }

    public static function ns(string $provider, string $id): string
    {
        return strtolower(trim($provider)) . ':' . trim($id);
    }

    /**
     * @return array{provider: string, id: string}
     */
    public static function parseNs(string $value): array
    {
        $parts = explode(':', $value, 2);

        if (count($parts) === 2) {
            return ['provider' => $parts[0], 'id' => $parts[1]];
        }

        return ['provider' => '', 'id' => $value];
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return list<string>
     */
    public static function extractIds(array $payload, string $path, ?string $namespace = null): array
    {
        $segments  = array_filter(explode('.', $path), static fn ($segment) => $segment !== '');
        $values    = self::traverse($payload, $segments);
        $flattened = self::flatten($values);

        $identifiers = [];

        foreach ($flattened as $value) {
            if (! is_scalar($value)) {
                continue;
            }

            $value = trim((string) $value);

            if ($value === '') {
                continue;
            }

            $identifiers[] = $namespace !== null ? self::ns($namespace, $value) : $value;
        }

        return array_values(array_unique($identifiers));
    }

    private static function traverse(mixed $data, array $segments): mixed
    {
        if ($segments === []) {
            return $data;
        }

        $segment = array_shift($segments);

        if (is_array($data)) {
            $results = [];

            if (array_key_exists($segment, $data)) {
                $results[] = self::traverse($data[$segment], $segments);
            }

            foreach ($data as $value) {
                if (is_array($value) && array_key_exists($segment, $value)) {
                    $results[] = self::traverse($value[$segment], $segments);
                }
            }

            return $results;
        }

        return null;
    }

    /**
     * @return list<mixed>
     */
    private static function flatten(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        if (! is_array($value)) {
            return [$value];
        }

        $results = [];

        foreach ($value as $item) {
            foreach (self::flatten($item) as $flattened) {
                $results[] = $flattened;
            }
        }

        return $results;
    }
}
