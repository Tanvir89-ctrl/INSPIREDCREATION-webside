<?php
// Ad Audience: pick products (and area / time), get every customer who bought them, and download a file that
// Facebook Ads Manager accepts as a "Customer list" custom audience — for ads, boosting and lookalikes.
require __DIR__ . '/_inc.php';
require_once __DIR__ . '/../api/lib-geo.php';
require_admin();

$pdo = ic_db();
$products = $pdo->query('SELECT id, title, image, price_bdt FROM products ORDER BY sort_order, title')->fetchAll();
$byId = array_column($products, null, 'id');

$picked = array_values(array_intersect(array_map('strval', (array) ($_GET['p'] ?? [])), array_keys($byId)));
$ranges = ['30' => 'Last 30 days', '90' => 'Last 3 months', '180' => 'Last 6 months', '365' => 'Last 12 months', 'all' => 'All time'];
$range = isset($ranges[$_GET['range'] ?? '']) ? (string) $_GET['range'] : 'all';
$shows = ['good' => 'Real orders (no fake / cancelled)', 'received' => 'Received only (delivered / partial)', 'all' => 'Every order'];
$show = isset($shows[$_GET['show'] ?? '']) ? (string) $_GET['show'] : 'good';
$areaOptions = ['all' => 'All of Bangladesh', 'zone:dhaka' => 'Inside Dhaka (delivery zone)', 'zone:outside' => 'Outside Dhaka (delivery zone)'];
foreach (IC_BD_DISTRICTS as $key => $d) {
    $areaOptions['d:' . $key] = 'District: ' . $d[0];
}
$area = isset($areaOptions[$_GET['area'] ?? '']) ? (string) $_GET['area'] : 'all';
$repeatOnly = !empty($_GET['repeat']);

/* ---------- Collect customers (one per phone number) ---------- */
$sql = 'SELECT code, status, customer_name, whatsapp, customer_email, address, region, items_json, created_at FROM orders WHERE 1 = 1';
$args = [];
if ($range !== 'all') {
    $sql .= ' AND created_at >= ?';
    $args[] = date('Y-m-d 00:00:00', strtotime('-' . (int) $range . ' days'));
}
if ($show === 'good') {
    $sql .= " AND status NOT IN ('fake', 'cancelled')";
} elseif ($show === 'received') {
    $sql .= " AND status IN ('delivered', 'partial')";
}
if ($area === 'zone:dhaka' || $area === 'zone:outside') {
    $sql .= ' AND region = ?';
    $args[] = substr($area, 5);
}
$stmt = $pdo->prepare($sql . ' ORDER BY created_at');
$stmt->execute($args);

$customers = [];
foreach ($stmt as $order) {
    $place = ic_geo_locate((string) $order['address'], $order['region']);
    if (strpos($area, 'd:') === 0 && ($place['district'] ?? '') !== substr($area, 2)) {
        continue;
    }
    $bought = [];
    $value = 0;
    foreach ((array) json_decode((string) $order['items_json'], true) as $item) {
        $id = (string) ($item['id'] ?? '');
        if ($picked && !in_array($id, $picked, true)) {
            continue;
        }
        $bought[$id] = true;
        $value += $item['unit_bdt'] === null ? 0 : (int) $item['unit_bdt'] * (int) ($item['qty'] ?? 1);
    }
    if (!$bought) {
        continue; // this order has none of the chosen products
    }
    $phone = (string) $order['whatsapp'];
    $c = &$customers[$phone];
    $c = $c ?? ['name' => '', 'phone' => $phone, 'email' => '', 'district' => null, 'orders' => 0, 'value' => 0, 'products' => [], 'last' => ''];
    $c['name'] = $order['customer_name']; // latest order wins
    $c['email'] = $order['customer_email'] ?: $c['email'];
    $c['district'] = $place['district'] ?? $c['district'];
    $c['orders']++;
    $c['value'] += $value;
    $c['products'] += $bought;
    $c['last'] = $order['created_at'];
    unset($c);
}
if ($repeatOnly) {
    $customers = array_filter($customers, function ($c) { return $c['orders'] >= 2; });
}
uasort($customers, function ($a, $b) { return [$b['value'], $b['last']] <=> [$a['value'], $a['last']]; });

