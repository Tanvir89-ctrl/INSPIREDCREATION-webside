<?php
// Shared setup for the public API (api/) and the admin panel (admin/).
// Works on PHP 7.4+ with MySQL on hosting, or SQLite for testing on your own PC.
declare(strict_types=1);

date_default_timezone_set('Asia/Dhaka');

// Never show PHP errors to visitors — they can reveal server folders. They are written to data/php-errors.log
// (blocked from the web). Put 'debug' => true in config.php only while fixing a problem.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
if (is_dir(__DIR__ . '/../data') && is_writable(__DIR__ . '/../data')) {
    ini_set('error_log', __DIR__ . '/../data/php-errors.log');
}

// Buffer output so stray bytes (e.g. a BOM added when config.php is edited in Notepad)
// can be discarded before sending JSON or files.
ob_start();

function ic_discard_output(): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
}

const IC_ROOT = __DIR__ . '/..';

// Order pipeline: New → Confirmed → Shipping → Waiting → Delivered / Partial / Return.
// Hold catches orders that missed shipping on the day they were placed.
const IC_ORDER_STATUSES = [
    'new'           => 'New',
    'confirmed'     => 'Confirmed',
    'hold'          => 'Hold',
    'fake'          => 'Fake order',
    'in_production' => 'In production',
    'shipping'      => 'Shipping',
    'waiting'       => 'Waiting',
    'delivered'     => 'Delivered',
    'partial'       => 'Partial',
    'returned'      => 'Return',
    'cancelled'     => 'Cancelled',
];

// Statuses where the order is finished and needs no more attention
const IC_CLOSED_STATUSES = ['delivered', 'partial', 'returned', 'cancelled'];

// Steadfast delivery_status → our status
const IC_STEADFAST_STATUS_MAP = [
    'in_review'                          => 'shipping',
    'pending'                            => 'waiting',
    'hold'                               => 'waiting',
    'delivered_approval_pending'         => 'delivered',
    'delivered'                          => 'delivered',
    'partial_delivered_approval_pending' => 'partial',
    'partial_delivered'                  => 'partial',
    'cancelled_approval_pending'         => 'returned',
    'cancelled'                          => 'returned',
];

const IC_SCHEMA_VERSION = 10;

// Products that start out customizable (engraved / branded pieces); the admin can change this per product
const IC_DEFAULT_CUSTOMIZABLE = ['corp-13', 'corp-14', 'corp-15', 'corp-16', 'key-18', 'box-01', 'frame-03'];

// Keys must match CATEGORY_LABELS in index.html
const IC_CATEGORIES = [
    'boxes'     => 'Personalized Gift Boxes',
    'wall'      => 'Wooden Wall Decor',
    'home'      => 'Handmade Home Accessories',
    'keepsakes' => 'Custom Keepsakes',
    'corporate' => 'Corporate Gifts',
];

function ic_config(): array
{
    static $config = null;
    if ($config === null) {
        $file = __DIR__ . '/config.php';
        if (!is_file($file)) {
            throw new RuntimeException('Missing api/config.php. Copy api/config.sample.php to api/config.php and fill in your database details.');
        }
        $config = require $file;
        if (!empty($config['debug'])) {
            ini_set('display_errors', '1');
        }
    }
    return $config;
}

function ic_db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }
    $db = ic_config()['db'];
    if ($db['driver'] === 'sqlite') {
        $dir = dirname($db['sqlite']);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $pdo = new PDO('sqlite:' . $db['sqlite']);
    } else {
        $pdo = new PDO("mysql:host={$db['host']};dbname={$db['name']};charset=utf8mb4", $db['user'], $db['pass']);
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $pdo;
}

function ic_now(): string
{
    return date('Y-m-d H:i:s');
}

