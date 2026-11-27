<?php
// Public: approved customer reviews for the website (newest first). ?product=<id> for one product.
require __DIR__ . '/bootstrap.php';

try {
    $pdo = ic_db();
    ic_migrate($pdo);
    $product = isset($_GET['product']) ? preg_replace('/[^\w-]/', '', (string) $_GET['product']) : null;
    $rows = ic_reviews_public($pdo, $product ?: null, 12);
    header('Cache-Control: public, max-age=300');
    ic_json(['ok' => true, 'reviews' => array_map(function ($r) {
        return [
            'name' => $r['name'], 'district' => $r['district'], 'rating' => (int) $r['rating'], 'body' => (string) $r['body'],
            'photo' => $r['photo'], 'product' => $r['product'], 'productId' => $r['product_id'],
            'date' => date('M Y', strtotime((string) ($r['approved_at'] ?: $r['created_at']))),
        ];
    }, $rows)]);
} catch (Throwable $e) {
    ic_json(['ok' => false], 503);
}
