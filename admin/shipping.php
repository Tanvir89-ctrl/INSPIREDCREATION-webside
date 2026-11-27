<?php
// Shipping: per-product delivery charges (Inside / Outside Dhaka) and free delivery.
// Tabs: add (set charges for chosen products + shop defaults), all (edit every product), free (free delivery products).
require __DIR__ . '/_inc.php';
require_admin();

$pdo = ic_db();
$tab = in_array($_GET['tab'] ?? '', ['add', 'all', 'free'], true) ? $_GET['tab'] : 'all';
$defaults = ic_shipping_defaults($pdo);

$fee = function (string $key) {
    $value = trim((string) ($_POST[$key] ?? ''));
    return $value === '' ? null : max(0, (int) $value);
};
$idList = function () {
    return array_values(array_filter((array) ($_POST['ids'] ?? []), 'is_string'));
};
$updateIds = function (string $sql, array $params, array $ids) use ($pdo) {
    if (!$ids) {
        return 0;
    }
    $marks = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("$sql WHERE id IN ($marks)");
    $stmt->execute(array_merge($params, $ids));
    return count($ids);
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'defaults') {
        ic_set_setting($pdo, 'ship_default_dhaka', (string) (int) $fee('default_dhaka'));
        ic_set_setting($pdo, 'ship_default_outside', (string) (int) $fee('default_outside'));
        ic_set_setting($pdo, 'ship_free_min', (string) (int) $fee('free_min'));
        flash('Shop default delivery charges saved.');
    } elseif ($action === 'apply') {
        $reset = !empty($_POST['reset']);
        $n = $updateIds('UPDATE products SET ship_dhaka = ?, ship_outside = ?, updated_at = ?',
            [$reset ? null : $fee('ship_dhaka'), $reset ? null : $fee('ship_outside'), ic_now()], $idList());
        flash($n ? "Delivery charge " . ($reset ? 'reset to default' : 'saved') . " for $n product" . ($n === 1 ? '' : 's') . '.' : 'Choose at least one product.');
    } elseif ($action === 'save_all') {
        $stmt = $pdo->prepare('UPDATE products SET ship_dhaka = ?, ship_outside = ?, updated_at = ? WHERE id = ?');
        foreach ((array) ($_POST['dhaka'] ?? []) as $id => $dhaka) {
            $outside = $_POST['outside'][$id] ?? '';
            $stmt->execute([
                trim((string) $dhaka) === '' ? null : max(0, (int) $dhaka),
                trim((string) $outside) === '' ? null : max(0, (int) $outside),
                ic_now(), (string) $id,
            ]);
        }
        flash('All delivery charges saved.');
    } elseif ($action === 'free_on' || $action === 'free_off') {
        $on = $action === 'free_on';
        $n = $updateIds('UPDATE products SET free_shipping = ?, updated_at = ?', [$on ? 1 : 0, ic_now()], $idList());
        $verb = $n === 1 ? 'ships' : 'ship';
        flash($n ? "$n product" . ($n === 1 ? '' : 's') . ($on ? " now $verb free." : ' no longer ' . ($n === 1 ? 'ships' : 'ship') . ' free.') : 'Choose at least one product.');
    }
    redirect("shipping.php?tab=$tab");
}

$products = $pdo->query('SELECT * FROM products ORDER BY sort_order, title')->fetchAll();
$chargeText = function (array $p, string $region) use ($defaults) {
    if (!empty($p['free_shipping'])) {
        return '<span class="text-emerald-700 font-semibold">Free</span>';
    }
    $own = $p[$region === 'dhaka' ? 'ship_dhaka' : 'ship_outside'];
    return $own === null ? money($defaults[$region]) . ' <span class="text-[10px] text-stone-400">default</span>' : money((int) $own);
};

admin_header('Shipping', 'shipping.php');
?>
<style>
  /* "Motion card" for free-delivery products */
  @keyframes freeShine { 0% { transform: translateX(-120%) skewX(-20deg); } 60%, 100% { transform: translateX(260%) skewX(-20deg); } }
  @keyframes freeBob { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-3px); } }
  .free-card { position: relative; overflow: hidden; }
  .free-card::after { content: ''; position: absolute; inset: 0 auto 0 0; width: 40%; background: linear-gradient(90deg, transparent, rgba(255,255,255,.45), transparent); animation: freeShine 3.2s ease-in-out infinite; pointer-events: none; }
  .free-truck { animation: freeBob 1.6s ease-in-out infinite; }
  @media (prefers-reduced-motion: reduce) { .free-card::after, .free-truck { animation: none; } }
