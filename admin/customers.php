<?php
// Customers by product: who bought each product, how often and for how much — with an Excel (CSV) download
require __DIR__ . '/_inc.php';
require_admin();

$pdo = ic_db();
$products = [];
foreach ($pdo->query('SELECT id, title, image, price_bdt, is_active FROM products ORDER BY sort_order, title') as $p) {
    $products[$p['id']] = $p;
}

$productId = isset($products[$_GET['product'] ?? '']) ? (string) $_GET['product'] : '';
$ranges = ['30' => 'Last 30 days', '90' => 'Last 3 months', '365' => 'Last 12 months', 'all' => 'All time'];
$range = isset($ranges[$_GET['range'] ?? '']) ? (string) $_GET['range'] : 'all';
$shows = ['good' => 'Real orders (no fake / cancelled)', 'received' => 'Received only (delivered / partial)', 'all' => 'Every order'];
$show = isset($shows[$_GET['show'] ?? '']) ? (string) $_GET['show'] : 'good';
$sorts = ['last' => 'Latest order', 'spent' => 'Spent most', 'orders' => 'Most orders'];
$sort = isset($sorts[$_GET['sort'] ?? '']) ? (string) $_GET['sort'] : 'last';
$q = trim((string) ($_GET['q'] ?? ''));

// One pass over the orders: per product totals, and per product+customer rows (a customer = a phone number)
$sql = 'SELECT id, code, type, status, customer_name, whatsapp, address, region, items_json, details_json, created_at, delivery_score FROM orders WHERE 1 = 1';
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
if ($productId !== '') {
    $sql .= ' AND items_json LIKE ?';
    $args[] = '%"id":"' . $productId . '"%';
}
$stmt = $pdo->prepare($sql . ' ORDER BY created_at');
$stmt->execute($args);

$stats = [];     // product id => totals
$customers = []; // product id => phone => customer row
foreach ($stmt as $order) {
    $details = json_decode((string) $order['details_json'], true) ?: [];
    $source = !empty($details['landing']) ? 'Landing page' : ($order['type'] === 'custom' ? 'Custom order' : 'Website cart');
    $seen = [];
    foreach ((array) json_decode((string) $order['items_json'], true) as $item) {
        $pid = (string) ($item['id'] ?? '');
        if ($pid === '' || ($productId !== '' && $pid !== $productId)) {
            continue;
        }
        $qty = (int) ($item['qty'] ?? 1);
        $amount = $item['unit_bdt'] === null ? 0 : (int) $item['unit_bdt'] * $qty;
        $phone = (string) $order['whatsapp'];
        $s = &$stats[$pid];
        $s = ($s ?? ['orders' => 0, 'qty' => 0, 'revenue' => 0, 'last' => '']);
        $c = &$customers[$pid][$phone];
        $c = ($c ?? ['name' => '', 'phone' => $phone, 'address' => '', 'region' => '', 'orders' => 0, 'qty' => 0, 'spent' => 0, 'first' => $order['created_at'], 'last' => '', 'status' => '', 'score' => null, 'sources' => [], 'codes' => []]);
        if (!isset($seen[$pid])) { // count an order once even if the product is on it twice
            $s['orders']++;
            $c['orders']++;
            $c['codes'][] = $order['code'];
            $seen[$pid] = true;
        }
        $s['qty'] += $qty;
        $s['revenue'] += $amount;
        $s['last'] = max($s['last'], $order['created_at']);
        $c['qty'] += $qty;
        $c['spent'] += $amount;
        // Orders are oldest first, so the latest order's name, address and status win
        $c['name'] = $order['customer_name'];
        $c['address'] = (string) $order['address'];
        $c['region'] = (string) $order['region'];
        $c['last'] = $order['created_at'];
        $c['status'] = $order['status'];
        $c['score'] = $order['delivery_score'];
        $c['sources'][$source] = true;
        unset($s, $c);
    }
}
foreach ($stats as $pid => &$s) {
    $s['customers'] = count($customers[$pid]);
    $s['repeat'] = count(array_filter($customers[$pid], function ($c) { return $c['orders'] >= 2; }));
}
unset($s);