// Creates all tables; safe to run more than once.
function ic_create_schema(PDO $pdo): void
{
    $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    $id = $mysql ? 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $long = $mysql ? 'MEDIUMTEXT' : 'TEXT';
    $tail = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';

    $pdo->exec("CREATE TABLE IF NOT EXISTS admins (
        id $id,
        username VARCHAR(50) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        created_at VARCHAR(19) NOT NULL
    )$tail");

    $pdo->exec("CREATE TABLE IF NOT EXISTS products (
        id VARCHAR(60) NOT NULL PRIMARY KEY,
        title VARCHAR(200) NOT NULL,
        category VARCHAR(30) NOT NULL,
        price_bdt INT NULL,
        price_usd DECIMAL(10,2) NULL,
        badge VARCHAR(60) NOT NULL DEFAULT '',
        image $long NOT NULL,
        wood VARCHAR(255) NOT NULL DEFAULT '',
        dimensions VARCHAR(255) NOT NULL DEFAULT '',
        finish VARCHAR(120) NOT NULL DEFAULT '',
        description TEXT NOT NULL,
        rating DECIMAL(2,1) NULL,
        review_count INT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        is_active TINYINT NOT NULL DEFAULT 1,
        updated_at VARCHAR(19) NOT NULL
    )$tail");

    $pdo->exec("CREATE TABLE IF NOT EXISTS orders (
        id $id,
        code VARCHAR(20) NOT NULL UNIQUE,
        type VARCHAR(10) NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'new',
        customer_name VARCHAR(120) NOT NULL,
        whatsapp VARCHAR(20) NOT NULL,
        address TEXT NULL,
        region VARCHAR(20) NULL,
        items_json TEXT NOT NULL,
        details_json TEXT NOT NULL,
        subtotal_bdt INT NULL,
        delivery_bdt INT NULL,
        total_bdt INT NULL,
        advance_bdt INT NULL,
        payment_method VARCHAR(20) NULL,
        trx_id VARCHAR(40) NULL,
        admin_note TEXT NULL,
        created_at VARCHAR(19) NOT NULL,
        updated_at VARCHAR(19) NOT NULL
    )$tail");

    $pdo->exec("CREATE TABLE IF NOT EXISTS order_files (
        id $id,
        order_id INT NOT NULL,
        original_name VARCHAR(255) NOT NULL,
        stored_name VARCHAR(100) NOT NULL,
        size_bytes INT NOT NULL,
        mime VARCHAR(120) NOT NULL DEFAULT '',
        created_at VARCHAR(19) NOT NULL
    )$tail");
}

function ic_setting(PDO $pdo, string $name, ?string $default = null): ?string
{
    $stmt = $pdo->prepare('SELECT value FROM settings WHERE name = ?');
    $stmt->execute([$name]);
    $value = $stmt->fetchColumn();
    return $value === false ? $default : (string) $value;
}

function ic_set_setting(PDO $pdo, string $name, string $value): void
{
    $pdo->prepare('DELETE FROM settings WHERE name = ?')->execute([$name]);
    $pdo->prepare('INSERT INTO settings (name, value) VALUES (?, ?)')->execute([$name, $value]);
}

function ic_columns(PDO $pdo, string $table): array
{
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        return array_column($pdo->query("SHOW COLUMNS FROM $table")->fetchAll(), 'Field');
    }
    return array_column($pdo->query("PRAGMA table_info($table)")->fetchAll(), 'name');
}

