<?php
// Landing Pages: every product's sales page (on/off, design, stats) and the template gallery
require __DIR__ . '/_inc.php';
require_admin();

$pdo = ic_db();
$tab = ($_GET['tab'] ?? '') === 'templates' ? 'templates' : 'pages';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $action = (string) ($_POST['action'] ?? '');
    $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->execute([(string) ($_POST['id'] ?? '')]);
    $product = $stmt->fetch();
    if ($action === 'toggle' && $product) {
        $on = !ic_landing_for($pdo, $product)['enabled'];
        ic_landing_save($pdo, $product['id'], ['is_enabled' => $on ? 1 : 0]);
        flash($on ? "Landing page for “{$product['title']}” is now ON." : "Landing page for “{$product['title']}” is now OFF — the shop shows the normal product page.");
        redirect('landing.php');
    }
    if ($action === 'settings') {
        ic_set_setting($pdo, 'landing_auto_on', empty($_POST['auto_on']) ? '0' : '1');
        flash('Saved. ' . (empty($_POST['auto_on']) ? 'New products start with the landing page off.' : 'New products get a landing page automatically.'));
        redirect('landing.php');
    }
    $key = (string) ($_POST['template'] ?? '');
    if (isset(IC_LANDING_TEMPLATES[$key])) {
        $name = IC_LANDING_TEMPLATES[$key]['name'];
        if ($action === 'default_template') {
            ic_set_setting($pdo, 'landing_default_template', $key);
            flash("“{$name}” is now the design for new products and for pages you have not styled yet.");
        } elseif ($action === 'apply_all') {
            ic_set_setting($pdo, 'landing_default_template', $key);
            $pdo->prepare('UPDATE landing_pages SET template = ?, updated_at = ?')->execute([$key, ic_now()]);
            flash("“{$name}” is now used on every landing page.");
        }
    }
    redirect('landing.php?tab=templates');
}

$products = $pdo->query('SELECT * FROM products ORDER BY sort_order, title')->fetchAll();
$rows = [];
foreach ($pdo->query('SELECT * FROM landing_pages') as $row) {
    $rows[$row['product_id']] = $row;
}
$orders = ic_landing_order_counts($pdo);
$defaultTpl = ic_landing_default_template($pdo);
$pages = [];
foreach ($products as $p) {
    $pages[] = ['p' => $p, 'lp' => ic_landing_for($pdo, $p, $rows[$p['id']] ?? null)];
}
$onCount = count(array_filter($pages, function ($x) { return $x['lp']['enabled']; }));
$totalViews = array_sum(array_map(function ($x) { return $x['lp']['views']; }, $pages));
$totalOrders = array_sum($orders);
$sample = null;
foreach ($products as $p) {
    if ($p['price_bdt'] !== null && $p['is_active']) {
        $sample = $p;
        break;
    }
}
$sample = $sample ?? ($products[0] ?? null);

$swatch = function (array $c, string $size = 'w-4 h-4'): string {
    $html = '<span class="inline-flex -space-x-1">';
    foreach (['bg', 'brand', 'accent', 'cta'] as $k) {
        $html .= '<span class="' . $size . ' rounded-full ring-2 ring-white" style="background:' . h($c[$k]) . '"></span>';
    }
    return $html . '</span>';
};

admin_header('Landing Pages', 'landing.php');
?>
<div class="flex flex-wrap items-end justify-between gap-3 mb-6">
  <div>
    <p class="text-xs uppercase tracking-[0.25em] text-gold-600">Sales pages</p>
    <h1 class="font-serif text-3xl text-wood-900 mt-1">Landing Pages</h1>
    <p class="text-sm text-stone-500 mt-1">Every product gets its own Bangla sales page with an order form. Share its link in Facebook ads.</p>
  </div>
  <div class="inline-flex rounded-xl bg-white border border-cream-300 p-1 text-sm font-semibold">
    <a href="landing.php" class="px-4 py-2 rounded-lg <?= $tab === 'pages' ? 'bg-wood-800 text-white' : 'text-wood-800 hover:bg-cream-100' ?>"><i class="fa-solid fa-table-cells-large mr-1"></i> All landing pages</a>
    <a href="landing.php?tab=templates" class="px-4 py-2 rounded-lg <?= $tab === 'templates' ? 'bg-wood-800 text-white' : 'text-wood-800 hover:bg-cream-100' ?>"><i class="fa-solid fa-swatchbook mr-1"></i> Templates (<?= count(IC_LANDING_TEMPLATES) ?>)</a>
  </div>
