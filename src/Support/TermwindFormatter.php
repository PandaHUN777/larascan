<?php

declare(strict_types=1);

namespace Larascan\Support;

use Larascan\Engine\InventoryResult;
use Symfony\Component\Console\Output\OutputInterface;

use function Termwind\render;
use function Termwind\renderUsing;

final class TermwindFormatter
{
    private const MAX_DISPLAY_UNUSED = 30;

    private const HEADER_CLASS = 'text-left font-bold text-gray-300 pr-4';

    private const HEADER_CLASS_NO_PR = 'text-left font-bold text-gray-300';

    public function __construct(private readonly ?OutputInterface $output = null)
    {
        if ($this->output !== null) {
            renderUsing($this->output);
        }
    }

    public function renderHeader(): void
    {
        render(<<<'HTML'
            <div class="my-1">
                <div class="px-2 py-1 bg-blue-600 text-white font-bold">
                    LARASCAN &bull; Laravel 13 Core Native Adoption &amp; Inventory Engine
                </div>
            </div>
        HTML);
    }

    public function renderResult(InventoryResult $result, bool $usedOnly = false, bool $unusedOnly = false): void
    {
        $laravelVersion = htmlspecialchars($result->getLaravelVersion());
        $scannedPath = htmlspecialchars($result->getScannedPath());
        $filesCount = $result->getScannedFilesCount();
        $usedCount = $result->getUsedCount();
        $unusedCount = $result->getUnusedCount();
        $rate = $result->getAdoptionRate();

        $bar = $this->makeProgressBar($rate);
        $color = $this->getAdoptionRateColor($rate);

        render(<<<HTML
            <div class="my-1 p-1 bg-gray-800">
                <div class="flex space-x-2">
                    <span class="text-white font-bold">Laravel Core Version:</span>
                    <span class="text-cyan-400">{$laravelVersion}</span>
                    <span class="text-gray-500">&bull;</span>
                    <span class="text-white font-bold">Scan Path:</span>
                    <span class="text-gray-300">{$scannedPath}</span>
                    <span class="text-gray-500">&bull;</span>
                    <span class="text-white font-bold">Files:</span>
                    <span class="text-gray-300">{$filesCount}</span>
                </div>
                <div class="mt-1 flex space-x-2">
                    <span class="text-white font-bold">Native Laravel Adoption Rate:</span>
                    <span class="{$color} font-bold">{$rate}%</span>
                    <span class="text-cyan-400 font-bold">[{$bar}]</span>
                    <span class="text-gray-400">({$usedCount} Used / {$unusedCount} Unused)</span>
                </div>
            </div>
        HTML);

        $used = $result->getUsed();
        $unused = $result->getUnused();

        // 1. Used Items Table
        if (! $unusedOnly && ! empty($used)) {
            $this->renderInventoryTable(
                "USED LARAVEL CAPABILITIES ({$usedCount})",
                'bg-green-700',
                $used,
                [
                    ['label' => 'Call Count', 'class' => 'text-right font-bold text-gray-300 pr-4'],
                    ['label' => 'File Count', 'class' => 'text-right font-bold text-gray-300'],
                ],
                fn(array $item) => [
                    ['content' => "{$item['count']} times", 'class' => 'text-right text-green-400 font-bold pr-4'],
                    ['content' => "{$item['files']} files", 'class' => 'text-right text-gray-400'],
                ]
            );
        }

        // 2. Unused Items Table
        if (! $usedOnly && ! empty($unused)) {
            $this->renderInventoryTable(
                "UNUSED LARAVEL CAPABILITIES ({$unusedCount})",
                'bg-red-800',
                array_slice($unused, 0, self::MAX_DISPLAY_UNUSED, true),
                [
                    ['label' => 'Status', 'class' => self::HEADER_CLASS_NO_PR],
                ],
                fn(array $item) => [
                    ['content' => 'Not yet used in project', 'class' => 'text-left text-gray-500'],
                ]
            );

            $totalUnused = count($unused);
            if ($totalUnused > self::MAX_DISPLAY_UNUSED) {
                $remaining = $totalUnused - self::MAX_DISPLAY_UNUSED;
                render("<div class=\"text-gray-500 my-1 italic\">... and {$remaining} more Laravel capabilities are not yet used in the project. Use --json to see the full list.</div>");
            }
        }

        // 3. Parse Errors Warning
        if ($result->hasParseErrors()) {
            $errorCount = count($result->getParseErrors());
            render("<div class=\"mt-1 text-yellow-500 font-bold\">⚠ {$errorCount} file(s) could not be parsed and were skipped.</div>");
        }
    }

