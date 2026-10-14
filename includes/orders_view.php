<?php
/**
 * Shared order-list renderer used by:
 *   orders.php, pending-orders.php, delivered-orders.php,
 *   returned-orders.php, cancelled-orders.php
 *
 * The page decides which order status it owns (or none for "All Orders"),
 * then calls render_orders_view().
 */

declare(strict_types=1);

/** Status tabs (each one is a separate page) + counts from the database. */
function order_status_tabs(string $currentStatus): string
{
    $counts = [
        'All' => 0,
        'Pending' => 0,
        'Delivered' => 0,
        'Returned' => 0,
        'Cancelled' => 0,
    ];
    foreach (db_all('SELECT order_status, COUNT(*) AS c FROM orders GROUP BY order_status') as $row) {
        $counts[(string) $row['order_status']] = (int) $row['c'];
        $counts['All'] += (int) $row['c'];
    }

    $links = [
        'All'       => 'orders.php',
        'Pending'   => 'pending-orders.php',
        'Delivered' => 'delivered-orders.php',
        'Returned'  => 'returned-orders.php',
        'Cancelled' => 'cancelled-orders.php',
    ];

    $icons = [
        'All'       => 'bag-check',
        'Pending'   => 'hourglass-split',
        'Delivered' => 'check2-circle',
        'Returned'  => 'arrow-counterclockwise',
        'Cancelled' => 'x-octagon',
    ];

    $html = '<div class="status-tabs mb-3">';

    // Keep every other filter (search, month, year, payment, per-page) when the
    // user hops between status tabs - only the status itself changes.
    $carry = $_GET;
    unset($carry['status'], $carry['page']);

    foreach ($links as $label => $url) {
        $active = ($label === $currentStatus) || ($currentStatus === '' && $label === 'All');
        $qs = http_build_query($carry);
        $href = $url . ($qs !== '' ? '?' . $qs : '');
        $html .= '<a class="status-tab' . ($active ? ' active' : '') . '" href="' . e($href) . '">'
            . '<i class="bi bi-' . $icons[$label] . '"></i>' . e($label) . ' Orders'
            . '<span class="count">' . number_format($counts[$label] ?? 0) . '</span></a>';
    }
    $html .= '</div>';
    return $html;
}

/**
 * Render the full list page body.
 *
 * @param array{status:string} $opts  '' = show every status.
 */