// Upgrades an existing database to the current schema (adds courier, reminder and pipeline fields)
function ic_migrate(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS settings (name VARCHAR(50) NOT NULL PRIMARY KEY, value TEXT NULL)');
    if ((int) ic_setting($pdo, 'schema_version', '1') >= IC_SCHEMA_VERSION) {
        return;
    }
    $columns = [
        'is_important'      => 'TINYINT NOT NULL DEFAULT 0',
        'needed_by'         => 'VARCHAR(10) NULL',
        'reminder_date'     => 'VARCHAR(10) NULL',
        'reminder_note'     => 'VARCHAR(255) NULL',
        'reminder_sent_at'  => 'VARCHAR(19) NULL',
        'courier'           => 'VARCHAR(30) NULL',
        'consignment_id'    => 'VARCHAR(40) NULL',
        'tracking_code'     => 'VARCHAR(60) NULL',
        'courier_status'    => 'VARCHAR(60) NULL',
        'cod_bdt'           => 'INT NULL',
        'shipped_at'        => 'VARCHAR(19) NULL',
        'status_changed_at' => 'VARCHAR(19) NULL',
        'delivery_score'    => 'INT NULL',
        'score_reasons'     => 'TEXT NULL',
        'client_ip'         => 'VARCHAR(45) NULL',
        'customer_email'    => 'VARCHAR(190) NULL',
        'meta_json'         => 'TEXT NULL', // the visitor's Facebook cookies, page and browser, for later Pixel events
    ];
    $have = ic_columns($pdo, 'orders');
    foreach ($columns as $column => $type) {
        if (!in_array($column, $have, true)) {
            $pdo->exec("ALTER TABLE orders ADD COLUMN $column $type");
        }
    }
    $productColumns = ic_columns($pdo, 'products');
    if (!in_array('is_customizable', $productColumns, true)) {
        $pdo->exec('ALTER TABLE products ADD COLUMN is_customizable TINYINT NOT NULL DEFAULT 0');
        $ids = "'" . implode("','", IC_DEFAULT_CUSTOMIZABLE) . "'";
        $pdo->exec("UPDATE products SET is_customizable = 1 WHERE id IN ($ids)");
    }
    // Per-product delivery charges (NULL = use the shop's default charge)
    foreach (['ship_dhaka' => 'INT NULL', 'ship_outside' => 'INT NULL', 'free_shipping' => 'TINYINT NOT NULL DEFAULT 0'] as $column => $type) {
        if (!in_array($column, $productColumns, true)) {
            $pdo->exec("ALTER TABLE products ADD COLUMN $column $type");
        }
    }
    // Product landing pages: one row per product once it is edited or visited (no row = default content)
    $long = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? 'MEDIUMTEXT' : 'TEXT';
    $tail = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
    $pdo->exec("CREATE TABLE IF NOT EXISTS landing_pages (
        product_id VARCHAR(60) NOT NULL PRIMARY KEY,
        is_enabled TINYINT NOT NULL DEFAULT 1,
        template VARCHAR(30) NULL,
        data_json $long NULL,
        views INT NOT NULL DEFAULT 0,
        updated_at VARCHAR(19) NOT NULL
    )$tail");
    // Recent login tries and orders per visitor, for brute-force and spam limits (old rows are cleaned up)
    $autoId = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $pdo->exec("CREATE TABLE IF NOT EXISTS attempts (
        id $autoId,
        kind VARCHAR(20) NOT NULL,
        k VARCHAR(120) NOT NULL,
        at VARCHAR(19) NOT NULL
    )$tail");
    // Customer reviews: written from a private link after delivery, shown only after the shop approves them
    $pdo->exec("CREATE TABLE IF NOT EXISTS reviews (
        id $autoId,
        order_id INT NOT NULL,
        product_id VARCHAR(60) NOT NULL,
        name VARCHAR(80) NOT NULL,
        district VARCHAR(60) NULL,
        rating TINYINT NOT NULL,
        body TEXT NULL,
        photo VARCHAR(120) NULL,
        status VARCHAR(12) NOT NULL DEFAULT 'pending',
        created_at VARCHAR(19) NOT NULL,
        approved_at VARCHAR(19) NULL
    )$tail");
    // Statuses from the first version of the admin panel
    $pdo->exec("UPDATE orders SET status = 'confirmed' WHERE status = 'advance_verified'");
    $pdo->exec("UPDATE orders SET status = 'shipping' WHERE status = 'ready'");
    $pdo->exec('UPDATE orders SET status_changed_at = updated_at WHERE status_changed_at IS NULL');
    ic_set_setting($pdo, 'schema_version', (string) IC_SCHEMA_VERSION);
}

// The visitor's IP address (as the web server sees it — not a header the visitor could fake)
function ic_client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

// Counting recent actions (failed logins, orders) for rate limits: kind = what, $key = who (IP, username, phone)
function ic_attempt_add(PDO $pdo, string $kind, string $key): void
{
    $pdo->prepare('INSERT INTO attempts (kind, k, at) VALUES (?, ?, ?)')->execute([$kind, substr($key, 0, 120), ic_now()]);
    if (random_int(1, 25) === 1) {
        $pdo->prepare('DELETE FROM attempts WHERE at < ?')->execute([date('Y-m-d H:i:s', strtotime('-2 days'))]);
    }
}

function ic_attempt_count(PDO $pdo, string $kind, string $key, int $seconds): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM attempts WHERE kind = ? AND k = ? AND at >= ?');
    $stmt->execute([$kind, substr($key, 0, 120), date('Y-m-d H:i:s', time() - $seconds)]);
    return (int) $stmt->fetchColumn();
}

function ic_attempt_clear(PDO $pdo, string $kind, string $key): void
{
    $pdo->prepare('DELETE FROM attempts WHERE kind = ? AND k = ?')->execute([$kind, substr($key, 0, 120)]);
}