// Rows for the chosen product (or every product+customer pair for the full download)
$rowsFor = function (string $pid) use ($customers, $q, $sort): array {
    $rows = array_values($customers[$pid] ?? []);
    if ($q !== '') {
        $needle = function_exists('mb_strtolower') ? mb_strtolower($q) : strtolower($q);
        $rows = array_values(array_filter($rows, function ($c) use ($needle) {
            $hay = $c['name'] . ' ' . $c['phone'] . ' ' . $c['address'];
            return strpos(function_exists('mb_strtolower') ? mb_strtolower($hay) : strtolower($hay), $needle) !== false;
        }));
    }
    usort($rows, function ($a, $b) use ($sort) {
        if ($sort === 'spent') {
            return [$b['spent'], $b['last']] <=> [$a['spent'], $a['last']];
        }
        if ($sort === 'orders') {
            return [$b['orders'], $b['last']] <=> [$a['orders'], $a['last']];
        }
        return strcmp($b['last'], $a['last']);
    });
    return $rows;
};

/* ---------- Excel download (CSV, opens in Excel with Bangla text intact) ---------- */
if (!empty($_GET['export'])) {
    $cell = function ($value): string {
        $value = (string) $value;
        if ($value !== '' && strpos('=+-@', $value[0]) !== false) {
            $value = "'" . $value; // never let a spreadsheet treat a name or address as a formula
        }
        return '"' . str_replace('"', '""', $value) . '"';
    };
    $name = 'customers-' . ($productId !== '' ? preg_replace('/[^\w-]/', '', $productId) : 'all-products') . '-' . date('Y-m-d') . '.csv';
    ic_discard_output();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Cache-Control: no-store');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // UTF-8 mark so Excel shows Bangla correctly
    $head = ['Product', 'Customer name', 'Phone (with country code)', 'Address', 'Area', 'Orders', 'Quantity', 'Spent (BDT)', 'First order', 'Last order', 'Last status', 'Came from'];
    fwrite($out, implode(',', array_map($cell, $head)) . "\r\n");
    foreach ($productId !== '' ? [$productId] : array_keys($customers) as $pid) {
        foreach ($rowsFor($pid) as $c) {
            fwrite($out, implode(',', array_map($cell, [
                $products[$pid]['title'] ?? $pid, $c['name'], '88' . $c['phone'], $c['address'],
                $c['region'] === 'outside' ? 'Outside Dhaka' : ($c['region'] === 'dhaka' ? 'Inside Dhaka' : ''),
                $c['orders'], $c['qty'], $c['spent'], substr($c['first'], 0, 10), substr($c['last'], 0, 10),
                IC_ORDER_STATUSES[$c['status']] ?? $c['status'], implode(' + ', array_keys($c['sources'])),
            ])) . "\r\n");
        }
    }
    fclose($out);
    exit;
}

$query = function (array $change) use ($productId, $range, $show, $sort, $q): string {
    $params = array_filter(array_merge(['product' => $productId, 'range' => $range, 'show' => $show, 'sort' => $sort, 'q' => $q], $change), function ($v) {
        return $v !== '' && $v !== null;
    });
    return 'customers.php' . ($params ? '?' . http_build_query($params) : '');
};

admin_header('Customers by product', 'customers.php');
?>
<div class="flex flex-wrap items-end justify-between gap-3 mb-6">
  <div>
    <p class="text-xs uppercase tracking-[0.25em] text-gold-600">Product</p>
    <h1 class="font-serif text-3xl text-wood-900 mt-1">Customers by product</h1>
    <p class="text-sm text-stone-500 mt-1">Who bought each product — for follow-ups, repeat offers and Facebook custom audiences.</p>
  </div>
  <a href="<?= h($query(['export' => 1])) ?>" class="btn btn-gold"><i class="fa-solid fa-file-excel"></i> Download Excel<?= $productId !== '' ? '' : ' (all products)' ?></a>
</div>

