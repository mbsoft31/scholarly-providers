<?php

declare(strict_types=1);

use Http\Mock\Client as MockHttpClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Log\NullLogger;
use Scholarly\Adapters\Crossref\DataSource;
use Scholarly\Contracts\Query;
use Scholarly\Core\Backoff;
use Scholarly\Core\Client;

it('applies polite mailto query and user agent', function () {
    $psr17 = new Psr17Factory();
    $http  = new MockHttpClient();

    $http->addResponse($psr17->createResponse(200)->withBody($psr17->createStream('{"message":{"items":[],"next-cursor":null}}')));

    $client = new Client(
        $http,
        $psr17,
        $psr17,
        $psr17,
        null,
        new NullLogger(),
        new Backoff(0.0, 0.0, 1.0),
    );

    $dataSource = new DataSource($client, 'agent@example.com', 50);
    $dataSource->searchWorks(Query::from(['q' => 'ethics']))->page();

    $request = $http->getRequests()[0];
    parse_str($request->getUri()->getQuery(), $params);

    expect($params['mailto'])->toBe('agent@example.com')
        ->and($request->getHeaderLine('User-Agent'))
        ->toContain('agent@example.com');
});

it('builds filters and paginates crossref works', function (): void {
    $psr17 = new Psr17Factory();
    $http  = new MockHttpClient();

    $firstPage = [
        'message' => [
            'items'       => [
                [
                    'DOI'                => '10.1000/alpha',
                    'title'              => ['First Work'],
                    'issued'             => ['date-parts' => [[2021, 6, 15]]],
                    'container-title'    => ['Journal A'],
                    'type'               => 'journal-article',
                    'is-referenced-by-count' => 5,
                    'references-count'   => 2,
                    'author'             => [
                        ['given' => 'Ada', 'family' => 'Lovelace'],
                    ],
                ],
            ],
            'next-cursor' => 'cursor-1',
        ],
    ];

    $secondPage = [
        'message' => [
            'items'       => [
                [
                    'DOI'                => '10.1000/beta',
                    'title'              => ['Second Work'],
                    'issued'             => ['date-parts' => [[2020, 1, 1]]],
                    'container-title'    => ['Journal B'],
                    'type'               => 'journal-article',
                    'is-referenced-by-count' => 3,
                    'references-count'   => 1,
                    'author'             => [
                        ['given' => 'Grace', 'family' => 'Hopper'],
                    ],
                ],
            ],
            'next-cursor' => null,
        ],
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
        'q'          => 'climate',
        'year'       => '2020-2022',
        'openAccess' => true,
        'venueIds'   => ['crossref:Nature'],
        'fields'     => ['title', 'authors'],
        'raw'        => ['sample' => 'value'],
        'limit'      => 2,
    ]);

    $dataSource = new DataSource($client, 'polite@example.com', 5);
    $items      = iterator_to_array($dataSource->searchWorks($query));

    expect($items)
        ->toHaveCount(2)
        ->and($items[0]['id'])->toBe('crossref:10.1000/alpha');

    $requests = $http->getRequests();
    expect($requests)->toHaveCount(2);

    parse_str($requests[0]->getUri()->getQuery(), $params);

    expect($params)
        ->toHaveKey('filter')
        ->and($params['filter'])->toContain('from-pub-date:2020')
        ->and($params['filter'])->toContain('has-license:true')
        ->and($params['filter'])->toContain('container-title:Nature')
        ->and($params['select'])->toContain('title');
});

it('lists citations for a DOI using crossref paginator', function (): void {
    $psr17 = new Psr17Factory();
    $http  = new MockHttpClient();

    $firstPage = [
        'message' => [
            'items'       => [
                [
                    'DOI'      => '10.1000/gamma',
                    'title'    => ['Gamma Work'],
                    'issued'   => ['date-parts' => [[2019, 3, 12]]],
                    'author'   => [['given' => 'Alan', 'family' => 'Turing']],
                    'type'     => 'journal-article',
                ],
            ],
            'next-cursor' => null,
        ],
    ];

    $http->addResponse(
        $psr17->createResponse(200)->withBody(
            $psr17->createStream(json_encode($firstPage, JSON_THROW_ON_ERROR))
        )
    );

    $client     = new Client($http, $psr17, $psr17, $psr17, null, new NullLogger(), new Backoff(0.0, 0.0, 1.0));
    $dataSource = new DataSource($client);

    $query   = Query::from(['limit' => 3, 'fields' => ['title']]);
    $results = iterator_to_array($dataSource->listCitations('10.1000/original', $query));

    expect($results)
        ->toHaveCount(1)
        ->and($results[0]['id'])->toBe('crossref:10.1000/gamma');

    parse_str($http->getRequests()[0]->getUri()->getQuery(), $params);
    expect($params['filter'])->toBe('reference:10.1000/original');
});

it('returns empty items when listing citations with invalid identifier', function (): void {
    $psr17 = new Psr17Factory();
    $http  = new MockHttpClient();
    $client = new Client($http, $psr17, $psr17, $psr17, null, new NullLogger(), new Backoff(0.0, 0.0, 1.0));

    $dataSource = new DataSource($client);
    $page       = $dataSource->listCitations('not-a-doi', Query::from([]))->page();

    expect($page['items'])->toBe([])
        ->and($page['nextCursor'])->toBeNull();
});

it('batches works by DOI identifiers', function (): void {
    $psr17 = new Psr17Factory();
    $http  = new MockHttpClient();

    $http->addResponse(
        $psr17->createResponse(200)->withBody(
            $psr17->createStream(json_encode(['message' => ['DOI' => '10.1000/alpha', 'title' => ['Alpha']]], JSON_THROW_ON_ERROR))
        )
    );
    $http->addResponse(
        $psr17->createResponse(200)->withBody(
            $psr17->createStream(json_encode(['message' => ['DOI' => '10.1000/beta', 'title' => ['Beta']]], JSON_THROW_ON_ERROR))
        )
    );

    $client     = new Client($http, $psr17, $psr17, $psr17, null, new NullLogger(), new Backoff(0.0, 0.0, 1.0));
    $dataSource = new DataSource($client);

    $works = iterator_to_array($dataSource->batchWorksByIds(['10.1000/alpha', '10.1000/beta'], Query::from([])));

    expect($works)
        ->toHaveCount(2)
        ->and($works[0]['id'])->toBe('crossref:10.1000/alpha');
});

it('returns false from health check when Crossref is unavailable', function (): void {
    $psr17 = new Psr17Factory();
    $http  = new MockHttpClient();

    for ($i = 0; $i < 3; $i++) {
        $http->addResponse($psr17->createResponse(500));
    }

    $client     = new Client($http, $psr17, $psr17, $psr17, null, new NullLogger(), new Backoff(0.0, 0.0, 1.0));
    $dataSource = new DataSource($client);

    expect($dataSource->health())->toBeFalse();
});
