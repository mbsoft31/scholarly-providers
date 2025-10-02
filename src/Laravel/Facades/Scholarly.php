<?php

declare(strict_types=1);

namespace Scholarly\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use Scholarly\Contracts\ScholarlyDataSource;
use Scholarly\Exporter\Graph\GraphExporter;

/**
 * @method static ScholarlyDataSource adapter(?string $name = null)
 * @method static GraphExporter graphExporter(?ScholarlyDataSource $dataSource = null)
 */
final class Scholarly extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'scholarly';
    }
}
