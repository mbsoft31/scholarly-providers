<?php

declare(strict_types=1);

use Http\Mock\Client as MockHttpClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Log\NullLogger;
use Scholarly\Adapters\OpenAlex\AuthorPaginator;
use Scholarly\Core\Backoff;
use Scholarly\Core\Client;

it('iterates paginated author responses and exposes the current items', function (): void {
    $psr17 = new Psr17Factory();
    $http = new MockHttpClient();

    $firstPage = [
        'results' => [
            ['id' => 'https://openalex.org/A1', 'display_name' => 'Alice'],
            ['id' => 'https://openalex.org/A2', 'display_name' => 'Bob'],
        ],
        'meta' => ['next_cursor' => 'cursor-1'],
    ];

    $secondPage = [
        'results' => [
            ['id' => 'https://openalex.org/A3', 'display_name' => 'Carol'],
        ],
        'meta' => ['next_cursor' => null],
    ];

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

    $paginator = new AuthorPaginator(
        $client,
        'https://api.openalex.org/authors',
        ['cursor' => '*', 'per-page' => 2],
        static fn(array $item): array => ['id' => $item['id'], 'name' => $item['display_name'] ?? null],
        $firstPage,
    );

    expect($paginator->items())
        ->toBeArray()
        ->toHaveCount(2)
        ->sequence(
            fn($item) => $item->toHaveKey('id', 'https://openalex.org/A1'),
            fn($item) => $item->toHaveKey('id', 'https://openalex.org/A2')
        );

    $collected = iterator_to_array($paginator);

    expect($collected)
        ->toHaveCount(3)
        ->and($collected[2]['id'])->toBe('https://openalex.org/A3');

    $request = $http->getRequests()[0];
    parse_str($request->getUri()->getQuery(), $params);

    expect($params['cursor'] ?? null)->toBe('cursor-1');
});
