<?php
/**
 * Dashboard - KPI overview, charts and recent orders.
 * Every number below is calculated from rows in the MySQL database.
 */

require_once __DIR__ . '/includes/init.php';
require_login();

/* ----------------------------- Filters -------------------------------- */

$filters = list_filters();
// The dashboard only exposes month / year filters.
$kpiFilters = [
    'q' => '', 'status' => '', 'pay' => '',
    'month' => $filters['month'], 'year' => $filters['year'],
    'per' => $filters['per'], 'page' => 1,
];

$totals = dashboard_totals($kpiFilters);
$years = available_years();
$activeYear = $kpiFilters['year'] ?: (int) ($years[0] ?? (int) date('Y'));

/* Deltas: compare the selected period with the previous one. ------------ */
$prevFilters = $kpiFilters;
$deltaLabel = '';
if ($kpiFilters['month'] > 0 && $kpiFilters['year'] > 0) {
    $pm = $kpiFilters['month'] - 1;
    $py = $kpiFilters['year'];
    if ($pm < 1) { $pm = 12; $py--; }
    $prevFilters['month'] = $pm;
    $prevFilters['year'] = $py;
    $deltaLabel = 'vs ' . month_name($pm) . ' ' . $py;
} elseif ($kpiFilters['year'] > 0) {
    $prevFilters['year'] = $kpiFilters['year'] - 1;
    $prevFilters['month'] = 0;
    $deltaLabel = 'vs ' . $kpiFilters['year'];
} else {
    $prevFilters = [];
}

$prevTotals = $prevFilters === [] ? null : dashboard_totals($prevFilters);

function pct_delta(?float $current, ?float $previous): ?float
{
    if ($previous === null || $previous <= 0) {
        return null;
    }
    return round((($current - $previous) / $previous) * 100, 1);
}

$deltaOrders = $prevTotals ? pct_delta((float) $totals['total_orders'], (float) $prevTotals['total_orders']) : null;
$deltaRevenue = $prevTotals ? pct_delta((float) $totals['total_payment'], (float) $prevTotals['total_payment']) : null;
$deltaReceived = $prevTotals ? pct_delta((float) $totals['received_payment'], (float) $prevTotals['received_payment']) : null;

/* ----------------------------- Charts data ----------------------------- */

$monthLabels = [];
$ordersSeries = [];
$revenueSeries = [];
$receivedSeries = [];
foreach (last_months_series(12) as $row) {
    $monthLabels[] = $row['label'];
    $ordersSeries[] = $row['orders'];
    $revenueSeries[] = round($row['revenue'], 2);
    $receivedSeries[] = round($row['received'], 2);
}

$statusSplit = $totals['status_split'];
$paymentSplit = [
    'Received' => round((float) $totals['received_payment'], 2),
    'Pending'  => round((float) $totals['pending_payment'], 2),
    'Refunded' => round((float) $totals['refunded_payment'], 2),
];

/* ----------------------------- Tables --------------------------------- */

$recentOrders = db_all(
    "SELECT o.*, p.payment_status, p.payment_mode, p.amount AS paid_amount
       FROM orders o
       LEFT JOIN payments p ON p.order_id = o.id
   ORDER BY o.order_date DESC, o.id DESC
      LIMIT 8"
);

$topProducts = db_all(
    "SELECT product_name, category,
            SUM(qty) AS units,
            COUNT(*) AS orders_count,
            SUM(CASE WHEN order_status <> 'Cancelled' THEN order_amount END) AS revenue
       FROM orders
   GROUP BY product_name, category
   ORDER BY revenue DESC
      LIMIT 6"
);

/* ----------------------------- Chart JSON ------------------------------ */

$json = static fn(array $cfg): string => json_encode(
    $cfg,
    JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);

