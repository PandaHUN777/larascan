<?php

declare(strict_types=1);

use Larascan\Engine\InventoryScanner;

return [
    /*
    |--------------------------------------------------------------------------
    | Scanned Paths
    |--------------------------------------------------------------------------
    |
    | Directories or files that will be analyzed for Laravel 13 native adoption.
    | Defaults to standard app directory.
    |
    */
    'paths' => [
        'app',
    ],

    /*
    |--------------------------------------------------------------------------
    | Skip Tests Directory
    |--------------------------------------------------------------------------
    |
    | Keeping this true focuses adoption analysis on production application code.
    |
    */
    'skip_tests' => true,

    /*
    |--------------------------------------------------------------------------
    | Ignored Paths
    |--------------------------------------------------------------------------
    */
    'ignore_paths' => InventoryScanner::DEFAULT_IGNORE_PATHS,
];
