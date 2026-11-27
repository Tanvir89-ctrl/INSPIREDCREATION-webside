<?php
// Settings → Security check: everything that keeps the shop safe in one list, with one-click fixes where possible
// (turn on the HTTPS redirect after checking SSL works, delete the setup file).
require __DIR__ . '/_inc.php';
require __DIR__ . '/_health.php';
require_admin();

$pdo = ic_db();
$htaccess = IC_ROOT . '/.htaccess';
const IC_HTTPS_BLOCK = "# BEGIN IC-HTTPS (managed by admin → Security check)\n<IfModule mod_rewrite.c>\nRewriteEngine On\nRewriteCond %{HTTPS} !=on\nRewriteCond %{HTTP:X-Forwarded-Proto} !https\nRewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]\n</IfModule>\n# END IC-HTTPS\n";
$current = is_file($htaccess) ? (string) file_get_contents($htaccess) : '';
$httpsOn = strpos($current, '# BEGIN IC-HTTPS') !== false;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'https_on') {
        // Only switch on when this domain really answers on https — otherwise the site would stop opening
        $host = preg_replace('/[^a-z0-9.\-:]/i', '', (string) ($_SERVER['HTTP_HOST'] ?? ''));
        $works = false;
        if (!ic_is_local_host() && $host !== '' && function_exists('curl_init')) {
            $ch = curl_init("https://$host/admin/login.php");
            curl_setopt_array($ch, [CURLOPT_NOBODY => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_FOLLOWLOCATION => false]);
            curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $works = $code >= 200 && $code < 400 && !curl_errno($ch);
        }
        if (ic_is_local_host()) {
            $error = 'HTTPS is only for the live website, not for testing on this computer.';
        } elseif (!$works) {
            $error = "https://$host does not work yet. In cPanel → SSL/TLS Status, run AutoSSL (free) for your domain, wait until it shows a green lock, then try again.";
        } elseif (@file_put_contents($htaccess, IC_HTTPS_BLOCK . ($current !== '' ? "\n" . $current : '')) === false) {
            $error = 'Could not write the .htaccess file. In cPanel → Domains, turn on “Force HTTPS Redirect” instead.';
        } else {
            flash('HTTPS is on — every visit now opens the secure https:// address.');
            redirect('https://' . $host . '/admin/security.php');
        }
    } elseif ($action === 'https_off') {
        $clean = preg_replace('/# BEGIN IC-HTTPS.*?# END IC-HTTPS\n?\n?/s', '', $current);
        @file_put_contents($htaccess, $clean);
        flash('HTTPS redirect turned off.');
        redirect('security.php');
    } elseif ($action === 'delete_setup') {
        if (ic_is_local_host()) {
            $error = 'Keep setup.php while testing on this computer — delete it on the live website.';
        } elseif (@unlink(__DIR__ . '/setup.php')) {
            flash('admin/setup.php deleted.');
            redirect('security.php');
        } else {
            $error = 'Could not delete it automatically. Delete admin/setup.php in cPanel → File Manager.';
        }
    }
}

$checks = ic_security_checks($pdo);
$okCount = count(array_filter($checks, function ($c) { return $c[1]; }));
admin_header('Security check', 'security.php');
?>
<div class="flex flex-wrap items-end justify-between gap-3 mb-6">
  <div>
    <p class="text-xs uppercase tracking-[0.25em] text-gold-600">Settings</p>
    <h1 class="font-serif text-3xl text-wood-900 mt-1">Security check</h1>
    <p class="text-sm text-stone-500 mt-1">Everything that keeps your shop, orders and customers safe — green means done.</p>
  </div>
  <span class="card px-4 py-2 text-sm font-semibold <?= $okCount === count($checks) ? 'text-emerald-700' : 'text-amber-700' ?>"><?= $okCount ?> / <?= count($checks) ?> safe</span>
</div>

<?php if ($error): ?>
  <div class="mb-6 rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800"><?= h($error) ?></div>
<?php endif; ?>