    private function makeProgressBar(float $rate, int $width = 20): string
    {
        $filled = (int) round(($rate / 100) * $width);
        $empty = max(0, $width - $filled);

        return str_repeat('■', $filled) . str_repeat('░', $empty);
    }

    private function getAdoptionRateColor(float $rate): string
    {
        return match (true) {
            $rate >= 50.0 => 'text-green-400',
            $rate >= 20.0 => 'text-yellow-400',
            default => 'text-red-400',
        };
    }

    private function renderSectionBadge(string $title, string $bgColor): void
    {
        render(sprintf('<div class="mt-2 mb-1"><span class="px-2 py-1 %s text-white font-bold">%s</span></div>', $bgColor, $title));
    }

    /**
     * @param  array<string, array{name: string, type: string, class?: string, count: int, files: int}>  $items
     * @param  array<array{label: string, class: string}>  $extraHeaders
     * @param  callable(array{name: string, type: string, class?: string, count: int, files: int}): array<array{content: string, class: string}>  $extraCellsResolver
     */
    private function renderInventoryTable(
        string $badgeTitle,
        string $badgeColor,
        array $items,
        array $extraHeaders,
        callable $extraCellsResolver
    ): void {
        $this->renderSectionBadge($badgeTitle, $badgeColor);

        $headers = [...$this->getBaseHeaders(), ...$extraHeaders];
        $rows = array_map(
            fn(array $item) => [...$this->getBaseCells($item), ...$extraCellsResolver($item)],
            $items
        );

        $this->renderTable($headers, $rows);
    }

    /**
     * @return array<array{label: string, class: string}>
     */
    private function getBaseHeaders(): array
    {
        return [
            ['label' => 'Class / Function', 'class' => self::HEADER_CLASS],
            ['label' => 'Type', 'class' => self::HEADER_CLASS],
        ];
    }

    /**
     * @param  array{name: string, type: string}  $item
     * @return array<array{content: string, class: string}>
     */
    private function getBaseCells(array $item): array
    {
        return [
            ['content' => htmlspecialchars($item['name']), 'class' => 'text-left font-bold text-white pr-4'],
            ['content' => $this->renderTypeBadge($item['type']), 'class' => 'text-left pr-4'],
        ];
    }

    /**
     * @param  array<array{label: string, class: string}>  $headers
     * @param  array<array<array{content: string, class: string}>>  $rows
     */
    private function renderTable(array $headers, array $rows): void
    {
        $thHtml = implode('', array_map(
            fn(array $h) => $this->renderHtmlTag('th', $h['class'], $h['label']),
            $headers
        ));

        $trHtml = implode('', array_map(function (array $cells) {
            $tdHtml = implode('', array_map(
                fn(array $c) => $this->renderHtmlTag('td', $c['class'], $c['content']),
                $cells
            ));
            return "<tr>{$tdHtml}</tr>";
        }, $rows));

        render("<table><thead><tr>{$thHtml}</tr></thead><tbody>{$trHtml}</tbody></table>");
    }

    private function renderHtmlTag(string $tag, string $class, string $content): string
    {
        return sprintf('<%1$s class="%2$s">%3$s</%1$s>', $tag, $class, $content);
    }

    private function renderTypeBadge(string $type): string
    {
        $color = \Larascan\Engine\InventoryType::tryFrom($type)?->badgeColor() ?? 'text-yellow-400';

        return sprintf('<span class="%s">%s</span>', $color, $type);
    }
}
