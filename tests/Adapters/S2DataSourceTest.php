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