</div>

<?php if ($tab === 'pages'): ?>
  <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <?php foreach ([
        ['fa-file-lines', 'Landing pages on', $onCount . ' / ' . count($products)],
        ['fa-eye', 'Page views', number_format($totalViews)],
        ['fa-bag-shopping', 'Orders from pages', number_format($totalOrders)],
        ['fa-percent', 'Visitors who ordered', $totalViews ? round($totalOrders / $totalViews * 100, 1) . '%' : '—'],
    ] as [$icon, $label, $value]): ?>
      <div class="card p-4 flex items-center gap-3">
        <span class="w-11 h-11 rounded-xl bg-cream-100 text-gold-600 flex items-center justify-center"><i class="fa-solid <?= $icon ?>"></i></span>
        <span><span class="block text-xs text-stone-500"><?= $label ?></span><span class="block text-xl font-semibold text-wood-900"><?= $value ?></span></span>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="card p-4 mb-6 flex flex-wrap items-center gap-4 justify-between">
    <form method="post" class="flex items-center gap-3">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="settings">
      <label class="flex items-center gap-3 cursor-pointer text-sm">
        <span class="relative">
          <input type="checkbox" name="auto_on" value="1" class="peer sr-only" <?= ic_landing_auto_on($pdo) ? 'checked' : '' ?> onchange="this.form.submit()">
          <span class="block w-11 h-6 rounded-full bg-stone-300 transition peer-checked:bg-emerald-600"></span>
          <span class="absolute top-0.5 left-0.5 w-5 h-5 rounded-full bg-white shadow transition peer-checked:translate-x-5"></span>
        </span>
        <span><strong class="text-wood-900">Auto landing page for new products</strong><span class="block text-xs text-stone-500">New products get a ready Bangla page right away</span></span>
      </label>
    </form>
    <div class="text-sm text-stone-500 flex items-center gap-2">
      Default design: <?= $swatch(IC_LANDING_TEMPLATES[$defaultTpl]['c']) ?> <strong class="text-wood-900"><?= h(IC_LANDING_TEMPLATES[$defaultTpl]['name']) ?></strong>
      <a href="landing.php?tab=templates" class="text-gold-700 font-semibold hover:underline">Change</a>
    </div>
    <div class="relative w-full sm:w-64">
      <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-stone-400 text-sm"></i>
      <input id="lpSearch" class="input !pl-9" placeholder="Search products…">
    </div>
  </div>

  <div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-5" id="lpGrid">
    <?php foreach ($pages as ['p' => $p, 'lp' => $lp]):
        $t = IC_LANDING_TEMPLATES[$lp['template']] ?? IC_LANDING_TEMPLATES[IC_LANDING_DEFAULT_TEMPLATE];
        $orderCount = $orders[$p['id']] ?? 0; ?>
      <div class="card overflow-hidden flex flex-col <?= $lp['enabled'] ? '' : 'opacity-70' ?>" data-title="<?= h(strtolower($p['title'])) ?>">
        <div class="relative aspect-[16/9] bg-cream-100">
          <img src="<?= h(asset_url($p['image'])) ?>" alt="" class="w-full h-full object-cover" loading="lazy">
          <span class="absolute top-3 left-3 inline-flex items-center gap-2 rounded-full bg-white/95 px-3 py-1 text-xs font-semibold text-wood-900 shadow"><?= $swatch($t['c'], 'w-3 h-3') ?> <?= h($t['name']) ?></span>
          <span class="absolute top-3 right-3 rounded-full px-2.5 py-1 text-[11px] font-bold shadow <?= $lp['enabled'] ? 'bg-emerald-600 text-white' : 'bg-stone-700 text-white' ?>"><?= $lp['enabled'] ? 'LIVE' : 'OFF' ?></span>
          <?php if (!$p['is_active']): ?><span class="absolute bottom-3 left-3 rounded-full bg-red-600 text-white px-2.5 py-1 text-[11px] font-semibold">Product hidden from shop</span><?php endif; ?>
        </div>
        <div class="p-4 flex-1 flex flex-col">
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <h2 class="font-semibold text-wood-900 truncate"><?= h($p['title']) ?></h2>
              <p class="text-sm <?= $p['price_bdt'] === null ? 'text-amber-700' : 'text-stone-500' ?>">
                <?= $p['price_bdt'] === null ? '<i class="fa-solid fa-triangle-exclamation"></i> No price — page shows Call/WhatsApp instead of the order form' : money($p['price_bdt']) . ($lp['data']['regular_price'] ? ' <s class="text-stone-400">' . money($lp['data']['regular_price']) . '</s>' : '') ?>
              </p>
            </div>
            <form method="post">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="id" value="<?= h($p['id']) ?>">
              <button class="relative inline-flex w-11 h-6 rounded-full transition <?= $lp['enabled'] ? 'bg-emerald-600' : 'bg-stone-300' ?>" title="Turn landing page <?= $lp['enabled'] ? 'off' : 'on' ?>">
                <span class="absolute top-0.5 left-0.5 w-5 h-5 rounded-full bg-white shadow transition <?= $lp['enabled'] ? 'translate-x-5' : '' ?>"></span>
                <span class="sr-only">Toggle</span>
              </button>
            </form>
          </div>
          <div class="grid grid-cols-3 gap-2 mt-3 text-center">
            <div class="rounded-lg bg-cream-50 py-2"><span class="block text-lg font-semibold text-wood-900"><?= number_format($lp['views']) ?></span><span class="text-[11px] text-stone-500">Views</span></div>
            <div class="rounded-lg bg-cream-50 py-2"><span class="block text-lg font-semibold text-wood-900"><?= number_format($orderCount) ?></span><span class="text-[11px] text-stone-500">Orders</span></div>
            <div class="rounded-lg bg-cream-50 py-2"><span class="block text-lg font-semibold text-wood-900"><?= $lp['views'] ? round($orderCount / $lp['views'] * 100, 1) . '%' : '—' ?></span><span class="text-[11px] text-stone-500">Converted</span></div>
          </div>
          <?php $running = $p['price_bdt'] === null ? null : ic_landing_offer($lp['data'], (int) $p['price_bdt']); ?>
          <?php if ($running): ?>
            <p class="mt-2 inline-flex items-center gap-1.5 self-start rounded-full bg-emerald-50 text-emerald-800 px-2.5 py-1 text-xs font-semibold"><i class="fa-solid fa-stopwatch"></i> Offer <?= money($running['price']) ?> until <?= h(date('d M, g:i a', $running['end'])) ?></p>
          <?php elseif (!empty($lp['data']['offer_price']) && $lp['data']['countdown_end'] !== ''): ?>
            <p class="mt-2 inline-flex items-center gap-1.5 self-start rounded-full bg-stone-100 text-stone-600 px-2.5 py-1 text-xs font-semibold"><i class="fa-solid fa-stopwatch"></i> Offer ended — start a new one in Edit page</p>
          <?php endif; ?>
          <p class="text-xs text-stone-400 mt-2"><?= $lp['custom'] ? '<i class="fa-solid fa-pen text-gold-600"></i> Your own text' : '<i class="fa-solid fa-wand-magic-sparkles text-gold-600"></i> Ready-made Bangla text — edit to make it yours' ?></p>
          <div class="mt-auto pt-4 flex flex-wrap gap-2">
            <a href="landing-edit.php?id=<?= h(rawurlencode($p['id'])) ?>" class="btn btn-dark flex-1"><i class="fa-solid fa-wand-magic-sparkles"></i> Edit page</a>
            <a href="../<?= h(ic_landing_url($p['id'])) ?><?= $lp['enabled'] ? '' : '&preview=1' ?>" target="_blank" class="btn btn-light" title="Open page"><i class="fa-solid fa-arrow-up-right-from-square"></i></a>
            <button type="button" class="btn btn-light" data-copy="<?= h(ic_landing_url($p['id'])) ?>" title="Copy link for ads"><i class="fa-regular fa-copy"></i></button>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <script>
    document.getElementById('lpSearch').addEventListener('input', (e) => {
      const q = e.target.value.trim().toLowerCase();
      document.querySelectorAll('#lpGrid > [data-title]').forEach(card => { card.hidden = q !== '' && !card.dataset.title.includes(q); });
    });
    document.querySelectorAll('[data-copy]').forEach(btn => btn.addEventListener('click', async () => {
      const url = new URL('../' + btn.dataset.copy, location.href).href;
      try { await navigator.clipboard.writeText(url); } catch (err) { prompt('Copy this link:', url); return; }
      const old = btn.innerHTML; btn.innerHTML = '<i class="fa-solid fa-check"></i> Copied'; setTimeout(() => { btn.innerHTML = old; }, 1600);
    }));
  </script>

