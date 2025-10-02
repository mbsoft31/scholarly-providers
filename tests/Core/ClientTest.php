<?php

declare(strict_types=1);

use Http\Mock\Client as MockHttpClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Log\NullLogger;
use Scholarly\Core\Backoff;
use Scholarly\Core\CacheLayer;
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

it('serves cached responses without additional network calls', function (): void {
    $psr17 = new Psr17Factory();
    $http  = new MockHttpClient();

    $http->addResponse(
        $psr17->createResponse(200)->withBody($psr17->createStream('{"value":1}'))
    );
    $http->addResponse(
        $psr17->createResponse(500)->withBody($psr17->createStream('{"error":true}'))
    );

    $cacheLayer = new CacheLayer(new ArrayCache());

    $client = new Client(
        $http,
        $psr17,
        $psr17,
        $psr17,
        null,
        new NullLogger(),
        new Backoff(0.0, 0.0, 1.0),
        $cacheLayer
    );

    $first  = $client->get('https://example.com/cached', ['foo' => 'bar'], [], 'search');
    $second = $client->get('https://example.com/cached', ['foo' => 'bar'], [], 'search');

    expect($first)->toBe(['value' => 1])
        ->and($second)->toBe(['value' => 1])
        ->and($http->getRequests())->toHaveCount(1);
});

it('honours retry-after headers when rate limited', function (): void {
    $psr17 = new Psr17Factory();
    $http  = new MockHttpClient();

    $http->addResponse(
        $psr17->createResponse(429)
            ->withHeader('Retry-After', '3')
            ->withBody($psr17->createStream('{"error":"rate"}'))
    );
    $http->addResponse(
        $psr17->createResponse(200)->withBody($psr17->createStream('{"ok":true}'))
    );

    $sleeps  = [];
    $backoff = new Backoff(
        baseDelay: 0.1,
        maxDelay: 1.0,
        factor: 2.0,
        jitter: static fn (float $delay, int $attempt): float => $delay,
        sleeper: function (float $seconds) use (&$sleeps): void {
            $sleeps[] = $seconds;
        }
    );

    $client = new Client(
        $http,
        $psr17,
        $psr17,
        $psr17,
        null,
        new NullLogger(),
        $backoff,
    );

    $result = $client->get('https://example.com/rate-limited');

    expect($result)->toBe(['ok' => true])
        ->and($http->getRequests())->toHaveCount(2)
        ->and($sleeps[0])->toBeGreaterThanOrEqual(3.0);
});
