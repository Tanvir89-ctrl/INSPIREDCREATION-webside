<?php
require __DIR__ . '/_inc.php';

if (!empty($_SESSION['admin_id'])) {
    redirect('index.php');
}

// Before setup has run there is no admin table (or no config) — send the owner to setup
try {
    $pdo = ic_db();
    $pdo->query('SELECT 1 FROM admins LIMIT 1');
    ic_migrate($pdo);
} catch (Throwable $e) {
    redirect('setup.php');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    // Password guessing is limited in the database (not the browser, which a guesser could reset):
    // 5 wrong tries for one username, or 10 from one IP address, lock it for 15 minutes
    $ip = ic_client_ip();
    $username = trim((string) ($_POST['username'] ?? ''));
    $userKey = strtolower($username);
    $locked = ic_attempt_count($pdo, 'login_ip', $ip, 900) >= 10
        || ($userKey !== '' && ic_attempt_count($pdo, 'login_user', $userKey, 900) >= 5);
    if ($locked) {
        $error = 'Too many wrong tries. For safety, login is paused for 15 minutes.';
        error_log("Admin login paused for user '$userKey' from $ip");
    } else {
        $stmt = $pdo->prepare('SELECT id, username, password_hash FROM admins WHERE username = ?');
        $stmt->execute([$username]);
        $admin = $stmt->fetch();
        if ($admin && password_verify((string) ($_POST['password'] ?? ''), $admin['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int) $admin['id'];
            $_SESSION['admin_name'] = $admin['username'];
            ic_attempt_clear($pdo, 'login_user', $userKey);
            ic_attempt_clear($pdo, 'login_ip', $ip);
            redirect('index.php');
        }
        ic_attempt_add($pdo, 'login_ip', $ip);
        if ($userKey !== '') {
            ic_attempt_add($pdo, 'login_user', $userKey);
        }
        usleep(400000);
        $error = 'Wrong username or password.';
    }
}

admin_header('Log in');
?>
<div class="max-w-sm mx-auto mt-10 card p-6 sm:p-8 space-y-5">
  <div class="text-center space-y-2">
    <img src="../assets/logo-mark.png" alt="" class="w-14 h-14 mx-auto rounded-full object-contain p-1.5 bg-[#140E0C]">
    <h1 class="font-serif text-2xl text-wood-900">Admin login</h1>
    <p class="text-xs uppercase tracking-[0.25em] text-gold-600">INSPIRED CREATIONS</p>
  </div>
  <?php if ($error): ?>
    <div class="rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800"><?= h($error) ?></div>
  <?php endif; ?>
  <form method="post" class="space-y-4">
    <?= csrf_field() ?>
    <div>
      <label class="label" for="username">Username</label>
      <input class="input" id="username" name="username" required autocomplete="username" autofocus>
    </div>
    <div>
      <label class="label" for="password">Password</label>
      <input class="input" id="password" name="password" type="password" required autocomplete="current-password">
    </div>
    <button class="btn btn-dark w-full">Log in</button>
  </form>
</div>
<?php admin_footer();
