<?php
/**
 * Delivered Orders - orders whose order_status = 'Delivered'.
 */

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/orders_view.php';
require_login();

$pageTitle = 'Delivered Orders';
$active = 'delivered';
require __DIR__ . '/includes/header.php';

render_orders_view(['status' => 'Delivered']);

require __DIR__ . '/includes/footer.php';
