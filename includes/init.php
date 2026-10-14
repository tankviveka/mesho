<?php
/**
 * Bootstrap file included at the top of every page.
 * Wires up config, session/auth helpers, helpers and navigation.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

/**
 * Sidebar navigation definition.
 *
 * @return array<int, array{group:?string, items: array<int, array{id:string,label:string,url:string,icon:string,badge:?string}>}>
 */
function nav_items(): array
{
    $pendingBadge = null;
    $deliveredBadge = null;

    try {
        $counts = db_row(
            "SELECT SUM(order_status = 'Pending')   AS p,
                    SUM(order_status = 'Delivered') AS d
               FROM orders"
        );
        if ($counts) {
            $pendingBadge = (int) $counts['p'] > 0 ? (string) (int) $counts['p'] : null;
            $deliveredBadge = (int) $counts['d'] > 0 ? (string) (int) $counts['d'] : null;
        }
    } catch (Throwable $e) {
        // Database not ready yet - navigation still renders.
    }

    return [
        [
            'group' => null,
            'items' => [
                ['id' => 'dashboard', 'label' => 'Dashboard',    'url' => 'dashboard.php',       'icon' => 'speedometer2', 'badge' => null],
            ],
        ],
        [
            'group' => 'Orders',
            'items' => [
                ['id' => 'orders',     'label' => 'All Orders',     'url' => 'orders.php',          'icon' => 'bag-check',   'badge' => null],
                ['id' => 'pending',    'label' => 'Pending Orders', 'url' => 'pending-orders.php',  'icon' => 'hourglass-split', 'badge' => $pendingBadge],
                ['id' => 'delivered',  'label' => 'Delivered Orders','url' => 'delivered-orders.php','icon' => 'check2-circle', 'badge' => $deliveredBadge],
                ['id' => 'returned',   'label' => 'Returned Orders','url' => 'returned-orders.php', 'icon' => 'arrow-counterclockwise', 'badge' => null],
                ['id' => 'cancelled',  'label' => 'Cancelled Orders','url' => 'cancelled-orders.php','icon' => 'x-octagon',   'badge' => null],
            ],
        ],
        [
            'group' => 'Reports',
            'items' => [
                ['id' => 'payments',   'label' => 'Payment Reports', 'url' => 'payment-reports.php', 'icon' => 'wallet2',      'badge' => null],
                ['id' => 'monthly',    'label' => 'Monthly Reports', 'url' => 'monthly-reports.php', 'icon' => 'bar-chart',    'badge' => null],
            ],
        ],
        [
            'group' => 'Account',
            'items' => [
                ['id' => 'settings',   'label' => 'Settings', 'url' => 'settings.php', 'icon' => 'gear', 'badge' => null],
            ],
        ],
    ];
}

/** Is this nav item the current page? */
function is_active_nav(string $id, string $active): bool
{
    return $id === $active;
}
