<?php

declare(strict_types=1);

use Scholarly\Adapters\Crossref\DataSource as CrossrefDataSource;
use Scholarly\Adapters\OpenAlex\DataSource as OpenAlexDataSource;
use Scholarly\Adapters\S2\DataSource as S2DataSource;
use Scholarly\Contracts\Query;
use Scholarly\Exporter\Graph\GraphExporter;
use Scholarly\Factory\AdapterFactory;

/**
 * @return array<string, mixed>
 */
function scholarlyConfig(): array
{
    return [
        'default'   => 'openalex',
        'http'      => [],
        'cache'     => ['store' => null],
        'graph'     => [],
        'providers' => [
            'openalex' => ['mailto' => 'team@example.com'],
            's2'       => ['api_key' => 'abc123'],
            'crossref' => ['mailto' => 'team@example.com'],
        ],
    ];
}

it('resolves adapters and graph exporter', function () {
    $factory = AdapterFactory::make(scholarlyConfig());

    expect($factory->adapter('openalex'))->toBeInstanceOf(OpenAlexDataSource::class)
        ->and($factory->adapter('s2'))->toBeInstanceOf(S2DataSource::class)
        ->and($factory->adapter('crossref'))->toBeInstanceOf(CrossrefDataSource::class)
        ->and($factory->graphExporter())->toBeInstanceOf(GraphExporter::class);

    $factory->reset();
});
