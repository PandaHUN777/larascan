<?php

declare(strict_types=1);

namespace Larascan\Engine;

use Larascan\Support\PathResolver;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use SplFileInfo;
use Symfony\Component\Finder\Finder;

/**
 * Scans a target codebase and aggregates the usage of all Laravel 13 Core Facades, Utilities, and Helpers.
 */
final class InventoryScanner
{
    public const DEFAULT_IGNORE_PATHS = ['vendor', 'storage', 'bootstrap/cache', 'node_modules'];

    public const DEFAULT_TEST_PATHS = ['tests', 'Test'];

    private Parser $parser;

    private CoreInventoryLoader $loader;

    /** @var array<string> */
    private array $ignoredPaths;

    /**
     * @param  array<string>|null  $ignoredPaths
     */
    public function __construct(?CoreInventoryLoader $loader = null, ?array $ignoredPaths = null)
    {
        $this->parser = (new ParserFactory())->createForNewestSupportedVersion();
        $this->loader = $loader ?? new CoreInventoryLoader();

        $configured = PathResolver::packageConfig('ignore_paths');
        $this->ignoredPaths = $ignoredPaths ?? (is_array($configured) ? $configured : self::DEFAULT_IGNORE_PATHS);
    }

    /**
     * Create pre-configured AST traverser with NameResolver and InventoryVisitor.
     */
    public static function createTraverser(InventoryVisitor $visitor): NodeTraverser
    {
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver(null, ['preserveOriginalNames' => true]));
        $traverser->addVisitor($visitor);

        return $traverser;
    }

    /**
     * Scan target path and generate InventoryResult.
     */
    public function scan(string $targetPath, bool $skipTests = true): InventoryResult
    {
        $coreData = $this->loader->load();
        $visitor = new InventoryVisitor($coreData['all_classes'], $coreData['all_helpers']);
        $traverser = self::createTraverser($visitor);

        $files = $this->findPhpFiles($targetPath, $skipTests);
        $scannedCount = 0;
        /** @var array<array{file: string, error: string}> */
        $parseErrors = [];

        foreach ($files as $file) {
            $filePath = $file->getRealPath() ?: $file->getPathname();
            $code = file_get_contents($filePath);
            if ($code === false) {
                continue;
            }

            try {
                $ast = $this->parser->parse($code);
                if ($ast !== null) {
                    $visitor->setCurrentFile($filePath);
                    $traverser->traverse($ast);
                    $scannedCount++;
                }
            } catch (\Throwable $e) {
                $parseErrors[] = ['file' => $filePath, 'error' => $e->getMessage()];
                continue;
            }
        }

        $usages = $visitor->getUsages();
        $fileLocations = $visitor->getFileLocations();

        // Build combined items map
        $items = [];
        $sources = [
            $coreData['facades'],
            $coreData['utilities'],
            $coreData['helpers'],
        ];

        foreach ($sources as $group) {
            foreach ($group as $name => $meta) {
                if (isset($items[$name])) {
                    continue;
                }

                $items[$name] = CoreInventoryLoader::makeItem(
                    name: $name,
                    type: $meta['type'],
                    class: $meta['class'] ?? null,
                    count: $usages[$name] ?? 0,
                    files: isset($fileLocations[$name]) ? count($fileLocations[$name]) : 0,
                );
            }
        }

        $laravelVersion = $this->detectLaravelVersion($targetPath);

        return new InventoryResult(
            items: $items,
            scannedFilesCount: $scannedCount,
            scannedPath: $targetPath,
            laravelVersion: $laravelVersion,
            parseErrors: $parseErrors,
        );
    }

    /**
     * Find PHP files in path.
     *
     * @return iterable<SplFileInfo>
     */
    private function findPhpFiles(string $path, bool $skipTests): iterable
    {
        if (is_file($path)) {
            return [new SplFileInfo($path)];
        }

        if (! is_dir($path)) {
            return [];
        }

        $finder = Finder::create()
            ->files()
            ->name('*.php')
            ->in($path)
            ->exclude($this->ignoredPaths)
            ->ignoreDotFiles(true)
            ->ignoreVCS(true);

        if ($skipTests) {
            $finder->exclude(self::DEFAULT_TEST_PATHS);
        }

        return $finder;
    }

    /**
     * Detect Laravel version from composer.lock or Application::VERSION.
     */
    private function detectLaravelVersion(string $targetPath): string
    {
        // 1. Check target path or project composer.lock first
        $lockCandidates = array_unique([
            rtrim($targetPath, '/') . '/composer.lock',
            PathResolver::basePath('composer.lock'),
        ]);

        foreach ($lockCandidates as $lockFile) {
            if (! file_exists($lockFile)) {
                continue;
            }

            $content = file_get_contents($lockFile);
            if ($content === false) {
                continue;
            }

            $json = json_decode($content, true);
            if (! is_array($json)) {
                continue;
            }

            $packages = array_merge(
                (array) ($json['packages'] ?? []),
                (array) ($json['packages-dev'] ?? [])
            );

            $pkg = array_find(
                $packages,
                fn(mixed $p) => is_array($p) && ($p['name'] ?? null) === 'laravel/framework' && isset($p['version'])
            );

            if ($pkg !== null) {
                return (string) $pkg['version'];
            }
        }

        // 2. Check runtime Application constant
        if (defined('\Illuminate\Foundation\Application::VERSION')) {
            return \Illuminate\Foundation\Application::VERSION;
        }

        return '13.x';
    }
}
