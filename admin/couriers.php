<?php
// Courier connections: connect Steadfast / Pathao / RedX once, test them, choose the default for one-click sending.
require __DIR__ . '/_inc.php';
require_admin();

$pdo = ic_db();
$testResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $courier = (string) ($_POST['courier'] ?? '');
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'default' && isset(IC_COURIERS[$courier])) {
        ic_set_setting($pdo, 'courier_default', $courier);
        flash(IC_COURIERS[$courier]['label'] . ' is now the default courier for one-click sending.');
        redirect('couriers.php');
    }
    if (isset(IC_COURIERS[$courier]) && in_array($action, ['save', 'test'], true)) {
        $info = IC_COURIERS[$courier];
        $settings = ic_courier_settings($pdo, $courier);
        if ($action === 'save') {
            foreach ($info['fields'] as $field => $label) {
                $value = trim((string) ($_POST[$field] ?? ''));
                // Secrets are never shown again; an empty box keeps the saved value
                if ($value !== '' || !in_array($field, $info['secrets'], true)) {
                    $settings[$field] = $value;
                }
            }
            $settings['enabled'] = !empty($_POST['enabled']) ? 1 : 0;
            $base = trim((string) ($_POST['base_url'] ?? ''));
            $settings['base_url'] = $base !== '' ? $base : $info['base_url'];
            ic_courier_save_settings($pdo, $courier, $settings);
            if ($courier === 'pathao') {
                ic_set_setting($pdo, 'pathao_token', '{}'); // log in again with the new details
            }
        }
        try {
            $testResult = [$courier, true, ic_courier_test($pdo, $courier)];
        } catch (Throwable $e) {
            $testResult = [$courier, false, $e->getMessage()];
        }
        if ($action === 'save') {
            flash($info['label'] . ' settings saved. ' . ($testResult[1] ? '✓ ' : '✗ ') . $testResult[2]);
            redirect('couriers.php');
        }
    }
}

$default = ic_default_courier($pdo);

// Per-courier delivery report from this shop's own orders
$report = [];
foreach ($pdo->query("SELECT courier, status, COUNT(*) AS n FROM orders WHERE courier IS NOT NULL GROUP BY courier, status") as $row) {
    $key = ic_courier_key_from_label($row['courier']) ?? $row['courier'];
    $report[$key][$row['status']] = (int) $row['n'];
}

admin_header('Courier', 'couriers.php');
?>
<div class="flex flex-wrap items-end justify-between gap-3 mb-6">
  <div>
    <p class="text-xs uppercase tracking-[0.25em] text-gold-600">Courier</p>
    <h1 class="font-serif text-3xl text-wood-900 mt-1">Courier connections</h1>
    <p class="text-sm text-stone-500 mt-1">Connect your courier accounts once. Then any order goes to the courier in one click, and its status updates by itself.</p>
  </div>
  <?php if ($default): ?>
    <span class="card px-4 py-2 text-sm"><i class="fa-solid fa-bolt text-gold-500"></i> One-click courier: <strong><?= h(IC_COURIERS[$default]['label']) ?></strong></span>
  <?php endif; ?>
</div>

<?php if ($testResult): ?>
  <div class="mb-6 rounded-xl px-4 py-3 text-sm <?= $testResult[1] ? 'bg-emerald-50 border border-emerald-200 text-emerald-800' : 'bg-red-50 border border-red-200 text-red-800' ?>">
    <strong><?= h(IC_COURIERS[$testResult[0]]['label']) ?>:</strong> <?= h($testResult[2]) ?>
  </div>