<?php else: ?>
  <p class="text-sm text-stone-500 mb-5">Each design has its own colours, fonts, layout and motion. Previews use <strong><?= h($sample['title'] ?? 'a product') ?></strong>. Pick a design per product in <em>Edit page</em>, or set one for all here.</p>
  <div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-5">
    <?php foreach (IC_LANDING_TEMPLATES as $key => $t):
        $used = count(array_filter($pages, function ($x) use ($key) { return $x['lp']['template'] === $key; })); ?>
      <div class="card overflow-hidden flex flex-col <?= $key === $defaultTpl ? 'ring-2 ring-gold-500' : '' ?>">
        <div class="tpl-frame relative h-72 overflow-hidden" style="background:<?= h($t['c']['bg']) ?>">
          <?php if ($sample): ?>
            <iframe data-src="../<?= h(ic_landing_url($sample['id'])) ?>&preview=1&tpl=<?= h($key) ?>" title="<?= h($t['name']) ?> preview" tabindex="-1" class="absolute top-0 left-0 origin-top-left pointer-events-none border-0" style="width:420px;height:900px"></iframe>
          <?php endif; ?>
          <?php if ($key === $defaultTpl): ?><span class="absolute top-3 right-3 rounded-full bg-gold-500 text-white px-3 py-1 text-[11px] font-bold shadow">DEFAULT</span><?php endif; ?>
        </div>
        <div class="p-4 flex-1 flex flex-col">
          <div class="flex items-center justify-between gap-2">
            <h2 class="font-semibold text-wood-900"><?= h($t['name']) ?> <span class="font-normal text-stone-400 text-sm"><?= h($t['bn']) ?></span></h2>
            <?= $swatch($t['c']) ?>
          </div>
          <p class="text-xs font-semibold text-gold-700 mt-1"><i class="fa-solid fa-bullseye"></i> <?= h($t['for']) ?></p>
          <p class="text-sm text-stone-500 mt-2"><?= h($t['desc']) ?></p>
          <div class="flex flex-wrap gap-1.5 mt-3">
            <?php foreach (array_filter([
                'Layout: ' . $t['hero'],
                'Button: ' . $t['cta'],
                $t['fx'] !== 'none' ? 'Effect: ' . $t['fx'] : null,
                $t['img'] !== 'none' ? 'Photo: ' . $t['img'] : null,
                'Reveal: ' . $t['reveal'],
            ]) as $tag): ?>
              <span class="rounded-full bg-cream-100 px-2 py-0.5 text-[11px] text-wood-800"><?= h($tag) ?></span>
            <?php endforeach; ?>
          </div>
          <p class="text-xs text-stone-400 mt-2">Used on <?= $used ?> product<?= $used === 1 ? '' : 's' ?></p>
          <div class="mt-auto pt-4 flex flex-wrap gap-2">
            <?php if ($sample): ?><a class="btn btn-light" target="_blank" href="../<?= h(ic_landing_url($sample['id'])) ?>&preview=1&tpl=<?= h($key) ?>"><i class="fa-regular fa-eye"></i> Full preview</a><?php endif; ?>
            <?php if ($key !== $defaultTpl): ?>
              <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="default_template"><input type="hidden" name="template" value="<?= h($key) ?>">
                <button class="btn btn-light" title="Used for new products and pages you have not styled"><i class="fa-regular fa-star"></i> Make default</button></form>
            <?php endif; ?>
            <form method="post" onsubmit="return confirm('Use “<?= h($t['name']) ?>” on ALL landing pages? Your text and photos stay the same.');"><?= csrf_field() ?><input type="hidden" name="action" value="apply_all"><input type="hidden" name="template" value="<?= h($key) ?>">
              <button class="btn btn-dark"><i class="fa-solid fa-layer-group"></i> Use on all</button></form>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <script>
    // Scale each live preview to its card and load it only when it scrolls into view
    const frames = document.querySelectorAll('.tpl-frame iframe');
    const fit = () => frames.forEach(f => { f.style.transform = `scale(${f.parentElement.clientWidth / 420})`; });
    addEventListener('resize', fit); fit();
    const io = new IntersectionObserver((entries) => entries.forEach(en => {
      if (en.isIntersecting) { en.target.src = en.target.dataset.src; io.unobserve(en.target); }
    }), { rootMargin: '200px' });
    frames.forEach(f => io.observe(f));
  </script>
<?php endif; ?>
<?php admin_footer();
