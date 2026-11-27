<?php
// Store Visibility: switch each product Online (shown in the shop) or Offline (hidden, not deleted)
require __DIR__ . '/_inc.php';
require_admin();

$pdo = ic_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $online = !empty($_POST['online']) ? 1 : 0;
    $ids = array_values(array_filter((array) ($_POST['ids'] ?? []), 'is_string'));
    if ($ids) {
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $pdo->prepare("UPDATE products SET is_active = ?, updated_at = ? WHERE id IN ($marks)")
            ->execute(array_merge([$online, ic_now()], $ids));
        flash(count($ids) === 1
            ? 'Product is now ' . ($online ? 'online — customers can see it.' : 'offline — hidden from the shop.')
            : count($ids) . ' products are now ' . ($online ? 'online.' : 'offline.'));
    }
    redirect('visibility.php?' . http_build_query(array_filter(['show' => (string) ($_POST['show'] ?? '')], 'strlen')));
}

$show = in_array($_GET['show'] ?? '', ['online', 'offline'], true) ? $_GET['show'] : '';
$all = $pdo->query('SELECT * FROM products ORDER BY sort_order, title')->fetchAll();
$onlineCount = count(array_filter($all, function ($p) { return (int) $p['is_active'] === 1; }));
$products = array_values(array_filter($all, function ($p) use ($show) {
    return $show === '' || ($show === 'online') === ((int) $p['is_active'] === 1);
}));

admin_header('Store Visibility', 'products.php');
?>
<div class="flex flex-wrap items-end justify-between gap-3 mb-6">
  <div>
    <p class="text-xs uppercase tracking-[0.25em] text-gold-600">Product</p>
    <h1 class="font-serif text-3xl text-wood-900 mt-1">Store Visibility</h1>
    <p class="text-sm text-stone-500 mt-1">Online products show in the shop. Offline products are hidden from customers but kept safe here.</p>
  </div>
  <div class="flex gap-3 text-sm">
    <span class="card px-4 py-2"><span class="font-serif text-xl text-emerald-700"><?= $onlineCount ?></span> online</span>
    <span class="card px-4 py-2"><span class="font-serif text-xl text-stone-500"><?= count($all) - $onlineCount ?></span> offline</span>
  </div>
</div>

<div class="flex flex-wrap items-center justify-between gap-3 mb-5">
  <div class="flex gap-2">
    <?php foreach (['' => 'All', 'online' => 'Online', 'offline' => 'Offline'] as $key => $label): ?>
      <a href="visibility.php<?= $key ? "?show=$key" : '' ?>" class="btn <?= $show === $key ? 'btn-dark' : 'btn-light' ?> !py-1.5"><?= $label ?></a>
    <?php endforeach; ?>
  </div>
  <?php if ($products): ?>
  <form method="post" class="flex gap-2">
    <?= csrf_field() ?>
    <input type="hidden" name="show" value="<?= h($show) ?>">
    <?php foreach ($products as $p): ?><input type="hidden" name="ids[]" value="<?= h($p['id']) ?>"><?php endforeach; ?>
    <button name="online" value="1" class="btn btn-light !py-1.5"><i class="fa-solid fa-eye text-emerald-600"></i> All shown online</button>
    <button name="online" value="0" class="btn btn-light !py-1.5" onclick="return confirm('Take all the products shown here offline?');"><i class="fa-solid fa-eye-slash text-stone-500"></i> All offline</button>
  </form>
  <?php endif; ?>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
  <?php foreach ($products as $p):
      $isOnline = (int) $p['is_active'] === 1; ?>
    <form method="post" class="card p-3 flex items-center gap-4 transition <?= $isOnline ? '' : 'opacity-70' ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="ids[]" value="<?= h($p['id']) ?>">
      <input type="hidden" name="show" value="<?= h($show) ?>">
      <img src="<?= h(asset_url($p['image'])) ?>" alt="" class="w-20 h-20 rounded-xl object-cover bg-cream-100 flex-shrink-0 <?= $isOnline ? '' : 'grayscale' ?>">
      <div class="flex-1 min-w-0">
        <a href="product.php?id=<?= h(rawurlencode($p['id'])) ?>" class="block font-semibold text-wood-900 truncate hover:underline"><?= h($p['title']) ?></a>
        <span class="block text-xs text-stone-500"><?= $p['price_bdt'] === null ? 'Price on request' : money($p['price_bdt']) ?></span>
        <span class="inline-flex items-center gap-1.5 mt-1.5 text-[11px] font-semibold <?= $isOnline ? 'text-emerald-700' : 'text-stone-500' ?>">
          <span class="w-2 h-2 rounded-full <?= $isOnline ? 'bg-emerald-500 animate-pulse' : 'bg-stone-400' ?>"></span><?= $isOnline ? 'Online' : 'Offline' ?>
        </span>
      </div>
      <!-- Flipping the switch submits right away -->
      <label class="relative flex-shrink-0 cursor-pointer" title="<?= $isOnline ? 'Take offline' : 'Put online' ?>">
        <input type="checkbox" name="online" value="1" class="peer sr-only" <?= $isOnline ? 'checked' : '' ?> onchange="this.form.submit()">
        <span class="block w-12 h-7 rounded-full bg-stone-300 transition peer-checked:bg-emerald-600"></span>
        <span class="absolute top-1 left-1 w-5 h-5 rounded-full bg-white shadow transition peer-checked:translate-x-5"></span>
      </label>
    </form>
  <?php endforeach; ?>
</div>
<?php if (!$products): ?>
  <p class="card p-6 text-sm text-stone-500">No products here.</p>
<?php endif; ?>
<?php admin_footer();
