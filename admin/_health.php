<?php
// Safety checks shown on the dashboard and on Settings → Security check.
// Each check: [id, ok?, title, what to do, link to fix it]

function ic_is_local_host(): bool
{
    $host = strtolower(explode(':', (string) ($_SERVER['HTTP_HOST'] ?? ''))[0]);
    return in_array($host, ['localhost', '127.0.0.1', '::1', '[::1]'], true) || substr($host, -10) === '.localhost' || substr($host, -5) === '.test';
}

function ic_is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

function ic_security_checks(PDO $pdo): array
{
    $cfg = ic_config();
    $local = ic_is_local_host();
    $checks = [];

    $last = ic_setting($pdo, 'last_backup_at');
    $days = $last ? (time() - strtotime($last)) / 86400 : null;
    $checks[] = ['backup', $days !== null && $days <= 7, 'Weekly backup',
        $days === null ? 'No backup downloaded yet.' : ($days > 7 ? 'Last backup was ' . floor($days) . ' days ago.' : 'Backup downloaded ' . ($days < 1 ? 'today' : floor($days) . ' day(s) ago') . '.'),
        'backup.php'];

    $checks[] = ['https', ic_is_https() || $local, 'Secure connection (HTTPS)',
        ic_is_https() ? 'The admin panel is on HTTPS.' : ($local ? 'Not needed while testing on this computer.' : 'The site is open without HTTPS — passwords and customer data travel unprotected.'),
        'security.php#https'];

    $setupLeft = is_file(__DIR__ . '/setup.php');
    $checks[] = ['setup', !$setupLeft || $local, 'Setup file removed',
        $setupLeft ? ($local ? 'Keep it while testing; delete it on the hosting after setup.' : 'admin/setup.php is still on the server. It is locked, but it should be deleted.') : 'admin/setup.php is gone.',
        'security.php#setup'];

    $lastCron = ic_setting($pdo, 'last_cron_run');
    $cronOk = $lastCron && strtotime($lastCron) > time() - 3 * 3600;
    $checks[] = ['cron', $cronOk || $local, 'Automatic jobs (cron)',
        $cronOk ? 'Ran ' . date('d M, g:i a', strtotime($lastCron)) . '.' : ($local ? 'Set it up in cPanel → Cron Jobs when the site is live.' : ($lastCron ? 'Has not run since ' . date('d M, g:i a', strtotime($lastCron)) . ' — Hold, reminders and courier updates are paused.' : 'Not set up — add it in cPanel → Cron Jobs (see the setup guide).')),
        'security.php#cron'];

    $key = (string) ($cfg['cron_key'] ?? '');
    $checks[] = ['cronkey', strlen($key) >= 20 && $key !== 'change-this-to-a-long-random-text', 'Secret cron key',
        strlen($key) >= 20 ? 'Set.' : 'In api/config.php, set cron_key to a long random text (20+ letters/numbers).', 'security.php#cronkey'];

    $checks[] = ['email', ($cfg['order_email'] ?? '') !== '', 'Order email',
        ($cfg['order_email'] ?? '') !== '' ? 'New orders are emailed to ' . $cfg['order_email'] . '.' : 'No order email set in api/config.php.', 'security.php'];

    $checks[] = ['debug', empty($cfg['debug']), 'Error messages hidden',
        empty($cfg['debug']) ? 'Visitors never see PHP errors; they go to data/php-errors.log.' : "'debug' => true in api/config.php shows errors to everyone — set it back to false.", 'security.php'];

    $admins = (int) $pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn();
    $paused = (int) $pdo->query("SELECT COUNT(DISTINCT k) FROM attempts WHERE kind = 'login_ip' AND at >= '" . date('Y-m-d H:i:s', time() - 86400) . "'")->fetchColumn();
    $checks[] = ['logins', true, 'Login protection',
        "On — 5 wrong passwords lock a username for 15 minutes. $admins admin login" . ($admins === 1 ? '' : 's') . ($paused ? "; wrong passwords from $paused place" . ($paused === 1 ? '' : 's') . ' in the last 24 hours.' : '.'),
        'account.php'];
    return $checks;
}