</style>

<div class="flex flex-wrap items-end justify-between gap-3 mb-6">
  <div>
    <p class="text-xs uppercase tracking-[0.25em] text-gold-600">Shipping</p>
    <h1 class="font-serif text-3xl text-wood-900 mt-1">Delivery charges</h1>
    <p class="text-sm text-stone-500 mt-1">Shop default: <?= money($defaults['dhaka']) ?> inside Dhaka · <?= money($defaults['outside']) ?> outside Dhaka<?= $defaults['freeMin'] ? ' · free on orders of ' . money($defaults['freeMin']) . '+' : '' ?>. A cart pays the highest charge among its products, once.</p>
  </div>
</div>

<div class="flex flex-wrap gap-2 mb-6">
  <?php foreach (['add' => ['Add shipping', 'fa-plus'], 'all' => ['All shipping', 'fa-table-list'], 'free' => ['Free shipping', 'fa-gift']] as $key => [$label, $icon]): ?>
    <a href="shipping.php?tab=<?= $key ?>" class="btn <?= $tab === $key ? 'btn-dark' : 'btn-light' ?>"><i class="fa-solid <?= $icon ?>"></i> <?= $label ?></a>
  <?php endforeach; ?>
</div>

<?php if ($tab === 'add'): ?>
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <form method="post" class="lg:col-span-2 card p-5 sm:p-6 space-y-5" id="applyForm">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="apply">
      <div>
        <h2 class="font-serif text-lg text-wood-900">Set a delivery charge</h2>
        <p class="text-xs text-stone-500">Choose products, enter the charges and apply. Empty boxes use the shop default.</p>
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="rounded-2xl bg-cream-50 border border-cream-300 p-4">
          <label class="label" for="ship_dhaka"><i class="fa-solid fa-city text-gold-600"></i> Inside Dhaka</label>
          <div class="relative"><span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-stone-400">৳</span>
            <input class="input !pl-8 text-lg font-semibold" id="ship_dhaka" name="ship_dhaka" type="number" min="0" placeholder="<?= $defaults['dhaka'] ?>"></div>
        </div>
        <div class="rounded-2xl bg-cream-50 border border-cream-300 p-4">
          <label class="label" for="ship_outside"><i class="fa-solid fa-map-location-dot text-gold-600"></i> Outside Dhaka</label>
          <div class="relative"><span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-stone-400">৳</span>
            <input class="input !pl-8 text-lg font-semibold" id="ship_outside" name="ship_outside" type="number" min="0" placeholder="<?= $defaults['outside'] ?>"></div>
        </div>
      </div>

      <div>
        <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
          <p class="label !mb-0">Products (<span id="pickedCount">0</span> chosen)</p>
          <div class="flex flex-wrap gap-1.5 text-xs" id="catFilter">
            <button type="button" data-cat="" class="btn btn-dark !px-3 !py-1 !text-xs">All</button>
            <?php foreach (['boxes' => 'Gift Boxes', 'wall' => 'Wall Decor', 'home' => 'Home', 'keepsakes' => 'Keepsakes', 'corporate' => 'Corporate'] as $key => $label): ?>
              <button type="button" data-cat="<?= $key ?>" class="btn btn-light !px-3 !py-1 !text-xs"><?= $label ?></button>
            <?php endforeach; ?>
            <button type="button" id="pickAll" class="btn btn-light !px-3 !py-1 !text-xs">Select shown</button>
          </div>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 max-h-[28rem] overflow-y-auto pr-1">
          <?php foreach ($products as $p): ?>
            <label class="pick cursor-pointer" data-cat="<?= h($p['category']) ?>">
              <input type="checkbox" name="ids[]" value="<?= h($p['id']) ?>" class="peer sr-only">
              <span class="block rounded-2xl border-2 border-cream-300 bg-white overflow-hidden transition peer-checked:border-wood-800 peer-checked:shadow-md">
                <span class="relative block">
                  <img src="<?= h(asset_url($p['image'])) ?>" alt="" class="w-full aspect-[4/3] object-cover">
                  <span class="check absolute top-2 right-2 w-6 h-6 rounded-full bg-white/90 border border-cream-300 flex items-center justify-center text-[11px] text-transparent"><i class="fa-solid fa-check"></i></span>
                </span>
                <span class="block p-2.5">
                  <span class="block text-xs font-semibold text-wood-900 truncate"><?= h($p['title']) ?></span>
                  <span class="block text-[11px] text-stone-500 mt-0.5">Dhaka <?= strip_tags($chargeText($p, 'dhaka')) ?> · Out <?= strip_tags($chargeText($p, 'outside')) ?></span>
                </span>
              </span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="flex flex-wrap gap-2">
        <button class="btn btn-gold"><i class="fa-solid fa-check"></i> Apply to chosen products</button>
        <button name="reset" value="1" class="btn btn-light"><i class="fa-solid fa-rotate-left"></i> Reset chosen to default</button>
      </div>
    </form>

    <form method="post" class="card p-5 sm:p-6 space-y-4 self-start">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="defaults">
      <div>
        <h2 class="font-serif text-lg text-wood-900">Shop default</h2>
        <p class="text-xs text-stone-500">Used for every product without its own charge.</p>
      </div>
      <div><label class="label" for="default_dhaka">Inside Dhaka (৳)</label><input class="input" id="default_dhaka" name="default_dhaka" type="number" min="0" value="<?= $defaults['dhaka'] ?>"></div>
      <div><label class="label" for="default_outside">Outside Dhaka (৳)</label><input class="input" id="default_outside" name="default_outside" type="number" min="0" value="<?= $defaults['outside'] ?>"></div>
      <div>
        <label class="label" for="free_min">Free delivery from (৳)</label>
        <input class="input" id="free_min" name="free_min" type="number" min="0" value="<?= $defaults['freeMin'] ?>">
        <p class="text-xs text-stone-400 mt-1">Cart total at or above this ships free. 0 = off.</p>
      </div>
      <button class="btn btn-dark w-full">Save defaults</button>
    </form>
  </div>

  <script>
    (() => {
      const picks = [...document.querySelectorAll('.pick')];
      const count = () => {
        document.getElementById('pickedCount').textContent = picks.filter(p => p.querySelector('input').checked).length;
        picks.forEach(p => p.querySelector('.check').classList.toggle('!bg-wood-800', p.querySelector('input').checked));
        picks.forEach(p => p.querySelector('.check').classList.toggle('!text-white', p.querySelector('input').checked));
      };
      picks.forEach(p => p.querySelector('input').addEventListener('change', count));
      document.querySelectorAll('#catFilter [data-cat]').forEach(btn => btn.addEventListener('click', () => {
        document.querySelectorAll('#catFilter [data-cat]').forEach(b => { b.classList.toggle('btn-dark', b === btn); b.classList.toggle('btn-light', b !== btn); });
        picks.forEach(p => { p.hidden = btn.dataset.cat !== '' && p.dataset.cat !== btn.dataset.cat; });
      }));
      document.getElementById('pickAll').addEventListener('click', () => { picks.filter(p => !p.hidden).forEach(p => { p.querySelector('input').checked = true; }); count(); });
      document.getElementById('applyForm').addEventListener('submit', (e) => {
        if (!picks.some(p => p.querySelector('input').checked)) { e.preventDefault(); alert('Choose at least one product first.'); }
      });
      count();
    })();
  </script>

