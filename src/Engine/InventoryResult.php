<?php

declare(strict_types=1);

namespace Larascan\Engine;

/**
 * Data value object containing the results of a Laravel 13 Core Inventory scan.
 */
final class InventoryResult
{
    /** @var array<string, array{name: string, type: string, class?: string, count: int, files: int}>|null */
    private ?array $used = null;

    /** @var array<string, array{name: string, type: string, class?: string, count: int, files: int}>|null */
    private ?array $unused = null;

    /**
     * @param  array<string, array{name: string, type: string, class?: string, count: int, files: int}>  $items
     * @param  array<array{file: string, error: string}>  $parseErrors
     */
    public function __construct(
        private readonly array $items,
        private readonly int $scannedFilesCount,
        private readonly string $scannedPath,
        private readonly string $laravelVersion = '13.x',
        private readonly array $parseErrors = [],
    ) {
    }

    /**
     * @return array<string, array{name: string, type: string, class?: string, count: int, files: int}>
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * Filter only items that are used (count > 0), sorted by count desc.
     *
     * @return array<string, array{name: string, type: string, class?: string, count: int, files: int}>
     */
    public function getUsed(): array
    {
        if ($this->used !== null) {
            return $this->used;
        }

        $used = array_filter($this->items, fn(array $item) => $item['count'] > 0);
        uasort($used, fn(array $a, array $b) => $b['count'] <=> $a['count']);

        return $this->used = $used;
    }

    /**
     * Filter only items that are unused (count === 0), sorted alphabetically.
     *
     * @return array<string, array{name: string, type: string, class?: string, count: int, files: int}>
     */
    public function getUnused(): array
    {
        if ($this->unused !== null) {
            return $this->unused;
        }

        $unused = array_filter($this->items, fn(array $item) => $item['count'] === 0);
        ksort($unused);

        return $this->unused = $unused;
    }

    public function getTotalTrackedCount(): int
    {
        return count($this->items);
    }

    public function getUsedCount(): int
    {
        return count($this->getUsed());
    }

    public function getUnusedCount(): int
    {
        return $this->unused !== null
            ? count($this->unused)
            : ($this->getTotalTrackedCount() - $this->getUsedCount());
    }

    public function getAdoptionRate(): float
    {
        $total = $this->getTotalTrackedCount();
        if ($total === 0) {
            return 100.0;
        }

        return round(($this->getUsedCount() / $total) * 100, 1);
    }

    public function getScannedFilesCount(): int
    {
        return $this->scannedFilesCount;
    }

    public function getScannedPath(): string
    {
        return $this->scannedPath;
    }

    public function getLaravelVersion(): string
    {
        return $this->laravelVersion;
    }

    /**
     * @return array<array{file: string, error: string}>
     */
    public function getParseErrors(): array
    {
        return $this->parseErrors;
    }

    public function hasParseErrors(): bool
    {
        return $this->parseErrors !== [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'laravel_version' => $this->laravelVersion,
            'scanned_path' => $this->scannedPath,
            'scanned_files_count' => $this->scannedFilesCount,
            'total_tracked' => $this->getTotalTrackedCount(),
            'used_count' => $this->getUsedCount(),
            'unused_count' => $this->getUnusedCount(),
            'adoption_rate' => $this->getAdoptionRate(),
            'used' => $this->getUsed(),
            'unused' => $this->getUnused(),
        ];

        if ($this->parseErrors !== []) {
            $data['parse_errors'] = $this->parseErrors;
        }

        return $data;
    }
}
