<?php

declare(strict_types=1);

use Http\Mock\Client as MockHttpClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Log\NullLogger;
use Scholarly\Adapters\S2\S2AuthorPaginator;
use Scholarly\Core\Backoff;
use Scholarly\Core\Client;

it('walks through offset-based author pagination', function (): void {
    $psr17 = new Psr17Factory();
    $http  = new MockHttpClient();

    $firstPage = [
        'data' => [
            ['authorId' => 'A1', 'name' => 'Ada'],
            ['authorId' => 'A2', 'name' => 'Ben'],
        ],
        'next' => 25,
    ];

    $secondPage = [
        'data' => [
            ['authorId' => 'A3', 'name' => 'Cora'],
        ],
        'next' => null,
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

    $paginator = new S2AuthorPaginator(
        $client,
        'https://api.semanticscholar.org/graph/v1/author/search',
        ['limit' => 2, 'offset' => 0],
        static fn (array $row): array => ['id' => $row['authorId'], 'name' => $row['name']],
        $firstPage,
    );

    expect($paginator->items())
        ->toHaveCount(2)
        ->sequence(
            fn ($item) => $item->toMatchArray(['id' => 'A1', 'name' => 'Ada']),
            fn ($item) => $item->toMatchArray(['id' => 'A2', 'name' => 'Ben'])
        );

    $collected = iterator_to_array($paginator);

    expect($collected)
        ->toHaveCount(3)
        ->and($collected[2]['id'])->toBe('A3');

    $request = $http->getRequests()[0];
    parse_str($request->getUri()->getQuery(), $params);

    expect($params['offset'] ?? null)->toBe('25');
});
