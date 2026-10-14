<?php
/**
 * Shared helper functions: formatting, dashboard totals, filters, pagination.
 *
 * -----------------------------------------------------------------------------
 * COUNTING RULES (kept deliberately simple so nothing is double counted):
 *
 *  1. `orders.order_status` holds EXACTLY ONE value per order:
 *        Pending | Delivered | Returned | Cancelled
 *     A returned order is NOT also counted as delivered, and a cancelled order
 *     is NOT counted anywhere else. Therefore:
 *        Total Orders = Pending + Delivered + Returned + Cancelled
 *
 *  2. `payments` has ONE row per order (unique index on order_id), with its own
 *     `payment_status`:
 *        Received | Pending | Refunded | Cancelled
 *     Order status and payment status are stored separately and never merged.
 *
 *  3. Money totals:
 *        Total Payment   = SUM(order_amount)  where order_status <> 'Cancelled'
 *        Received Payment= SUM(payment.amount) where payment_status = 'Received'
 *        Pending Payment = SUM(payment.amount) where payment_status = 'Pending'
 *        Refunded Payment= SUM(payment.amount) where payment_status = 'Refunded'
 *     Because payments is 1:1 with orders, the JOIN cannot inflate any total.
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

/* --------------------------------------------------------------------- */
/* Static vocabularies                                                    */
/* --------------------------------------------------------------------- */

/** Allowed order statuses - mutually exclusive. */
const ORDER_STATUSES = ['Pending', 'Delivered', 'Returned', 'Cancelled'];

/** Allowed payment statuses - mutually exclusive. */
const PAYMENT_STATUSES = ['Received', 'Pending', 'Refunded', 'Cancelled'];

/** Allowed payment modes. */
const PAYMENT_MODES = ['UPI', 'Bank Transfer', 'Wallet', 'Cash on Delivery', 'Card'];

/** Bootstrap badge class for each order status. */
function order_status_badge(string $status): string
{
    $map = [
        'Pending'   => 'bg-warning text-dark',
        'Delivered' => 'bg-success',
        'Returned'  => 'bg-info text-dark',
        'Cancelled' => 'bg-danger',
    ];
    $class = $map[$status] ?? 'bg-secondary';
    return '<span class="badge rounded-pill ' . $class . '">' . e($status) . '</span>';
}

/** Bootstrap badge class for each payment status. */
function payment_status_badge(string $status): string
{
    $map = [
        'Received' => 'bg-success',
        'Pending'  => 'bg-warning text-dark',
        'Refunded' => 'bg-info text-dark',
        'Cancelled'=> 'bg-secondary',
    ];
    $class = $map[$status] ?? 'bg-secondary';
    return '<span class="badge rounded-pill ' . $class . '">' . e($status) . '</span>';
}

/* --------------------------------------------------------------------- */
/* Formatting                                                             */
/* --------------------------------------------------------------------- */

/** Indian Rupee formatting, e.g. 1296781 => "₹12,96,781" / "₹12,96,781.00" */
function inr(mixed $amount, bool $decimals = true): string
{
    $value = (float) $amount;
    $negative = $value < 0;
    $abs = abs($value);

    // number_format WITHOUT a thousands separator, so we can group it ourselves.
    $raw = number_format($abs, $decimals ? 2 : 0, '.', '');
    $parts = explode('.', $raw);
    $int = $parts[0];

    if (strlen($int) > 3) {
        $last3 = substr($int, -3);
        $rest = substr($int, 0, -3);
        // Indian numbering: groups of two digits after the last three.
        $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
        $int = $rest . ',' . $last3;
    }

    $out = $int . (isset($parts[1]) ? '.' . $parts[1] : '');
    return ($negative ? '-₹' : '₹') . $out;
}

