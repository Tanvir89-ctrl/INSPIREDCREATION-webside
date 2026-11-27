<?php
// Settings → Backup: one click downloads everything needed to rebuild the shop — the database (orders, customers,
// products, landing pages, settings) and the uploaded pictures/files — as a single .zip.
require __DIR__ . '/_inc.php';
require_admin();

$pdo = ic_db();
$isMysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
$folders = ['uploads/orders' => 'Customer design files', 'assets/products' => 'Product photos', 'assets/landing' => 'Landing page photos', 'assets/payment' => 'Bangla QR picture'];

// Writes the whole MySQL database as SQL that phpMyAdmin → Import can load back
function backup_mysql_dump(PDO $pdo, $out): void
{
    fwrite($out, "-- INSPIRED CREATIONS database backup " . date('Y-m-d H:i:s') . "\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n");
    foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
        $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM)[1];
        fwrite($out, "DROP TABLE IF EXISTS `$table`;\n$create;\n");
        foreach ($pdo->query("SELECT * FROM `$table`", PDO::FETCH_ASSOC) as $row) {
            $values = array_map(function ($v) use ($pdo) { return $v === null ? 'NULL' : $pdo->quote((string) $v); }, $row);
            fwrite($out, "INSERT INTO `$table` (`" . implode('`, `', array_keys($row)) . '`) VALUES (' . implode(', ', $values) . ");\n");
        }
        fwrite($out, "\n");
    }
    fwrite($out, "SET FOREIGN_KEY_CHECKS = 1;\n");
}

$sendFile = function (string $path, string $name, string $type) {
    ic_discard_output();
    header('Content-Type: ' . $type);
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: no-store');
    readfile($path);
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    @set_time_limit(300);
    $stamp = date('Y-m-d-His');
    $tmpDir = sys_get_temp_dir();

    // The database: an SQL file on MySQL hosting, the database file itself when testing with SQLite
    if ($isMysql) {
        $dbFile = tempnam($tmpDir, 'icdb');
        $fh = fopen($dbFile, 'w');
        backup_mysql_dump($pdo, $fh);
        fclose($fh);
        $dbName = 'database.sql';
    } else {
        $dbFile = (string) ic_config()['db']['sqlite'];
        $dbName = 'database.sqlite';
    }
    ic_set_setting($pdo, 'last_backup_at', ic_now());

    if (!class_exists('ZipArchive')) {
        // Without the zip tool on the hosting, at least the database can be saved
        $sendFile($dbFile, "inspired-backup-$stamp-" . $dbName, $isMysql ? 'application/sql' : 'application/octet-stream');
        if ($isMysql) {
            @unlink($dbFile);
        }
        exit;
    }

    $zipFile = tempnam($tmpDir, 'iczip');
    $zip = new ZipArchive();
    $zip->open($zipFile, ZipArchive::OVERWRITE);
    $zip->addFile($dbFile, $dbName);
    $count = 0;
    foreach (array_keys($folders) as $folder) {
        $dir = IC_ROOT . '/' . $folder;
        if (!is_dir($dir)) {
            continue;
        }
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $f) {
            if ($f->isFile()) {
                $zip->addFile($f->getPathname(), 'files/' . $folder . '/' . substr($f->getPathname(), strlen($dir) + 1));
                $count++;
            }
        }
    }
    $zip->addFromString('README.txt', "INSPIRED CREATIONS backup — " . date('d M Y, g:i a') . "\r\n\r\n"
        . "database." . ($isMysql ? 'sql' : 'sqlite') . " = all orders, customers, products, landing pages and settings.\r\n"
        . "files/ = uploaded pictures and customer files ($count files). Put each folder back in the same place on the website.\r\n\r\n"
        . "To restore on cPanel: phpMyAdmin -> choose your database -> Import -> database.sql.\r\n"
        . "Keep this file private: it contains customer names, phone numbers and your courier / Facebook keys.\r\n");
    $zip->close();
    if ($isMysql) {
        @unlink($dbFile);
    }
    $sendFile($zipFile, "inspired-backup-$stamp.zip", 'application/zip');
    @unlink($zipFile);
    exit;
}

$last = ic_setting($pdo, 'last_backup_at');
$days = $last ? (int) floor((time() - strtotime($last)) / 86400) : null;
$orders = (int) $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();
admin_header('Backup', 'backup.php');
?>
<div class="mb-6">
  <p class="text-xs uppercase tracking-[0.25em] text-gold-600">Settings</p>
  <h1 class="font-serif text-3xl text-wood-900 mt-1">Backup</h1>
  <p class="text-sm text-stone-500 mt-1">Download a copy of everything, so a hosting problem can never wipe out your orders and customers.</p>
</div>

<div class="grid lg:grid-cols-2 gap-6 items-start">
  <section class="card p-5 sm:p-6 space-y-4">
    <div class="flex items-center gap-3">
      <span class="w-12 h-12 rounded-xl flex items-center justify-center text-xl <?= $days === null || $days > 7 ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700' ?>"><i class="fa-solid fa-cloud-arrow-down"></i></span>
      <div>
        <p class="text-sm text-stone-500">Last backup</p>
        <p class="text-lg font-semibold text-wood-900"><?= $last ? h(date('d M Y, g:i a', strtotime($last))) . ' (' . ($days === 0 ? 'today' : $days . ' day' . ($days === 1 ? '' : 's') . ' ago') . ')' : 'Never' ?></p>
      </div>
    </div>
    <ul class="text-sm text-stone-600 space-y-1">
      <li><i class="fa-solid fa-check text-emerald-600 w-4"></i> All <?= number_format($orders) ?> orders and their customers</li>
      <li><i class="fa-solid fa-check text-emerald-600 w-4"></i> Products, prices, shipping, landing pages, settings</li>
      <?php foreach ($folders as $label): ?><li><i class="fa-solid fa-check text-emerald-600 w-4"></i> <?= h($label) ?></li><?php endforeach; ?>
    </ul>
    <form method="post">
      <?= csrf_field() ?>
      <button class="btn btn-gold w-full !py-3"><i class="fa-solid fa-file-zipper"></i> Download backup now (.zip)</button>
    </form>
    <?php if (!class_exists('ZipArchive')): ?>
      <p class="text-xs text-amber-700">Your hosting has no zip tool, so only the database file is downloaded. Ask your hosting to enable the PHP “zip” extension to include pictures too.</p>
    <?php endif; ?>
    <p class="text-xs text-stone-400">Keep the file private (Google Drive / your computer) — it holds customer phone numbers and your courier &amp; Facebook keys.</p>
  </section>

  <section class="card p-5 sm:p-6">
    <h2 class="font-serif text-lg text-wood-900 mb-2">Good habits</h2>
    <ol class="text-sm text-stone-600 space-y-2 list-decimal list-inside">
      <li>Download a backup <strong>every week</strong> (the dashboard reminds you after 7 days) and keep the last few.</li>
      <li>Also turn on your hosting's own backups: <strong>cPanel → Backup / JetBackup</strong>.</li>
      <li>To restore: in <strong>cPanel → phpMyAdmin</strong>, open your database → <strong>Import</strong> → choose <code>database.sql</code> from the zip. Upload the <code>files</code> folders back to the same places with File Manager.</li>
    </ol>
  </section>
</div>
<?php admin_footer();