function render_orders_view(array $opts = []): void
{
    $presetStatus = (string) ($opts['status'] ?? '');

    $filters = list_filters();
    if ($presetStatus !== '') {
        $filters['status'] = $presetStatus; // locked by the page
    }

    [$where, $params] = order_where($filters, 'o', 'p');

    $countSql = "SELECT COUNT(*) FROM orders o LEFT JOIN payments p ON p.order_id = o.id" . $where;
    $total = (int) db_var($countSql, $params);

    $perPage = (int) $filters['per'];
    $pages = (int) max(1, ceil($total / $perPage));
    $page = min(max(1, (int) $filters['page']), $pages);
    $offset = ($page - 1) * $perPage;

    // LIMIT/OFFSET are cast to integers in PHP, then inlined as literal numbers.
    $rows = db_all(
        "SELECT o.id, o.order_number, o.order_date, o.delivery_date, o.customer_name,
                o.city, o.state, o.product_name, o.category, o.qty, o.unit_price,
                o.order_amount, o.order_status, o.courier, o.tracking_number,
                p.payment_status, p.payment_mode, p.payment_ref, p.amount AS paid_amount,
                p.payment_date
           FROM orders o
           LEFT JOIN payments p ON p.order_id = o.id"
        . $where
        . " ORDER BY o.order_date DESC, o.id DESC"
        . " LIMIT " . (int) $perPage . " OFFSET " . (int) $offset,
        $params
    );

    // Sub-totals for the current filter (independent of the current page).
    $sums = db_row(
        "SELECT COUNT(*) AS cnt,
                COALESCE(SUM(CASE WHEN o.order_status <> 'Cancelled' THEN o.order_amount END), 0) AS gross,
                COALESCE(SUM(CASE WHEN p.payment_status = 'Received' THEN p.amount END), 0) AS received,
                COALESCE(SUM(CASE WHEN p.payment_status = 'Pending' THEN p.amount END), 0) AS pending
           FROM orders o
           LEFT JOIN payments p ON p.order_id = o.id"
        . $where,
        $params
    ) ?? [];

    $years = available_years();
    $activeYear = $filters['year'];

    $resetUrl = 'orders.php';
    if ($presetStatus !== '') {
        $map = [
            'Pending'   => 'pending-orders.php',
            'Delivered' => 'delivered-orders.php',
            'Returned'  => 'returned-orders.php',
            'Cancelled' => 'cancelled-orders.php',
        ];
        $resetUrl = $map[$presetStatus] ?? 'orders.php';
    }
    ?>

    <?= demo_banner() ?>

    <?= order_status_tabs($presetStatus) ?>

    <?php /* -------------------------- Filters --------------------------- */ ?>
    <form class="filter-bar mb-3" method="get" action="<?= e($_SERVER['PHP_SELF']) ?>">
        <div class="row g-3 align-items-end">
            <div class="col-12 col-lg-4">
                <label class="form-label" for="q">Search</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input class="form-control" type="search" id="q" name="q"
                           value="<?= e($filters['q']) ?>" maxlength="100"
                           placeholder="Order no, customer, product, city, payment ref..."
                           data-autosubmit>
                </div>
            </div>

            <div class="col-6 col-lg-2">
                <label class="form-label" for="month">Month</label>
                <select class="form-select" id="month" name="month" data-autosubmit>
                    <option value="">All months</option>
                    <?php foreach (available_months() as $m): ?>
                        <option value="<?= $m ?>" <?= $filters['month'] === $m ? 'selected' : '' ?>>
                            <?= e(month_name($m)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-6 col-lg-2">
                <label class="form-label" for="year">Year</label>
                <select class="form-select" id="year" name="year" data-autosubmit>
                    <option value="">All years</option>
                    <?php foreach ($years as $y): ?>
                        <option value="<?= $y ?>" <?= $activeYear === $y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if ($presetStatus === ''): ?>
                <div class="col-6 col-lg-2">
                    <label class="form-label" for="status">Order Status</label>
                    <select class="form-select" id="status" name="status" data-autosubmit>
                        <option value="">All statuses</option>
                        <?php foreach (ORDER_STATUSES as $s): ?>
                            <option value="<?= e($s) ?>" <?= $filters['status'] === $s ? 'selected' : '' ?>>
                                <?= e($s) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php else: ?>
                <div class="col-6 col-lg-2">
                    <label class="form-label">Order Status</label>
                    <input type="text" class="form-control" value="<?= e($presetStatus) ?>" disabled>
                    <input type="hidden" name="status" value="<?= e($presetStatus) ?>">
                </div>
            <?php endif; ?>

            <div class="col-6 col-lg-2">
                <label class="form-label" for="pay">Payment Status</label>
                <select class="form-select" id="pay" name="pay" data-autosubmit>
                    <option value="">All payments</option>
                    <?php foreach (PAYMENT_STATUSES as $s): ?>
                        <option value="<?= e($s) ?>" <?= $filters['pay'] === $s ? 'selected' : '' ?>>
                            <?= e($s) ?>
                        </option>
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

            <div class="col-12 col-lg-4">
                <div class="d-flex flex-wrap gap-2">
                    <button class="btn btn-primary btn-sm" type="submit">
                        <i class="bi bi-funnel me-1"></i>Apply filters
                    </button>
                    <a class="btn btn-light btn-sm" href="<?= e($resetUrl) ?>">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
                    </a>
                    <button class="btn btn-light btn-sm" type="button" data-print>
                        <i class="bi bi-printer me-1"></i>Print
                    </button>
                </div>
            </div>
        </div>
    </form>

    <?php /* ------------------------- Summary row -------------------------- */ ?>
    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3">
            <div class="kpi-card">
                <div class="kpi-label">Orders found</div>
                <div class="kpi-value"><?= number_format((float) ($sums['cnt'] ?? 0)) ?></div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="kpi-card">
                <div class="kpi-label">Gross value</div>
                <div class="kpi-value sm"><?= inr($sums['gross'] ?? 0, false) ?></div>
                <div class="kpi-sub">Excludes cancelled orders</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="kpi-card">
                <div class="kpi-label">Received</div>
                <div class="kpi-value sm"><?= inr($sums['received'] ?? 0, false) ?></div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="kpi-card">
                <div class="kpi-label">Pending payment</div>
                <div class="kpi-value sm"><?= inr($sums['pending'] ?? 0, false) ?></div>
            </div>
        </div>
    </div>

    <?php /* ---------------------------- Table ----------------------------- */ ?>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span>
                <i class="bi bi-table me-2 text-primary"></i>
                <?= $presetStatus !== '' ? e($presetStatus) . ' Orders' : 'All Orders' ?>
                <span class="section-sub ms-2"><?= e(showing_text($total, $page, $perPage)) ?></span>
            </span>
            <span class="section-sub">
                <i class="bi bi-shield-check me-1"></i>Prepared statements in use
            </span>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle">
                    <thead>
                        <tr>
                            <th>Order No</th>
                            <th>Product</th>
                            <th>Customer</th>
                            <th class="text-center">Qty</th>
                            <th class="text-end">Amount</th>
                            <th>Order Date</th>
                            <th>Delivered On</th>
                            <th>Order Status</th>
                            <th>Payment</th>
                            <th>Courier</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$rows): ?>
                            <tr>
                                <td colspan="10">
                                    <div class="empty-state">
                                        <i class="bi bi-inbox"></i>
                                        <strong>No orders match your filters.</strong>
                                        <div class="small mt-1">Try clearing the search box or choosing a different month / year.</div>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($rows as $i => $row): ?>
                                <tr>
                                    <td>
                                        <span class="order-no"><?= e($row['order_number']) ?></span><br>
                                        <span class="cell-muted">#<?= (int) $row['id'] ?></span>
                                    </td>
                                    <td>
                                        <div class="product-cell">
                                            <span class="product-thumb t<?= ($i % 6) + 1 ?>"><i class="bi bi-box"></i></span>
                                            <span>
                                                <span class="cell-strong truncate-2"><?= e($row['product_name']) ?></span><br>
                                                <span class="cell-muted"><?= e($row['category']) ?></span>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="cell-strong"><?= e($row['customer_name']) ?></span><br>
                                        <span class="cell-muted"><?= e($row['city']) ?>, <?= e($row['state']) ?></span>
                                    </td>
                                    <td class="text-center"><?= (int) $row['qty'] ?></td>
                                    <td class="text-end">
                                        <span class="cell-strong"><?= inr($row['order_amount']) ?></span><br>
                                        <span class="cell-muted"><?= inr($row['unit_price'], false) ?> &times; <?= (int) $row['qty'] ?></span>
                                    </td>
                                    <td class="cell-muted"><?= date_fmt($row['order_date']) ?></td>
                                    <td class="cell-muted">
                                        <?= $row['delivery_date'] ? date_fmt($row['delivery_date']) : '&mdash;' ?>
                                    </td>
                                    <td><?= order_status_badge((string) $row['order_status']) ?></td>
                                    <td>
                                        <?= payment_status_badge((string) ($row['payment_status'] ?? 'Pending')) ?>
                                        <br><span class="cell-muted"><?= e($row['payment_mode'] ?? '-') ?></span>
                                    </td>
                                    <td>
                                        <span class="cell-muted"><?= e($row['courier'] ?? '-') ?></span><br>
                                        <span class="cell-muted"><?= e($row['tracking_number'] ?? '') ?></span>
                                    </td>
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

    <?php
}