<div class="card divide-y divide-cream-200 mb-6">
  <?php foreach ($checks as [$id, $ok, $title, $text, $link]): ?>
    <div class="flex items-start gap-3 p-4" id="<?= h($id) ?>">
      <span class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 <?= $ok ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' ?>"><i class="fa-solid <?= $ok ? 'fa-check' : 'fa-exclamation' ?>"></i></span>
      <div class="flex-1 min-w-0"><p class="font-semibold text-wood-900"><?= h($title) ?></p><p class="text-sm text-stone-500"><?= h($text) ?></p></div>
      <?php if (!$ok && strpos($link, 'security.php') !== 0): ?><a href="<?= h($link) ?>" class="btn btn-light !py-1.5 !px-3 text-xs">Fix</a><?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>

<div class="grid lg:grid-cols-2 gap-6 items-start">
  <section class="card p-5 sm:p-6 space-y-3" id="https">
    <h2 class="font-serif text-lg text-wood-900"><i class="fa-solid fa-lock text-gold-600 text-sm"></i> Always use HTTPS</h2>
    <p class="text-sm text-stone-600">Sends every visitor to the secure <strong>https://</strong> address (green lock). First get free SSL in <strong>cPanel → SSL/TLS Status → Run AutoSSL</strong>; the button checks that it works before switching on.</p>
    <p class="text-sm">Status: <strong class="<?= $httpsOn ? 'text-emerald-700' : 'text-stone-600' ?>"><?= $httpsOn ? 'Redirect to HTTPS is ON' : 'Off' ?></strong></p>
    <form method="post"><?= csrf_field() ?>
      <?php if ($httpsOn): ?>
        <button class="btn btn-light" name="action" value="https_off"><i class="fa-solid fa-toggle-on"></i> Turn redirect off</button>
      <?php else: ?>
        <button class="btn btn-dark" name="action" value="https_on"><i class="fa-solid fa-shield-halved"></i> Check SSL &amp; turn on HTTPS</button>
      <?php endif; ?>
    </form>
  </section>

  <section class="card p-5 sm:p-6 space-y-3" id="setup">
    <h2 class="font-serif text-lg text-wood-900"><i class="fa-solid fa-file-circle-xmark text-gold-600 text-sm"></i> Remove the setup file</h2>
    <p class="text-sm text-stone-600"><code>admin/setup.php</code> was only needed once. It locks itself, but deleting it is safest.</p>
    <?php if (is_file(__DIR__ . '/setup.php')): ?>
      <form method="post" onsubmit="return confirm('Delete admin/setup.php from the server?');"><?= csrf_field() ?>
        <button class="btn btn-danger" name="action" value="delete_setup"><i class="fa-regular fa-trash-can"></i> Delete setup.php</button>
      </form>
    <?php else: ?>
      <p class="text-sm text-emerald-700"><i class="fa-solid fa-check"></i> Already deleted.</p>
    <?php endif; ?>
  </section>

  <section class="card p-5 sm:p-6 space-y-2" id="cron">
    <h2 class="font-serif text-lg text-wood-900"><i class="fa-solid fa-clock-rotate-left text-gold-600 text-sm"></i> Automatic jobs (cron)</h2>
    <p class="text-sm text-stone-600">Moves missed orders to Hold, updates courier statuses and sends reminders. In <strong>cPanel → Cron Jobs</strong> add, every 15 minutes:</p>
    <code class="block text-xs bg-cream-50 border border-cream-300 rounded-lg p-2 break-all">php <?= h(realpath(IC_ROOT . '/api/cron.php') ?: '/home/USER/public_html/api/cron.php') ?></code>
  </section>

  <section class="card p-5 sm:p-6 space-y-2" id="cronkey">
    <h2 class="font-serif text-lg text-wood-900"><i class="fa-solid fa-key text-gold-600 text-sm"></i> Other safety settings (api/config.php)</h2>
    <ul class="text-sm text-stone-600 space-y-1 list-disc list-inside">
      <li><code>cron_key</code>: a long random text (20+ characters).</li>
      <li><code>'debug' =&gt; false</code> (or leave it out) so visitors never see errors.</li>
      <li>Never upload the <code>data</code> folder from your computer — the live site makes its own database.</li>
    </ul>
  </section>
</div>
<?php admin_footer();