/** Short money for chart axes: ₹1.2L, ₹34.5K */
function inr_short(mixed $amount): string
{
    $v = (float) $amount;
    $abs = abs($v);
    if ($abs >= 10000000) {
        return '₹' . number_format($v / 10000000, 2) . 'Cr';
    }
    if ($abs >= 100000) {
        return '₹' . number_format($v / 100000, 2) . 'L';
    }
    if ($abs >= 1000) {
        return '₹' . number_format($v / 1000, 1) . 'K';
    }
    return '₹' . number_format($v, 0);
}

/** 2026-10-14 => 14 Oct 2026 */
function date_fmt(?string $date, string $format = 'd M Y'): string
{
    if (!$date) {
        return '-';
    }
    $ts = strtotime($date);
    return $ts ? date($format, $ts) : '-';
}

/** Month name helper: 10 => October */
function month_name(int $month): string
{
    $month = max(1, min(12, $month));
    return date('F', mktime(0, 0, 0, $month, 1));
}

/* --------------------------------------------------------------------- */
/* Settings (stored in the `settings` table)                              */
/* --------------------------------------------------------------------- */

function get_setting(string $key, string $default = ''): string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (db_all('SELECT skey, svalue FROM settings') as $row) {
            $cache[(string) $row['skey']] = (string) $row['svalue'];
        }
    }
    return $cache[$key] ?? $default;
}

function set_setting(string $key, string $value): void
{
    db_query(
        'INSERT INTO settings (skey, svalue) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)',
        [$key, $value]
    );
}

/** Default table page size (stored in settings, falls back to the constant). */
function default_per_page(): int
{
    try {
        $value = (int) get_setting('default_per_page', (string) ITEMS_PER_PAGE_DEFAULT);
    } catch (Throwable $e) {
        $value = ITEMS_PER_PAGE_DEFAULT;
    }
    return in_array($value, ITEMS_PER_PAGE_OPTIONS, true) ? $value : ITEMS_PER_PAGE_DEFAULT;
}

/**
 * The "DEMO MODE" banner shown at the top of data pages.
 * Can be switched off in Settings -> Dashboard Preferences.
 */
function demo_banner(): string
{
    try {
        $show = get_setting('show_demo_banner', '1') === '1';
    } catch (Throwable $e) {
        $show = true;
    }
    if (!$show) {
        return '';
    }
    return '<div class="demo-banner mb-3">'
        . '<i class="bi bi-exclamation-triangle-fill"></i>'
        . '<div><strong>DEMO MODE:</strong> every record shown on this page is generated sample data '
        . 'stored in your local MySQL database. This dashboard is <strong>not</strong> connected to '
        . 'Meesho and does not use any real seller data.</div>'
        . '</div>';
}

/* --------------------------------------------------------------------- */
/* Request helpers                                                        */
/* --------------------------------------------------------------------- */

/** Whitelisted GET value (single scalar, trimmed, length limited). */
function get_param(string $key, string $default = ''): string
{
    $value = $_GET[$key] ?? null;
    if (!is_string($value)) {
        return $default;
    }
    $value = trim($value);
    if (strlen($value) > 100) {
        $value = substr($value, 0, 100);
    }
    return $value;
}

/** Build a URL preserving the current query string. */
function with_query(array $overrides): string
{
    $params = $_GET;
    foreach ($overrides as $k => $v) {
        if ($v === null || $v === '') {
            unset($params[$k]);
        } else {
            $params[$k] = $v;
        }
    }
    $qs = http_build_query($params);
    return strtok($_SERVER['REQUEST_URI'] ?? '', '?') . ($qs !== '' ? '?' . $qs : '');
}

/**
 * Validate and normalise the standard list filters used by every page.
 *
 * Recognised query params:
 *   q       free text search (order no, customer, product, city)
 *   status  order status filter (validated against ORDER_STATUSES)
 *   pay     payment status filter (validated against PAYMENT_STATUSES)
 *   month   1-12
 *   year    4 digit year
 *   per     page size (validated against ITEMS_PER_PAGE_OPTIONS)
 *   page    1-based page number
 *
 * @return array<string, mixed>
 */
