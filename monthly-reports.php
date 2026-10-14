<?php
/**
 * Monthly Reports - month-by-month performance for a chosen year,
 * plus a year-over-year comparison. Includes a CSV export.
 */

require_once __DIR__ . '/includes/init.php';
require_login();

$years = available_years();
if (!$years) {
    $years = [(int) date('Y')];
}

$year = (int) get_param('year');
if (!in_array($year, $years, true)) {
    $year = (int) $years[0];
}

/* --------------------------- Core aggregates --------------------------- */

function monthly_report_rows(int $year): array
{
    $rows = db_all(
        "SELECT MONTH(o.order_date) AS m,
                COUNT(o.id) AS orders,
                COALESCE(SUM(o.order_status = 'Pending'), 0)   AS pending,
                COALESCE(SUM(o.order_status = 'Delivered'), 0) AS delivered,
                COALESCE(SUM(o.order_status = 'Returned'), 0)  AS returned,
                COALESCE(SUM(o.order_status = 'Cancelled'), 0) AS cancelled,
                COALESCE(SUM(CASE WHEN o.order_status <> 'Cancelled' THEN o.order_amount END), 0) AS revenue,
                COALESCE(SUM(CASE WHEN p.payment_status = 'Received' THEN p.amount END), 0) AS received,
                COALESCE(SUM(CASE WHEN p.payment_status = 'Pending'  THEN p.amount END), 0) AS pay_pending
           FROM orders o
           LEFT JOIN payments p ON p.order_id = o.id
          WHERE YEAR(o.order_date) = ?
       GROUP BY MONTH(o.order_date)
       ORDER BY m",
        [$year]
    );

    $byMonth = [];
    foreach ($rows as $r) {
        $byMonth[(int) $r['m']] = $r;
    }

    $out = [];
    for ($m = 1; $m <= 12; $m++) {
        $r = $byMonth[$m] ?? null;
        $out[$m] = [
            'month'       => $m,
            'orders'      => $r ? (int) $r['orders'] : 0,
            'pending'     => $r ? (int) $r['pending'] : 0,
            'delivered'   => $r ? (int) $r['delivered'] : 0,
            'returned'    => $r ? (int) $r['returned'] : 0,
            'cancelled'   => $r ? (int) $r['cancelled'] : 0,
            'revenue'     => $r ? (float) $r['revenue'] : 0.0,
            'received'    => $r ? (float) $r['received'] : 0.0,
            'pay_pending' => $r ? (float) $r['pay_pending'] : 0.0,
        ];
    }
    return $out;
}

$months = monthly_report_rows($year);
$yearTotals = yearly_totals($year);

/* ---------------------------- CSV export ------------------------------- */
if (get_param('export') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="demo-monthly-report-' . $year . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    fwrite($out, "DEMO MODE - generated sample data, not real Meesho data\n");
    fputcsv($out, ['Monthly Report', 'Year: ' . $year, 'Generated: ' . date('d M Y H:i')]);
    fputcsv($out, []);
    fputcsv($out, [
        'Month', 'Orders', 'Pending', 'Delivered', 'Returned', 'Cancelled',
        'Revenue (INR)', 'Received (INR)', 'Pending Payment (INR)',
    ]);
    foreach ($months as $row) {
        fputcsv($out, [
            month_name($row['month']),
            $row['orders'],
            $row['pending'],
            $row['delivered'],
            $row['returned'],
            $row['cancelled'],
            number_format($row['revenue'], 2, '.', ''),
            number_format($row['received'], 2, '.', ''),
            number_format($row['pay_pending'], 2, '.', ''),
        ]);
    }
    fputcsv($out, [
        'TOTAL',
        (int) $yearTotals['total_orders'],
        (int) $yearTotals['pending_orders'],
        (int) $yearTotals['delivered_orders'],
        (int) $yearTotals['returned_orders'],
        (int) $yearTotals['cancelled_orders'],
        number_format((float) $yearTotals['total_payment'], 2, '.', ''),
        number_format((float) $yearTotals['received_payment'], 2, '.', ''),
        number_format((float) $yearTotals['pending_payment'], 2, '.', ''),
    ]);
    fclose($out);
    exit;
}

/* ------------------------ Year comparison table ------------------------ */
$yearly = [];
foreach ($years as $y) {
    $t = yearly_totals($y);
    $yearly[] = [
        'year' => $y,
        'orders' => (int) $t['total_orders'],
        'delivered' => (int) $t['delivered_orders'],
        'returned' => (int) $t['returned_orders'],
        'revenue' => (float) $t['total_payment'],
        'received' => (float) $t['received_payment'],
    ];
}
rsort($yearly);

/* ------------------------------- Chart --------------------------------- */
$json = static fn(array $cfg): string => json_encode(
    $cfg,
    JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);

$labels = [];
$orderSeries = [];
$revenueSeries = [];
$receivedSeries = [];
foreach ($months as $row) {
    $labels[] = month_name($row['month']);
    $orderSeries[] = $row['orders'];
    $revenueSeries[] = round($row['revenue'], 2);
    $receivedSeries[] = round($row['received'], 2);
}

