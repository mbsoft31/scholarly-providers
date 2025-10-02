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