function list_filters(): array
{
    $status = get_param('status');
    if (!in_array($status, ORDER_STATUSES, true)) {
        $status = '';
    }

    $pay = get_param('pay');
    if (!in_array($pay, PAYMENT_STATUSES, true)) {
        $pay = '';
    }

    $month = (int) get_param('month');
    if ($month < 1 || $month > 12) {
        $month = 0;
    }

    $year = (int) get_param('year');
    if ($year < 2000 || $year > 2100) {
        $year = 0;
    }

    $per = (int) get_param('per', (string) default_per_page());
    if (!in_array($per, ITEMS_PER_PAGE_OPTIONS, true)) {
        $per = default_per_page();
    }

    $page = (int) get_param('page', '1');
    if ($page < 1) {
        $page = 1;
    }

    $q = get_param('q');
    // Strip characters that would only ever be typos/noise in a search box.
    $q = preg_replace('/[\x00-\x1F]/u', '', $q) ?? '';

    return [
        'q'      => $q,
        'status' => $status,
        'pay'    => $pay,
        'month'  => $month,
        'year'   => $year,
        'per'    => $per,
        'page'   => $page,
    ];
}

/**
 * Translate the filters above into a parameterised WHERE clause.
 *
 * NOTE: named placeholders (:status, :q1 ...) are used on purpose - the same
 * parameter list is reused by several queries on a page, and named parameters
 * work with real (non-emulated) prepared statements.
 *
 * @param  array<string, mixed> $filters
 * @param  string $o  table alias for `orders`
 * @param  string $p  table alias for `payments`
 * @return array{0:string,1:array<string,mixed>} [sql, params]
 */
function order_where(array $filters, string $o = 'o', string $p = 'p'): array
{
    $sql = [];
    $params = [];

    if (!empty($filters['status'])) {
        $sql[] = "$o.order_status = :status";
        $params['status'] = $filters['status'];
    }
    if (!empty($filters['pay'])) {
        $sql[] = "$p.payment_status = :pay";
        $params['pay'] = $filters['pay'];
    }
    if (!empty($filters['month'])) {
        $sql[] = "MONTH($o.order_date) = :month";
        $params['month'] = (int) $filters['month'];
    }
    if (!empty($filters['year'])) {
        $sql[] = "YEAR($o.order_date) = :year";
        $params['year'] = (int) $filters['year'];
    }
    if (!empty($filters['q'])) {
        $like = '%' . $filters['q'] . '%';
        $sql[] = "($o.order_number LIKE :q1 OR $o.customer_name LIKE :q2 OR $o.city LIKE :q3
                   OR $o.state LIKE :q4 OR $p.payment_ref LIKE :q5 OR $o.product_name LIKE :q6)";
        $params['q1'] = $like;
        $params['q2'] = $like;
        $params['q3'] = $like;
        $params['q4'] = $like;
        $params['q5'] = $like;
        $params['q6'] = $like;
    }

    $where = $sql ? (' WHERE ' . implode(' AND ', $sql)) : '';
    return [$where, $params];
}

/* --------------------------------------------------------------------- */
/* Aggregates - every KPI is calculated from database rows                */
/* --------------------------------------------------------------------- */

/**
 * Main KPI totals. See the counting rules at the top of this file.
 *
 * @param  array<string, mixed> $filters
 * @return array<string, float|int>
 */