$chartMonthly = $json([
    'type' => 'bar',
    'data' => [
        'labels' => $labels,
        'datasets' => [
            [
                'label' => 'Orders',
                'data' => $orderSeries,
                'backgroundColor' => 'rgba(123,44,191,.8)',
                'borderRadius' => 5,
                'maxBarThickness' => 22,
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
                'tension' => .35,
                'borderWidth' => 2.5,
                'pointRadius' => 3,
                'yAxisID' => 'y1',
            ],
            [
                'type' => 'line',
                'label' => 'Received',
                'msFormat' => 'inr',
                'data' => $receivedSeries,
                'borderColor' => '#16A34A',
                'backgroundColor' => 'transparent',
                'borderDash' => [6, 4],
                'tension' => .35,
                'borderWidth' => 2,
                'pointRadius' => 2.5,
                'yAxisID' => 'y1',
            ],
        ],
    ],
    'options' => [
        'interaction' => ['mode' => 'index', 'intersect' => false],
        'plugins' => ['legend' => ['position' => 'top', 'align' => 'end']],
        'scales' => [
            'x'  => ['grid' => ['display' => false]],
            'y'  => ['beginAtZero' => true, 'position' => 'left',
                      'ticks' => ['precision' => 0]],
            'y1' => ['beginAtZero' => true, 'position' => 'right',
                      'grid' => ['drawOnChartArea' => false],
                      'ticks' => ['msFormat' => 'inr']],
        ],
    ],
]);

$pageTitle = 'Monthly Reports';
$active = 'monthly';
$hasCharts = true;
require __DIR__ . '/includes/header.php';

$grand = [
    'orders' => 0, 'pending' => 0, 'delivered' => 0, 'returned' => 0,
    'cancelled' => 0, 'revenue' => 0.0, 'received' => 0.0, 'pay_pending' => 0.0,
];
foreach ($months as $row) {
    foreach ($grand as $k => $v) {
        $grand[$k] = $v + (float) $row[$k];
    }
}
?>

<?= demo_banner() ?>

<?php /* ----------------------------- Filters ---------------------------- */ ?>
<form class="filter-bar mb-3" method="get" action="monthly-reports.php">
    <div class="row g-3 align-items-end">
        <div class="col-6 col-md-3 col-lg-2">
            <label class="form-label" for="year">Financial Year</label>
            <select class="form-select" id="year" name="year" data-autosubmit>
                <?php foreach ($years as $y): ?>
                    <option value="<?= $y ?>" <?= $year === $y ? 'selected' : '' ?>><?= $y ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-9 col-lg-10">
            <div class="d-flex flex-wrap gap-2">
                <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-eye me-1"></i>View report</button>
                <a class="btn btn-outline-primary btn-sm" href="monthly-reports.php?year=<?= $year ?>&export=csv">
                    <i class="bi bi-download me-1"></i>Download CSV
                </a>
                <a class="btn btn-light btn-sm" href="monthly-reports.php"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset</a>
                <button class="btn btn-light btn-sm" type="button" data-print><i class="bi bi-printer me-1"></i>Print</button>
            </div>
        </div>
    </div>
</form>

<?php /* ----------------------------- KPI cards -------------------------- */ ?>
<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3">
        <div class="kpi-card">
            <div class="kpi-top"><span class="kpi-icon"><i class="bi bi-bag-check"></i></span></div>
            <div class="kpi-label">Total Orders</div>
            <div class="kpi-value"><?= number_format((float) $yearTotals['total_orders']) ?></div>
            <div class="kpi-sub">Year <?= $year ?></div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="kpi-card">
            <div class="kpi-top"><span class="kpi-icon t-green"><i class="bi bi-check2-circle"></i></span></div>
            <div class="kpi-label">Delivered</div>
            <div class="kpi-value"><?= number_format((float) $yearTotals['delivered_orders']) ?></div>
            <div class="kpi-sub">Pending <?= number_format((float) $yearTotals['pending_orders']) ?></div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="kpi-card">
            <div class="kpi-top"><span class="kpi-icon t-cyan"><i class="bi bi-arrow-counterclockwise"></i></span></div>
            <div class="kpi-label">Returned / Cancelled</div>
            <div class="kpi-value sm">
                <?= number_format((float) $yearTotals['returned_orders']) ?> /
                <?= number_format((float) $yearTotals['cancelled_orders']) ?>
            </div>
            <div class="kpi-sub">Counted once each</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="kpi-card">
            <div class="kpi-top"><span class="kpi-icon t-pink"><i class="bi bi-cash-stack"></i></span></div>
            <div class="kpi-label">Revenue / Received</div>
            <div class="kpi-value sm">
                <?= inr($yearTotals['total_payment'], false) ?>
                <span class="fs-6 text-muted">/ <?= inr($yearTotals['received_payment'], false) ?></span>
            </div>
            <div class="kpi-sub">Pending <?= inr($yearTotals['pending_payment'], false) ?></div>
        </div>
    </div>
</div>

