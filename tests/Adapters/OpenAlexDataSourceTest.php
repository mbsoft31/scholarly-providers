<?php

declare(strict_types=1);

use Http\Mock\Client as MockHttpClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Log\NullLogger;
use Scholarly\Adapters\OpenAlex\DataSource;
use Scholarly\Contracts\Query;
use Scholarly\Core\Backoff;
use Scholarly\Core\Client;

it('attaches mailto parameter and selects fields when searching works', function () {
    $psr17 = new Psr17Factory();
    $http  = new MockHttpClient();

    $http->addResponse($psr17->createResponse(200)->withBody($psr17->createStream('{"results":[],"meta":{"next_cursor":null}}')));

    $client = new Client(
        $http,
        $psr17,
        $psr17,
        $psr17,
        null,
        new NullLogger(),
        new Backoff(0.0, 0.0, 1.0),
    );

    $dataSource = new DataSource($client, 'agent@example.com', 100);
    $paginator  = $dataSource->searchWorks(Query::from(['q' => 'artificial intelligence', 'fields' => ['title']]));
    $paginator->page();

    $request = $http->getRequests()[0];
    parse_str($request->getUri()->getQuery(), $params);

    expect($params['mailto'])->toBe('agent@example.com')
        ->and($params['search'])->toBe('artificial intelligence')
        ->and($params['select'])->toContain('display_name');
});

it('paginates openalex works with filters and field selection', function (): void {
    $psr17 = new Psr17Factory();
    $http  = new MockHttpClient();

    $firstPage = [
        'results' => [
            [
                'id'                     => 'https://openalex.org/W1',
                'display_name'           => 'First Work',
                'publication_year'       => 2022,
                'host_venue'             => ['display_name' => 'Venue A', 'type' => 'journal', 'id' => 'https://openalex.org/V1'],
                'ids'                    => ['doi' => 'https://doi.org/10.1000/alpha'],
                'cited_by_count'         => 10,
                'referenced_works_count' => 2,
                'authorships'            => [
                    [
                        'author'       => ['id' => 'https://openalex.org/A1', 'display_name' => 'Alice'],
                        'institutions' => [['display_name' => 'Institute A']],
                    ],
                ],
            ],
        ],
        'meta' => ['next_cursor' => 'cursor-1'],
    ];

    $secondPage = [
        'results' => [
            [
                'id'                     => 'https://openalex.org/W2',
                'display_name'           => 'Second Work',
                'publication_year'       => 2021,
                'host_venue'             => ['display_name' => 'Venue B', 'type' => 'journal', 'id' => 'https://openalex.org/V2'],
                'ids'                    => ['doi' => 'https://doi.org/10.1000/beta'],
                'cited_by_count'         => 5,
                'referenced_works_count' => 1,
                'authorships'            => [
                    [
                        'author'       => ['id' => 'https://openalex.org/A2', 'display_name' => 'Bob'],
                        'institutions' => [['display_name' => 'Institute B']],
                    ],
                ],
            ],
        ],
        'meta' => ['next_cursor' => null],
    ];

    $http->addResponse(
        $psr17->createResponse(200)->withBody(
            $psr17->createStream(json_encode($firstPage, JSON_THROW_ON_ERROR))
        )
    );
    $http->addResponse(
        $psr17->createResponse(200)->withBody(
            $psr17->createStream(json_encode($secondPage, JSON_THROW_ON_ERROR))
        )
    );

    $client = new Client(
        $http,
        $psr17,
        $psr17,
        $psr17,
        null,
        new NullLogger(),
        new Backoff(0.0, 0.0, 1.0),
    );

    $query = Query::from([
        'q'            => 'climate',
        'year'         => '2020',
        'openAccess'   => true,
        'minCitations' => 5,
        'venueIds'     => ['https://openalex.org/V1'],
        'fields'       => ['title', 'venue'],
        'raw'          => ['extra' => 'value'],
        'limit'        => 2,
    ]);

    $dataSource = new DataSource($client, 'mailto@example.com');
    $items      = iterator_to_array($dataSource->searchWorks($query));

    expect($items)
        ->toHaveCount(2)
        ->and($items[1]['id'])->toBe('openalex:W2');

    parse_str($http->getRequests()[0]->getUri()->getQuery(), $params);

    expect($params)
        ->toHaveKey('filter')
        ->and($params['filter'])->toContain('publication_year:2020')
        ->and($params['filter'])->toContain('is_oa:true')
        ->and($params['filter'])->toContain('cited_by_count:>5');
});

