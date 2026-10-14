<?php
/**
 * Cancelled Orders - orders whose order_status = 'Cancelled'.
 *
 * Cancelled orders are excluded from "Total Payment" so their value is never
 * counted in the money totals (they still appear in the Total Orders count).
 */

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/orders_view.php';
require_login();

$pageTitle = 'Cancelled Orders';
$active = 'cancelled';
require __DIR__ . '/includes/header.php';

render_orders_view(['status' => 'Cancelled']);

require __DIR__ . '/includes/footer.php';
