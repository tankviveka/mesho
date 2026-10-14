<?php
/**
 * Pending Orders - orders whose order_status = 'Pending'.
 */

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/orders_view.php';
require_login();

$pageTitle = 'Pending Orders';
$active = 'pending';
require __DIR__ . '/includes/header.php';

render_orders_view(['status' => 'Pending']);

require __DIR__ . '/includes/footer.php';