<form method="get" class="card p-4 mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-5 items-end">
  <div class="lg:col-span-2">
    <label class="label" for="product">Product</label>
    <select class="input" id="product" name="product" onchange="this.form.submit()">
      <option value="">All products — overview</option>
      <?php foreach ($products as $p): ?>
        <option value="<?= h($p['id']) ?>" <?= $p['id'] === $productId ? 'selected' : '' ?>><?= h($p['title']) ?> (<?= (int) ($stats[$p['id']]['customers'] ?? 0) ?>)</option>
      <?php endforeach; ?>
    </select>
  </div>
  <div>
    <label class="label" for="range">Time</label>
    <select class="input" id="range" name="range" onchange="this.form.submit()">
      <?php foreach ($ranges as $key => $label): ?><option value="<?= $key ?>" <?= $key === $range ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?>
    </select>
  </div>
  <div>
    <label class="label" for="show">Which orders</label>
    <select class="input" id="show" name="show" onchange="this.form.submit()">
      <?php foreach ($shows as $key => $label): ?><option value="<?= $key ?>" <?= $key === $show ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?>
    </select>
  </div>
  <?php if ($productId !== ''): ?>
    <div>
      <label class="label" for="sort">Sort</label>
      <select class="input" id="sort" name="sort" onchange="this.form.submit()">
        <?php foreach ($sorts as $key => $label): ?><option value="<?= $key ?>" <?= $key === $sort ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="sm:col-span-2 lg:col-span-5 flex gap-2">
      <input class="input" name="q" value="<?= h($q) ?>" placeholder="Search name, phone or address…">
      <button class="btn btn-dark"><i class="fa-solid fa-magnifying-glass"></i> Search</button>
      <?php if ($q !== ''): ?><a class="btn btn-light" href="<?= h($query(['q' => ''])) ?>">Clear</a><?php endif; ?>
    </div>
  <?php endif; ?>
</form>

<?php if ($productId === ''): ?>
  <!-- Overview: every product with its customer numbers -->
  <?php
  $list = $products;
  uasort($list, function ($a, $b) use ($stats) {
      return [($stats[$b['id']]['customers'] ?? 0), ($stats[$b['id']]['revenue'] ?? 0)] <=> [($stats[$a['id']]['customers'] ?? 0), ($stats[$a['id']]['revenue'] ?? 0)];
  });
  ?>
  <div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-5">
    <?php foreach ($list as $p): $s = $stats[$p['id']] ?? null; ?>
      <a href="<?= h($query(['product' => $p['id']])) ?>" class="card overflow-hidden flex hover:shadow-lg hover:-translate-y-0.5 transition <?= $s ? '' : 'opacity-60' ?>">
        <img src="<?= h(asset_url($p['image'])) ?>" alt="" class="w-28 object-cover flex-shrink-0 bg-cream-100" loading="lazy">
        <div class="p-4 flex-1 min-w-0">
          <h2 class="font-semibold text-wood-900 truncate"><?= h($p['title']) ?></h2>
          <?php if ($s): ?>
            <div class="grid grid-cols-3 gap-2 mt-2 text-center">
              <div class="rounded-lg bg-cream-50 py-1.5"><span class="block font-semibold text-wood-900"><?= $s['customers'] ?></span><span class="text-[10px] text-stone-500">Customers</span></div>
              <div class="rounded-lg bg-cream-50 py-1.5"><span class="block font-semibold text-wood-900"><?= $s['orders'] ?></span><span class="text-[10px] text-stone-500">Orders</span></div>
              <div class="rounded-lg bg-cream-50 py-1.5"><span class="block font-semibold text-wood-900"><?= $s['repeat'] ?></span><span class="text-[10px] text-stone-500">Came back</span></div>
            </div>
            <p class="text-xs text-stone-500 mt-2"><?= money($s['revenue']) ?> · last order <?= h(date('d M Y', strtotime($s['last']))) ?></p>
          <?php else: ?>
            <p class="text-sm text-stone-400 mt-2">No customers yet<?= $range !== 'all' ? ' in this time' : '' ?>.</p>
          <?php endif; ?>
        </div>
      </a>
    <?php endforeach; ?>
  </div>

