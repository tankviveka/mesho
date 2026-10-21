f<?php
/**
 * Payment Reports - payment status, modes and monthly settlements.
 *
 * Payment status is stored SEPARATELY from order status; totals below are
 * sums over the `payments` table only (one row per order, so no double counting).
 */

require_once __DIR__ . '/includes/init.php';
require_login();

$filters = list_filters();
[$where, $params] = order_where($filters, 'o', 'p');

$totals = dashboard_totals([
    'q' => $filters['q'], 'status' => $filters['status'], 'pay' => $filters['pay'],
    'month' => $filters['month'], 'year' => $filters['year'],
    'per' => $filters['per'], 'page' => 1,
]);

$years = available_years();
$chartYear = $filters['year'] ?: (int) ($years[0] ?? (int) date('Y'));

/* ---------------- Monthly payment buckets for the chart ---------------- */
$monthRows = db_all(
    "SELECT MONTH(o.order_date) AS m,
            COALESCE(SUM(CASE WHEN p.payment_status = 'Received' THEN p.amount END), 0) AS received,
            COALESCE(SUM(CASE WHEN p.payment_status = 'Pending'  THEN p.amount END), 0) AS pending_amt,
            COALESCE(SUM(CASE WHEN p.payment_status = 'Refunded' THEN p.amount END), 0) AS refunded
       FROM orders o
       LEFT JOIN payments p ON p.order_id = o.id
      WHERE YEAR(o.order_date) = ?"
    . ($filters['month'] ? ' AND MONTH(o.order_date) = ?' : '')
    . " GROUP BY MONTH(o.order_date) ORDER BY m",
    $filters['month']
        ? [$chartYear, $filters['month']]
        : [$chartYear]
);

$byMonth = array_fill(1, 12, ['received' => 0.0, 'pending' => 0.0, 'refunded' => 0.0]);
foreach ($monthRows as $r) {
    $m = (int) $r['m'];
    if ($m >= 1 && $m <= 12) {
        $byMonth[$m] = [
            'received'  => (float) $r['received'],
            'pending'   => (float) $r['pending_amt'],
            'refunded'  => (float) $r['refunded'],
        ];
    }
}

$labels = [];
$receivedSeries = [];
$pendingSeries = [];
$refundedSeries = [];
for ($m = 1; $m <= 12; $m++) {
    $labels[] = month_name($m);
    $receivedSeries[] = round($byMonth[$m]['received'], 2);
    $pendingSeries[] = round($byMonth[$m]['pending'], 2);
    $refundedSeries[] = round($byMonth[$m]['refunded'], 2);
}

/* ------------------- Payment mode breakdown (received) ----------------- */
// Re-use the same WHERE clause, but append it with AND (it starts with " WHERE ").
$extraWhere = $where !== '' ? (' AND ' . ltrim(substr($where, 7))) : '';

$modeRows = db_all(
    "SELECT p.payment_mode, COUNT(*) AS cnt, COALESCE(SUM(p.amount), 0) AS amount
       FROM orders o
       JOIN payments p ON p.order_id = o.id
      WHERE p.payment_status = 'Received'"
    . $extraWhere
    . " GROUP BY p.payment_mode ORDER BY amount DESC",
    $params
);

/* --------------------- Payments list with pagination ------------------- */
$countSql = "SELECT COUNT(*) FROM orders o LEFT JOIN payments p ON p.order_id = o.id" . $where;
$total = (int) db_var($countSql, $params);
$perPage = (int) $filters['per'];
$pages = (int) max(1, ceil($total / $perPage));
$page = min(max(1, (int) $filters['page']), $pages);
$offset = ($page - 1) * $perPage;

$paymentRows = db_all(
    "SELECT o.id, o.order_number, o.order_date, o.customer_name, o.city, o.order_status,
            o.order_amount, p.payment_status, p.payment_mode, p.payment_ref,
            p.amount, p.payment_date
       FROM orders o
       LEFT JOIN payments p ON p.order_id = o.id"
    . $where
    . " ORDER BY o.order_date DESC, o.id DESC"
    . " LIMIT " . (int) $perPage . " OFFSET " . (int) $offset,
    $params
);

$json = static fn(array $cfg): string => json_encode(
    $cfg,
    JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);