function dashboard_totals(array $filters = []): array
{
    [$where, $params] = order_where($filters, 'o', 'p');

    $sql = "SELECT
                COUNT(o.id) AS total_orders,
                COALESCE(SUM(o.order_status = 'Pending'), 0)   AS pending_orders,
                COALESCE(SUM(o.order_status = 'Delivered'), 0) AS delivered_orders,
                COALESCE(SUM(o.order_status = 'Returned'), 0)  AS returned_orders,
                COALESCE(SUM(o.order_status = 'Cancelled'), 0) AS cancelled_orders,
                COALESCE(SUM(o.qty), 0)                        AS units_sold,
                /* Gross value of every billable (non-cancelled) order */
                COALESCE(SUM(CASE WHEN o.order_status <> 'Cancelled'
                                  THEN o.order_amount END), 0) AS total_payment,
                /* Monetary columns come from the payments table only */
                COALESCE(SUM(CASE WHEN p.payment_status = 'Received'
                                  THEN p.amount END), 0)       AS received_payment,
                COALESCE(SUM(CASE WHEN p.payment_status = 'Pending'
                                  THEN p.amount END), 0)       AS pending_payment,
                COALESCE(SUM(CASE WHEN p.payment_status = 'Refunded'
                                  THEN p.amount END), 0)       AS refunded_payment
             FROM orders o
             LEFT JOIN payments p ON p.order_id = o.id"
             . $where;

    $row = db_row($sql, $params) ?? [];

    $out = [];
    foreach ([
        'total_orders', 'pending_orders', 'delivered_orders', 'returned_orders',
        'cancelled_orders', 'units_sold', 'total_payment', 'received_payment',
        'pending_payment', 'refunded_payment',
    ] as $key) {
        $out[$key] = isset($row[$key]) ? (float) $row[$key] : 0.0;
    }
    $out['total_payment'] = round($out['total_payment'], 2);
    $out['received_payment'] = round($out['received_payment'], 2);
    $out['pending_payment'] = round($out['pending_payment'], 2);
    $out['refunded_payment'] = round($out['refunded_payment'], 2);

    // Order status breakdown (mutually exclusive, sums to total_orders).
    $out['status_split'] = [
        'Pending'   => (int) $out['pending_orders'],
        'Delivered' => (int) $out['delivered_orders'],
        'Returned'  => (int) $out['returned_orders'],
        'Cancelled' => (int) $out['cancelled_orders'],
    ];

    return $out;
}

/**
 * Orders + revenue grouped by month for a given year (1..12 buckets).
 *
 * @param  array<string, mixed> $filters
 * @return array<int, array<string, int|float>>
 */
function monthly_series(array $filters = []): array
{
    $year = (int) ($filters['year'] ?? 0);
    $months = array_fill(1, 12, ['orders' => 0, 'delivered' => 0, 'revenue' => 0.0, 'received' => 0.0]);
    if ($year <= 0) {
        return $months;
    }

    [$where, $params] = order_where($filters, 'o', 'p');

    $rows = db_all(
        "SELECT MONTH(o.order_date) AS m,
                COUNT(o.id) AS orders,
                COALESCE(SUM(o.order_status = 'Delivered'), 0) AS delivered,
                COALESCE(SUM(CASE WHEN o.order_status <> 'Cancelled' THEN o.order_amount END), 0) AS revenue,
                COALESCE(SUM(CASE WHEN p.payment_status = 'Received' THEN p.amount END), 0) AS received
           FROM orders o
           LEFT JOIN payments p ON p.order_id = o.id"
           . $where
           . " GROUP BY MONTH(o.order_date) ORDER BY m",
        $params
    );

    foreach ($rows as $row) {
        $m = (int) $row['m'];
        if ($m >= 1 && $m <= 12) {
            $months[$m] = [
                'orders'    => (int) $row['orders'],
                'delivered' => (int) $row['delivered'],
                'revenue'   => (float) $row['revenue'],
                'received'  => (float) $row['received'],
            ];
        }
    }

    return $months;
}

/**
 * Last N months of totals (used by dashboard charts).
 *
 * @return array<int, array{label:string, year:int, month:int, orders:int, revenue:float, received:float}>
 */
