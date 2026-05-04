<?php

return [

    'enabled' => env('SERVER_LENS_ENABLED', true),

    'route_prefix' => 'ops',
    'middleware'   => ['web', 'auth'],

    'poll_seconds'  => 5,
    'cache_seconds' => 3,

    'prune_after_days' => 30,

    'log_mode' => env('SERVER_LENS_LOG_MODE', 'all'),

    'thresholds' => [
        'cpu'         => ['warning' => 70,   'critical' => 90],
        'memory'      => ['warning' => 75,   'critical' => 90],
        'disk'        => ['warning' => 80,   'critical' => 95],
        'response_ms' => ['warning' => 500,  'critical' => 1500],
    ],

    'skip_extensions' => [
        'css', 'js', 'map', 'png', 'jpg', 'jpeg', 'gif',
        'webp', 'ico', 'svg', 'woff', 'woff2', 'ttf', 'eot', 'otf', 'pdf',
    ],

    'skip_paths' => [],

    'geo' => [
        'driver'    => env('SERVER_LENS_GEO_DRIVER', 'none'),
        'mmdb_path' => env('SERVER_LENS_MMDB_PATH', ''),
    ],

    'api' => [
        'enabled' => env('SERVER_LENS_API_ENABLED', false),
        'token'   => env('SERVER_LENS_API_TOKEN', ''),
    ],

];
