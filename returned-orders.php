<?php
/**
 * Returned Orders - orders whose order_status = 'Returned'.
 *
 * A returned order is counted ONLY here: `order_status` holds a single value,
 * so it is never also counted as delivered or cancelled.
 */

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/orders_view.php';
require_login();

$pageTitle = 'Returned Orders';
$active = 'returned';
require __DIR__ . '/includes/header.php';

render_orders_view(['status' => 'Returned']);

require __DIR__ . '/includes/footer.php';
