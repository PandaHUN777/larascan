<?php

declare(strict_types=1);

namespace Larascan\Tests\Unit;

use Larascan\Engine\CoreInventoryLoader;
use Larascan\Engine\InventoryResult;
use Larascan\Engine\InventoryScanner;
use Larascan\Engine\InventoryType;
use Larascan\Tests\TestCase;

class InventoryEngineTest extends TestCase
{
    public function test_inventory_loader_loads_facades_utilities_and_helpers(): void
    {
        $loader = new CoreInventoryLoader();
        $data = $loader->load();

        $this->assertArrayHasKey('facades', $data);
        $this->assertArrayHasKey('utilities', $data);
        $this->assertArrayHasKey('helpers', $data);
        $this->assertArrayHasKey('all_classes', $data);
        $this->assertArrayHasKey('all_helpers', $data);

        $this->assertNotEmpty($data['facades']);
        $this->assertNotEmpty($data['utilities']);
        $this->assertNotEmpty($data['helpers']);
    }

    public function test_inventory_result_memoization_and_calculations(): void
    {
        $items = [
            'Route' => CoreInventoryLoader::makeItem('Route', InventoryType::Facade, count: 5, files: 2),
            'Str' => CoreInventoryLoader::makeItem('Str', InventoryType::Utility, count: 0, files: 0),
            'now' => CoreInventoryLoader::makeItem('now', InventoryType::Helper, count: 3, files: 1),
        ];

        $result = new InventoryResult($items, 10, '/test/path', '13.0');

        $this->assertSame(3, $result->getTotalTrackedCount());
        $this->assertSame(2, $result->getUsedCount());
        $this->assertSame(1, $result->getUnusedCount());
        $this->assertSame(66.7, $result->getAdoptionRate());

        // Test memoization identity
        $usedFirst = $result->getUsed();
        $usedSecond = $result->getUsed();
        $this->assertSame($usedFirst, $usedSecond);

        $unusedFirst = $result->getUnused();
        $unusedSecond = $result->getUnused();
        $this->assertSame($unusedFirst, $unusedSecond);
    }

    public function test_ast_visitor_ignores_custom_user_classes_with_same_name_as_facades(): void
    {
        $code = <<<'PHP'
        <?php
        namespace App\Services;

        use App\Models\Process;
        use App\Models\File;
        use Illuminate\Support\Facades\Route;

        class UserService
        {
            public function handle(): void
            {
                Process::find(1);
                File::create([]);
                Route::get('/users', fn() => []);
                now();
            }
        }
        PHP;

        $parser = (new \PhpParser\ParserFactory())->createForNewestSupportedVersion();
        $ast = $parser->parse($code);

        $trackedClasses = [
            'Process' => 'Illuminate\Support\Facades\Process',
            'File' => 'Illuminate\Support\Facades\File',
            'Route' => 'Illuminate\Support\Facades\Route',
        ];
        $trackedHelpers = [
            'now' => 'now',
        ];

        $visitor = new \Larascan\Engine\InventoryVisitor($trackedClasses, $trackedHelpers);
        $traverser = InventoryScanner::createTraverser($visitor);

        $visitor->setCurrentFile('/app/Services/UserService.php');
        $traverser->traverse($ast);

        $usages = $visitor->getUsages();

        // Process and File must NOT be counted because they are App\Models\Process and App\Models\File
        $this->assertArrayNotHasKey('Process', $usages);
        $this->assertArrayNotHasKey('File', $usages);

        // Route and now() MUST be counted
        $this->assertArrayHasKey('Route', $usages);
        $this->assertSame(1, $usages['Route']);

        $this->assertArrayHasKey('now', $usages);
        $this->assertSame(1, $usages['now']);
    }

    public function test_inventory_type_enum_badge_colors(): void
    {
        $this->assertSame('text-cyan-400', InventoryType::Facade->badgeColor());
        $this->assertSame('text-blue-400', InventoryType::Utility->badgeColor());
        $this->assertSame('text-yellow-400', InventoryType::Helper->badgeColor());
    }

