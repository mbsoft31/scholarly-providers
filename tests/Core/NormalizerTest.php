<?php

declare(strict_types=1);

use Scholarly\Core\Normalizer;

it('normalizes OpenAlex work payloads', function () {
    $raw = [
        'id'               => 'https://openalex.org/W123',
        'display_name'     => 'Sample Work',
        'publication_year' => 2020,
        'open_access'      => ['is_oa' => true],
        'ids'              => ['doi' => '10.1234/example'],
        'authorships'      => [
            ['author' => ['id' => 'https://openalex.org/A1', 'display_name' => 'Ada Lovelace']],
        ],
    ];

    $normalized = Normalizer::work($raw, 'openalex');

    expect($normalized['id'])->toBe('openalex:W123')
        ->and($normalized['title'])->toBe('Sample Work')
        ->and($normalized['external_ids']['doi'])->toBe('10.1234/example')
        ->and($normalized['authors'][0]['name'])->toBe('Ada Lovelace');
});

it('normalizes S2 author payloads', function () {
    $raw = [
        'authorId'   => '123',
        'name'       => 'Grace Hopper',
        'orcid'      => '0000-0001-2345-6789',
        'paperCount' => 42,
    ];

    $normalized = Normalizer::author($raw, 's2');

    expect($normalized['id'])->toBe('s2:123')
        ->and($normalized['counts']['works'])->toBe(42);
});

it('normalizes S2 work payloads including alternate fields', function (): void {
    $raw = [
        'paperId'          => 'W500',
        'title'            => 'S2 Title',
        'abstractText'     => 'Fallback abstract',
        'externalIds'      => ['DOI' => '10.5555/s2', 'ArXiv' => '2101.12345v3'],
        'publicationVenue' => ['id' => 'V1', 'name' => 'Venue', 'type' => 'journal'],
        'openAccessPdf'    => ['url' => 'https://example.com/pdf'],
        'isOpenAccess'     => true,
    ];

    $normalized = Normalizer::work($raw, 's2');

    expect($normalized['id'])->toBe('s2:W500')
        ->and($normalized['abstract'])->toBe('Fallback abstract')
        ->and($normalized['external_ids']['arxiv'])->toBe('2101.12345')
        ->and($normalized['venue']['id'])->toBe('s2:V1');
});

it('normalizes Crossref work payloads', function () {
    $raw = [
        'DOI'    => '10.5555/example',
        'title'  => ['Important Results'],
        'author' => [
            ['given' => 'Alan', 'family' => 'Turing', 'ORCID' => '0000-0002-1825-0097'],
        ],
        'issued' => ['date-parts' => [[1950, 10, 1]]],
    ];

    $normalized = Normalizer::work($raw, 'crossref');

    expect($normalized['id'])->toBe('crossref:10.5555/example')
        ->and($normalized['authors'][0]['orcid'])->toBe('0000-0002-1825-0097')
        ->and($normalized['year'])->toBe(1950);
});

it('reconstructs abstract text from OpenAlex inverted index', function (): void {
    $raw = [
        'id'                      => 'https://openalex.org/W9',
        'display_name'            => 'Abstract Example',
        'abstract_inverted_index' => [
            'hello' => [0],
            'world' => [1],
        ],
    ];

    $normalized = Normalizer::work($raw, 'openalex');

    expect($normalized['abstract'])->toBe('hello world');
});

it('falls back to generic work and author normalization', function (): void {
    $work   = Normalizer::work(['id' => '123', 'title' => 'Generic'], 'unknown');
    $author = Normalizer::author(['name' => 'Unknown'], 'unknown');

    expect($work['id'])->toBe('unknown:123')
        ->and($work['title'])->toBe('Generic')
        ->and($author['name'])->toBe('Unknown');
});
