<?php

declare(strict_types=1);

namespace Larascan\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Larascan\Commands\StatsCommand;
use Larascan\Engine\CoreInventoryLoader;
use Larascan\Engine\InventoryScanner;
use Larascan\LarascanServiceProvider;
use Larascan\Tests\TestCase;

class ServiceProviderTest extends TestCase
{
    public function test_it_registers_singletons_in_container(): void
    {
        $this->assertTrue($this->app->bound(CoreInventoryLoader::class));
        $this->assertTrue($this->app->bound(InventoryScanner::class));

        $scanner = $this->app->make(InventoryScanner::class);
        $this->assertInstanceOf(InventoryScanner::class, $scanner);
    }

    public function test_it_registers_artisan_commands(): void
    {
        $commands = Artisan::all();

        $this->assertArrayHasKey(StatsCommand::COMMAND_NAME, $commands);
    }

    public function test_config_merging(): void
    {
        $this->assertTrue(config(LarascanServiceProvider::CONFIG_KEY . '.skip_tests'));
        $this->assertIsArray(config(LarascanServiceProvider::CONFIG_KEY . '.paths'));
    }
}