<?php /* ------------------------------ Chart ------------------------------ */ ?>
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-graph-up me-2 text-primary"></i>Orders, Revenue &amp; Received Payment</span>
        <span class="section-sub">Jan &ndash; Dec <?= $year ?></span>
    </div>
    <div class="card-body">
        <div class="chart-box lg"><canvas data-chart-config="chartMonthly"></canvas></div>
    </div>
</div>

<?php /* --------------------------- Month cells --------------------------- */ ?>
<div class="divider-title"><h2 class="section-title">Month Overview</h2></div>
<div class="month-grid mb-4">
    <?php foreach ($months as $row): ?>
        <div class="month-cell">
            <div class="m-name"><?= e(substr(month_name($row['month']), 0, 3)) ?></div>
            <div class="m-orders"><?= number_format($row['orders']) ?></div>
            <div class="m-sub">orders &middot; <?= number_format($row['delivered']) ?> delivered</div>
            <div class="m-sub"><strong><?= inr($row['revenue'], false) ?></strong></div>
        </div>
    <?php endforeach; ?>
</div>

<?php /* ------------------------- Monthly table --------------------------- */ ?>
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-table me-2 text-primary"></i>Monthly Breakdown &mdash; <?= $year ?></span>
        <span class="section-sub">Totals calculated by SQL (SUM/COUNT)</span>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover table-striped align-middle">
                <thead>
                    <tr>
                        <th>Month</th>
                        <th class="text-end">Orders</th>
                        <th class="text-end">Pending</th>
                        <th class="text-end">Delivered</th>
                        <th class="text-end">Returned</th>
                        <th class="text-end">Cancelled</th>
                        <th class="text-end">Revenue</th>
                        <th class="text-end">Received</th>
                        <th class="text-end">Pending Payment</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($months as $row): ?>
                        <?php $isEmpty = $row['orders'] === 0; ?>
                        <tr<?= $isEmpty ? ' class="table-light"' : '' ?>>
                            <td>
                                <span class="cell-strong"><?= e(month_name($row['month'])) ?></span>
                            </td>
                            <td class="text-end"><?= number_format($row['orders']) ?></td>
                            <td class="text-end"><?= number_format($row['pending']) ?></td>
                            <td class="text-end"><?= number_format($row['delivered']) ?></td>
                            <td class="text-end"><?= number_format($row['returned']) ?></td>
                            <td class="text-end"><?= number_format($row['cancelled']) ?></td>
                            <td class="text-end cell-strong"><?= inr($row['revenue']) ?></td>
                            <td class="text-end"><?= inr($row['received']) ?></td>
                            <td class="text-end"><?= inr($row['pay_pending']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="table-primary">
                        <td><strong>TOTAL <?= $year ?></strong></td>
                        <td class="text-end"><strong><?= number_format((float) $grand['orders']) ?></strong></td>
                        <td class="text-end"><strong><?= number_format((float) $grand['pending']) ?></strong></td>
                        <td class="text-end"><strong><?= number_format((float) $grand['delivered']) ?></strong></td>
                        <td class="text-end"><strong><?= number_format((float) $grand['returned']) ?></strong></td>
                        <td class="text-end"><strong><?= number_format((float) $grand['cancelled']) ?></strong></td>
                        <td class="text-end"><strong><?= inr($grand['revenue']) ?></strong></td>
                        <td class="text-end"><strong><?= inr($grand['received']) ?></strong></td>
                        <td class="text-end"><strong><?= inr($grand['pay_pending']) ?></strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div class="small text-muted mt-3">
            <i class="bi bi-info-circle me-1"></i>
            <strong>Total Orders</strong> = Pending + Delivered + Returned + Cancelled (each order holds exactly one status).
            <strong>Total Payment</strong> excludes cancelled orders, so returns and cancellations are never counted twice.
        </div>
    </div>
</div>

<?php /* ------------------------ Year comparison -------------------------- */ ?>
<div class="card mb-3">
    <div class="card-header"><i class="bi bi-calendar2-bar me-2 text-primary"></i>Year Comparison</div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Year</th>
                        <th class="text-end">Orders</th>
                        <th class="text-end">Delivered</th>
                        <th class="text-end">Returned</th>
                        <th class="text-end">Revenue</th>
                        <th class="text-end">Received</th>
                        <th class="text-end">Delivery Rate</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($yearly as $row): ?>
                        <tr<?= $row['year'] === $year ? ' class="table-primary"' : '' ?>>
                            <td>
                                <a class="cell-strong" href="monthly-reports.php?year=<?= (int) $row['year'] ?>">
                                    <?= (int) $row['year'] ?>
                                </a>
                            </td>
                            <td class="text-end"><?= number_format($row['orders']) ?></td>
                            <td class="text-end"><?= number_format($row['delivered']) ?></td>
                            <td class="text-end"><?= number_format($row['returned']) ?></td>
                            <td class="text-end cell-strong"><?= inr($row['revenue']) ?></td>
                            <td class="text-end"><?= inr($row['received']) ?></td>
                            <td class="text-end">
                                <?= number_format($row['orders'] > 0 ? ($row['delivered'] / $row['orders']) * 100 : 0, 1) ?>%
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script type="application/json" id="chartMonthly"><?= $chartMonthly ?></script>

<?php require __DIR__ . '/includes/footer.php'; ?>
