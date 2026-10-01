<?php

declare(strict_types=1);

namespace Larascan;

use Illuminate\Support\ServiceProvider;
use Larascan\Commands\StatsCommand;
use Larascan\Engine\CoreInventoryLoader;
use Larascan\Engine\InventoryScanner;
use Larascan\Support\PathResolver;

class LarascanServiceProvider extends ServiceProvider
{
    public const CONFIG_PATH = __DIR__ . '/../config/larascan.php';

    public const CONFIG_KEY = 'larascan';

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(self::CONFIG_PATH, self::CONFIG_KEY);

        $this->app->singleton(CoreInventoryLoader::class, function () {
            return new CoreInventoryLoader(PathResolver::basePath());
        });

        $this->app->singleton(InventoryScanner::class, function ($app) {
            return new InventoryScanner($app->make(CoreInventoryLoader::class));
        });
    }

    /**
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                self::CONFIG_PATH => config_path(self::CONFIG_KEY . '.php'),
            ], 'larascan-config');

            $this->commands([
                StatsCommand::class,
            ]);
        }
    }
}