<?php else:
  $p = $products[$productId];
  $s = $stats[$productId] ?? ['customers' => 0, 'orders' => 0, 'qty' => 0, 'revenue' => 0, 'repeat' => 0];
  $rows = $rowsFor($productId); ?>
  <div class="card p-4 mb-5 flex flex-wrap items-center gap-4">
    <img src="<?= h(asset_url($p['image'])) ?>" alt="" class="w-16 h-16 rounded-xl object-cover">
    <div class="flex-1 min-w-[180px]">
      <a href="<?= h($query(['product' => '', 'q' => ''])) ?>" class="text-xs text-gold-700 font-semibold hover:underline"><i class="fa-solid fa-arrow-left"></i> All products</a>
      <h2 class="font-serif text-2xl text-wood-900"><?= h($p['title']) ?></h2>
    </div>
    <div class="grid grid-cols-2 sm:grid-cols-5 gap-2 text-center w-full lg:w-auto">
      <?php foreach ([['Customers', $s['customers']], ['Came back', $s['repeat']], ['Orders', $s['orders']], ['Pieces', $s['qty']], ['Sales', money($s['revenue'])]] as [$label, $value]): ?>
        <div class="rounded-xl bg-cream-50 px-4 py-2"><span class="block text-lg font-semibold text-wood-900"><?= $value ?></span><span class="text-[11px] text-stone-500"><?= $label ?></span></div>
      <?php endforeach; ?>
    </div>
  </div>

  <?php if (!$rows): ?>
    <div class="card p-10 text-center text-stone-500"><i class="fa-regular fa-user text-3xl text-gold-500"></i><p class="mt-2">No customers found<?= $q !== '' ? ' for “' . h($q) . '”' : '' ?>. Try “All time” or “Every order”.</p></div>
  <?php else: ?>
    <div class="card overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-cream-200">
          <thead class="bg-cream-50">
            <tr><th class="th">Customer</th><th class="th">Address</th><th class="th text-center">Orders</th><th class="th text-center">Pieces</th><th class="th">Spent</th><th class="th">Last order</th><th class="th text-right">Contact</th></tr>
          </thead>
          <tbody class="divide-y divide-cream-200">
            <?php foreach ($rows as $c): ?>
              <tr class="hover:bg-cream-50">
                <td class="td">
                  <span class="font-semibold text-wood-900"><?= h($c['name']) ?></span>
                  <?php if ($c['orders'] >= 2): ?><span class="ml-1 rounded-full bg-emerald-100 text-emerald-800 px-2 py-0.5 text-[10px] font-semibold">Came back ×<?= $c['orders'] ?></span><?php endif; ?>
                  <span class="block text-xs text-stone-500"><?= h($c['phone']) ?> <?= score_badge($c['score']) ?></span>
                  <span class="block text-[11px] text-stone-400"><?= h(implode(' + ', array_keys($c['sources']))) ?></span>
                </td>
                <td class="td max-w-xs"><span class="line-clamp-2"><?= h($c['address']) ?></span><span class="text-[11px] text-stone-400"><?= $c['region'] === 'outside' ? 'Outside Dhaka' : ($c['region'] === 'dhaka' ? 'Inside Dhaka' : '') ?></span></td>
                <td class="td text-center font-semibold"><?= $c['orders'] ?></td>
                <td class="td text-center"><?= $c['qty'] ?></td>
                <td class="td whitespace-nowrap"><?= money($c['spent']) ?></td>
                <td class="td whitespace-nowrap"><?= h(date('d M Y', strtotime($c['last']))) ?><span class="block mt-1"><?= status_badge($c['status']) ?></span></td>
                <td class="td text-right whitespace-nowrap">
                  <a class="btn btn-light !px-3 !py-1.5" title="WhatsApp" target="_blank" rel="noopener" href="<?= h(whatsapp_link($c['phone'], 'আসসালামু আলাইকুম ' . $c['name'] . ', INSPIRED CREATIONS থেকে বলছি। আপনার কেনা "' . $p['title'] . '" কেমন লাগছে?')) ?>"><i class="fa-brands fa-whatsapp text-emerald-600"></i></a>
                  <a class="btn btn-light !px-3 !py-1.5" title="Call" href="tel:<?= h($c['phone']) ?>"><i class="fa-solid fa-phone"></i></a>
                  <a class="btn btn-light !px-3 !py-1.5" title="See this customer's orders" href="orders.php?q=<?= h(rawurlencode($c['phone'])) ?>"><i class="fa-solid fa-receipt"></i></a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <p class="text-xs text-stone-400 mt-3"><?= count($rows) ?> customer<?= count($rows) === 1 ? '' : 's' ?> · one row per phone number · name and address from their latest order. The Excel file has phone numbers with 88 in front, ready for Facebook custom audiences.</p>
  <?php endif; ?>
<?php endif; ?>
<?php admin_footer();
