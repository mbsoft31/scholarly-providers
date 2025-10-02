<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Facade;
use Scholarly\Adapters\OpenAlex\DataSource as OpenAlexDataSource;
use Scholarly\Contracts\ScholarlyDataSource;
use Scholarly\Exporter\Graph\GraphExporter;
use Scholarly\Factory\AdapterFactory;
use Scholarly\Laravel\Facades\Scholarly as ScholarlyFacade;
use Scholarly\Laravel\ScholarlyServiceProvider;

if (!function_exists('config_path')) {
    function config_path(string $path = ''): string
    {
        return $path;
    }
}

it('registers Laravel bindings for scholarly services', function (): void {
    /** @var Application&Container $container */
    $container = new Container();
    $container->instance('config', new Repository([
        'scholarly' => [
            'default' => 'openalex',
            'http' => [],
            'cache' => ['store' => null],
            'graph' => [],
            'providers' => [
                'openalex' => ['mailto' => 'team@example.com'],
                's2' => ['api_key' => 'secret'],
                'crossref' => ['mailto' => 'team@example.com'],
            ],
        ],
    ]));

    $provider = new ScholarlyServiceProvider($container);
    $provider->register();

    /** @var AdapterFactory $factory */
    $factory = $container->make(AdapterFactory::class);

    expect($factory)->toBeInstanceOf(AdapterFactory::class)
        ->and($container->make('scholarly'))->toBe($factory)
        ->and($container->make(ScholarlyDataSource::class))->toBeInstanceOf(OpenAlexDataSource::class)
        ->and($container->make(GraphExporter::class))->toBeInstanceOf(GraphExporter::class);

    $factory->reset();
});

it('resolves bindings through the scholarly facade', function (): void {
    /** @var Application&Container $container */
    $container = new Container();
    Facade::setFacadeApplication($container);

    $container->instance('config', new Repository([
        'scholarly' => [
            'default'   => 'openalex',
            'http'      => [],
            'cache'     => ['store' => null],
            'graph'     => [],
            'providers' => [
                'openalex' => ['mailto' => 'team@example.com'],
                's2'       => ['api_key' => 'secret'],
                'crossref' => ['mailto' => 'team@example.com'],
            ],
        ],
    ]));

    $provider = new ScholarlyServiceProvider($container);
    $provider->register();

    expect(ScholarlyFacade::adapter())->toBeInstanceOf(OpenAlexDataSource::class)
        ->and(ScholarlyFacade::graphExporter())->toBeInstanceOf(GraphExporter::class);

    /** @var AdapterFactory $factory */
    $factory = $container->make(AdapterFactory::class);
    $factory->reset();
    Facade::setFacadeApplication(null);
});