// Shop-wide delivery defaults: set in the admin Shipping page, falling back to config.php
function ic_shipping_defaults(PDO $pdo): array
{
    $cfg = ic_config();
    return [
        'dhaka'   => (int) ic_setting($pdo, 'ship_default_dhaka', (string) ($cfg['delivery_fee']['dhaka'] ?? 80)),
        'outside' => (int) ic_setting($pdo, 'ship_default_outside', (string) ($cfg['delivery_fee']['outside'] ?? 140)),
        'freeMin' => (int) ic_setting($pdo, 'ship_free_min', (string) ($cfg['free_delivery_min'] ?? 0)),
    ];
}

// One parcel, one charge: the highest delivery charge among the cart's products (free-shipping products cost 0).
// Orders at or above the free-delivery amount ship free.
function ic_delivery_fee(array $productRows, string $region, int $subtotal, array $defaults): int
{
    if ($defaults['freeMin'] > 0 && $subtotal >= $defaults['freeMin']) {
        return 0;
    }
    $fee = 0;
    foreach ($productRows as $p) {
        if (!empty($p['free_shipping'])) {
            continue;
        }
        $own = $region === 'outside' ? $p['ship_outside'] : $p['ship_dhaka'];
        $fee = max($fee, $own === null ? $defaults[$region] : (int) $own);
    }
    return $fee;
}

function ic_set_status(PDO $pdo, int $orderId, string $status): void
{
    $now = ic_now();
    $pdo->prepare('UPDATE orders SET status = ?, status_changed_at = ?, updated_at = ? WHERE id = ?')
        ->execute([$status, $now, $now, $orderId]);
    if ($status === 'delivered') {
        ic_review_after_delivery($pdo, $orderId); // emails the review link once, when the customer gave an email
    }
}

