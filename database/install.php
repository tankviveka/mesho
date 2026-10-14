<?php
/**
 * =============================================================================
 *  Meesho Seller Dashboard - DEMO : database installer / reseeder
 * =============================================================================
 *
 *  What it does
 *  ------------
 *   1. Creates the `mesho_demo` MySQL database (if missing).
 *   2. Creates the demo tables: users, products, orders, payments, settings.
 *   3. Inserts realistic GENERATED sample data (orders across the last 24
 *      months, products, customers and payment settlements).
 *
 *  ⚠ DEMO ONLY ⚠
 *  This file does NOT talk to Meesho in any way. Every row it writes is
 *  invented sample data for demonstration purposes.
 *
 *  Usage
 *  -----
 *    Web  : http://localhost/mesho/database/install.php
 *    CLI  : php database/install.php            (install / refresh, keeps data)
 *           php database/install.php --drop     (drop & rebuild everything)
 *
 * =============================================================================
 */

declare(strict_types=1);

/* ------------------------------------------------------------------ */
/* Configuration                                                       */
/* ------------------------------------------------------------------ */

const MS_DB_HOST = '127.0.0.1';
const MS_DB_PORT = '3306';
const MS_DB_NAME = 'mesho_demo';
const MS_DB_USER = 'root';
const MS_DB_PASS = '';
const MS_CHARSET = 'utf8mb4';

/* ------------------------------------------------------------------ */
/* Schema                                                              */
/* ------------------------------------------------------------------ */

/**
 * @return string[]
 */
