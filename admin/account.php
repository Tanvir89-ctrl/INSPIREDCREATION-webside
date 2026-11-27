<?php
// Settings → Account & admins: change your password, add or remove admin logins, and how to recover a lost password
require __DIR__ . '/_inc.php';
require_admin();

$pdo = ic_db();
$me = (int) $_SESSION['admin_id'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    @set_time_limit(120); // password hashing is slow on purpose; give busy shared hosting time for it
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'password') {
        $stmt = $pdo->prepare('SELECT password_hash FROM admins WHERE id = ?');
        $stmt->execute([$me]);
        $hash = (string) $stmt->fetchColumn();
        $new = (string) ($_POST['new_password'] ?? '');
        if (!password_verify((string) ($_POST['current_password'] ?? ''), $hash)) {
            $errors[] = 'Your current password is not right.';
        } elseif (strlen($new) < 10) {
            $errors[] = 'The new password must be at least 10 characters.';
        } elseif ($new !== (string) ($_POST['new_password_confirm'] ?? '')) {
            $errors[] = 'The two new passwords do not match.';
        } else {
            $pdo->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $me]);
            session_regenerate_id(true);
            flash('Password changed. Use the new password next time you log in.');
            redirect('account.php');
        }
    }

    if ($action === 'add') {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $exists = $pdo->prepare('SELECT 1 FROM admins WHERE LOWER(username) = LOWER(?)');
        $exists->execute([$username]);
        if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) {
            $errors[] = 'Username must be 3–50 letters, numbers, dots, dashes or underscores.';
        } elseif ($exists->fetchColumn()) {
            $errors[] = "“{$username}” already exists.";
        } elseif (strlen($password) < 10) {
            $errors[] = 'The password must be at least 10 characters.';
        } else {
            $pdo->prepare('INSERT INTO admins (username, password_hash, created_at) VALUES (?, ?, ?)')
                ->execute([$username, password_hash($password, PASSWORD_DEFAULT), ic_now()]);
            flash("Admin “{$username}” added. Give them the password privately (WhatsApp / in person).");
            redirect('account.php');
        }
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $count = (int) $pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn();
        if ($id === $me) {
            $errors[] = 'You cannot remove your own login while using it.';
        } elseif ($count <= 1) {
            $errors[] = 'There must always be at least one admin.';
        } else {
            $pdo->prepare('DELETE FROM admins WHERE id = ?')->execute([$id]);
            flash('Admin login removed.');
            redirect('account.php');
        }
    }
}

$admins = $pdo->query('SELECT id, username, created_at FROM admins ORDER BY id')->fetchAll();
admin_header('Account & admins', 'account.php');
?>
<div class="mb-6">
  <p class="text-xs uppercase tracking-[0.25em] text-gold-600">Settings</p>
  <h1 class="font-serif text-3xl text-wood-900 mt-1">Account &amp; admins</h1>
  <p class="text-sm text-stone-500 mt-1">Change your password, add logins for helpers, and know how to get back in if a password is lost.</p>
</div>

<?php foreach ($errors as $e): ?>
  <div class="mb-4 rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800"><?= h($e) ?></div>
<?php endforeach; ?>

<div class="grid lg:grid-cols-2 gap-6 items-start">
  <form method="post" class="card p-5 sm:p-6 space-y-4">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="password">
    <h2 class="font-serif text-lg text-wood-900"><i class="fa-solid fa-key text-gold-600 text-sm"></i> Change my password</h2>
    <p class="text-xs text-stone-500">Logged in as <strong><?= h($_SESSION['admin_name'] ?? '') ?></strong>.</p>
    <div><label class="label" for="cur">Current password</label><input class="input" id="cur" name="current_password" type="password" required autocomplete="current-password"></div>
    <div><label class="label" for="new">New password (10+ characters)</label><input class="input" id="new" name="new_password" type="password" required minlength="10" autocomplete="new-password"></div>
    <div><label class="label" for="new2">Repeat new password</label><input class="input" id="new2" name="new_password_confirm" type="password" required minlength="10" autocomplete="new-password"></div>
    <p class="text-xs text-stone-400">Tip: a short sentence you remember is strong, e.g. “amar-dokan-kath-2026!”. Don't reuse your Facebook or bKash password.</p>
    <button class="btn btn-gold"><i class="fa-regular fa-floppy-disk"></i> Change password</button>
  </form>

  <div class="space-y-6">
    <section class="card p-5 sm:p-6">
      <h2 class="font-serif text-lg text-wood-900 mb-3"><i class="fa-solid fa-users-gear text-gold-600 text-sm"></i> Admin logins (<?= count($admins) ?>)</h2>
      <ul class="divide-y divide-cream-200">
        <?php foreach ($admins as $a): ?>
          <li class="flex items-center justify-between gap-3 py-2.5">
            <span><span class="font-semibold text-wood-900"><?= h($a['username']) ?></span><?= (int) $a['id'] === $me ? ' <span class="text-xs text-emerald-700">(you)</span>' : '' ?>
              <span class="block text-xs text-stone-400">Added <?= h(date('d M Y', strtotime($a['created_at']))) ?></span></span>
            <?php if ((int) $a['id'] !== $me): ?>
              <form method="post" onsubmit="return confirm('Remove the login “<?= h($a['username']) ?>”?');">
                <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                <button class="btn btn-danger !px-3 !py-1.5"><i class="fa-regular fa-trash-can"></i> Remove</button>
              </form>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
      <form method="post" class="mt-4 pt-4 border-t border-cream-200 grid sm:grid-cols-[1fr_1fr_auto] gap-2 items-end">
        <?= csrf_field() ?><input type="hidden" name="action" value="add">
        <div><label class="label" for="nu">New username</label><input class="input" id="nu" name="username" required pattern="[A-Za-z0-9_.\-]{3,50}" autocomplete="off"></div>
        <div><label class="label" for="np">Password (10+)</label><input class="input" id="np" name="password" type="password" required minlength="10" autocomplete="new-password"></div>
        <button class="btn btn-dark"><i class="fa-solid fa-user-plus"></i> Add</button>
      </form>
      <p class="text-xs text-stone-400 mt-2">Every admin can see orders and customer data, so only add people you trust. Remove a login when someone stops working with you.</p>
    </section>

    <section class="card p-5 sm:p-6">
      <h2 class="font-serif text-lg text-wood-900 mb-2"><i class="fa-solid fa-life-ring text-gold-600 text-sm"></i> If a password is lost</h2>
      <ol class="text-sm text-stone-600 space-y-1.5 list-decimal list-inside">
        <li>Log in to your hosting <strong>cPanel → File Manager</strong> and open the website's <code>data</code> folder.</li>
        <li>Create a file named <code>reset-admin.txt</code>. Inside, write one line: <code>username:NewPassword</code> (new password 10+ characters).</li>
        <li>Open <code>yourwebsite.com/admin/recover.php</code> in the browser — the password is changed and the file deletes itself.</li>
      </ol>
      <p class="text-xs text-stone-400 mt-2">Only someone with your hosting login can do this, so keep the cPanel password safe too.</p>
    </section>
  </div>
</div>
<?php admin_footer();
