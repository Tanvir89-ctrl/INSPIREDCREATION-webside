<?php
// Public: active products for the website (index.html falls back to its built-in list if this fails)
require __DIR__ . '/bootstrap.php';

try {
    $pdo = ic_db();
    ic_migrate($pdo);
    $rows = $pdo->query('SELECT * FROM products WHERE is_active = 1 ORDER BY sort_order, title')->fetchAll();
    // Products whose landing page is on open it from the shop ("landing": link, or null)
    $landingRows = [];
    foreach ($pdo->query('SELECT product_id, is_enabled FROM landing_pages') as $row) {
        $landingRows[$row['product_id']] = (bool) $row['is_enabled'];
    }
    $autoOn = ic_landing_auto_on($pdo);
    $products = array_map(function ($r) use ($landingRows, $autoOn) {
        $on = $landingRows[$r['id']] ?? $autoOn;
        return ic_product_to_public($r) + ['landing' => $on ? ic_landing_url($r['id']) : null];
    }, $rows);
    header('Cache-Control: no-store');
    ic_json(['ok' => true, 'products' => $products, 'shipping' => ic_shipping_defaults($pdo)]);
} catch (Throwable $e) {
    ic_json(['ok' => false, 'error' => 'Products are unavailable right now.'], 503);
}
