<?php

declare(strict_types=1);

return [
    'default' => env('SCHOLARLY_DEFAULT_ADAPTER', 'openalex'),

    'http' => [
        'client' => env('SCHOLARLY_HTTP_CLIENT'),
        'timeout' => env('SCHOLARLY_HTTP_TIMEOUT') !== null ? (float) env('SCHOLARLY_HTTP_TIMEOUT') : null,
        'user_agent' => env('SCHOLARLY_HTTP_USER_AGENT'),
        'logger' => env('SCHOLARLY_LOG_CHANNEL'),
        'backoff' => [
            'base' => env('SCHOLARLY_BACKOFF_BASE', 0.5),
            'max' => env('SCHOLARLY_BACKOFF_MAX', 60),
            'factor' => env('SCHOLARLY_BACKOFF_FACTOR', 2),
        ],
    ],

    'cache' => [
        'store' => env('SCHOLARLY_CACHE_STORE'),
        'logger' => env('SCHOLARLY_CACHE_LOGGER'),
    ],

    'graph' => [
        'max_works' => env('SCHOLARLY_GRAPH_MAX_WORKS'),
        'min_collaborations' => env('SCHOLARLY_GRAPH_MIN_COLLABS', 1),
    ],

    'providers' => [
        'openalex' => [
            'mailto' => env('OPENALEX_MAILTO', env('SCHOLARLY_MAILTO')),
            'max_per_page' => env('OPENALEX_MAX_PER_PAGE', 200),
        ],
        's2' => [
            'api_key' => env('S2_API_KEY'),
            'max_per_page' => env('S2_MAX_PER_PAGE', 100),
        ],
        'crossref' => [
            'mailto' => env('CROSSREF_MAILTO', env('SCHOLARLY_MAILTO')),
            'max_rows' => env('CROSSREF_MAX_ROWS', 100),
        ],
    ],
];
