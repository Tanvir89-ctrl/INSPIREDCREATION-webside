<?php
// Automatic order rules: moves missed orders to Hold, syncs courier statuses, emails due reminders.
// Set up in cPanel → Cron Jobs to run every 15–30 minutes:
//   php /home/YOUR_USER/public_html/api/cron.php
// or, if your host only allows URLs:  https://your-domain.com/api/cron.php?key=YOUR_CRON_KEY
require __DIR__ . '/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    $key = (string) (ic_config()['cron_key'] ?? '');
    if ($key === '' || $key === 'change-this-to-a-long-random-text' || !hash_equals($key, (string) ($_GET['key'] ?? ''))) {
        ic_json(['ok' => false, 'error' => 'Forbidden'], 403);
    }
}

$pdo = ic_db();
ic_migrate($pdo);
$result = ic_run_automation($pdo, true);
ic_set_setting($pdo, 'last_cron_run', ic_now()); // shown in admin → Settings → Security check

if (PHP_SAPI === 'cli') {
    ic_discard_output();
    echo date('Y-m-d H:i:s') . ' ' . json_encode($result) . "\n";
} else {
    ic_json(['ok' => true] + $result);
}
