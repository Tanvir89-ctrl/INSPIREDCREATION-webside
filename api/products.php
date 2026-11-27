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
    $stars = ic_review_stats($pdo); // stars come from approved customer reviews
    $products = array_map(function ($r) use ($landingRows, $autoOn, $stars) {
        $on = $landingRows[$r['id']] ?? $autoOn;
        $p = ic_product_to_public($r) + ['landing' => $on ? ic_landing_url($r['id']) : null];
        if (isset($stars[$r['id']])) {
            $p['rating'] = $stars[$r['id']]['rating'];
            $p['reviewCount'] = $stars[$r['id']]['count'];
        }
        return $p;
    }, $rows);
    header('Cache-Control: no-store');
    ic_json(['ok' => true, 'products' => $products, 'shipping' => ic_shipping_defaults($pdo)]);
} catch (Throwable $e) {
    ic_json(['ok' => false, 'error' => 'Products are unavailable right now.'], 503);
}