$chartPayments = $json([
    'type' => 'bar',
    'data' => [
        'labels' => $labels,
        'datasets' => [
            [
                'label' => 'Received',
                'msFormat' => 'inr',
                'data' => $receivedSeries,
                'backgroundColor' => '#16A34A',
                'borderRadius' => 5,
                'maxBarThickness' => 22,
            ],
            [
                'label' => 'Pending',
                'msFormat' => 'inr',
                'data' => $pendingSeries,
                'backgroundColor' => '#F59E0B',
                'borderRadius' => 5,
                'maxBarThickness' => 22,
            ],
            [
                'label' => 'Refunded',
                'msFormat' => 'inr',
                'data' => $refundedSeries,
                'backgroundColor' => '#0EA5E9',
                'borderRadius' => 5,
                'maxBarThickness' => 22,
            ],
        ],
    ],
    'options' => [
        'interaction' => ['mode' => 'index', 'intersect' => false],
        'plugins' => ['legend' => ['position' => 'top', 'align' => 'end']],
        'scales' => [
            'x' => ['stacked' => true, 'grid' => ['display' => false]],
            'y' => ['stacked' => true, 'beginAtZero' => true, 'ticks' => ['msFormat' => 'inr']],
        ],
    ],
]);

$pageTitle = 'Payment Reports';
$active = 'payments';
$hasCharts = true;
require __DIR__ . '/includes/header.php';
?>

<?= demo_banner() ?>

<?php /* --------------------------- KPI cards --------------------------- */ ?>
<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3">
        <div class="kpi-card">
            <div class="kpi-top"><span class="kpi-icon t-pink"><i class="bi bi-wallet2"></i></span></div>
            <div class="kpi-label">Total Payment</div>
            <div class="kpi-value sm"><?= inr($totals['total_payment'], false) ?></div>
            <div class="kpi-sub">Gross value of non-cancelled orders</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="kpi-card">
            <div class="kpi-top"><span class="kpi-icon t-green"><i class="bi bi-check-circle"></i></span></div>
            <div class="kpi-label">Received Payment</div>
            <div class="kpi-value sm"><?= inr($totals['received_payment'], false) ?></div>
            <div class="kpi-sub">Settled to the seller account</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="kpi-card">
            <div class="kpi-top"><span class="kpi-icon t-amber"><i class="bi bi-clock-history"></i></span></div>
            <div class="kpi-label">Pending Payment</div>
            <div class="kpi-value sm"><?= inr($totals['pending_payment'], false) ?></div>
            <div class="kpi-sub">Not settled yet</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="kpi-card">
            <div class="kpi-top"><span class="kpi-icon t-cyan"><i class="bi bi-arrow-return-left"></i></span></div>
            <div class="kpi-label">Refunded Payment</div>
            <div class="kpi-value sm"><?= inr($totals['refunded_payment'], false) ?></div>
            <div class="kpi-sub">Returned orders only</div>
        </div>
    </div>
</div>

