<?php

declare(strict_types=1);

namespace Larascan\Commands;

use Illuminate\Console\Command;
use Larascan\Engine\InventoryScanner;
use Larascan\Support\PathResolver;
use Larascan\Support\TermwindFormatter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class StatsCommand extends Command
{
    public const COMMAND_NAME = 'native:stats';

    public const DEFAULT_ALIASES = [
        'larascan:stats',
        'larascan:inventory',
        'larascan:scan',
    ];

    /**
     * The console command name.
     */
    protected $name = self::COMMAND_NAME;

    /**
     * The console command description.
     */
    protected $description = 'Analyze codebase adoption and inventory of native Laravel 13 Core Facades, Utilities, and Helpers';

    /**
     * The console command aliases.
     *
     * @var array<string>
     */
    protected $aliases = self::DEFAULT_ALIASES;

    /**
     * Get aliases excluding a specific command name.
     *
     * @return array<string>
     */
    public static function aliasesExcept(string $commandName): array
    {
        return array_values(array_diff(
            [self::COMMAND_NAME, ...self::DEFAULT_ALIASES],
            [$commandName]
        ));
    }

    public function __construct(private ?InventoryScanner $scanner = null)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('path', InputArgument::OPTIONAL, 'Path to file or directory to scan');
        $this->addOption('skip-tests', null, InputOption::VALUE_NONE, 'Skip tests directory from scan');
        $this->addOption('no-skip-tests', null, InputOption::VALUE_NONE, 'Do not skip tests directory');
        $this->addOption('used', null, InputOption::VALUE_NONE, 'Show only used Laravel 13 features');
        $this->addOption('unused', null, InputOption::VALUE_NONE, 'Show only unused Laravel 13 features');
        $this->addOption('json', null, InputOption::VALUE_NONE, 'Output result in JSON format');
    }

    public function handle(?InventoryScanner $scanner = null): int
    {
        $path = $this->resolvePath();
        $skipTests = $this->resolveSkipTests();

        $usedOnly = (bool) $this->option('used');
        $unusedOnly = (bool) $this->option('unused');
        $asJson = (bool) $this->option('json');

        $activeScanner = $scanner ?? $this->scanner ?? $this->resolveScanner();
        $result = $activeScanner->scan($path, $skipTests);

        $formatter = new TermwindFormatter($this->output);

        if ($asJson) {
            $this->output->writeln(json_encode($result->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } else {
            $formatter->renderHeader();
            $formatter->renderResult($result, usedOnly: $usedOnly, unusedOnly: $unusedOnly);
        }

        return Command::SUCCESS;
    }

    private function resolvePath(): string
    {
        $configuredPaths = (array) PathResolver::packageConfig('paths', []);

        return PathResolver::resolveScanPath($this->argument('path'), $configuredPaths);
    }

    private function resolveSkipTests(): bool
    {
        if ($this->option('no-skip-tests')) {
            return false;
        }

        if ($this->option('skip-tests')) {
            return true;
        }

        return (bool) PathResolver::packageConfig('skip_tests', true);
    }

    private function resolveScanner(): InventoryScanner
    {
        if ($this->laravel !== null && $this->laravel->bound(InventoryScanner::class)) {
            return $this->laravel->make(InventoryScanner::class);
        }

        return new InventoryScanner();
    }
}
