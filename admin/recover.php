<?php
// Lost admin password: the owner puts data/reset-admin.txt ("username:NewPassword") on the hosting with cPanel's
// File Manager, then opens this page. Only someone with hosting access can do that. The file is deleted after use.
require __DIR__ . '/_inc.php';

$file = IC_ROOT . '/data/reset-admin.txt';
$message = null;
$ok = false;

if (is_file($file)) {
    try {
        $pdo = ic_db();
        ic_migrate($pdo);
        if (filemtime($file) < time() - 86400) {
            $message = 'The reset file is more than a day old, so it was not used. Create it again and open this page right away.';
        } else {
            $line = trim((string) strtok((string) file_get_contents($file), "\n"));
            [$username, $password] = array_pad(explode(':', $line, 2), 2, '');
            $username = trim($username);
            $password = trim($password);
            $stmt = $pdo->prepare('SELECT id FROM admins WHERE username = ?');
            $stmt->execute([$username]);
            $id = $stmt->fetchColumn();
            if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) {
                $message = 'The file must contain one line like:  username:NewPassword';
            } elseif (strlen($password) < 10) {
                $message = 'The new password in the file must be at least 10 characters.';
            } elseif ($id) {
                $pdo->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
                ic_attempt_clear($pdo, 'login_user', strtolower($username));
                $message = "Password for “{$username}” has been changed. You can log in now.";
                $ok = true;
            } else {
                $pdo->prepare('INSERT INTO admins (username, password_hash, created_at) VALUES (?, ?, ?)')
                    ->execute([$username, password_hash($password, PASSWORD_DEFAULT), ic_now()]);
                $message = "There was no admin called “{$username}”, so a new admin login was created. You can log in now.";
                $ok = true;
            }
            error_log("Admin password reset used for '$username' from " . ic_client_ip());
        }
    } catch (Throwable $e) {
        $message = 'Could not reach the database. Check api/config.php.';
    }
    // The file holds a password in plain text: never leave it behind
    if (!@unlink($file)) {
        $message .= ' IMPORTANT: delete data/reset-admin.txt from File Manager now — it could not be removed automatically.';
    }
}

admin_header('Recover admin login');
?>
<div class="max-w-lg mx-auto mt-6 card p-6 sm:p-8 space-y-4">
  <h1 class="font-serif text-2xl text-wood-900 text-center">Recover admin login</h1>
  <?php if ($message): ?>
    <div class="rounded-xl px-4 py-3 text-sm <?= $ok ? 'bg-emerald-50 border border-emerald-200 text-emerald-800' : 'bg-red-50 border border-red-200 text-red-800' ?>"><?= h($message) ?></div>
    <?php if ($ok): ?><a href="login.php" class="btn btn-dark w-full">Go to login</a><?php endif; ?>
  <?php else: ?>
    <ol class="text-sm text-stone-600 space-y-2 list-decimal list-inside">
      <li>Log in to your hosting <strong>cPanel → File Manager</strong> and open the website's <code>data</code> folder.</li>
      <li>Create a file named <code>reset-admin.txt</code> with one line: <code>username:NewPassword</code> (10+ characters).</li>
      <li>Refresh this page. The password changes and the file is deleted.</li>
    </ol>
  <?php endif; ?>
</div>
<?php admin_footer();
