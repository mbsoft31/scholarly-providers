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
