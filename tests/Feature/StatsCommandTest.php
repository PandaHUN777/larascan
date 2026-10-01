<?php

declare(strict_types=1);

namespace Larascan\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Larascan\Commands\StatsCommand;
use Larascan\Tests\TestCase;

class StatsCommandTest extends TestCase
{
    public function test_it_executes_artisan_command_with_json_output(): void
    {
        $exitCode = Artisan::call(StatsCommand::COMMAND_NAME, [
            'path' => $this->workbenchPath(),
            '--json' => true,
        ]);

        $this->assertSame(0, $exitCode);
        $output = Artisan::output();

        $this->assertJson($output);
        $data = json_decode($output, true);

        $this->assertArrayHasKey('laravel_version', $data);
        $this->assertArrayHasKey('adoption_rate', $data);
        $this->assertArrayHasKey('used_count', $data);
        $this->assertArrayHasKey('unused_count', $data);
        $this->assertArrayHasKey('used', $data);
        $this->assertArrayHasKey('unused', $data);
    }

    public function test_it_executes_artisan_command_with_formatted_output(): void
    {
        $exitCode = Artisan::call(StatsCommand::COMMAND_NAME, [
            'path' => $this->workbenchPath(),
        ]);

        $this->assertSame(0, $exitCode);
        $output = Artisan::output();

        $this->assertStringContainsString('LARASCAN', $output);
        $this->assertStringContainsString('Laravel Core Version', $output);
        $this->assertStringContainsString('USED LARAVEL CAPABILITIES', $output);
    }
}