    public function test_stats_command_aliases_except(): void
    {
        $aliases = \Larascan\Commands\StatsCommand::aliasesExcept('larascan:stats');

        $this->assertNotContains('larascan:stats', $aliases);
        $this->assertContains(\Larascan\Commands\StatsCommand::COMMAND_NAME, $aliases);
        $this->assertContains('larascan:scan', $aliases);
    }

    public function test_ast_visitor_ignores_namespaced_custom_function_calls(): void
    {
        $code = <<<'PHP'
        <?php
        namespace App\Services;

        class NotificationService
        {
            public function run(): void
            {
                \App\Utils\now();
                Utils\now();
                now();
                \now();
            }
        }
        PHP;

        $parser = (new \PhpParser\ParserFactory())->createForNewestSupportedVersion();
        $ast = $parser->parse($code);

        $trackedHelpers = ['now' => 'now'];
        $visitor = new \Larascan\Engine\InventoryVisitor([], $trackedHelpers);
        $traverser = InventoryScanner::createTraverser($visitor);

        $visitor->setCurrentFile('/app/Services/NotificationService.php');
        $traverser->traverse($ast);

        $usages = $visitor->getUsages();

        // Only now() and \now() must be counted (2 times), \App\Utils\now() and Utils\now() must NOT
        $this->assertSame(2, $usages['now'] ?? 0);
    }

    public function test_ast_visitor_ignores_unimported_local_sibling_classes(): void
    {
        $tempDir = sys_get_temp_dir() . '/larascan_test_' . uniqid();
        mkdir($tempDir, 0777, true);
        file_put_contents($tempDir . '/File.php', '<?php namespace App\Models; class File {}');

        $orderFilePath = $tempDir . '/Order.php';
        $orderCode = <<<'PHP'
        <?php
        namespace App\Models;

        class Order
        {
            public function process(): void
            {
                File::create();
                Route::get('/orders', fn() => []);
            }
        }
        PHP;

        file_put_contents($orderFilePath, $orderCode);

        $parser = (new \PhpParser\ParserFactory())->createForNewestSupportedVersion();
        $ast = $parser->parse($orderCode);

        $trackedClasses = [
            'File' => 'Illuminate\Support\Facades\File',
            'Route' => 'Illuminate\Support\Facades\Route',
        ];

        $visitor = new \Larascan\Engine\InventoryVisitor($trackedClasses, []);
        $traverser = InventoryScanner::createTraverser($visitor);

        $visitor->setCurrentFile($orderFilePath);
        $traverser->traverse($ast);

        $usages = $visitor->getUsages();

        // Clean up temp files
        unlink($tempDir . '/File.php');
        unlink($orderFilePath);
        rmdir($tempDir);

        // File must not be counted because File.php exists as a local sibling class
        $this->assertArrayNotHasKey('File', $usages);

        // Route must be counted as unimported facade alias
        $this->assertArrayHasKey('Route', $usages);
        $this->assertSame(1, $usages['Route']);
    }

    public function test_detect_laravel_version_reads_from_packages_dev(): void
    {
        $tempDir = sys_get_temp_dir() . '/larascan_lock_test_' . uniqid();
        mkdir($tempDir, 0777, true);

        $lockContent = json_encode([
            'packages' => [],
            'packages-dev' => [
                [
                    'name' => 'laravel/framework',
                    'version' => 'v13.5.0',
                ],
            ],
        ], JSON_PRETTY_PRINT);

        file_put_contents($tempDir . '/composer.lock', $lockContent);

        $scanner = new InventoryScanner();
        $reflector = new \ReflectionMethod($scanner, 'detectLaravelVersion');
        $detected = $reflector->invoke($scanner, $tempDir);

        unlink($tempDir . '/composer.lock');
        rmdir($tempDir);

        $this->assertSame('v13.5.0', $detected);
    }
}
