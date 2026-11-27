<?php
// Public: website settings the browser needs (only safe values — never tokens or keys)
require __DIR__ . '/bootstrap.php';

try {
    $pdo = ic_db();
    ic_migrate($pdo);
    $meta = ic_meta_settings($pdo);
    header('Cache-Control: no-store');
    // "server": the browser should also send each event to api/event.php (Conversions API is set up)
    $qr = ic_bangla_qr($pdo); // the shop's Bangla QR for checkout (admin → Payment → Bangla QR)
    ic_json([
        'ok'    => true,
        'pixel' => ic_meta_active($meta) ? ['id' => $meta['pixel_id'], 'server' => $meta['capi_token'] !== ''] : null,
        'qr'    => ic_bangla_qr_active($qr) ? ['image' => $qr['image'], 'merchant' => $qr['merchant'], 'note' => $qr['note']] : null,
    ]);
} catch (Throwable $e) {
    ic_json(['ok' => false], 503);
}
