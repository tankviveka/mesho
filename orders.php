<?php
/**
 * All Orders - every demo order regardless of status.
 */

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/orders_view.php';
require_login();

$pageTitle = 'All Orders';
$active = 'orders';
require __DIR__ . '/includes/header.php';

render_orders_view(['status' => '']);

require __DIR__ . '/includes/footer.php';
