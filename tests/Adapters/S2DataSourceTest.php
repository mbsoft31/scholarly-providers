<?php

declare(strict_types=1);

use Http\Mock\Client as MockHttpClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Log\NullLogger;
use Scholarly\Adapters\S2\DataSource;
use Scholarly\Contracts\Query;
use Scholarly\Core\Backoff;
use Scholarly\Core\Client;

it('sends API key header when provided', function () {
    $psr17 = new Psr17Factory();
    $http  = new MockHttpClient();

    $http->addResponse($psr17->createResponse(200)->withBody($psr17->createStream('{"data":[],"next":null}')));

    $client = new Client(
        $http,
        $psr17,
        $psr17,
        $psr17,
        null,
        new NullLogger(),
        new Backoff(0.0, 0.0, 1.0),
    );

    $dataSource = new DataSource($client, 'secret-key', 50);
    $dataSource->searchWorks(Query::from(['q' => 'graphs']))->page();

    $request = $http->getRequests()[0];

    expect($request->getHeaderLine('x-api-key'))->toBe('secret-key');
});

it('paginates S2 works and retrieves citations and references', function (): void {
    $psr17 = new Psr17Factory();
    $http  = new MockHttpClient();

    $firstPage = [
        'data' => [
            [
                'paperId'        => 'W1',
                'title'          => 'First Paper',
                'year'           => 2022,
                'venue'          => 'Venue A',
                'externalIds'    => ['DOI' => '10.1000/w1'],
                'authors'        => [['authorId' => 'A1', 'name' => 'Alice']],
                'isOpenAccess'   => true,
                'openAccessPdf'  => ['url' => 'https://example.com/pdf'],
            ],
        ],
        'next' => 25,
    ];

    $secondPage = [
        'data' => [
            [
                'paperId'        => 'W2',
                'title'          => 'Second Paper',
                'year'           => 2021,
                'venue'          => 'Venue B',
                'externalIds'    => ['DOI' => '10.1000/w2'],
                'authors'        => [['authorId' => 'A2', 'name' => 'Bob']],
                'isOpenAccess'   => false,
            ],
        ],
        'next' => null,
    ];

    $citations = [
        'data' => [
            ['citingPaper' => ['paperId' => 'C1', 'title' => 'Citing Paper']],
        ],
        'next' => null,
    ];

    $references = [
        'data' => [
            ['citedPaper' => ['paperId' => 'R1', 'title' => 'Reference Paper']],
        ],
        'next' => null,
    ];

    $http->addResponse($psr17->createResponse(200)->withBody($psr17->createStream(json_encode($firstPage, JSON_THROW_ON_ERROR))));
    $http->addResponse($psr17->createResponse(200)->withBody($psr17->createStream(json_encode($secondPage, JSON_THROW_ON_ERROR))));
    $http->addResponse($psr17->createResponse(200)->withBody($psr17->createStream(json_encode($citations, JSON_THROW_ON_ERROR))));
    $http->addResponse($psr17->createResponse(200)->withBody($psr17->createStream(json_encode($references, JSON_THROW_ON_ERROR))));

    $client     = new Client($http, $psr17, $psr17, $psr17, null, new NullLogger(), new Backoff(0.0, 0.0, 1.0));
    $dataSource = new DataSource($client);

    $query  = Query::from(['fields' => ['title'], 'limit' => 2, 'raw' => ['offset' => 0]]);
    $works  = iterator_to_array($dataSource->searchWorks($query));
    $citing = iterator_to_array($dataSource->listCitations('W1', Query::from(['limit' => 1])));
    $cited  = iterator_to_array($dataSource->listReferences('W1', Query::from(['limit' => 1])));

    expect($works)
        ->toHaveCount(2)
        ->and($works[0]['id'])->toBe('s2:W1');

    expect($citing[0]['id'])->toBe('s2:C1')
        ->and($cited[0]['id'])->toBe('s2:R1');

    parse_str($http->getRequests()[0]->getUri()->getQuery(), $params);
    expect($params)->toHaveKey('fields')->and($params['fields'])->toContain('title');
});
