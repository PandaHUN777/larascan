<?php

declare(strict_types=1);

namespace Larascan\Engine;

/**
 * Native Laravel core feature types tracked by Larascan.
 */
enum InventoryType: string
{
    case Facade = 'Facade';
    case Utility = 'Utility';
    case Helper = 'Helper';

    /**
     * Termwind color badge for this inventory type.
     */
    public function badgeColor(): string
    {
        return match ($this) {
            self::Facade => 'text-cyan-400',
            self::Utility => 'text-blue-400',
            self::Helper => 'text-yellow-400',
        };
    }
}