// Meta's customer-list columns: phone with country code (digits), first name, last name, city, country
$metaRow = function (array $c, bool $withValue): array {
    $parts = preg_split('/\s+/u', trim(preg_replace('/^(md|mohammad|muhammad|mst|mrs?|ms)\.?\s+/iu', '', $c['name']))) ?: [];
    $fn = $parts ? array_shift($parts) : '';
    $row = [
        '88' . $c['phone'],
        strtolower((string) $c['email']),
        $fn,
        implode(' ', $parts),
        $c['district'] ? strtolower(str_replace(["'", ' '], '', IC_BD_DISTRICTS[$c['district']][0])) : '',
        'bd',
    ];
    if ($withValue) {
        $row[] = $c['value'];
    }
    return $row;
};

/* ---------- Downloads ---------- */
$export = (string) ($_GET['export'] ?? '');
if ($export === 'meta' || $export === 'meta_value') {
    $withValue = $export === 'meta_value';
    ic_discard_output();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="meta-audience-' . ($withValue ? 'value-' : '') . date('Y-m-d') . '.csv"');
    header('Cache-Control: no-store');
    $out = fopen('php://output', 'w');
    fputcsv($out, $withValue ? ['phone', 'email', 'fn', 'ln', 'ct', 'country', 'value'] : ['phone', 'email', 'fn', 'ln', 'ct', 'country'], ',', '"', '');
    foreach ($customers as $c) {
        fputcsv($out, array_map(function ($v) {
            $v = (string) $v;
            return ($v !== '' && strpos('=+-@', $v[0]) !== false) ? "'" . $v : $v; // never a spreadsheet formula
        }, $metaRow($c, $withValue)), ',', '"', '');
    }
    fclose($out);
    exit;
}

$count = count($customers);
$repeat = count(array_filter($customers, function ($c) { return $c['orders'] >= 2; }));
$totalValue = array_sum(array_column($customers, 'value'));
$query = function (array $change) use ($picked, $range, $show, $area, $repeatOnly): string {
    $params = array_merge(['p' => $picked, 'range' => $range, 'show' => $show, 'area' => $area, 'repeat' => $repeatOnly ? 1 : ''], $change);
    $params = array_filter($params, function ($v) { return $v !== '' && $v !== [] && $v !== null; });
    return 'audience.php' . ($params ? '?' . http_build_query($params) : '');
};

admin_header('Ad audience', 'audience.php');
?>
<div class="flex flex-wrap items-end justify-between gap-3 mb-6">
  <div>
    <p class="text-xs uppercase tracking-[0.25em] text-gold-600">Customer insights</p>
    <h1 class="font-serif text-3xl text-wood-900 mt-1">Ad audience</h1>
    <p class="text-sm text-stone-500 mt-1">Choose products and get every customer who bought them — ready for Facebook Ads Manager, boosting and lookalike audiences.</p>
  </div>
</div>