<?php endif; ?>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
  <?php foreach (IC_COURIERS as $key => $info):
      $s = ic_courier_settings($pdo, $key);
      $connected = ic_courier_connected($pdo, $key);
      $stats = $report[$key] ?? [];
      $done = ($stats['delivered'] ?? 0) + ($stats['partial'] ?? 0) + ($stats['returned'] ?? 0);
      $successRate = $done ? (int) round(($stats['delivered'] ?? 0) / $done * 100) : null;
      $onTheWay = ($stats['shipping'] ?? 0) + ($stats['waiting'] ?? 0);
      $colors = ['steadfast' => 'from-emerald-700 to-emerald-900', 'pathao' => 'from-red-600 to-red-800', 'redx' => 'from-rose-700 to-red-900'][$key]; ?>
    <section class="card overflow-hidden flex flex-col <?= $default === $key ? 'ring-2 ring-gold-500' : '' ?>">
      <div class="bg-gradient-to-br <?= $colors ?> text-white p-5">
        <div class="flex items-start justify-between gap-3">
          <div>
            <p class="font-serif text-2xl"><?= h($info['label']) ?></p>
            <p class="text-xs text-white/80 mt-1"><?= h($info['help']) ?></p>
          </div>
          <span class="flex-shrink-0 inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold <?= $connected ? 'bg-white text-emerald-800' : 'bg-white/15 text-white' ?>">
            <span class="w-2 h-2 rounded-full <?= $connected ? 'bg-emerald-500 animate-pulse' : 'bg-white/60' ?>"></span><?= $connected ? 'Connected' : 'Not connected' ?>
          </span>
        </div>
        <div class="grid grid-cols-3 gap-2 mt-4 text-center">
          <div class="rounded-xl bg-white/10 py-2"><p class="font-serif text-xl"><?= $onTheWay ?></p><p class="text-[10px] uppercase tracking-wider text-white/75">On the way</p></div>
          <div class="rounded-xl bg-white/10 py-2"><p class="font-serif text-xl"><?= $stats['delivered'] ?? 0 ?></p><p class="text-[10px] uppercase tracking-wider text-white/75">Delivered</p></div>
          <div class="rounded-xl bg-white/10 py-2"><p class="font-serif text-xl"><?= $successRate === null ? '—' : $successRate . '%' ?></p><p class="text-[10px] uppercase tracking-wider text-white/75">Success</p></div>
        </div>
      </div>

      <form method="post" class="p-5 space-y-3 flex-1 flex flex-col">
        <?= csrf_field() ?>
        <input type="hidden" name="courier" value="<?= $key ?>">
        <?php foreach ($info['fields'] as $field => $label):
            $isSecret = in_array($field, $info['secrets'], true);
            $saved = (string) ($s[$field] ?? ''); ?>
          <div>
            <label class="label" for="<?= "$key-$field" ?>"><?= h($label) ?></label>
            <input class="input" id="<?= "$key-$field" ?>" name="<?= h($field) ?>" autocomplete="off"
              type="<?= $isSecret ? 'password' : 'text' ?>"
              value="<?= $isSecret ? '' : h($saved) ?>"
              placeholder="<?= $isSecret && $saved !== '' ? 'Saved ••••' . h(substr($saved, -4)) . ' — leave empty to keep' : '' ?>">
          </div>
        <?php endforeach; ?>
        <details class="text-xs">
          <summary class="cursor-pointer text-stone-500">Advanced</summary>
          <label class="label mt-2" for="<?= $key ?>-base">API address</label>
          <input class="input" id="<?= $key ?>-base" name="base_url" value="<?= h($s['base_url']) ?>">
        </details>
        <label class="flex items-center gap-2 text-sm">
          <input type="checkbox" name="enabled" value="1" <?= !empty($s['enabled']) ? 'checked' : '' ?> class="rounded"> Use this courier
        </label>
        <div class="mt-auto pt-2 flex flex-wrap gap-2">
          <button name="action" value="save" class="btn btn-dark flex-1"><i class="fa-solid fa-plug"></i> Save &amp; connect</button>
          <?php if ($connected): ?>
            <button name="action" value="test" class="btn btn-light"><i class="fa-solid fa-stethoscope"></i> Test</button>
          <?php endif; ?>
        </div>
      </form>
      <?php if ($connected): ?>
        <form method="post" class="px-5 pb-5">
          <?= csrf_field() ?>
          <input type="hidden" name="courier" value="<?= $key ?>">
          <input type="hidden" name="action" value="default">
          <?php if ($default === $key): ?>
            <p class="text-center text-sm font-semibold text-gold-700"><i class="fa-solid fa-bolt"></i> Default for one-click sending</p>
          <?php else: ?>
            <button class="btn btn-light w-full"><i class="fa-solid fa-bolt text-gold-500"></i> Make default for one click</button>
          <?php endif; ?>
        </form>
      <?php endif; ?>
    </section>
  <?php endforeach; ?>
</div>

<section class="card p-5 sm:p-6 mt-6">
  <h2 class="font-serif text-lg text-wood-900 mb-3">How one-click sending works</h2>
  <ol class="grid grid-cols-1 md:grid-cols-4 gap-4 text-sm">
    <li class="rounded-xl bg-cream-50 border border-cream-300 p-4"><strong class="block text-wood-900">1. Connect</strong>Paste your courier keys above and press Save &amp; connect — it tests them right away.</li>
    <li class="rounded-xl bg-cream-50 border border-cream-300 p-4"><strong class="block text-wood-900">2. Confirm orders</strong>Confirm the order (or tick many in the Orders list).</li>
    <li class="rounded-xl bg-cream-50 border border-cream-300 p-4"><strong class="block text-wood-900">3. One click</strong>“Send to courier” books the parcel with the COD amount filled in. The order moves to Shipping.</li>
    <li class="rounded-xl bg-cream-50 border border-cream-300 p-4"><strong class="block text-wood-900">4. Auto updates</strong>Every 15 minutes the status syncs: Waiting → Delivery / Partial / Return.</li>
  </ol>
  <p class="text-xs text-stone-500 mt-4"><i class="fa-solid fa-circle-info text-gold-600"></i> RedX needs the delivery area: it is picked automatically from the address when possible, otherwise choose it on the order page. Pathao can take city / zone / area from the order page.</p>
</section>
<?php admin_footer();
