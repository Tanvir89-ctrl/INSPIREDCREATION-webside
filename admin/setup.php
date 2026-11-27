<?php
// One-time setup: creates the database tables, imports the website's products and creates the first admin.
// It locks itself once an admin exists. Delete this file from the server after setup.
require __DIR__ . '/_inc.php';

$error = null;
$done = false;

try {
    $pdo = ic_db();
    ic_create_schema($pdo);
    ic_migrate($pdo);
    $locked = (int) $pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn() > 0;
} catch (Throwable $e) {
    $locked = false;
    $error = 'Could not connect to the database: ' . $e->getMessage();
}

if (!$error && !$locked && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) {
        $error = 'Username must be 3–50 letters, numbers, dots, dashes or underscores.';
    } elseif (strlen($password) < 10) {
        $error = 'Password must be at least 10 characters.';
    } elseif ($password !== ($_POST['password_confirm'] ?? '')) {
        $error = 'The two passwords do not match.';
    } else {
        $pdo->prepare('INSERT INTO admins (username, password_hash, created_at) VALUES (?, ?, ?)')
            ->execute([$username, password_hash($password, PASSWORD_DEFAULT), ic_now()]);

        // Import the starting product list once
        if ((int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn() === 0) {
            $seed = json_decode((string) file_get_contents(__DIR__ . '/../api/seed-products.json'), true) ?: [];
            $insert = $pdo->prepare('INSERT INTO products (id, title, category, price_bdt, price_usd, badge, image, wood, dimensions,
                    finish, description, rating, review_count, sort_order, is_active, is_customizable, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?)');
            foreach ($seed as $p) {
                $insert->execute([
                    $p['id'], $p['title'], $p['category'], $p['priceBDT'] ?? null, $p['priceUSD'] ?? null,
                    $p['badge'] ?? '', $p['image'], $p['wood'] ?? '', $p['dimensions'] ?? '', $p['finish'] ?? '',
                    $p['desc'] ?? '', $p['rating'] ?? null, $p['reviewCount'] ?? null, $p['sortOrder'] ?? 0,
                    empty($p['customizable']) ? 0 : 1, ic_now(),
                ]);
            }
        }
        $done = true;
    }
}

admin_header('Setup');
?>
<div class="max-w-lg mx-auto card p-6 sm:p-8 space-y-5">
  <div class="text-center space-y-2">
    <img src="../assets/logo-mark.png" alt="" class="w-14 h-14 mx-auto rounded-full object-contain p-1.5 bg-[#140E0C]">
    <h1 class="font-serif text-2xl text-wood-900">Admin panel setup</h1>
  </div>

  <?php if ($error): ?>
    <div class="rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800"><?= h($error) ?></div>
  <?php endif; ?>

  <?php if ($done): ?>
    <div class="rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-800 space-y-2">
      <p><strong>All set!</strong> Tables are created, your products are imported and your admin account is ready.</p>
      <p>For safety, <strong>delete <code>admin/setup.php</code></strong> from your hosting now.</p>
    </div>
    <a href="login.php" class="btn btn-dark w-full">Go to login</a>
  <?php elseif ($locked): ?>
    <p class="text-sm text-center">Setup is already complete. Please delete <code>admin/setup.php</code> from your hosting.</p>
    <a href="login.php" class="btn btn-dark w-full">Go to login</a>
  <?php elseif (!$error || $_SERVER['REQUEST_METHOD'] === 'POST'): ?>
    <p class="text-sm text-center text-stone-500">Create the admin account you'll use to manage orders and products.</p>
    <form method="post" class="space-y-4">
      <?= csrf_field() ?>
      <div>
        <label class="label" for="username">Username</label>
        <input class="input" id="username" name="username" required autocomplete="username" value="<?= h($_POST['username'] ?? '') ?>">
      </div>
      <div>
        <label class="label" for="password">Password (10+ characters)</label>
        <input class="input" id="password" name="password" type="password" required minlength="10" autocomplete="new-password">
      </div>
      <div>
        <label class="label" for="password_confirm">Repeat password</label>
        <input class="input" id="password_confirm" name="password_confirm" type="password" required minlength="10" autocomplete="new-password">
      </div>
      <button class="btn btn-gold w-full">Create admin &amp; finish setup</button>
    </form>
  <?php endif; ?>
</div>
<?php admin_footer();