<form method="get" id="audForm" class="grid lg:grid-cols-[minmax(0,1fr)_320px] gap-6 items-start">
  <section class="card p-5">
    <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
      <h2 class="font-serif text-lg text-wood-900">1. Products <span class="text-sm font-sans text-stone-400"><?= $picked ? count($picked) . ' chosen' : 'none chosen = all products' ?></span></h2>
      <div class="flex gap-2">
        <button type="button" class="btn btn-light !py-1.5 !px-3 text-xs" data-all="1">Select all</button>
        <button type="button" class="btn btn-light !py-1.5 !px-3 text-xs" data-all="0">Clear</button>
      </div>
    </div>
    <div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-2 max-h-[420px] overflow-y-auto pr-1">
      <?php foreach ($products as $p): ?>
        <label class="flex items-center gap-3 rounded-xl border-2 border-cream-300 bg-white p-2 cursor-pointer hover:border-gold-500 has-[:checked]:border-wood-800 has-[:checked]:bg-cream-50">
          <input type="checkbox" name="p[]" value="<?= h($p['id']) ?>" class="w-4 h-4 accent-[#5C3118]" <?= in_array($p['id'], $picked, true) ? 'checked' : '' ?>>
          <img src="<?= h(asset_url($p['image'])) ?>" alt="" class="w-11 h-11 rounded-lg object-cover flex-shrink-0" loading="lazy">
          <span class="min-w-0 text-sm font-semibold text-wood-900 leading-tight line-clamp-2"><?= h($p['title']) ?></span>
        </label>
      <?php endforeach; ?>
    </div>
  </section>

  <aside class="card p-5 space-y-3 lg:sticky lg:top-4">
    <h2 class="font-serif text-lg text-wood-900">2. Who</h2>
    <div>
      <label class="label" for="area">Area</label>
      <select class="input" id="area" name="area">
        <?php foreach ($areaOptions as $key => $label): ?><option value="<?= h($key) ?>" <?= $key === $area ? 'selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="label" for="range">Time</label>
      <select class="input" id="range" name="range">
        <?php foreach ($ranges as $key => $label): ?><option value="<?= $key ?>" <?= $key === $range ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="label" for="show">Which orders</label>
      <select class="input" id="show" name="show">
        <?php foreach ($shows as $key => $label): ?><option value="<?= $key ?>" <?= $key === $show ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?>
      </select>
    </div>
    <label class="flex items-center gap-2 text-sm cursor-pointer"><input type="checkbox" name="repeat" value="1" class="w-4 h-4 accent-[#5C3118]" <?= $repeatOnly ? 'checked' : '' ?>> Only customers who ordered 2+ times</label>
    <button class="btn btn-dark w-full"><i class="fa-solid fa-users-viewfinder"></i> Show customers</button>
  </aside>
</form>