// Orders still New/Confirmed after the day they reached that status missed shipping → Hold
function ic_auto_hold(PDO $pdo): int
{
    $now = ic_now();
    $stmt = $pdo->prepare("UPDATE orders SET status = 'hold', status_changed_at = ?, updated_at = ?
        WHERE status IN ('new', 'confirmed') AND COALESCE(status_changed_at, created_at) < ?");
    $stmt->execute([$now, $now, date('Y-m-d 00:00:00')]);
    return $stmt->rowCount();
}

// Emails the shop once for each important order whose reminder date has arrived
function ic_send_due_reminders(PDO $pdo): int
{
    $to = ic_config()['order_email'] ?? '';
    if ($to === '' || !function_exists('mail')) {
        return 0;
    }
    $closed = "'" . implode("','", IC_CLOSED_STATUSES) . "'";
    $due = $pdo->prepare("SELECT * FROM orders WHERE is_important = 1 AND reminder_date IS NOT NULL AND reminder_date <= ?
        AND reminder_sent_at IS NULL AND status NOT IN ($closed)");
    $due->execute([date('Y-m-d')]);
    $sent = 0;
    $host = preg_replace('/[^a-z0-9.-]/i', '', explode(':', $_SERVER['HTTP_HOST'] ?? 'localhost')[0]);
    foreach ($due->fetchAll() as $order) {
        $subject = '=?UTF-8?B?' . base64_encode("Reminder: order {$order['code']} — {$order['customer_name']}") . '?=';
        $body = "Reminder for order {$order['code']} ({$order['customer_name']}, {$order['whatsapp']})\n"
            . ($order['needed_by'] ? "Customer needs it by: {$order['needed_by']}\n" : '')
            . ($order['reminder_note'] ? "Note: {$order['reminder_note']}\n" : '')
            . 'Status: ' . (IC_ORDER_STATUSES[$order['status']] ?? $order['status']);
        $headers = "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nFrom: INSPIRED CREATIONS <no-reply@{$host}>";
        if (@mail($to, $subject, $body, $headers)) {
            $pdo->prepare('UPDATE orders SET reminder_sent_at = ? WHERE id = ?')->execute([ic_now(), $order['id']]);
            $sent++;
        }
    }
    return $sent;
}

// True when at least one courier is connected (see api/lib-courier.php)
function ic_courier_enabled(): bool
{
    return (bool) ic_connected_couriers(ic_db());
}

// Books the parcel with the shop's default courier; the order moves to Shipping
function ic_courier_create(PDO $pdo, array $order, int $cod, ?string $courier = null, array $opts = []): void
{
    $courier = $courier ?? ic_default_courier($pdo);
    if ($courier === null) {
        throw new RuntimeException('No courier is connected yet.');
    }
    ic_courier_send($pdo, $order, $courier, $cod, $opts);
}

// Asks each courier for the latest status of every parcel on the way and moves orders along
function ic_courier_sync(PDO $pdo, int $limit = 40): int
{
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE consignment_id IS NOT NULL AND status IN ('shipping', 'waiting')
        ORDER BY status_changed_at LIMIT $limit");
    $stmt->execute();
    $moved = 0;
    foreach ($stmt->fetchAll() as $order) {
        $courier = ic_courier_key_from_label($order['courier']);
        if ($courier === null || !ic_courier_connected($pdo, $courier)) {
            continue;
        }
        try {
            $raw = ic_courier_fetch_status($pdo, $courier, $order);
        } catch (Throwable $e) {
            error_log("Courier sync failed for order {$order['id']}: " . $e->getMessage());
            continue;
        }
        if ($raw === '' || $raw === $order['courier_status']) {
            continue;
        }
        $pdo->prepare('UPDATE orders SET courier_status = ?, updated_at = ? WHERE id = ?')->execute([$raw, ic_now(), $order['id']]);
        $status = ic_courier_map_status($courier, $raw);
        if ($status !== null && $status !== $order['status']) {
            ic_set_status($pdo, (int) $order['id'], $status);
            $moved++;
        }
    }
    return $moved;
}

// Estimates how likely (0–100%) the customer is to accept the parcel, from this shop's own history with the
// number plus warning signs in the order itself. Saves the score and the reasons on the order.
function ic_score_order(PDO $pdo, int $orderId): array
{
    $cfg = ic_config();
    $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    if (!$order) {
        return ['score' => null, 'reasons' => []];
    }

    $rule = 70; // a first-time customer with no warning signs
    $reasons = [];
    $add = function (string $label, int $points) use (&$rule, &$reasons) {
        $rule += $points;
        $reasons[] = ['label' => $label, 'points' => $points];
    };
    $len = function (string $text) {
        return function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
    };

    if (!empty($order['trx_id'])) {
        $add('Paid an advance (Transaction ID given)', 20);
    }
    $name = trim($order['customer_name']);
    if ($len($name) < 3 || preg_match('/\d/', $name) || preg_match('/(.)\1{3,}/u', $name) || preg_match('/\b(test|fake|asdf|abc|xyz)\b/i', $name)) {
        $add('Name does not look real', -25);
    }
    if ($order['type'] === 'cart' && $len(trim((string) $order['address'])) < 15) {
        $add('Delivery address is very short', -15);
    }

    $recent = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE whatsapp = ? AND id <> ? AND created_at >= ?');
    $recent->execute([$order['whatsapp'], $orderId, date('Y-m-d H:i:s', strtotime('-24 hours'))]);
    $recentCount = (int) $recent->fetchColumn();
    if ($recentCount >= 2) {
        $add("$recentCount other orders from this number in the last 24 hours", -20);
    }

    $names = $pdo->prepare('SELECT DISTINCT LOWER(TRIM(customer_name)) FROM orders WHERE whatsapp = ? AND id <> ?');
    $names->execute([$order['whatsapp'], $orderId]);
    $otherNames = array_diff($names->fetchAll(PDO::FETCH_COLUMN), [strtolower($name)]);
    if (count($otherNames) >= 2) {
        $add('Number used with ' . count($otherNames) . ' other names', -15);
    }

    if (empty($order['trx_id']) && (int) $order['total_bdt'] > (int) ($cfg['fake_high_cod'] ?? 10000)) {
        $add('High cash-on-delivery amount with no advance', -10);
    }
    $rule = max(0, min(100, $rule));

    // This shop's own record with the number: delivered = received; partial or returned = refused
    $history = $pdo->prepare("SELECT status, COUNT(*) AS n FROM orders WHERE whatsapp = ? AND id <> ?
        AND status IN ('delivered', 'partial', 'returned') GROUP BY status");
    $history->execute([$order['whatsapp'], $orderId]);
    $received = 0;
    $refused = 0;
    foreach ($history->fetchAll() as $row) {
        if ($row['status'] === 'delivered') {
            $received += (int) $row['n'];
        } else {
            $refused += (int) $row['n'];
        }
    }
    $parcels = $received + $refused;
    if ($parcels > 0) {
        // The more past parcels, the more the real record counts (up to 80% weight from 5 parcels)
        $weight = min($parcels, 5) / 5 * 0.8;
        $score = $weight * ($received / $parcels * 100) + (1 - $weight) * $rule;
        array_unshift($reasons, ['label' => "Past parcels with us: $received received, $refused returned", 'points' => null]);
    } else {
        $score = $rule;
        array_unshift($reasons, ['label' => 'First order from this number — no delivery history yet', 'points' => null]);
    }
    $score = (int) round(max(0, min(100, $score)));

    $pdo->prepare('UPDATE orders SET delivery_score = ?, score_reasons = ? WHERE id = ?')
        ->execute([$score, json_encode($reasons, JSON_UNESCAPED_UNICODE), $orderId]);
    return ['score' => $score, 'reasons' => $reasons];
}

// Runs the automatic rules. Called by cron and, at most every 15 minutes, when an admin opens the panel.
function ic_run_automation(PDO $pdo, bool $force = false): array
{
    $result = ['held' => ic_auto_hold($pdo), 'courier_moved' => 0, 'reminders_sent' => 0];
    $lastSync = (int) ic_setting($pdo, 'last_courier_sync', '0');
    if (ic_courier_enabled() && ($force || time() - $lastSync >= 900)) {
        ic_set_setting($pdo, 'last_courier_sync', (string) time());
        $result['courier_moved'] = ic_courier_sync($pdo);
    }
    $result['reminders_sent'] = ic_send_due_reminders($pdo);
    return $result;
}

// Database row → the product shape index.html expects
function ic_product_to_public(array $r): array
{
    return [
        'id'          => $r['id'],
        'title'       => $r['title'],
        'category'    => $r['category'],
        'priceBDT'    => $r['price_bdt'] === null ? null : (int) $r['price_bdt'],
        'priceUSD'    => $r['price_usd'] === null ? null : (float) $r['price_usd'],
        'badge'       => $r['badge'],
        'image'       => $r['image'],
        'wood'        => $r['wood'],
        'dimensions'  => $r['dimensions'],
        'finish'      => $r['finish'],
        'desc'        => $r['description'],
        'rating'      => $r['rating'] === null ? null : (float) $r['rating'],
        'reviewCount' => $r['review_count'] === null ? null : (int) $r['review_count'],
        'customizable' => !empty($r['is_customizable']),
        'shipDhaka'    => isset($r['ship_dhaka']) ? (int) $r['ship_dhaka'] : null,
        'shipOutside'  => isset($r['ship_outside']) ? (int) $r['ship_outside'] : null,
        'freeShipping' => !empty($r['free_shipping']),
    ];
}

function ic_json(array $data, int $status = 200): void
{
    ic_discard_output();
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Accepts 01XXXXXXXXX, +8801XXXXXXXXX, 8801…; returns 01XXXXXXXXX or '' if invalid
function ic_normalize_phone(string $raw): string
{
    $digits = preg_replace('/\D/', '', $raw);
    if (strpos($digits, '880') === 0) {
        $digits = substr($digits, 2);
    }
    return preg_match('/^01[3-9]\d{8}$/', $digits) ? $digits : '';
}

function ic_clean(string $value, int $max): string
{
    $value = trim(str_replace("\0", '', $value));
    return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
}

function ic_uploads_dir(): string
{
    $dir = IC_ROOT . '/uploads/orders';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return $dir;
}

function ic_new_order_code(PDO $pdo): string
{
    $check = $pdo->prepare('SELECT 1 FROM orders WHERE code = ?');
    do {
        $code = 'IC-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
        $check->execute([$code]);
    } while ($check->fetchColumn());
    return $code;
}

// Plain-text summary used for the notification email and the admin panel
function ic_order_summary(array $order, array $items, array $details, array $fileNames): string
{
    $lines = [
        "Order {$order['code']} ({$order['type']})",
        "Customer: {$order['customer_name']}",
        "WhatsApp: {$order['whatsapp']}",
    ];
    if (!empty($order['customer_email'])) {
        $lines[] = "Email: {$order['customer_email']}";
    }
    if (!empty($order['address'])) {
        $lines[] = "Address: {$order['address']}";
    }
    $lines[] = '';
    foreach ($items as $item) {
        $unit = $item['unit_bdt'] === null ? 'price on request' : '৳' . number_format($item['unit_bdt']);
        $lines[] = "- {$item['title']} × {$item['qty']} ({$unit})";
    }
    $lines[] = '';
    $lines[] = 'Total: ' . ($order['total_bdt'] === null ? 'price on request' : '৳' . number_format($order['total_bdt']));
    if ($order['advance_bdt'] !== null) {
        $lines[] = 'Advance (30%): ৳' . number_format($order['advance_bdt']);
    }
    if (!empty($order['payment_method'])) {
        $lines[] = "Payment: {$order['payment_method']}" . (!empty($order['trx_id']) ? " — Trx ID {$order['trx_id']}" : '');
    }
    foreach ($details as $key => $value) {
        if ($key === 'marks') {
            foreach ($value as $i => $mark) {
                $lines[] = 'Mark ' . ($i + 1) . ': ' . ($mark['note'] !== '' ? $mark['note'] : '(no note)');
            }
        } elseif ($value !== '' && $value !== null) {
            $lines[] = ucfirst(str_replace('_', ' ', $key)) . ': ' . $value;
        }
    }
    if ($fileNames) {
        $lines[] = 'Files: ' . implode(', ', $fileNames);
    }
    return implode("\n", $lines);
}

// Order confirmation for the customer (Bangla, with the order details). Uses the hosting's PHP mail();
// replies go to the shop's order email. Returns false when the hosting could not send it.
function ic_send_order_confirmation(array $order, array $items, array $details): bool
{
    $to = (string) ($order['customer_email'] ?? '');
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL) || !function_exists('mail')) {
        return false;
    }
    $cfg = ic_config();
    $e = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
    $tk = function ($n) { return $n === null ? 'দাম জানানো হবে' : '৳' . number_format((int) $n); };
    $host = preg_replace('/[^a-z0-9.-]/i', '', explode(':', $_SERVER['HTTP_HOST'] ?? 'localhost')[0]);
    $wa = preg_replace('/\D/', '', (string) ($cfg['whatsapp'] ?? '8801321744476'));

    $rows = '';
    foreach ($items as $item) {
        $line = $item['unit_bdt'] === null ? null : (int) $item['unit_bdt'] * (int) $item['qty'];
        $rows .= '<tr><td style="padding:8px 0;border-bottom:1px solid #eee">' . $e($item['title']) . ' × ' . (int) $item['qty'] . '</td>'
            . '<td style="padding:8px 0;border-bottom:1px solid #eee;text-align:right;white-space:nowrap">' . $tk($line) . '</td></tr>';
    }
    $money = function (string $label, $value, bool $bold = false) use ($e) {
        return '<tr><td style="padding:4px 0;color:' . ($bold ? '#111' : '#555') . ($bold ? ';font-weight:700;font-size:17px' : '') . '">' . $label . '</td>'
            . '<td style="padding:4px 0;text-align:right;' . ($bold ? 'font-weight:700;font-size:17px;color:#9c3d12' : '') . '">' . $e($value) . '</td></tr>';
    };
    $totals = '';
    if ($order['subtotal_bdt'] !== null) {
        $totals .= $money('পণ্যের দাম', $tk($order['subtotal_bdt']));
    }
    if ($order['delivery_bdt'] !== null) {
        $totals .= $money('ডেলিভারি চার্জ', $order['delivery_bdt'] ? $tk($order['delivery_bdt']) : 'ফ্রি');
    }
    $totals .= $money('সর্বমোট', $tk($order['total_bdt']), true);
    if ($order['advance_bdt'] !== null) {
        $totals .= $money('অগ্রিম (৩০%)', $tk($order['advance_bdt']));
    }
    $payment = (string) ($order['payment_method'] ?? '');
    $paymentBn = $payment === 'Cash on Delivery' ? 'ক্যাশ অন ডেলিভারি — পণ্য হাতে পেয়ে টাকা দিন' : $payment;
    if (!empty($order['trx_id'])) {
        $paymentBn .= ' (Transaction ID: ' . $order['trx_id'] . ' — আমরা যাচাই করে নেব)';
    }

    $html = '<!DOCTYPE html><html lang="bn"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>'
        . '<body style="margin:0;background:#f7f1e8;font-family:\'Hind Siliguri\',Arial,sans-serif;color:#222">'
        . '<div style="max-width:560px;margin:0 auto;padding:24px 16px">'
        . '<div style="background:#24130a;color:#f7f1e8;border-radius:16px 16px 0 0;padding:20px;text-align:center">'
        . '<div style="font-size:20px;letter-spacing:3px;font-weight:700">INSPIRED CREATIONS</div>'
        . '<div style="font-size:12px;color:#ddb982;margin-top:4px">হাতে তৈরি কাঠের উপহার</div></div>'
        . '<div style="background:#fff;border-radius:0 0 16px 16px;padding:24px">'
        . '<p style="font-size:18px;margin:0 0 8px">প্রিয় ' . $e($order['customer_name']) . ',</p>'
        . '<p style="margin:0 0 16px;line-height:1.6">ধন্যবাদ! আপনার অর্ডারটি আমরা পেয়েছি। আমাদের প্রতিনিধি খুব শীঘ্রই ফোন করে অর্ডারটি কনফার্ম করবেন।</p>'
        . '<div style="background:#f7f1e8;border-radius:12px;padding:12px 16px;margin-bottom:16px">অর্ডার নম্বর: <b style="font-size:18px;letter-spacing:1px">' . $e($order['code']) . '</b></div>'
        . '<table style="width:100%;border-collapse:collapse;font-size:15px">' . $rows . '</table>'
        . '<table style="width:100%;border-collapse:collapse;font-size:15px;margin-top:10px">' . $totals . '</table>'
        . ($paymentBn !== '' ? '<p style="margin:16px 0 4px;color:#555">পেমেন্ট: <b style="color:#222">' . $e($paymentBn) . '</b></p>' : '')
        . (!empty($order['address']) ? '<p style="margin:4px 0;color:#555">ডেলিভারি ঠিকানা: <b style="color:#222">' . $e($order['address']) . '</b></p>' : '')
        . (!empty($details['needed_by']) ? '<p style="margin:4px 0;color:#555">যে তারিখের মধ্যে দরকার: <b style="color:#222">' . $e($details['needed_by']) . '</b></p>' : '')
        . '<p style="margin:20px 0 8px">কোনো প্রশ্ন থাকলে এই ইমেইলের রিপ্লাই দিন অথবা WhatsApp করুন:</p>'
        . '<a href="https://wa.me/' . $e($wa) . '?text=' . rawurlencode('অর্ডার নম্বর: ' . $order['code']) . '" style="display:inline-block;background:#1fa855;color:#fff;text-decoration:none;padding:10px 20px;border-radius:99px;font-weight:700">WhatsApp করুন</a>'
        . '</div><p style="text-align:center;font-size:12px;color:#8a7a6a;margin-top:16px">আপনি ' . $e($host) . ' থেকে অর্ডার করেছেন বলে এই ইমেইলটি পাঠানো হয়েছে।</p>'
        . '</div></body></html>';

    $subject = '=?UTF-8?B?' . base64_encode('অর্ডার পেয়েছি — ' . $order['code'] . ' | INSPIRED CREATIONS') . '?=';
    $from = '=?UTF-8?B?' . base64_encode('INSPIRED CREATIONS') . "?= <no-reply@{$host}>";
    $headers = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\nFrom: $from";
    $replyTo = (string) ($cfg['order_email'] ?? '');
    if ($replyTo !== '') {
        $headers .= "\r\nReply-To: $replyTo";
    }
    return @mail($to, $subject, chunk_split(base64_encode($html)), $headers);
}

// Emails the shop about a new order (uses the hosting's PHP mail(); failures are ignored)
function ic_notify_new_order(array $order, string $summary): void
{
    $to = ic_config()['order_email'] ?? '';
    if ($to === '' || !function_exists('mail')) {
        return;
    }
    $host = preg_replace('/[^a-z0-9.-]/i', '', explode(':', $_SERVER['HTTP_HOST'] ?? 'localhost')[0]);
    $subject = '=?UTF-8?B?' . base64_encode("New order {$order['code']} — {$order['customer_name']}") . '?=';
    $headers = "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nFrom: INSPIRED CREATIONS <no-reply@{$host}>";
    $body = $summary . "\n\nOpen the admin panel to see files and update the status: https://{$host}/admin/order.php?code=" . rawurlencode($order['code']);
    @mail($to, $subject, $body, $headers);
}

// Courier connectors (Steadfast, Pathao, RedX)
require __DIR__ . '/lib-courier.php';
require __DIR__ . '/lib-meta.php';
require __DIR__ . '/lib-landing.php';
require_once __DIR__ . '/lib-geo.php';
require __DIR__ . '/lib-reviews.php';
