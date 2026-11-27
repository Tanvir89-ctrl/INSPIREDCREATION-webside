<?php
// JSON lists for the order page's courier location pickers (admins only)
require __DIR__ . '/_inc.php';
require_admin();

$pdo = ic_db();
try {
    $type = (string) ($_GET['type'] ?? '');
    if ($type === 'redx_areas') {
        $items = ic_redx_areas($pdo, ic_clean((string) ($_GET['district'] ?? 'Dhaka'), 40));
    } elseif (in_array($type, ['cities', 'zones', 'areas'], true)) {
        $items = ic_pathao_locations($pdo, $type, (int) ($_GET['id'] ?? 0));
    } else {
        ic_json(['ok' => false, 'error' => 'Unknown list'], 400);
    }
    ic_json(['ok' => true, 'items' => $items]);
} catch (Throwable $e) {
    ic_json(['ok' => false, 'error' => $e->getMessage()], 502);
}
