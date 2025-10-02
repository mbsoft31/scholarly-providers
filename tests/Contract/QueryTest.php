<?php

declare(strict_types=1);

use Scholarly\Contracts\Query;

it('hydrates from array and exports back to array', function () {
    $payload = [
        'q'            => 'graph neural networks',
        'year'         => '2019-2022',
        'openAccess'   => true,
        'minCitations' => 10,
        'maxCitations' => 500,
        'venueIds'     => ['V123', 'V456'],
        'fields'       => ['title', 'year'],
        'limit'        => 50,
        'cursor'       => 'abc',
        'offset'       => 10,
        'raw'          => ['foo' => 'bar'],
    ];

    $query = Query::from($payload);

    expect($query->toArray())
        ->toMatchArray($payload);
});

it('supports fluent mutation helpers', function () {
    $query = new Query();

    $query
        ->q('test')
        ->year('2020')
        ->openAccess(true)
        ->minCitations(5)
        ->maxCitations(100)
        ->venueIds(['A', 'B'])
        ->fields(['TITLE', 'YEAR'])
        ->addField('DOI')
        ->limit(10)
        ->cursor('next')
        ->offset(5)
        ->raw(['provider' => 'openalex']);

    expect($query->q)->toBe('test')
        ->and($query->year)->toBe('2020')
        ->and($query->openAccess)->toBeTrue()
        ->and($query->minCitations)->toBe(5)
        ->and($query->maxCitations)->toBe(100)
        ->and($query->venueIds)->toBe(['A', 'B'])
        ->and($query->fields)->toContain('title', 'year', 'doi')
        ->and($query->limit)->toBe(10)
        ->and($query->cursor)->toBe('next')
        ->and($query->offset)->toBe(5)
        ->and($query->raw['provider'])->toBe('openalex');
});