$chartTrend = $json([
    'type' => 'bar',
    'data' => [
        'labels' => $monthLabels,
        'datasets' => [
            [
                'type' => 'bar',
                'label' => 'Orders',
                'data' => $ordersSeries,
                'backgroundColor' => 'rgba(123,44,191,.75)',
                'hoverBackgroundColor' => '#7B2CBF',
                'borderRadius' => 6,
                'maxBarThickness' => 26,
                'yAxisID' => 'y',
            ],
            [
                'type' => 'line',
                'label' => 'Revenue',
                'msFormat' => 'inr',
                'data' => $revenueSeries,
                'borderColor' => '#F43397',
                'backgroundColor' => 'rgba(244,51,151,.12)',
                'fill' => true,
                'tension' => .38,
                'borderWidth' => 2.5,
                'pointRadius' => 3,
                'pointBackgroundColor' => '#F43397',
                'yAxisID' => 'y1',
            ],
        ],
    ],
    'options' => [
        'interaction' => ['mode' => 'index', 'intersect' => false],
        'plugins' => [
            'legend' => ['position' => 'top', 'align' => 'end'],
        ],
        'scales' => [
            'y'  => ['beginAtZero' => true, 'position' => 'left',
                      'ticks' => ['precision' => 0]],
            'y1' => ['beginAtZero' => true, 'position' => 'right',
                      'grid' => ['drawOnChartArea' => false],
                      'ticks' => ['msFormat' => 'inr']],
            'x'  => ['grid' => ['display' => false]],
        ],
    ],
]);

$chartStatus = $json([
    'type' => 'doughnut',
    'data' => [
        'labels' => array_keys($statusSplit),
        'datasets' => [[
            'data' => array_values($statusSplit),
            'backgroundColor' => ['#F59E0B', '#16A34A', '#0EA5E9', '#E11D48'],
            'borderColor' => '#fff',
            'borderWidth' => 3,
            'hoverOffset' => 8,
        ]],
    ],
    'options' => [
        'cutout' => '66%',
        'plugins' => ['legend' => ['position' => 'bottom']],
    ],
]);

$chartPayments = $json([
    'type' => 'doughnut',
    'data' => [
        'labels' => array_keys($paymentSplit),
        'datasets' => [[
            'msFormat' => 'inr',
            'data' => array_values($paymentSplit),
            'backgroundColor' => ['#16A34A', '#F59E0B', '#0EA5E9'],
            'borderColor' => '#fff',
            'borderWidth' => 3,
            'hoverOffset' => 8,
        ]],
    ],
    'options' => [
        'cutout' => '66%',
        'plugins' => ['legend' => ['position' => 'bottom']],
    ],
]);

$pageTitle = 'Dashboard';
$active = 'dashboard';
$hasCharts = true;
require __DIR__ . '/includes/header.php';

/* ----------------------------- Helpers -------------------------------- */

function delta_chip(?float $delta): string
{
    if ($delta === null) {
        return '<span class="kpi-delta flat">no comparison</span>';
    }
    if ($delta > 0) {
        return '<span class="kpi-delta up"><i class="bi bi-arrow-up-short"></i>' . e((string) $delta) . '%</span>';
    }
    if ($delta < 0) {
        return '<span class="kpi-delta down"><i class="bi bi-arrow-down-short"></i>' . e((string) abs($delta)) . '%</span>';
    }
    return '<span class="kpi-delta flat">0%</span>';
}

$periodLabel = trim(
    ($kpiFilters['month'] ? month_name($kpiFilters['month']) . ' ' : '') .
    ($kpiFilters['year'] ? (string) $kpiFilters['year'] : '')
);
$periodLabel = $periodLabel !== '' ? $periodLabel : 'All time';
?>

<?= demo_banner() ?>

