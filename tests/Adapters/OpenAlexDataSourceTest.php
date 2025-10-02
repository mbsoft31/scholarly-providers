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
