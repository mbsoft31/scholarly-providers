<?php

declare(strict_types=1);

use Http\Mock\Client as MockHttpClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Log\NullLogger;
use Scholarly\Core\Backoff;
use Scholarly\Core\Client;
use Scholarly\Core\Exceptions\NotFoundException;

it('retries on server errors before succeeding', function () {
    $psr17 = new Psr17Factory();
    $http  = new MockHttpClient();

    $http->addResponse($psr17->createResponse(500)->withBody($psr17->createStream('{"error": "fail"}')));
    $http->addResponse($psr17->createResponse(200)->withBody($psr17->createStream('{"success":true}')));

    $client = new Client(
        $http,
        $psr17,
        $psr17,
        $psr17,
        null,
        new NullLogger(),
        new Backoff(0.0, 0.0, 1.0),
    );

    $payload = $client->get('https://example.com/works');

    expect($payload)->toBe(['success' => true])
        ->and($http->getRequests())
        ->toHaveCount(2);
});

it('throws not found exception for 404 responses', function () {
    $psr17 = new Psr17Factory();
    $http  = new MockHttpClient();
    $http->addResponse($psr17->createResponse(404));

    $client = new Client(
        $http,
        $psr17,
        $psr17,
        $psr17,
        null,
        new NullLogger(),
        new Backoff(0.0, 0.0, 1.0),
    );

    expect(fn () => $client->get('https://example.com/missing'))
        ->toThrow(NotFoundException::class);
});