<?php /* ------------------------- Filter bar ---------------------------- */ ?>
<form class="filter-bar mb-4" method="get" action="dashboard.php">
    <div class="row g-3 align-items-end">
        <div class="col-6 col-md-3 col-xl-2">
            <label class="form-label" for="month">Month</label>
            <select class="form-select" id="month" name="month" data-autosubmit>
                <option value="">All months</option>
                <?php foreach (available_months() as $m): ?>
                    <option value="<?= $m ?>" <?= $kpiFilters['month'] === $m ? 'selected' : '' ?>>
                        <?= e(month_name($m)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3 col-xl-2">
            <label class="form-label" for="year">Year</label>
            <select class="form-select" id="year" name="year" data-autosubmit>
                <option value="">All years</option>
                <?php foreach ($years as $y): ?>
                    <option value="<?= $y ?>" <?= $kpiFilters['year'] === $y ? 'selected' : '' ?>><?= $y ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-6 col-xl-4">
            <div class="d-flex flex-wrap gap-2">
                <button class="btn btn-primary btn-sm" type="submit">
                    <i class="bi bi-funnel me-1"></i>Apply
                </button>
                <a class="btn btn-light btn-sm" href="dashboard.php">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
                </a>
                <a class="btn btn-outline-primary btn-sm" href="monthly-reports.php">
                    <i class="bi bi-bar-chart me-1"></i>Full monthly report
                </a>
            </div>
        </div>
        <div class="col-12 col-xl-4 text-xl-end">
            <span class="section-sub">
                Showing totals for: <strong class="text-dark"><?= e($periodLabel) ?></strong>
            </span>
        </div>
    </div>
</form>

<?php /* --------------------------- KPI cards --------------------------- */ ?>
<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3">
        <div class="kpi-card">
            <div class="kpi-top">
                <span class="kpi-icon"><i class="bi bi-bag-check"></i></span>
                <?= $deltaLabel !== '' ? delta_chip($deltaOrders) : '' ?>
            </div>
            <div class="kpi-label">Total Orders</div>
            <div class="kpi-value"><?= number_format((float) $totals['total_orders']) ?></div>
            <div class="kpi-sub"><?= e($periodLabel) ?> &middot; <?= number_format((float) $totals['units_sold']) ?> units</div>
        </div>
    </div>

    <div class="col-6 col-xl-3">
        <div class="kpi-card">
            <div class="kpi-top">
                <span class="kpi-icon t-amber"><i class="bi bi-hourglass-split"></i></span>
            </div>
            <div class="kpi-label">Pending Orders</div>
            <div class="kpi-value"><?= number_format((float) $totals['pending_orders']) ?></div>
            <div class="kpi-sub">
                <?= number_format((float) $totals['total_orders'] > 0 ? ((float) $totals['pending_orders'] / (float) $totals['total_orders']) * 100 : 0, 1) ?>%
                of all orders
            </div>
        </div>
    </div>

    <div class="col-6 col-xl-3">
        <div class="kpi-card">
            <div class="kpi-top">
                <span class="kpi-icon t-green"><i class="bi bi-check2-circle"></i></span>
            </div>
            <div class="kpi-label">Delivered Orders</div>
            <div class="kpi-value"><?= number_format((float) $totals['delivered_orders']) ?></div>
            <div class="kpi-sub">
                <?= number_format((float) $totals['total_orders'] > 0 ? ((float) $totals['delivered_orders'] / (float) $totals['total_orders']) * 100 : 0, 1) ?>%
                delivery rate
            </div>
        </div>
    </div>

    <div class="col-6 col-xl-3">
        <div class="kpi-card">
            <div class="kpi-top">
                <span class="kpi-icon t-cyan"><i class="bi bi-arrow-counterclockwise"></i></span>
            </div>
            <div class="kpi-label">Returned Orders</div>
            <div class="kpi-value"><?= number_format((float) $totals['returned_orders']) ?></div>
            <div class="kpi-sub">
                <?= number_format((float) $totals['total_orders'] > 0 ? ((float) $totals['returned_orders'] / (float) $totals['total_orders']) * 100 : 0, 1) ?>%
                return rate
            </div>
        </div>
    </div>

    <div class="col-6 col-xl-3">
        <div class="kpi-card">
            <div class="kpi-top">
                <span class="kpi-icon t-red"><i class="bi bi-x-octagon"></i></span>
            </div>
            <div class="kpi-label">Cancelled Orders</div>
            <div class="kpi-value"><?= number_format((float) $totals['cancelled_orders']) ?></div>
            <div class="kpi-sub">
                <?= number_format((float) $totals['total_orders'] > 0 ? ((float) $totals['cancelled_orders'] / (float) $totals['total_orders']) * 100 : 0, 1) ?>%
                cancellation rate
            </div>
        </div>
    </div>

    <div class="col-6 col-xl-3">
        <div class="kpi-card">
            <div class="kpi-top">
                <span class="kpi-icon t-pink"><i class="bi bi-wallet2"></i></span>
                <?= $deltaLabel !== '' ? delta_chip($deltaRevenue) : '' ?>
            </div>
            <div class="kpi-label">Total Payment</div>
            <div class="kpi-value sm"><?= inr($totals['total_payment'], false) ?></div>
            <div class="kpi-sub">Gross value of non-cancelled orders</div>
        </div>
    </div>

    <div class="col-6 col-xl-3">
        <div class="kpi-card">
            <div class="kpi-top">
                <span class="kpi-icon t-green"><i class="bi bi-cash-stack"></i></span>
                <?= $deltaLabel !== '' ? delta_chip($deltaReceived) : '' ?>
            </div>
            <div class="kpi-label">Received Payment</div>
            <div class="kpi-value sm"><?= inr($totals['received_payment'], false) ?></div>
            <div class="kpi-sub">Settled &amp; credited to seller</div>
        </div>
    </div>

    <div class="col-6 col-xl-3">
        <div class="kpi-card">
            <div class="kpi-top">
                <span class="kpi-icon t-amber"><i class="bi bi-clock-history"></i></span>
            </div>
            <div class="kpi-label">Pending Payment</div>
            <div class="kpi-value sm"><?= inr($totals['pending_payment'], false) ?></div>
            <div class="kpi-sub">Awaiting settlement</div>
        </div>
    </div>
</div>

<?php /* ---------------------------- Charts ------------------------------ */ ?>
<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-graph-up me-2 text-primary"></i>Orders &amp; Revenue Trend</span>
                <span class="section-sub">Last 12 months</span>
            </div>
            <div class="card-body">
                <div class="chart-box lg">
                    <canvas data-chart-config="chartTrend"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">
                <i class="bi bi-pie-chart me-2 text-primary"></i>Order Status Split
            </div>
            <div class="card-body">
                <div class="chart-box sm">
                    <canvas data-chart-config="chartStatus"></canvas>
                </div>
                <div class="mt-3">
                    <?php foreach ($statusSplit as $label => $count): ?>
                        <div class="stat-row">
                            <span class="label"><?= e($label) ?></span>
                            <span class="value"><?= number_format($count) ?></span>
                        </div>
                    <?php endforeach; ?>
                    <div class="stat-row">
                        <span class="label"><strong>Total</strong></span>
                        <span class="value"><?= number_format((float) $totals['total_orders']) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">
                <i class="bi bi-cash-coin me-2 text-primary"></i>Payment Status
            </div>
            <div class="card-body">
                <div class="chart-box sm">
                    <canvas data-chart-config="chartPayments"></canvas>
                </div>
                <div class="mt-3">
                    <?php foreach ($paymentSplit as $label => $amount): ?>
                        <div class="stat-row">
                            <span class="label"><?= e($label) ?></span>
                            <span class="value"><?= inr($amount) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="small text-muted mt-2">
                    Total Payment = Received + Pending + Refunded.
                    Cancelled orders are excluded so nothing is counted twice.
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">
                <i class="bi bi-star me-2 text-primary"></i>Top Products
            </div>
            <div class="card-body">
                <?php if (!$topProducts): ?>
                    <div class="empty-state"><i class="bi bi-box-seam"></i>No product data yet.</div>
                <?php else: ?>
                    <?php foreach ($topProducts as $i => $p): ?>
                        <div class="stat-row">
                            <span class="label">
                                <span class="product-thumb t<?= ($i % 6) + 1 ?>" style="width:28px;height:28px;font-size:13px;display:inline-grid;vertical-align:middle;margin-right:8px;">
                                    <i class="bi bi-box"></i>
                                </span>
                                <?= e($p['product_name']) ?>
                                <br><small class="ms-4"><?= number_format((int) $p['units']) ?> units &middot; <?= number_format((int) $p['orders_count']) ?> orders</small>
                            </span>
                            <span class="value"><?= inr($p['revenue'], false) ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">
                <i class="bi bi-lightning me-2 text-primary"></i>Quick Actions
            </div>
            <div class="card-body d-grid gap-2">
                <a class="btn btn-primary" href="orders.php"><i class="bi bi-bag me-1"></i>Browse all orders</a>
                <a class="btn btn-outline-primary" href="pending-orders.php"><i class="bi bi-hourglass-split me-1"></i>Pending orders</a>
                <a class="btn btn-outline-primary" href="payment-reports.php"><i class="bi bi-wallet2 me-1"></i>Payment reports</a>
                <a class="btn btn-outline-primary" href="monthly-reports.php"><i class="bi bi-bar-chart me-1"></i>Monthly reports</a>
                <a class="btn btn-light" href="settings.php"><i class="bi bi-gear me-1"></i>Settings</a>
                <div class="demo-banner mt-2">
                    <i class="bi bi-shield-lock"></i>
                    <div class="small">Demo data lives in the <code>mesho_demo</code> MySQL database and can be reseeded at any time.</div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php /* -------------------------- Recent orders -------------------------- */ ?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-clock-history me-2 text-primary"></i>Recent Orders</span>
        <a class="btn btn-sm btn-outline-primary" href="orders.php">View all <i class="bi bi-arrow-right ms-1"></i></a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Order No</th>
                        <th>Product</th>
                        <th>Customer</th>
                        <th>Date</th>
                        <th class="text-end">Amount</th>
                        <th>Order Status</th>
                        <th>Payment</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$recentOrders): ?>
                        <tr><td colspan="7">
                            <div class="empty-state"><i class="bi bi-inbox"></i>No orders found. Import the demo data first.</div>
                        </td></tr>
                    <?php else: ?>
                        <?php foreach ($recentOrders as $i => $row): ?>
                            <tr>
                                <td><span class="order-no"><?= e($row['order_number']) ?></span></td>
                                <td>
                                    <div class="product-cell">
                                        <span class="product-thumb t<?= ($i % 6) + 1 ?>"><i class="bi bi-box"></i></span>
                                        <span>
                                            <span class="cell-strong"><?= e($row['product_name']) ?></span><br>
                                            <span class="cell-muted"><?= e($row['category']) ?> &middot; Qty <?= (int) $row['qty'] ?></span>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <span class="cell-strong"><?= e($row['customer_name']) ?></span><br>
                                    <span class="cell-muted"><?= e($row['city']) ?>, <?= e($row['state']) ?></span>
                                </td>
                                <td class="cell-muted"><?= date_fmt($row['order_date']) ?></td>
                                <td class="text-end cell-strong"><?= inr($row['order_amount']) ?></td>
                                <td><?= order_status_badge((string) $row['order_status']) ?></td>
                                <td><?= payment_status_badge((string) ($row['payment_status'] ?? 'Pending')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script type="application/json" id="chartTrend"><?= $chartTrend ?></script>
<script type="application/json" id="chartStatus"><?= $chartStatus ?></script>
<script type="application/json" id="chartPayments"><?= $chartPayments ?></script>

<?php require __DIR__ . '/includes/footer.php'; ?>