function ms_schema(): array
{
    return [
        "CREATE TABLE IF NOT EXISTS users (
            id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
            name          VARCHAR(80)  NOT NULL,
            username      VARCHAR(60)  NOT NULL,
            email         VARCHAR(120) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            role          VARCHAR(30)  NOT NULL DEFAULT 'Seller',
            created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_users_username (username),
            UNIQUE KEY uq_users_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS products (
            id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
            sku        VARCHAR(40)  NOT NULL,
            name       VARCHAR(120) NOT NULL,
            category   VARCHAR(60)  NOT NULL,
            price      DECIMAL(10,2) NOT NULL,
            stock      INT UNSIGNED NOT NULL DEFAULT 0,
            created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_products_sku (sku),
            KEY idx_products_category (category)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS orders (
            id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
            order_number    VARCHAR(30)  NOT NULL,
            product_id      INT UNSIGNED NOT NULL,
            product_name    VARCHAR(120) NOT NULL,
            category        VARCHAR(60)  NOT NULL,
            customer_name   VARCHAR(80)  NOT NULL,
            city            VARCHAR(50)  NOT NULL,
            state           VARCHAR(50)  NOT NULL,
            pincode         VARCHAR(10)  NOT NULL,
            qty             TINYINT UNSIGNED NOT NULL DEFAULT 1,
            unit_price      DECIMAL(10,2) NOT NULL,
            order_amount    DECIMAL(10,2) NOT NULL,
            order_date      DATE NOT NULL,
            delivery_date   DATE NULL,
            /* ONE status per order - this is what stops double counting */
            order_status    ENUM('Pending','Delivered','Returned','Cancelled') NOT NULL DEFAULT 'Pending',
            courier         VARCHAR(40) NULL,
            tracking_number VARCHAR(40) NULL,
            created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_orders_number (order_number),
            KEY idx_orders_date (order_date),
            KEY idx_orders_status (order_status),
            KEY idx_orders_customer (customer_name),
            KEY idx_orders_product (product_id),
            CONSTRAINT fk_orders_product FOREIGN KEY (product_id)
                REFERENCES products (id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        /* UNIQUE order_id => exactly ONE payment row per order.
           Joining orders->payments therefore can never duplicate a row. */
        "CREATE TABLE IF NOT EXISTS payments (
            id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id       INT UNSIGNED NOT NULL,
            amount         DECIMAL(10,2) NOT NULL,
            payment_status ENUM('Received','Pending','Refunded','Cancelled') NOT NULL DEFAULT 'Pending',
            payment_mode   VARCHAR(30)  NOT NULL DEFAULT 'UPI',
            payment_date   DATE NULL,
            payment_ref    VARCHAR(40)  NOT NULL,
            created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_payments_order (order_id),
            UNIQUE KEY uq_payments_ref (payment_ref),
            KEY idx_payments_status (payment_status),
            KEY idx_payments_date (payment_date),
            CONSTRAINT fk_payments_order FOREIGN KEY (order_id)
                REFERENCES orders (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS settings (
            skey  VARCHAR(60)  NOT NULL,
            svalue TEXT NOT NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                             ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (skey)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];
}

/* ------------------------------------------------------------------ */
/* Sample data definitions                                             */
/* ------------------------------------------------------------------ */

/** @return array<int, array{name:string,category:string,price:int}> */
function ms_products(): array
{
    return [
        ['name' => 'Chanderi Silk Printed Saree',        'category' => 'Sarees',        'price' => 899],
        ['name' => 'Banarasi Art Silk Saree',            'category' => 'Sarees',        'price' => 1249],
        ['name' => 'Cotton Linen Saree with Blouse',     'category' => 'Sarees',        'price' => 749],
        ['name' => 'Georgette Embroidered Saree',        'category' => 'Sarees',        'price' => 1099],
        ['name' => 'Chiffon Floral Print Saree',         'category' => 'Sarees',        'price' => 699],
        ['name' => 'Anarkali Kurti Rayon',               'category' => 'Kurtis',        'price' => 449],
        ['name' => 'Cotton Straight Kurti',              'category' => 'Kurtis',        'price' => 399],
        ['name' => 'Printed Rayon Palazzo Set',          'category' => 'Kurtis',        'price' => 599],
        ['name' => 'Embroidered Kurti with Dupatta',     'category' => 'Kurtis',        'price' => 799],
        ['name' => 'A-Line Cotton Kurti',                'category' => 'Kurtis',        'price' => 429],
        ['name' => 'Women Round Neck Top',               'category' => 'Tops',          'price' => 299],
        ['name' => 'Stylish Off-Shoulder Top',           'category' => 'Tops',          'price' => 379],
        ['name' => 'Checked Casual Shirt Top',           'category' => 'Tops',          'price' => 349],
        ['name' => 'Solid Ribbed Knit Top',              'category' => 'Tops',          'price' => 279],
        ['name' => 'Silicone Mobile Cover - iPhone',     'category' => 'Mobile Covers', 'price' => 149],
        ['name' => 'Printed Back Cover - Samsung',       'category' => 'Mobile Covers', 'price' => 129],
        ['name' => 'Shockproof Mobile Cover',            'category' => 'Mobile Covers', 'price' => 199],
        ['name' => 'Designer Mobile Cover with Strap',   'category' => 'Mobile Covers', 'price' => 229],
        ['name' => 'Artificial Gold Plated Necklace Set','category' => 'Jewellery',     'price' => 349],
        ['name' => 'Kundan Earring Combo',               'category' => 'Jewellery',     'price' => 249],
        ['name' => 'Oxidised Jhumka Earrings',           'category' => 'Jewellery',     'price' => 199],
        ['name' => 'Meenakari Bridal Choker Set',        'category' => 'Jewellery',     'price' => 549],
        ['name' => 'Kids Cotton Printed Frock',          'category' => 'Kids Wear',     'price' => 329],
        ['name' => 'Boys Cotton Shirt & Shorts Set',     'category' => 'Kids Wear',     'price' => 449],
        ['name' => 'Kids Casual Sneakers',               'category' => 'Kids Wear',     'price' => 499],
        ['name' => 'Baby Winter Jacket',                 'category' => 'Kids Wear',     'price' => 599],
        ['name' => 'Women Block Heel Sandals',           'category' => 'Footwear',      'price' => 549],
        ['name' => 'Canvas Slip-On Shoes',               'category' => 'Footwear',      'price' => 469],
        ['name' => 'Flat Comfort Flats',                 'category' => 'Footwear',      'price' => 349],
        ['name' => 'Running Sports Shoes',               'category' => 'Footwear',      'price' => 799],
        ['name' => 'Pu Leather Handbag',                 'category' => 'Bags',          'price' => 649],
        ['name' => 'Canvas Travel Duffel Bag',           'category' => 'Bags',          'price' => 749],
        ['name' => 'Compact Zip-Around Wallet',          'category' => 'Bags',          'price' => 299],
        ['name' => 'Macrame Wall Hanging',               'category' => 'Home Decor',    'price' => 399],
        ['name' => 'LED Fairy String Lights',            'category' => 'Home Decor',    'price' => 249],
        ['name' => 'Ceramic Planter Set of 3',           'category' => 'Home Decor',    'price' => 549],
        ['name' => 'Non-Stick Kadai 24cm',               'category' => 'Kitchen',       'price' => 699],
        ['name' => 'Steel Lunch Box 3 Container',        'category' => 'Kitchen',       'price' => 549],
        ['name' => 'Borosilicate Glass Set of 6',        'category' => 'Kitchen',       'price' => 449],
        ['name' => 'Face Serum Vitamin C 30ml',          'category' => 'Beauty',        'price' => 349],
        ['name' => 'Matte Lipstick Combo Pack of 4',     'category' => 'Beauty',        'price' => 399],
        ['name' => 'Hair Repair Oil 200ml',              'category' => 'Beauty',        'price' => 299],
    ];
}

/** @return array<int, array{city:string,state:string,pin:string}> */
function ms_cities(): array
{
    return [
        ['city' => 'Mumbai', 'state' => 'Maharashtra', 'pin' => '400001'],
        ['city' => 'Pune', 'state' => 'Maharashtra', 'pin' => '411001'],
        ['city' => 'Nagpur', 'state' => 'Maharashtra', 'pin' => '440001'],
        ['city' => 'Thane', 'state' => 'Maharashtra', 'pin' => '400601'],
        ['city' => 'New Delhi', 'state' => 'Delhi', 'pin' => '110001'],
        ['city' => 'Noida', 'state' => 'Uttar Pradesh', 'pin' => '201301'],
        ['city' => 'Ghaziabad', 'state' => 'Uttar Pradesh', 'pin' => '201001'],
        ['city' => 'Lucknow', 'state' => 'Uttar Pradesh', 'pin' => '226001'],
        ['city' => 'Kanpur', 'state' => 'Uttar Pradesh', 'pin' => '208001'],
        ['city' => 'Varanasi', 'state' => 'Uttar Pradesh', 'pin' => '221001'],
        ['city' => 'Bengaluru', 'state' => 'Karnataka', 'pin' => '560001'],
        ['city' => 'Mysuru', 'state' => 'Karnataka', 'pin' => '570001'],
        ['city' => 'Hubballi', 'state' => 'Karnataka', 'pin' => '580020'],
        ['city' => 'Hyderabad', 'state' => 'Telangana', 'pin' => '500001'],
        ['city' => 'Warangal', 'state' => 'Telangana', 'pin' => '506001'],
        ['city' => 'Chennai', 'state' => 'Tamil Nadu', 'pin' => '600001'],
        ['city' => 'Coimbatore', 'state' => 'Tamil Nadu', 'pin' => '641001'],
        ['city' => 'Madurai', 'state' => 'Tamil Nadu', 'pin' => '625001'],
        ['city' => 'Kolkata', 'state' => 'West Bengal', 'pin' => '700001'],
        ['city' => 'Howrah', 'state' => 'West Bengal', 'pin' => '711101'],
        ['city' => 'Siliguri', 'state' => 'West Bengal', 'pin' => '734001'],
        ['city' => 'Ahmedabad', 'state' => 'Gujarat', 'pin' => '380001'],
        ['city' => 'Surat', 'state' => 'Gujarat', 'pin' => '395001'],
        ['city' => 'Vadodara', 'state' => 'Gujarat', 'pin' => '390001'],
        ['city' => 'Rajkot', 'state' => 'Gujarat', 'pin' => '360001'],
        ['city' => 'Jaipur', 'state' => 'Rajasthan', 'pin' => '302001'],
        ['city' => 'Jodhpur', 'state' => 'Rajasthan', 'pin' => '342001'],
        ['city' => 'Udaipur', 'state' => 'Rajasthan', 'pin' => '313001'],
        ['city' => 'Indore', 'state' => 'Madhya Pradesh', 'pin' => '452001'],
        ['city' => 'Bhopal', 'state' => 'Madhya Pradesh', 'pin' => '462001'],
        ['city' => 'Gwalior', 'state' => 'Madhya Pradesh', 'pin' => '474001'],
        ['city' => 'Patna', 'state' => 'Bihar', 'pin' => '800001'],
        ['city' => 'Gaya', 'state' => 'Bihar', 'pin' => '823001'],
        ['city' => 'Ranchi', 'state' => 'Jharkhand', 'pin' => '834001'],
        ['city' => 'Raipur', 'state' => 'Chhattisgarh', 'pin' => '492001'],
        ['city' => 'Bhubaneswar', 'state' => 'Odisha', 'pin' => '751001'],
        ['city' => 'Cuttack', 'state' => 'Odisha', 'pin' => '753001'],
        ['city' => 'Kochi', 'state' => 'Kerala', 'pin' => '682001'],
        ['city' => 'Thiruvananthapuram', 'state' => 'Kerala', 'pin' => '695001'],
        ['city' => 'Chandigarh', 'state' => 'Punjab', 'pin' => '160001'],
        ['city' => 'Amritsar', 'state' => 'Punjab', 'pin' => '143001'],
        ['city' => 'Ludhiana', 'state' => 'Punjab', 'pin' => '141001'],
        ['city' => 'Guwahati', 'state' => 'Assam', 'pin' => '781001'],
        ['city' => 'Dehradun', 'state' => 'Uttarakhand', 'pin' => '248001'],
        ['city' => 'Visakhapatnam', 'state' => 'Andhra Pradesh', 'pin' => '530001'],
        ['city' => 'Vijayawada', 'state' => 'Andhra Pradesh', 'pin' => '520001'],
    ];
}

/** @return string[] */
function ms_first_names(): array
{
    return [
        'Aarav', 'Vivaan', 'Aditya', 'Arjun', 'Sai', 'Krishna', 'Ishaan', 'Rohan',
        'Ananya', 'Diya', 'Priya', 'Sneha', 'Pooja', 'Neha', 'Riya', 'Kavya',
        'Meera', 'Aisha', 'Tanvi', 'Sanjana', 'Divya', 'Swati', 'Payal', 'Ritika',
        'Rahul', 'Amit', 'Vikram', 'Karan', 'Suresh', 'Mahesh', 'Nitin', 'Pankaj',
        'Sunita', 'Rekha', 'Kiran', 'Manisha', 'Deepa', 'Shalini', 'Anjali', 'Pallavi',
        'Ramesh', 'Sathish', 'Girish', 'Harish', 'Mukesh', 'Dinesh', 'Yogesh', 'Sagar',
        'Farhan', 'Imran', 'Zoya', 'Sana', 'Ayesha', 'Nasreen', 'Rajesh', 'Suman',
        'Vaishnavi', 'Shruti', 'Bhavna', 'Chetan', 'Gaurav', 'Nikhil', 'Pranav', 'Tejas',
    ];
}

/** @return string[] */
function ms_last_names(): array
{
    return [
        'Sharma', 'Verma', 'Patel', 'Reddy', 'Iyer', 'Nair', 'Gupta', 'Mehta',
        'Singh', 'Kumar', 'Das', 'Banerjee', 'Chatterjee', 'Joshi', 'Desai',
        'Kulkarni', 'Shetty', 'Rao', 'Chauhan', 'Yadav', 'Mishra', 'Pandey',
        'Trivedi', 'Shah', 'Bhatt', 'Kapoor', 'Malhotra', 'Chopra', 'Saxena',
        'Agarwal', 'Goyal', 'Soni', 'Gandhi', 'Thakur', 'Rawat', 'Naidu',
    ];
}

/** @return string[] */
function ms_couriers(): array
{
    return ['Delhivery', 'Blue Dart', 'Ekart Logix', 'Shadowfax', 'Xpressbees', 'India Post'];
}

/* ------------------------------------------------------------------ */
/* Installer                                                           */
/* ------------------------------------------------------------------ */

/** Create a connection WITHOUT selecting a database. */
function ms_server(): PDO
{
    $dsn = 'mysql:host=' . MS_DB_HOST . ';port=' . MS_DB_PORT . ';charset=' . MS_CHARSET;
    return new PDO($dsn, MS_DB_USER, MS_DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

/** Create the database (idempotent). */
function ms_create_database(): PDO
{
    $pdo = ms_server();
    $pdo->exec(
        'CREATE DATABASE IF NOT EXISTS `' . MS_DB_NAME . '` '
        . 'CHARACTER SET ' . MS_CHARSET . ' COLLATE ' . MS_CHARSET . '_unicode_ci'
    );
    $dsn = 'mysql:host=' . MS_DB_HOST . ';port=' . MS_DB_PORT . ';dbname=' . MS_DB_NAME
         . ';charset=' . MS_CHARSET;
    return new PDO($dsn, MS_DB_USER, MS_DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

/** Drop every demo table (only the ones this project owns). */
function ms_drop_tables(PDO $pdo): void
{
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach (['payments', 'orders', 'products', 'settings', 'users'] as $table) {
        $pdo->exec('DROP TABLE IF EXISTS `' . $table . '`');
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
}

/** Create tables + default settings rows. */
function ms_install_schema(PDO $pdo): void
{
    foreach (ms_schema() as $statement) {
        $pdo->exec($statement);
    }

    $defaults = [
        'store_name'        => 'Demo Fashion Hub',
        'owner_name'        => 'Demo Seller',
        'store_email'       => 'demo.seller@example.com',
        'store_phone'       => '+91 98765 43210',
        'store_city'        => 'Indore',
        'store_state'       => 'Madhya Pradesh',
        'gst_no'            => '23AAAAA0000A1Z5',
        'default_per_page'  => '10',
        'show_demo_banner'  => '1',
        'compact_tables'    => '0',
    ];
    $stmt = $pdo->prepare(
        'INSERT INTO settings (skey, svalue) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)'
    );
    foreach ($defaults as $key => $value) {
        $stmt->execute([$key, $value]);
    }
}

/** Demo login accounts (password hashes generated at install time). */
function ms_seed_users(PDO $pdo): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO users (name, username, email, password_hash, role)
         VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash)'
    );

    $stmt->execute(['Demo Admin', 'admin', 'admin@demo.local', password_hash('admin123', PASSWORD_DEFAULT), 'Administrator']);
    $stmt->execute(['Demo Seller', 'seller', 'seller@demo.local', password_hash('seller123', PASSWORD_DEFAULT), 'Seller']);
}

/**
 * Generate and insert ~$orderCount orders + their 1:1 payment rows.
 * Everything is deterministic (seeded RNG) so reseeding gives stable data.
 */
function ms_seed_demo_data(PDO $pdo, int $orderCount = 1400): array
{
    mt_srand(20240101);

    /* ---------- products ---------- */
    $products = ms_products();
    $pdo->exec('DELETE FROM products');
    $stmt = $pdo->prepare(
        'INSERT INTO products (sku, name, category, price, stock) VALUES (?, ?, ?, ?, ?)'
    );
    foreach ($products as $i => $p) {
        $stmt->execute([
            'SKU-' . str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
            $p['name'],
            $p['category'],
            $p['price'],
            mt_rand(20, 400),
        ]);
    }

    $productRows = $pdo->query('SELECT id, name, category, price FROM products ORDER BY id')->fetchAll();
    $cities = ms_cities();
    $first = ms_first_names();
    $last = ms_last_names();
    $couriers = ms_couriers();
    $modes = ['UPI', 'UPI', 'UPI', 'Bank Transfer', 'Wallet', 'Card'];

    $today = new DateTimeImmutable('today');
    $start = new DateTimeImmutable('-24 months');
    $start = $start->modify('first day of this month');

    // Month buckets from $start up to today (inclusive).
    $months = [];
    $weights = [];
    $totalWeight = 0;
    $cursor = $start;
    while ($cursor <= $today) {
        $months[] = $cursor;
        $w = (count($months)) ** 2; // quadratic growth: recent months are busier
        $weights[] = $w;
        $totalWeight += $w;
        $cursor = $cursor->modify('first day of next month');
    }
    $monthCount = count($months);

    $pdo->beginTransaction();

    $orderStmt = $pdo->prepare(
        'INSERT INTO orders
            (order_number, product_id, product_name, category, customer_name, city, state,
             pincode, qty, unit_price, order_amount, order_date, delivery_date,
             order_status, courier, tracking_number)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    $orderNumbers = [];

    for ($i = 0; $i < $orderCount; $i++) {
        // Weighted month pick: recent months get more orders (organic growth).
        $pick = mt_rand(1, $totalWeight);
        $monthIndex = 0;
        foreach ($weights as $w) {
            $pick -= $w;
            if ($pick <= 0) {
                break;
            }
            $monthIndex++;
        }
        $monthStart = $months[min($monthIndex, $monthCount - 1)];
        $daysInMonth = (int) $monthStart->format('t');
        $maxDay = (int) $monthStart->format('d');
        if ($monthStart->format('Y-m') === $today->format('Y-m')) {
            $maxDay = (int) $today->format('d');
        }
        $maxDay = max(1, min($daysInMonth, $maxDay));
        $day = mt_rand(1, $maxDay);
        $orderDate = $monthStart->setDate(
            (int) $monthStart->format('Y'),
            (int) $monthStart->format('n'),
            $day
        );

        $product = $productRows[mt_rand(0, count($productRows) - 1)];
        $qty = mt_rand(1, 3);
        $unitPrice = (float) $product['price'] + (mt_rand(0, 20) - 10);
        if ($unitPrice < 99) {
            $unitPrice = 99.0;
        }
        $unitPrice = round($unitPrice / 10) * 10 - 1;
        if ($unitPrice < 99) {
            $unitPrice = 99.0;
        }
        $amount = round($unitPrice * $qty, 2);

        $cityRow = $cities[mt_rand(0, count($cities) - 1)];
        $customer = $first[mt_rand(0, count($first) - 1)] . ' ' . $last[mt_rand(0, count($last) - 1)];

        $age = (int) $orderDate->diff($today)->days;

        /* ---- order status: exactly ONE value ---- */
        if ($age <= 4) {
            $r = mt_rand(1, 100);
            $status = $r <= 78 ? 'Pending' : ($r <= 90 ? 'Cancelled' : 'Delivered');
        } else {
            $r = mt_rand(1, 100);
            if ($r <= 72) {
                $status = 'Delivered';
            } elseif ($r <= 82) {
                $status = 'Returned';
            } elseif ($r <= 87) {
                $status = 'Cancelled';
            } else {
                $status = 'Pending'; // unusually slow / lost shipment
            }
        }

        $deliveryDate = null;
        $courier = null;
        $tracking = null;
        if (in_array($status, ['Delivered', 'Returned'], true)) {
            $courier = $couriers[mt_rand(0, count($couriers) - 1)];
            $eta = mt_rand(2, 7);
            $deliveryDate = $orderDate->modify('+' . $eta . ' days');
            if ($deliveryDate > $today) {
                $deliveryDate = $today;
            }
            $tracking = strtoupper(substr($courier, 0, 2)) . mt_rand(100000000, 999999999) . 'IN';
        } elseif ($status === 'Pending') {
            // Shipped but not yet delivered - still in transit.
            $courier = $couriers[mt_rand(0, count($couriers) - 1)];
        }

        $orderNumber = 'DEMO-' . (1000000 + $i + 1);

        $orderStmt->execute([
            $orderNumber,
            (int) $product['id'],
            $product['name'],
            $product['category'],
            $customer,
            $cityRow['city'],
            $cityRow['state'],
            $cityRow['pin'],
            $qty,
            $unitPrice,
            $amount,
            $orderDate->format('Y-m-d'),
            $deliveryDate?->format('Y-m-d'),
            $status,
            $courier,
            $tracking,
        ]);

        $orderNumbers[] = [$orderNumber, $amount, $status, $orderDate->format('Y-m-d')];
    }

    /* ---------- payments: one row per order (UNIQUE order_id) ---------- */
    $paymentStmt = $pdo->prepare(
        'INSERT INTO payments (order_id, amount, payment_status, payment_mode, payment_date, payment_ref)
         VALUES (?, ?, ?, ?, ?, ?)'
    );

    $idMap = [];
    $stmt = $pdo->query('SELECT id, order_number FROM orders');
    foreach ($stmt as $row) {
        $idMap[$row['order_number']] = (int) $row['id'];
    }

    foreach ($orderNumbers as $index => [$orderNumber, $amount, $status, $orderDate]) {
        $orderId = $idMap[$orderNumber] ?? null;
        if ($orderId === null) {
            continue;
        }

        $mode = $modes[mt_rand(0, count($modes) - 1)];
        $ref = null;
        $paymentDate = null;

        switch ($status) {
            case 'Delivered':
                $r = mt_rand(1, 100);
                $paymentStatus = $r <= 84 ? 'Received' : 'Pending';
                if ($paymentStatus === 'Received') {
                    $ref = 'SETT-' . (7000000 + $index + 1);
                    $paymentDate = date('Y-m-d', strtotime($orderDate . ' +' . mt_rand(4, 12) . ' days'));
                    if ($paymentDate > $today->format('Y-m-d')) {
                        $paymentDate = $today->format('Y-m-d');
                    }
                } else {
                    $ref = 'HOLD-' . (7000000 + $index + 1);
                    $paymentDate = null;
                }
                break;

            case 'Returned':
                // Money came back to the customer - refund, never "received".
                $paymentStatus = 'Refunded';
                $ref = 'RFND-' . (7000000 + $index + 1);
                $paymentDate = date('Y-m-d', strtotime($orderDate . ' +' . mt_rand(8, 20) . ' days'));
                if ($paymentDate > $today->format('Y-m-d')) {
                    $paymentDate = $today->format('Y-m-d');
                }
                break;

            case 'Cancelled':
                $paymentStatus = 'Cancelled';
                $ref = 'VOID-' . (7000000 + $index + 1);
                $paymentDate = null;
                break;

            default: // Pending
                $paymentStatus = 'Pending';
                $ref = 'OPEN-' . (7000000 + $index + 1);
                $paymentDate = null;
                break;
        }

        $paymentStmt->execute([
            $orderId,
            $amount,
            $paymentStatus,
            $mode,
            $paymentDate,
            $ref,
        ]);
    }

    $pdo->commit();

    return [
        'products' => count($productRows),
        'orders' => count($orderNumbers),
        'payments' => count($orderNumbers),
    ];
}

/** Full install: schema + users + demo rows. */
function ms_full_install(bool $drop = false): array
{
    $pdo = ms_create_database();
    if ($drop) {
        ms_drop_tables($pdo);
    }
    ms_install_schema($pdo);
    ms_seed_users($pdo);

    // Wipe transactional demo rows before reseeding.
    $pdo->exec('DELETE FROM payments');
    $pdo->exec('DELETE FROM orders');
    $pdo->exec('DELETE FROM products');

    $counts = ms_seed_demo_data($pdo);
    return $counts + ['database' => MS_DB_NAME];
}

/* ------------------------------------------------------------------ */
/* Status report                                                       */
/* ------------------------------------------------------------------ */

function ms_status(): array
{
    $out = [
        'database_exists' => false,
        'tables' => [],
        'counts' => [],
        'error' => null,
    ];

    try {
        $server = ms_server();
        $row = $server->query(
            'SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '
            . $server->quote(MS_DB_NAME)
        )->fetch();
        $out['database_exists'] = $row !== false;
    } catch (Throwable $e) {
        $out['error'] = $e->getMessage();
        return $out;
    }

    if (!$out['database_exists']) {
        return $out;
    }

    try {
        $pdo = ms_create_database();
        foreach (['users', 'products', 'orders', 'payments', 'settings'] as $table) {
            $exists = $pdo->query(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
                  WHERE TABLE_SCHEMA = '" . MS_DB_NAME . "' AND TABLE_NAME = '" . $table . "'"
            )->fetchColumn();
            $out['tables'][$table] = (int) $exists > 0;
            if ((int) $exists > 0) {
                $out['counts'][$table] = (int) $pdo->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
            }
        }
    } catch (Throwable $e) {
        $out['error'] = $e->getMessage();
    }

    return $out;
}

/* ------------------------------------------------------------------ */
/* Entry points                                                         */
/* ------------------------------------------------------------------ */

function ms_run_install(bool $drop): array
{
    return ms_full_install($drop);
}

/** CLI: php database/install.php [--drop] */
if (PHP_SAPI === 'cli') {
    $drop = in_array('--drop', $argv ?? [], true);
    try {
        $counts = ms_run_install($drop);
        echo "DEMO MODE - sample data generated locally, no Meesho connection involved.\n";
        echo 'Database  : ' . $counts['database'] . "\n";
        echo 'Products  : ' . $counts['products'] . "\n";
        echo 'Orders    : ' . $counts['orders'] . "\n";
        echo 'Payments  : ' . $counts['payments'] . "\n";
        echo "Login     : admin / admin123\n";
        exit(0);
    } catch (Throwable $e) {
        fwrite(STDERR, 'Install failed: ' . $e->getMessage() . "\n");
        exit(1);
    }
}

/** Web UI - only when this file is the requested script. */
$msIsDirect = isset($_SERVER['SCRIPT_FILENAME'])
    && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__;

if (!$msIsDirect) {
    return; // safe to include elsewhere as a library of functions
}

$flash = null;

if ($_SERVER['REQUEST_METHOD'] === 'GET'
    && ($_GET['action'] ?? '') === 'install'
    && ($_GET['confirm'] ?? '') === '1'
) {
    $drop = isset($_GET['drop']) && $_GET['drop'] === '1';
    try {
        $counts = ms_run_install($drop);
        $flash = ['ok', sprintf(
            'Demo database rebuilt successfully: %d products, %d orders, %d payment records inserted.',
            $counts['products'],
            $counts['orders'],
            $counts['payments']
        )];
    } catch (Throwable $e) {
        $flash = ['bad', 'Installation failed: ' . $e->getMessage()];
    }
}

$status = ms_status();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Demo Database Installer | Meesho Seller Dashboard</title>
    <link rel="stylesheet" href="../assets/vendor/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/vendor/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="container py-5" style="max-width:920px;">
    <div class="auth-card mx-auto" style="max-width:920px;">
        <div class="auth-head">
            <span class="demo-ribbon"><span class="demo-dot"></span> DEMO MODE</span>
            <div class="logo"><i class="bi bi-database"></i></div>
            <h2>Demo Database Installer</h2>
            <p>Creates the schema and inserts generated sample data into local MySQL</p>
        </div>

        <div class="auth-body">
            <?php if ($flash): ?>
                <div class="alert alert-<?= $flash[0] === 'ok' ? 'success' : 'danger' ?>">
                    <?= htmlspecialchars($flash[1]) ?>
                </div>
            <?php endif; ?>

            <div class="demo-banner mb-3">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <div><strong>DEMO MODE:</strong> this tool only ever writes invented sample rows to
                    <code><?= htmlspecialchars(MS_DB_NAME) ?></code>. It has no connection to Meesho
                    or any real seller account.</div>
            </div>

            <h6 class="divider-title">Environment</h6>
            <div class="stat-row"><span class="label">PHP</span><span class="value"><?= htmlspecialchars(PHP_VERSION) ?></span></div>
            <div class="stat-row"><span class="label">MySQL host</span><span class="value"><?= htmlspecialchars(MS_DB_HOST . ':' . MS_DB_PORT) ?></span></div>
            <div class="stat-row"><span class="label">Database</span><span class="value"><?= htmlspecialchars(MS_DB_NAME) ?></span></div>
            <div class="stat-row">
                <span class="label">Database exists</span>
                <span class="value"><?= $status['database_exists'] ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-danger">No</span>' ?></span>
            </div>

            <?php if ($status['error']): ?>
                <div class="alert alert-warning mt-3 py-2 small">
                    <?= htmlspecialchars($status['error']) ?> &mdash; start MySQL in the XAMPP control panel.
                </div>
            <?php endif; ?>

            <?php if ($status['tables']): ?>
                <h6 class="divider-title mt-4">Tables</h6>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead><tr><th>Table</th><th class="text-end">Rows</th></tr></thead>
                        <tbody>
                        <?php foreach ($status['tables'] as $table => $exists): ?>
                            <tr>
                                <td><?= htmlspecialchars($table) ?>
                                    <?= $exists ? '<span class="badge bg-success">ready</span>' : '<span class="badge bg-secondary">missing</span>' ?>
                                </td>
                                <td class="text-end"><?= isset($status['counts'][$table]) ? number_format($status['counts'][$table]) : '-' ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <div class="d-flex flex-wrap gap-2 mt-4">
                <a class="btn btn-primary" href="?action=install&amp;confirm=1">
                    <i class="bi bi-arrow-repeat me-1"></i>Install / Refresh demo data
                </a>
                <a class="btn btn-outline-danger" href="?action=install&amp;confirm=1&amp;drop=1"
                   data-confirm="Drop and rebuild every demo table? All current demo rows will be lost.">
                    <i class="bi bi-trash me-1"></i>Drop &amp; rebuild
                </a>
                <a class="btn btn-light" href="../login.php"><i class="bi bi-box-arrow-in-right me-1"></i>Go to login</a>
            </div>

            <div class="small text-muted mt-4">
                <strong>Command line:</strong><br>
                <code>php database/install.php</code> &nbsp;(refresh data)<br>
                <code>php database/install.php --drop</code> &nbsp;(rebuild everything)
            </div>
        </div>
    </div>
</div>

<script src="../assets/vendor/bootstrap.bundle.min.js"></script>
<script src="../assets/js/app.js"></script>
</body>
</html>