<?php elseif ($tab === 'all'): ?>
  <form method="post" class="card overflow-hidden">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_all">
    <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 border-b border-cream-300">
      <p class="text-sm text-stone-500">Edit any charge and save. Leave a box empty to use the default (<?= money($defaults['dhaka']) ?> / <?= money($defaults['outside']) ?>).</p>
      <button class="btn btn-dark"><i class="fa-regular fa-floppy-disk"></i> Save all changes</button>
    </div>
    <div class="overflow-x-auto">
      <table class="min-w-full divide-y divide-cream-200">
        <thead class="bg-cream-50"><tr><th class="th">Product</th><th class="th">Inside Dhaka</th><th class="th">Outside Dhaka</th><th class="th">Delivery</th></tr></thead>
        <tbody class="divide-y divide-cream-200">
          <?php foreach ($products as $p): ?>
            <tr class="hover:bg-cream-50 <?= $p['is_active'] ? '' : 'opacity-60' ?>">
              <td class="td">
                <div class="flex items-center gap-3">
                  <img src="<?= h(asset_url($p['image'])) ?>" alt="" class="w-11 h-11 rounded-lg object-cover bg-cream-100 flex-shrink-0">
                  <a href="product.php?id=<?= h(rawurlencode($p['id'])) ?>" class="font-semibold text-wood-900 hover:underline"><?= h($p['title']) ?></a>
                </div>
              </td>
              <td class="td"><div class="relative w-28"><span class="absolute left-3 top-1/2 -translate-y-1/2 text-stone-400 text-xs">৳</span>
                <input class="input !py-2 !pl-7" name="dhaka[<?= h($p['id']) ?>]" type="number" min="0" value="<?= h($p['ship_dhaka']) ?>" placeholder="<?= $defaults['dhaka'] ?>"></div></td>
              <td class="td"><div class="relative w-28"><span class="absolute left-3 top-1/2 -translate-y-1/2 text-stone-400 text-xs">৳</span>
                <input class="input !py-2 !pl-7" name="outside[<?= h($p['id']) ?>]" type="number" min="0" value="<?= h($p['ship_outside']) ?>" placeholder="<?= $defaults['outside'] ?>"></div></td>
              <td class="td">
                <?php if ($p['free_shipping']): ?>
                  <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800"><i class="fa-solid fa-truck-fast"></i> Free</span>
                <?php else: ?>
                  <span class="text-xs text-stone-500">Charged</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </form>

