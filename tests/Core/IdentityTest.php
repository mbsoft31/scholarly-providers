<?php

declare(strict_types=1);

use Scholarly\Core\Identity;

it('normalizes DOI variations', function () {
    expect(Identity::normalizeDoi('https://doi.org/10.1234/ABC'))
        ->toBe('10.1234/abc');
});

it('normalizes ORCID into canonical form', function () {
    expect(Identity::normalizeOrcid('https://orcid.org/0000-0002-1825-0097'))
        ->toBe('0000-0002-1825-0097');
});

it('normalizes arXiv identifiers and strips versions', function () {
    expect(Identity::normalizeArxiv('arXiv:2201.12345v2'))
        ->toBe('2201.12345');
});

it('creates and parses namespaced identifiers', function () {
    $ns = Identity::ns('openalex', 'W123');

    expect($ns)->toBe('openalex:W123')
        ->and(Identity::parseNs($ns))
        ->toMatchArray(['provider' => 'openalex', 'id' => 'W123']);
});

it('extracts identifiers from nested payloads', function (): void {
    $payload = [
        'authors' => [
            ['ids' => ['orcid' => '0000-0001-2345-6789']],
            ['ids' => ['orcid' => '0000-0001-2345-6789']],
        ],
    ];

    $ids = Identity::extractIds($payload, 'authors.ids.orcid', 'orcid');

    expect($ids)->toBe(['orcid:0000-0001-2345-6789']);
});

it('normalizes pmid values and builds URLs', function (): void {
    expect(Identity::normalizePmid('PMID  12345'))->toBe('12345')
        ->and(Identity::pmidToUrl('12345'))->toBe('https://pubmed.ncbi.nlm.nih.gov/12345/');
});

it('returns null url when DOI is missing', function (): void {
    expect(Identity::doiToUrl(''))->toBeNull()
        ->and(Identity::parseNs('identifier'))
        ->toMatchArray(['provider' => '', 'id' => 'identifier']);
});