function last_months_series(int $count = 12): array
{
    $out = [];
    for ($i = $count - 1; $i >= 0; $i--) {
        $ts = strtotime(date('Y-m-01') . ' -' . $i . ' months');
        $year = (int) date('Y', $ts);
        $month = (int) date('n', $ts);
        $filters = ['year' => $year, 'month' => $month, 'status' => '', 'pay' => '', 'q' => ''];
        $totals = dashboard_totals($filters);
        $out[] = [
            'label'   => date('M y', $ts),
            'year'    => $year,
            'month'   => $month,
            'orders'  => (int) $totals['total_orders'],
            'revenue' => (float) $totals['total_payment'],
            'received'=> (float) $totals['received_payment'],
        ];
    }
    return $out;
}

/**
 * Per-year rollup used by the Monthly Reports page and its chart.
 *
 * @return array<int, array<string, float|int>>
 */
function yearly_totals(int $year): array
{
    return dashboard_totals([
        'year' => $year, 'month' => 0, 'status' => '', 'pay' => '', 'q' => '',
    ]);
}

/** Distinct years present in the orders table (newest first). */
function available_years(): array
{
    $rows = db_all(
        'SELECT DISTINCT YEAR(order_date) AS y FROM orders ORDER BY y DESC'
    );
    return array_map(static fn($r) => (int) $r['y'], $rows);
}

/** 1..12 months for the month dropdown. */
function available_months(): array
{
    return range(1, 12);
}

/* --------------------------------------------------------------------- */
/* Pagination                                                             */
/* --------------------------------------------------------------------- */

/**
 * Render a Bootstrap pagination control that keeps every existing filter.
 *
 * @param  array<string, mixed> $filters
 */
function pagination(int $total, array $filters): string
{
    $perPage = max(1, (int) $filters['per']);
    $page = max(1, (int) $filters['page']);
    $pages = (int) max(1, ceil($total / $perPage));
    if ($page > $pages) {
        $page = $pages;
    }
    if ($pages <= 1) {
        return '';
    }

    $link = static function (int $target, string $label, bool $disabled, bool $active = false) use ($filters): string {
        $cls = 'page-item' . ($disabled ? ' disabled' : '') . ($active ? ' active' : '');
        if ($disabled) {
            return '<li class="' . $cls . '"><span class="page-link">' . $label . '</span></li>';
        }
        $url = e(with_query(['page' => $target > 1 ? (string) $target : null]));
        return '<li class="' . $cls . '"><a class="page-link" href="' . $url . '">' . $label . '</a></li>';
    };

    $html = '<nav aria-label="Table pagination"><ul class="pagination mb-0">';
    $html .= $link($page - 1, '&laquo;', $page <= 1);

    $window = 2;
    $start = max(1, $page - $window);
    $end = min($pages, $page + $window);

    if ($start > 1) {
        $html .= $link(1, '1', false, $page === 1);
        if ($start > 2) {
            $html .= '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>';
        }
    }
    for ($i = $start; $i <= $end; $i++) {
        $html .= $link($i, (string) $i, false, $i === $page);
    }
    if ($end < $pages) {
        if ($end < $pages - 1) {
            $html .= '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>';
        }
        $html .= $link($pages, (string) $pages, false, $page === $pages);
    }

    $html .= $link($page + 1, '&raquo;', $page >= $pages);
    $html .= '</ul></nav>';
    return $html;
}

/** "Showing 11 - 20 of 137 results" */
function showing_text(int $total, int $page, int $perPage): string
{
    $pages = (int) max(1, ceil($total / $perPage));
    $page = min(max(1, $page), $pages);
    $from = $total === 0 ? 0 : ($page - 1) * $perPage + 1;
    $to = min($total, $page * $perPage);
    return sprintf('Showing %d - %d of %d results', $from, $to, $total);
}

/* --------------------------------------------------------------------- */
/* Flash messages                                                         */
/* --------------------------------------------------------------------- */

function flash_set(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function flash_take(): ?array
{
    if (!empty($_SESSION['flash']) && is_array($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}
