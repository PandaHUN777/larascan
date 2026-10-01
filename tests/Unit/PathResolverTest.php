<?php

declare(strict_types=1);

namespace Larascan\Tests\Unit;

use Larascan\Support\PathResolver;
use Larascan\Tests\TestCase;

class PathResolverTest extends TestCase
{
    public function test_it_detects_base_path_in_laravel_environment(): void
    {
        $this->assertTrue(PathResolver::hasBasePath());
        $this->assertNotNull(PathResolver::container());
        $this->assertNotEmpty(PathResolver::basePath());
        $this->assertStringContainsString('workbench', PathResolver::basePath('workbench'));
    }

    public function test_it_resolves_scan_path(): void
    {
        // Explicit argument takes precedence
        $resolved = PathResolver::resolveScanPath('/custom/dir');
        $this->assertSame('/custom/dir', $resolved);

        // Fallback to configured or base path
        $defaultResolved = PathResolver::resolveScanPath(null);
        $this->assertNotEmpty($defaultResolved);
    }

    public function test_it_safely_reads_bound_config(): void
    {
        $this->assertTrue(PathResolver::config('larascan.skip_tests', false));
        $this->assertSame('default_val', PathResolver::config('non.existent.key', 'default_val'));
    }

    public function test_it_reads_package_config(): void
    {
        $this->assertTrue(PathResolver::packageConfig('skip_tests', false));
        $this->assertSame('custom', PathResolver::packageConfig('non_existent', 'custom'));
    }
}