it('lists openalex citations and references for a work', function (): void {
    $psr17 = new Psr17Factory();
    $http  = new MockHttpClient();

    $citationPage = [
        'results' => [
            ['citing_work' => ['id' => 'https://openalex.org/W10', 'display_name' => 'Citing']],
        ],
        'meta' => ['next_cursor' => null],
    ];

    $referencePage = [
        'results' => [
            ['referenced_work' => ['id' => 'https://openalex.org/W20', 'display_name' => 'Reference']],
        ],
        'meta' => ['next_cursor' => null],
    ];

    // Responses consumed in sequence: citations request then references request
    $http->addResponse(
        $psr17->createResponse(200)->withBody(
            $psr17->createStream(json_encode($citationPage, JSON_THROW_ON_ERROR))
        )
    );
    $http->addResponse(
        $psr17->createResponse(200)->withBody(
            $psr17->createStream(json_encode($referencePage, JSON_THROW_ON_ERROR))
        )
    );

    $client     = new Client($http, $psr17, $psr17, $psr17, null, new NullLogger(), new Backoff(0.0, 0.0, 1.0));
    $dataSource = new DataSource($client);
    $query      = Query::from(['limit' => 1]);

    $citations  = iterator_to_array($dataSource->listCitations('https://openalex.org/W123', $query));
    $references = iterator_to_array($dataSource->listReferences('https://openalex.org/W123', $query));

    expect($citations[0]['id'])->toBe('openalex:W10')
        ->and($references[0]['id'])->toBe('openalex:W20');
});

it('returns empty results when OpenAlex identifier cannot be normalized', function (): void {
    $psr17  = new Psr17Factory();
    $http   = new MockHttpClient();
    $client = new Client($http, $psr17, $psr17, $psr17, null, new NullLogger(), new Backoff(0.0, 0.0, 1.0));

    $dataSource = new DataSource($client);
    $paginator  = $dataSource->listCitations('invalid', Query::from(['limit' => 1]));

    expect($paginator->page()['items'])->toBe([]);
});

it('batches works through the OpenAlex batch endpoint', function (): void {
    $psr17 = new Psr17Factory();
    $http  = new MockHttpClient();

    $batchPayload = [
        'results' => [
            ['id' => 'https://openalex.org/W100', 'display_name' => 'Batch Work'],
            ['id' => 'https://openalex.org/W200', 'display_name' => 'Second Batch Work'],
        ],
    ];

    $http->addResponse(
        $psr17->createResponse(200)->withBody(
            $psr17->createStream(json_encode($batchPayload, JSON_THROW_ON_ERROR))
        )
    );

    $client     = new Client($http, $psr17, $psr17, $psr17, null, new NullLogger(), new Backoff(0.0, 0.0, 1.0));
    $dataSource = new DataSource($client);

    $works = iterator_to_array($dataSource->batchWorksByIds(['https://openalex.org/W100', 'https://openalex.org/W200'], Query::from([])));

    expect($works)
        ->toHaveCount(2)
        ->and($works[0]['id'])->toBe('openalex:W100');
});

it('reports health failure when OpenAlex endpoint is unavailable', function (): void {
    $psr17 = new Psr17Factory();
    $http  = new MockHttpClient();
    for ($i = 0; $i < 3; $i++) {
        $http->addResponse($psr17->createResponse(500));
    }

    $client     = new Client($http, $psr17, $psr17, $psr17, null, new NullLogger(), new Backoff(0.0, 0.0, 1.0));
    $dataSource = new DataSource($client);

    expect($dataSource->health())->toBeFalse();
});
