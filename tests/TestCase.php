<?php

declare(strict_types=1);

namespace Larascan\Tests;

use Larascan\LarascanServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            LarascanServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set(LarascanServiceProvider::CONFIG_KEY . '.skip_tests', true);
    }

    protected function workbenchPath(string $path = ''): string
    {
        $base = dirname(__DIR__) . '/workbench';

        return $path === '' ? $base : $base . '/' . ltrim($path, '/');
    }
}