<?php else:
    $free = array_values(array_filter($products, function ($p) { return !empty($p['free_shipping']); }));
    $paid = array_values(array_filter($products, function ($p) { return empty($p['free_shipping']); })); ?>
  <section class="mb-8">
    <h2 class="font-serif text-xl text-wood-900 mb-4">Free delivery products <span class="text-sm font-sans text-stone-400">(<?= count($free) ?>)</span></h2>
    <?php if (!$free): ?>
      <p class="card p-6 text-sm text-stone-500">No free-delivery products yet. Choose some below.</p>
    <?php else: ?>
      <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
        <?php foreach ($free as $p): ?>
          <form method="post" class="free-card rounded-2xl bg-gradient-to-br from-emerald-600 to-emerald-800 text-white p-4 flex items-center gap-4 shadow-lg">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="free_off">
            <input type="hidden" name="ids[]" value="<?= h($p['id']) ?>">
            <img src="<?= h(asset_url($p['image'])) ?>" alt="" class="w-16 h-16 rounded-xl object-cover ring-2 ring-white/40 flex-shrink-0">
            <div class="flex-1 min-w-0">
              <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-emerald-100"><i class="fa-solid fa-truck-fast free-truck inline-block"></i> Free Delivery</p>
              <p class="font-semibold truncate"><?= h($p['title']) ?></p>
              <p class="text-xs text-emerald-100"><?= $p['price_bdt'] === null ? 'Price on request' : money($p['price_bdt']) ?></p>
            </div>
            <button class="relative z-10 text-xs font-semibold rounded-lg bg-white/15 hover:bg-white/25 px-3 py-1.5" title="Charge delivery again">Remove</button>
          </form>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <?php if ($paid): ?>
  <form method="post" class="card p-5 sm:p-6">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="free_on">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
      <div>
        <h2 class="font-serif text-lg text-wood-900">Make delivery free</h2>
        <p class="text-xs text-stone-500">Chosen products show an animated “Free Delivery” card on the website and cost no delivery.</p>
      </div>
      <button class="btn btn-gold"><i class="fa-solid fa-gift"></i> Make chosen free</button>
    </div>
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
      <?php foreach ($paid as $p): ?>
        <label class="cursor-pointer">
          <input type="checkbox" name="ids[]" value="<?= h($p['id']) ?>" class="peer sr-only">
          <span class="flex items-center gap-3 rounded-2xl border-2 border-cream-300 bg-white p-2.5 transition peer-checked:border-emerald-600 peer-checked:bg-emerald-50">
            <img src="<?= h(asset_url($p['image'])) ?>" alt="" class="w-12 h-12 rounded-lg object-cover flex-shrink-0">
            <span class="min-w-0"><span class="block text-xs font-semibold text-wood-900 truncate"><?= h($p['title']) ?></span>
              <span class="block text-[11px] text-stone-500"><?= strip_tags($chargeText($p, 'dhaka')) ?> / <?= strip_tags($chargeText($p, 'outside')) ?></span></span>
          </span>
        </label>
      <?php endforeach; ?>
    </div>
  </form>
  <?php endif; ?>
<?php endif; ?>
<?php admin_footer();
