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
