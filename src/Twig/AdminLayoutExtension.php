<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

/**
 * Exposes layout-wide data (sidebar notification badges) to every template.
 * Counts default to 0 until a real data source is wired in.
 */
final class AdminLayoutExtension extends AbstractExtension implements GlobalsInterface
{
    public function getGlobals(): array
    {
        return [
            'nav_badges' => [
                'orders' => 0,
                'inventory' => 0,
            ],
        ];
    }
}