<?php /* ---------------------------- Filters ---------------------------- */ ?>
<form class="filter-bar mb-3" method="get" action="payment-reports.php">
    <div class="row g-3 align-items-end">
        <div class="col-12 col-lg-4">
            <label class="form-label" for="q">Search</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input class="form-control" type="search" id="q" name="q" maxlength="100"
                       value="<?= e($filters['q']) ?>" placeholder="Order no, customer, payment reference..." data-autosubmit>
            </div>
        </div>
        <div class="col-6 col-lg-2">
            <label class="form-label" for="month">Month</label>
            <select class="form-select" id="month" name="month" data-autosubmit>
                <option value="">All months</option>
                <?php foreach (available_months() as $m): ?>
                    <option value="<?= $m ?>" <?= $filters['month'] === $m ? 'selected' : '' ?>><?= e(month_name($m)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-lg-2">
            <label class="form-label" for="year">Year</label>
            <select class="form-select" id="year" name="year" data-autosubmit>
                <option value="">All years</option>
                <?php foreach ($years as $y): ?>
                    <option value="<?= $y ?>" <?= $filters['year'] === $y ? 'selected' : '' ?>><?= $y ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-lg-2">
            <label class="form-label" for="pay">Payment Status</label>
            <select class="form-select" id="pay" name="pay" data-autosubmit>
                <option value="">All</option>
                <?php foreach (PAYMENT_STATUSES as $s): ?>
                    <option value="<?= e($s) ?>" <?= $filters['pay'] === $s ? 'selected' : '' ?>><?= e($s) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-lg-2">
            <label class="form-label" for="per">Per page</label>
            <select class="form-select" id="per" name="per" data-autosubmit>
                <?php foreach (ITEMS_PER_PAGE_OPTIONS as $n): ?>
                    <option value="<?= $n ?>" <?= $filters['per'] === $n ? 'selected' : '' ?>><?= $n ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12">
            <div class="d-flex flex-wrap gap-2">
                <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-funnel me-1"></i>Apply filters</button>
                <a class="btn btn-light btn-sm" href="payment-reports.php"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset</a>
                <button class="btn btn-light btn-sm" type="button" data-print><i class="bi bi-printer me-1"></i>Print</button>
            </div>
        </div>
    </div>
</form>

<?php /* ----------------------------- Chart ------------------------------ */ ?>
<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-cash-stack me-2 text-primary"></i>Monthly Payment Settlement</span>
                <span class="section-sub">Year <?= $chartYear ?></span>
            </div>
            <div class="card-body">
                <div class="chart-box lg"><canvas data-chart-config="chartPayments"></canvas></div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-credit-card me-2 text-primary"></i>Received by Mode</div>
            <div class="card-body">
                <?php if (!$modeRows): ?>
                    <div class="empty-state"><i class="bi bi-wallet2"></i>No received payments for this filter.</div>
                <?php else: ?>
                    <?php $modeTotal = 0; foreach ($modeRows as $m): $modeTotal += (float) $m['amount']; ?>
                        <div class="stat-row">
                            <span class="label"><?= e($m['payment_mode']) ?>
                                <br><small><?= number_format((int) $m['cnt']) ?> settlements</small></span>
                            <span class="value"><?= inr($m['amount']) ?></span>
                        </div>
                    <?php endforeach; ?>
                    <div class="stat-row">
                        <span class="label"><strong>Total received</strong></span>
                        <span class="value"><?= inr($modeTotal) ?></span>
                    </div>
                    <div class="small text-muted mt-2">
                        Totals come from the <code>payments</code> table only. Order status
                        (<em>Delivered / Returned</em>) is tracked separately in <code>orders</code>.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php /* --------------------------- Payments table ------------------------ */ ?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="bi bi-list-check me-2 text-primary"></i>Payment Records</span>
        <span class="section-sub"><?= e(showing_text($total, $page, $perPage)) ?></span>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover table-striped align-middle">
                <thead>
                    <tr>
                        <th>Payment Ref</th>
                        <th>Order No</th>
                        <th>Customer</th>
                        <th>Order Date</th>
                        <th>Payment Date</th>
                        <th>Mode</th>
                        <th class="text-end">Order Amount</th>
                        <th class="text-end">Payment Amount</th>
                        <th>Order Status</th>
                        <th>Payment Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$paymentRows): ?>
                        <tr><td colspan="10">
                            <div class="empty-state"><i class="bi bi-inbox"></i>
                                <strong>No payment records match your filters.</strong></div>
                        </td></tr>
                    <?php else: ?>
                        <?php foreach ($paymentRows as $row): ?>
                            <tr>
                                <td><span class="order-no"><?= e($row['payment_ref'] ?? '-') ?></span></td>
                                <td><span class="cell-strong"><?= e($row['order_number']) ?></span></td>
                                <td>
                                    <span class="cell-strong"><?= e($row['customer_name']) ?></span><br>
                                    <span class="cell-muted"><?= e($row['city']) ?></span>
                                </td>
                                <td class="cell-muted"><?= date_fmt($row['order_date']) ?></td>
                                <td class="cell-muted"><?= $row['payment_date'] ? date_fmt($row['payment_date']) : '&mdash;' ?></td>
                                <td class="cell-muted"><?= e($row['payment_mode'] ?? '-') ?></td>
                                <td class="text-end cell-muted"><?= inr($row['order_amount']) ?></td>
                                <td class="text-end cell-strong"><?= inr($row['amount'] ?? 0) ?></td>
                                <td><?= order_status_badge((string) $row['order_status']) ?></td>
                                <td><?= payment_status_badge((string) ($row['payment_status'] ?? 'Pending')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="table-footer">
            <span class="showing"><?= e(showing_text($total, $page, $perPage)) ?></span>
            <?= pagination($total, $filters) ?>
        </div>
    </div>
</div>

<script type="application/json" id="chartPayments"><?= $chartPayments ?></script>

<?php require __DIR__ . '/includes/footer.php'; ?>