<section class="mt-6 space-y-5">
  <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
    <?php foreach ([
        ['fa-users', 'Customers', number_format($count)],
        ['fa-rotate', 'Bought 2+ times', number_format($repeat)],
        ['fa-sack-dollar', 'They spent', money($totalValue)],
        ['fa-bullseye', 'Ready for Meta', $count >= 100 ? 'Yes' : 'Need ' . (100 - $count) . ' more'],
    ] as [$icon, $label, $value]): ?>
      <div class="card p-4 flex items-center gap-3">
        <span class="w-11 h-11 rounded-xl bg-cream-100 text-gold-600 flex items-center justify-center"><i class="fa-solid <?= $icon ?>"></i></span>
        <span><span class="block text-xs text-stone-500"><?= $label ?></span><span class="block text-xl font-semibold text-wood-900"><?= h($value) ?></span></span>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if ($count > 0 && $count < 100): ?>
    <p class="rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-sm p-3"><i class="fa-solid fa-circle-info"></i> Facebook needs about <strong>100 or more</strong> matched people before it can show ads to a customer list. You can still upload it now — choose more products or a longer time to make it bigger, or use it as the source for a <strong>lookalike audience</strong> later.</p>
  <?php endif; ?>

  <div class="card p-5">
    <h2 class="font-serif text-lg text-wood-900 mb-3">3. Download for Facebook</h2>
    <?php if (!$count): ?>
      <p class="text-sm text-stone-500">No customers match these choices yet.</p>
    <?php else: ?>
      <div class="grid sm:grid-cols-3 gap-3">
        <a href="<?= h($query(['export' => 'meta'])) ?>" class="rounded-xl border-2 border-[#1877F2] bg-[#1877F2] text-white p-4 hover:brightness-110">
          <i class="fa-brands fa-meta text-xl"></i><span class="block font-semibold mt-1">Customer list (CSV)</span><span class="block text-xs opacity-90">For Custom audience → Customer list. phone, email, name, city, country.</span>
        </a>
        <a href="<?= h($query(['export' => 'meta_value'])) ?>" class="rounded-xl border-2 border-[#1877F2] text-[#1877F2] p-4 hover:bg-blue-50">
          <i class="fa-solid fa-chart-line text-xl"></i><span class="block font-semibold mt-1">With customer value (CSV)</span><span class="block text-xs text-stone-500">Same list + how much each spent — for a value-based lookalike.</span>
        </a>
        <button type="button" id="copyPhones" class="rounded-xl border-2 border-cream-300 text-wood-900 p-4 text-left hover:border-gold-500" data-phones="<?= h(implode("\n", array_map(function ($c) { return '+88' . $c['phone']; }, $customers))) ?>">
          <i class="fa-regular fa-copy text-xl text-gold-600"></i><span class="block font-semibold mt-1">Copy phone numbers</span><span class="block text-xs text-stone-500">All <?= $count ?> numbers (+8801…), one per line, to paste in Ads Manager.</span>
        </button>
      </div>

      <div class="overflow-x-auto mt-5 rounded-xl border border-cream-200">
        <table class="min-w-full divide-y divide-cream-200">
          <thead class="bg-cream-50"><tr><th class="th">Customer</th><th class="th">District</th><th class="th text-center">Orders</th><th class="th">Spent</th><th class="th">Bought</th></tr></thead>
          <tbody class="divide-y divide-cream-200">
            <?php foreach (array_slice($customers, 0, 25) as $c): ?>
              <tr>
                <td class="td"><span class="font-semibold text-wood-900"><?= h($c['name']) ?></span><span class="block text-xs text-stone-500"><?= h($c['phone']) ?></span></td>
                <td class="td"><?= $c['district'] ? h(IC_BD_DISTRICTS[$c['district']][0]) : '<span class="text-stone-400">—</span>' ?></td>
                <td class="td text-center"><?= $c['orders'] ?></td>
                <td class="td whitespace-nowrap"><?= money($c['value']) ?></td>
                <td class="td text-xs text-stone-600"><?= h(implode(', ', array_map(function ($id) use ($byId) { return $byId[$id]['title'] ?? $id; }, array_keys($c['products'])))) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php if ($count > 25): ?><p class="text-xs text-stone-400 mt-2">Showing the 25 who spent most — the download has all <?= $count ?>.</p><?php endif; ?>
    <?php endif; ?>
  </div>

  <div class="card p-5">
    <h2 class="font-serif text-lg text-wood-900 mb-3"><i class="fa-brands fa-meta text-[#1877F2]"></i> How to use it in Ads Manager</h2>
    <ol class="text-sm text-stone-600 space-y-2 list-decimal list-inside">
      <li>Download <strong>Customer list (CSV)</strong> above.</li>
      <li>Open <strong>Meta Ads Manager → Audiences → Create audience → Custom audience → Customer list</strong>.</li>
      <li>Upload the file. Meta reads the columns by themselves (phone, email, fn, ln, ct, country) — press <strong>Import &amp; create</strong>. Meta scrambles (hashes) the data before matching.</li>
      <li>Then <strong>Create audience → Lookalike audience</strong>, source = this list, location = <strong>Bangladesh</strong>, size <strong>1%</strong> — new people who look like your buyers.</li>
      <li>Use the audience in a new ad, or when you press <strong>Boost post</strong> on your Page choose it under <em>Audience</em>.</li>
      <li>Tip: <em>exclude</em> this list from an ad when you only want new customers, or show a “come back” offer only to it.</li>
    </ol>
    <p class="text-xs text-stone-400 mt-3">Only upload customers who ordered from you, as Meta's rules require. Keep the downloaded files private — they hold names and phone numbers.</p>
  </div>
</section>

<script>
  document.querySelectorAll('[data-all]').forEach(b => b.addEventListener('click', () => {
    document.querySelectorAll('input[name="p[]"]').forEach(cb => { cb.checked = b.dataset.all === '1'; });
  }));
  document.getElementById('copyPhones')?.addEventListener('click', async (e) => {
    const btn = e.currentTarget;
    try { await navigator.clipboard.writeText(btn.dataset.phones); } catch (err) { prompt('Copy these numbers:', btn.dataset.phones); return; }
    const title = btn.querySelector('.font-semibold'); const old = title.textContent;
    title.textContent = 'Copied ✓'; setTimeout(() => { title.textContent = old; }, 1800);
  });
</script>
<?php admin_footer();
