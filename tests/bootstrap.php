<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

if (class_exists(\Dotenv\Dotenv::class) && file_exists(__DIR__ . '/../.env')) {
    \Dotenv\Dotenv::createImmutable(__DIR__ . '/..')->safeLoad();
}

date_default_timezone_set('UTC');

/*function fixture(string ): string
{
    = __DIR__ . '/Fixtures/' . ltrim(, '/');

    if (! file_exists()) {
        throw new RuntimeException(sprintf('Fixture not found: %s', ));
    }

    return file_get_contents() ?: '';
}*/
