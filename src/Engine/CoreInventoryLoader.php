<?php

declare(strict_types=1);

namespace Larascan\Engine;

use Larascan\Support\PathResolver;
use PhpToken;
use ReflectionClass;

/**
 * Dynamically discovers all native Laravel Facades, Utilities, and Helpers
 * directly from the Laravel Core framework files (vendor/laravel/framework).
 */
final class CoreInventoryLoader
{
    private const ILLUMINATE_PATH = '/vendor/laravel/framework/src/Illuminate';

    private string $illuminatePath;

    public function __construct(?string $basePath = null)
    {
        $this->illuminatePath = $this->locateIlluminate($basePath);
    }

    /**
     * Locate the Illuminate framework directory.
     */
    private function locateIlluminate(?string $basePath): string
    {
        $baseDirs = array_filter([
            $basePath,
            PathResolver::basePath(),
            dirname(__DIR__, 2),
        ]);

        $candidates = array_map(
            fn(string $dir) => rtrim($dir, '/') . self::ILLUMINATE_PATH,
            $baseDirs
        );

        if (class_exists(\Illuminate\Foundation\Application::class)) {
            $reflector = new ReflectionClass(\Illuminate\Foundation\Application::class);
            $fileName = $reflector->getFileName();
            if ($fileName !== false) {
                $candidates[] = dirname($fileName, 2);
            }
        }

        $found = array_find(
            $candidates,
            fn(string $dir) => is_dir($dir) && is_dir($dir . '/Support/Facades')
        );

        return $found ?? PathResolver::basePath(self::ILLUMINATE_PATH);
    }

    /**
     * Get the resolved Illuminate path.
     */
    public function getIlluminatePath(): string
    {
        return $this->illuminatePath;
    }

    /**
     * Load all Facades, Utilities, and Global Helpers directly from Laravel Core.
     *
     * @return array{
     *     facades: array<string, array{name: string, type: string, class: string}>,
     *     utilities: array<string, array{name: string, type: string, class: string}>,
     *     helpers: array<string, array{name: string, type: string}>,
     *     all_classes: array<string, string>,
     *     all_helpers: array<string, string>
     * }
     */
    public function load(): array
    {
        $facades = $this->loadFacades();
        $utilities = $this->loadUtilities();
        $helpers = $this->loadHelpers();

        $allClasses = array_merge(
            array_column($facades, 'class', 'name'),
            array_column($utilities, 'class', 'name')
        );

        $allHelpers = array_combine(array_keys($helpers), array_keys($helpers));

        return [
            'facades' => $facades,
            'utilities' => $utilities,
            'helpers' => $helpers,
            'all_classes' => $allClasses,
            'all_helpers' => $allHelpers,
        ];
    }

    /**
     * Discover all Facades in Illuminate/Support/Facades/*.php.
     *
     * @return array<string, array{name: string, type: string, class: string}>
     */
    private function loadFacades(): array
    {
        $facades = [];
        $facadesDir = $this->illuminatePath . '/Support/Facades';

        if (! is_dir($facadesDir)) {
            return $facades;
        }

        $files = glob($facadesDir . '/*.php') ?: [];
        foreach ($files as $file) {
            $name = basename($file, '.php');
            if ($name === 'Facade') {
                continue;
            }

            $facades[$name] = $this->makeItem($name, InventoryType::Facade, "Illuminate\\Support\\Facades\\{$name}");
        }

        ksort($facades);

        return $facades;
    }

    /**
     * Discover core utility classes in Illuminate/Support and Illuminate/Collections.
     *
     * @return array<string, array{name: string, type: string, class: string}>
     */
    private function loadUtilities(): array
    {
        $utilities = [];
        $candidates = [
            'Arr' => 'Illuminate\\Collections\\Arr',
            'Benchmark' => 'Illuminate\\Support\\Benchmark',
            'Carbon' => 'Illuminate\\Support\\Carbon',
            'Collection' => 'Illuminate\\Support\\Collection',
            'Fluent' => 'Illuminate\\Support\\Fluent',
            'HtmlString' => 'Illuminate\\Support\\HtmlString',
            'LazyCollection' => 'Illuminate\\Support\\LazyCollection',
            'Lottery' => 'Illuminate\\Support\\Lottery',
            'Number' => 'Illuminate\\Support\\Number',
            'Optional' => 'Illuminate\\Support\\Optional',
            'Pipeline' => 'Illuminate\\Pipeline\\Pipeline',
            'Pluralizer' => 'Illuminate\\Support\\Pluralizer',
            'Sleep' => 'Illuminate\\Support\\Sleep',
            'Str' => 'Illuminate\\Support\\Str',
            'Stringable' => 'Illuminate\\Support\\Stringable',
            'Timebox' => 'Illuminate\\Support\\Timebox',
        ];

        foreach ($candidates as $name => $class) {
            $utilities[$name] = $this->makeItem($name, InventoryType::Utility, $class);
        }

        ksort($utilities);

        return $utilities;
    }

    /**
     * Discover all global helper functions from Laravel Core helpers.php files.
     *
     * @return array<string, array{name: string, type: string}>
     */
    private function loadHelpers(): array
    {
        $helpers = [];
        $helperFiles = [
            $this->illuminatePath . '/Support/helpers.php',
            $this->illuminatePath . '/Foundation/helpers.php',
        ];

        foreach ($helperFiles as $file) {
            foreach ($this->extractFunctionsFromFile($file) as $fnName) {
                $helpers[$fnName] = $this->makeItem($fnName, InventoryType::Helper);
            }
        }

        ksort($helpers);

        return $helpers;
    }

    /**
     * Extract global function names from a PHP file using PhpToken.
     *
     * @return array<string>
     */
    private function extractFunctionsFromFile(string $file): array
    {
        if (! file_exists($file)) {
            return [];
        }

        $content = file_get_contents($file);
        if ($content === false) {
            return [];
        }

        $functions = [];
        $tokens = PhpToken::tokenize($content);
        $tokenCount = count($tokens);

        for ($i = 0; $i < $tokenCount; $i++) {
            if ($tokens[$i]->is(T_FUNCTION)) {
                $next = $i + 1;
                while ($next < $tokenCount && $tokens[$next]->isIgnorable()) {
                    $next++;
                }

                if ($next < $tokenCount && $tokens[$next]->is(T_STRING)) {
                    $functions[] = $tokens[$next]->text;
                }
            }
        }

        return $functions;
    }

    /**
     * Build standardized inventory metadata array.
     *
     * @return array{name: string, type: string, class?: string, count?: int, files?: int}
     */
    public static function makeItem(
        string $name,
        string|InventoryType $type,
        ?string $class = null,
        ?int $count = null,
        ?int $files = null
    ): array {
        $item = [
            'name' => $name,
            'type' => $type instanceof InventoryType ? $type->value : $type,
        ];

        if ($count !== null) {
            $item['count'] = $count;
        }

        if ($files !== null) {
            $item['files'] = $files;
        }

        if ($class !== null) {
            $item['class'] = $class;
        }

        return $item;
    }
}
