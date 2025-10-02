<?php

declare(strict_types=1);

namespace Scholarly\Laravel;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;
use Psr\Container\ContainerInterface;
use Scholarly\Contracts\ScholarlyDataSource;
use Scholarly\Exporter\Graph\GraphExporter;
use Scholarly\Factory\AdapterFactory;
use function is_array;

final class ScholarlyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/scholarly.php', 'scholarly');

        $this->app->singleton(AdapterFactory::class, function (Container $app): AdapterFactory {
            /** @var Repository $configRepo */
            $configRepo = $app->make('config');
            $config = $configRepo->get('scholarly', []);

            return AdapterFactory::make(is_array($config) ? $config : [], $this->psrContainer($app));
        });

        $this->app->alias(AdapterFactory::class, 'scholarly');

        $this->app->bind(ScholarlyDataSource::class, function (Container $app): ScholarlyDataSource {
            /** @var AdapterFactory $factory */
            $factory = $app->make(AdapterFactory::class);

            return $factory->adapter();
        });

        $this->app->bind(GraphExporter::class, function (Container $app): GraphExporter {
            /** @var AdapterFactory $factory */
            $factory = $app->make(AdapterFactory::class);

            return $factory->graphExporter();
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../../config/scholarly.php' => config_path('scholarly.php'),
        ], 'scholarly-config');
    }

    private function psrContainer(Container $app): ContainerInterface
    {
        if ($app instanceof ContainerInterface) {
            return $app;
        }

        return new class ($app) implements ContainerInterface {
            public function __construct(private Container $app)
            {
            }

            public function get(string $id): mixed
            {
                return $this->app->make($id);
            }

            public function has(string $id): bool
            {
                return $this->app->bound($id);
            }
        };
    }
}
