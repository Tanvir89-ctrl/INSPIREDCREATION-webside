<?php
// Order Map: where orders come from — districts across Bangladesh and areas inside Dhaka, read from the
// delivery addresses. Bigger, redder circles = more orders.
require __DIR__ . '/_inc.php';
require_once __DIR__ . '/../api/lib-geo.php';
require_admin();

$pdo = ic_db();
$products = [];
foreach ($pdo->query('SELECT id, title FROM products ORDER BY sort_order, title') as $p) {
    $products[$p['id']] = $p['title'];
}
$productId = isset($products[$_GET['product'] ?? '']) ? (string) $_GET['product'] : '';
$ranges = ['30' => 'Last 30 days', '90' => 'Last 3 months', '365' => 'Last 12 months', 'all' => 'All time'];
$range = isset($ranges[$_GET['range'] ?? '']) ? (string) $_GET['range'] : 'all';
$shows = ['good' => 'Real orders (no fake / cancelled)', 'received' => 'Received only (delivered / partial)', 'all' => 'Every order'];
$show = isset($shows[$_GET['show'] ?? '']) ? (string) $_GET['show'] : 'good';

$sql = "SELECT id, code, status, customer_name, whatsapp, address, region, items_json, total_bdt, created_at FROM orders WHERE address IS NOT NULL AND address <> ''";
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
$stmt = $pdo->prepare($sql . ' ORDER BY created_at DESC');
$stmt->execute($args);

$districts = [];
$areas = [];
$divisions = [];
$unplaced = [];
$total = 0;
$guessed = 0;
$inside = 0;
$add = function (array &$bucket, string $key, string $phone, int $amount) {
    $bucket[$key] = $bucket[$key] ?? ['orders' => 0, 'phones' => [], 'sales' => 0];
    $bucket[$key]['orders']++;
    $bucket[$key]['phones'][$phone] = true;
    $bucket[$key]['sales'] += $amount;
};
foreach ($stmt as $order) {
    $amount = (int) $order['total_bdt'];
    if ($productId !== '') { // with a product chosen, count only that product's money
        $amount = 0;
        foreach ((array) json_decode((string) $order['items_json'], true) as $item) {
            if (($item['id'] ?? '') === $productId && $item['unit_bdt'] !== null) {
                $amount += (int) $item['unit_bdt'] * (int) ($item['qty'] ?? 1);
            }
        }
    }
    $total++;
    $inside += $order['region'] === 'dhaka' ? 1 : 0;
    $place = ic_geo_locate((string) $order['address'], $order['region']);
    if ($place === null) {
        if (count($unplaced) < 40) {
            $unplaced[] = $order;
        }
        continue;
    }
    $add($districts, $place['district'], $order['whatsapp'], $amount);
    $add($divisions, IC_BD_DISTRICTS[$place['district']][4], $order['whatsapp'], $amount);
    if ($place['district'] === 'dhaka') {
        $add($areas, $place['area'] ?? '_unknown', $order['whatsapp'], $amount);
    }
    $guessed += $place['guessed'] ? 1 : 0;
}
$sortDesc = function (array &$list) {
    uasort($list, function ($a, $b) { return [$b['orders'], $b['sales']] <=> [$a['orders'], $a['sales']]; });
};
$sortDesc($districts);
$sortDesc($areas);
$sortDesc($divisions);
$placedCount = array_sum(array_column($districts, 'orders'));

// Points for the map (customers = different phone numbers)
$points = function (array $list, array $places, int $nameIdx, int $bnIdx) {
    $out = [];
    foreach ($list as $key => $v) {
        if (!isset($places[$key])) {
            continue;
        }
        $p = $places[$key];
        $out[] = ['key' => $key, 'name' => $p[$nameIdx], 'bn' => $p[$bnIdx], 'lat' => $p[2], 'lng' => $p[3],
            'orders' => $v['orders'], 'customers' => count($v['phones']), 'sales' => $v['sales']];
    }
    return $out;
};
$mapData = [
    'districts' => $points($districts, IC_BD_DISTRICTS, 0, 1),
    'areas'     => $points($areas, IC_DHAKA_AREAS, 0, 1),
];
$topDistrict = $mapData['districts'][0] ?? null;
$topArea = $mapData['areas'][0] ?? null;
$query = function (array $change) use ($productId, $range, $show): string {
    $params = array_filter(array_merge(['product' => $productId, 'range' => $range, 'show' => $show], $change), 'strlen');
    return 'map.php' . ($params ? '?' . http_build_query($params) : '');
};

admin_header('Order map', 'map.php');
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
<style>
  .leaflet-popup-content { font-family: 'Plus Jakarta Sans', sans-serif; margin: 12px 14px; }
  /* The zone with the most orders: glowing outline + a label that stays on it */
  .top-zone { animation: zonePulse 1.8s ease-in-out infinite; }
  @keyframes zonePulse { 0%, 100% { stroke: #7f1d1d; stroke-width: 3px; } 50% { stroke: #ef4444; stroke-width: 7px; } }
  .thanas-on .top-zone-d { animation: none; stroke-width: 0; }
  .top-label-wrap { background: none; border: 0; }
  .top-label { position: absolute; left: 0; bottom: 14px; transform: translateX(-50%); background: #7f1d1d; color: #fff; border-radius: 12px; padding: 6px 12px; white-space: nowrap; text-align: center; box-shadow: 0 10px 24px -8px rgba(127,29,29,.7); font: 600 12px/1.35 'Plus Jakarta Sans', sans-serif; animation: labelBob 2.4s ease-in-out infinite; pointer-events: none; }
  .top-label::after { content: ''; position: absolute; left: 50%; bottom: -6px; margin-left: -6px; border: 6px solid transparent; border-bottom: 0; border-top-color: #7f1d1d; }
  .top-label .tl-k { display: block; font-size: 10px; letter-spacing: .04em; color: #fde68a; }
  .top-label b { font-size: 14px; }
  .top-dot { position: absolute; width: 12px; height: 12px; margin: -6px 0 0 -6px; border-radius: 50%; background: #fff; border: 3px solid #7f1d1d; box-shadow: 0 0 0 0 rgba(239,68,68,.6); animation: dotPing 1.8s infinite; }
  @keyframes labelBob { 50% { transform: translateX(-50%) translateY(-4px); } }
  @keyframes dotPing { 70% { box-shadow: 0 0 0 14px rgba(239,68,68,0); } 100% { box-shadow: 0 0 0 0 rgba(239,68,68,0); } }
</style>

<div class="flex flex-wrap items-end justify-between gap-3 mb-6">
  <div>
    <p class="text-xs uppercase tracking-[0.25em] text-gold-600">Orders</p>
    <h1 class="font-serif text-3xl text-wood-900 mt-1">Order map</h1>
    <p class="text-sm text-stone-500 mt-1">Where your orders come from — read from each delivery address. Bigger, redder circles = more orders.</p>
  </div>
</div>

<form method="get" class="card p-4 mb-6 grid gap-3 sm:grid-cols-3 items-end">
  <div>
    <label class="label" for="product">Product</label>
    <select class="input" id="product" name="product" onchange="this.form.submit()">
      <option value="">All products</option>
      <?php foreach ($products as $id => $title): ?><option value="<?= h($id) ?>" <?= $id === $productId ? 'selected' : '' ?>><?= h($title) ?></option><?php endforeach; ?>
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
</form>

<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
  <?php foreach ([
      ['fa-location-dot', 'Orders on the map', $placedCount . ' / ' . $total, $total ? round($placedCount / $total * 100) . '% of addresses found' : ''],
      ['fa-trophy', 'Top district', $topDistrict ? $topDistrict['name'] : '—', $topDistrict ? $topDistrict['orders'] . ' orders' : ''],
      ['fa-city', 'Top Dhaka area', $topArea ? $topArea['name'] : '—', $topArea ? $topArea['orders'] . ' orders' : ''],
      ['fa-truck-fast', 'Inside Dhaka / outside', $inside . ' / ' . ($total - $inside), $total ? round($inside / $total * 100) . '% inside Dhaka' : ''],
  ] as [$icon, $label, $value, $hint]): ?>
    <div class="card p-4 flex items-center gap-3">
      <span class="w-11 h-11 rounded-xl bg-cream-100 text-gold-600 flex items-center justify-center flex-shrink-0"><i class="fa-solid <?= $icon ?>"></i></span>
      <span class="min-w-0"><span class="block text-xs text-stone-500"><?= $label ?></span><span class="block text-lg font-semibold text-wood-900 truncate"><?= h($value) ?></span><span class="block text-[11px] text-stone-400"><?= h($hint) ?></span></span>
    </div>
  <?php endforeach; ?>
</div>

<div class="grid lg:grid-cols-[minmax(0,1fr)_340px] gap-6 items-start">
  <div class="card p-3">
    <div class="flex flex-wrap items-center justify-between gap-2 px-1 pb-3">
      <div class="inline-flex rounded-xl bg-cream-100 p-1 text-sm font-semibold">
        <button type="button" class="px-3 py-1.5 rounded-lg bg-wood-800 text-white" data-view="bd"><i class="fa-solid fa-earth-asia"></i> Bangladesh</button>
        <button type="button" class="px-3 py-1.5 rounded-lg text-wood-800" data-view="dhaka"><i class="fa-solid fa-city"></i> Dhaka areas</button>
      </div>
      <span class="flex items-center gap-2 text-[11px] text-stone-500"><span class="w-3.5 h-3.5 rounded-sm border border-stone-300 bg-stone-100"></span> No orders <span class="ml-2">Fewer</span> <span class="w-24 h-2.5 rounded-full" style="background:linear-gradient(90deg,#fde047,#f97316,#b91c1c)"></span> More orders</span>
    </div>
    <div id="orderMap" class="rounded-xl overflow-hidden relative" style="height:min(75vh,680px);isolation:isolate;z-index:0;background:#fbf8f3"></div>
    <p class="text-[11px] text-stone-400 mt-2 px-1">Each zone is coloured by how many orders came from it; the zone with the most orders glows and carries a 🏆 label. Zoom into Dhaka (or press “Dhaka areas”) to see its thanas. Hover a zone for its numbers.</p>
  </div>

  <aside class="space-y-6">
    <section class="card p-4">
      <h2 class="font-serif text-lg text-wood-900 mb-3">Districts</h2>
      <?php if (!$mapData['districts']): ?>
        <p class="text-sm text-stone-500">No orders with a readable address yet.</p>
      <?php endif; ?>
      <ol class="space-y-2 max-h-80 overflow-y-auto pr-1">
        <?php $maxD = $mapData['districts'][0]['orders'] ?? 1; foreach ($mapData['districts'] as $i => $d): ?>
          <li>
            <button type="button" class="w-full text-left group" data-fly="d:<?= h($d['key']) ?>">
              <span class="flex items-center justify-between gap-2 text-sm"><span class="font-semibold text-wood-900 group-hover:underline"><?= $i + 1 ?>. <?= h($d['name']) ?> <span class="font-normal text-stone-400"><?= h($d['bn']) ?></span></span><span class="text-stone-600 whitespace-nowrap"><?= $d['orders'] ?> · <?= money($d['sales']) ?></span></span>
              <span class="block h-1.5 rounded-full bg-cream-200 mt-1"><span class="block h-full rounded-full bg-gradient-to-r from-amber-400 to-red-700" style="width:<?= max(4, round($d['orders'] / $maxD * 100)) ?>%"></span></span>
            </button>
          </li>
        <?php endforeach; ?>
      </ol>
      <?php if ($divisions): ?>
        <div class="flex flex-wrap gap-1.5 mt-4 pt-3 border-t border-cream-200">
          <?php foreach ($divisions as $name => $v): ?><span class="rounded-full bg-cream-100 px-2.5 py-1 text-[11px] text-wood-800"><?= h($name) ?> division · <b><?= $v['orders'] ?></b></span><?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    <section class="card p-4">
      <h2 class="font-serif text-lg text-wood-900 mb-3">Dhaka areas</h2>
      <?php if (!$areas): ?><p class="text-sm text-stone-500">No Dhaka orders yet.</p><?php endif; ?>
      <ol class="space-y-2 max-h-72 overflow-y-auto pr-1">
        <?php $maxA = max(array_column($areas, 'orders') ?: [1]); $n = 0; foreach ($areas as $key => $a): $n++; $known = isset(IC_DHAKA_AREAS[$key]); ?>
          <li>
            <button type="button" class="w-full text-left group" <?= $known ? 'data-fly="a:' . h($key) . '"' : 'disabled' ?>>
              <span class="flex items-center justify-between gap-2 text-sm"><span class="<?= $known ? 'font-semibold text-wood-900 group-hover:underline' : 'text-stone-500 italic' ?>"><?= $n ?>. <?= $known ? h(IC_DHAKA_AREAS[$key][0]) : 'Area not written (Dhaka)' ?></span><span class="text-stone-600 whitespace-nowrap"><?= $a['orders'] ?> · <?= money($a['sales']) ?></span></span>
              <span class="block h-1.5 rounded-full bg-cream-200 mt-1"><span class="block h-full rounded-full <?= $known ? 'bg-gradient-to-r from-amber-400 to-red-700' : 'bg-stone-300' ?>" style="width:<?= max(4, round($a['orders'] / $maxA * 100)) ?>%"></span></span>
            </button>
          </li>
        <?php endforeach; ?>
      </ol>
    </section>

    <?php if ($unplaced): ?>
      <section class="card p-4">
        <h2 class="font-serif text-lg text-wood-900">Not on the map (<?= $total - $placedCount ?>)</h2>
        <p class="text-xs text-stone-500 mb-3">These addresses have no district name. Open the order and add the district to the address to place it.</p>
        <ul class="space-y-2 max-h-60 overflow-y-auto pr-1 text-sm">
          <?php foreach ($unplaced as $o): ?>
            <li><a href="order.php?id=<?= (int) $o['id'] ?>" class="font-semibold text-wood-900 hover:underline"><?= h($o['code']) ?></a> · <?= h($o['customer_name']) ?><span class="block text-xs text-stone-500 truncate"><?= h($o['address']) ?></span></li>
          <?php endforeach; ?>
        </ul>
      </section>
    <?php endif; ?>
  </aside>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script>
  (() => {
    const DATA = <?= json_encode($mapData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    const box = document.getElementById('orderMap');
    if (typeof L === 'undefined') {
      box.innerHTML = '<p class="p-6 text-sm text-stone-500">The map could not load (no internet?). The lists on the right still show where orders come from.</p>';
      return;
    }
    // Only Bangladesh: no world map underneath, just the district shapes (and Dhaka's thanas when zoomed in)
    const map = L.map('orderMap', { scrollWheelZoom: true, minZoom: 6, maxZoom: 14, zoomSnap: .25, attributionControl: true }).setView([23.7, 90.35], 7);
    map.attributionControl.setPrefix(false).addAttribution('Boundaries: geoBoundaries (BBS / OCHA), CC BY 3.0 IGO');

    // Each zone is coloured by its number of orders: none = pale, few = yellow, most = deep red
    const mix = (t) => {
      const stops = [[253, 224, 71], [249, 115, 22], [185, 28, 28]];
      const s = t < .5 ? [stops[0], stops[1], t * 2] : [stops[1], stops[2], (t - .5) * 2];
      return `rgb(${s[0].map((v, i) => Math.round(v + (s[1][i] - v) * s[2])).join(',')})`;
    };
    const taka = (n) => '৳' + Math.round(n).toLocaleString('en-US');
    const byKey = (list) => Object.fromEntries(list.map(p => [p.key, p]));
    const counts = { d: byKey(DATA.districts), a: byKey(DATA.areas) };
    const maxOf = (list) => Math.max(1, ...list.map(p => p.orders));
    const max = { d: maxOf(DATA.districts), a: maxOf(DATA.areas) };
    const top = { d: DATA.districts[0] && DATA.districts[0].key, a: DATA.areas[0] && DATA.areas[0].key };
    const zones = { d: {}, a: {} };
    let thanasOn = false;

    const style = (type, f) => {
      const p = counts[type][f.properties.k];
      const n = p ? p.orders : 0;
      const hideDhaka = type === 'd' && f.properties.k === 'dhaka' && thanasOn; // its thanas are drawn on top
      if (hideDhaka) return { color: '#ffffff', weight: 0, fillOpacity: 0 };
      if (!n) return { color: '#ffffff', weight: type === 'a' ? 1.2 : .9, fillColor: '#e9e2d6', fillOpacity: 1 };
      const t = Math.sqrt(n / max[type]);
      const best = f.properties.k === top[type];
      return { color: best ? '#7f1d1d' : '#ffffff', weight: best ? 3 : 1.2, fillColor: mix(t), fillOpacity: 1, className: best ? 'top-zone top-zone-' + type : '' };
    };
    const describe = (type, f) => {
      const p = counts[type][f.properties.k];
      const name = type === 'd' ? `${f.properties.n} <span style="color:#78716c">${f.properties.bn}</span>` : (p ? p.name : f.properties.n);
      return p
        ? `<b style="font-size:15px">${name}</b><br>${p.orders} order${p.orders === 1 ? '' : 's'} · ${p.customers} customer${p.customers === 1 ? '' : 's'}<br>Sales ${taka(p.sales)}`
        : `<b>${name}</b><br><span style="color:#78716c">No orders yet</span>`;
    };
    const build = (type, geo) => L.geoJSON(geo, {
      style: (f) => style(type, f),
      onEachFeature: (f, lyr) => {
        lyr.bindTooltip(() => describe(type, f), { sticky: true });
        lyr.on('mouseover', () => { lyr.setStyle({ weight: 3, color: '#3d1f0e' }); lyr.bringToFront(); });
        lyr.on('mouseout', () => { lyr.setStyle(style(type, f)); });
        lyr.on('click', () => map.fitBounds(lyr.getBounds(), { maxZoom: type === 'a' ? 13 : 9, padding: [20, 20] }));
        (zones[type][f.properties.k] = zones[type][f.properties.k] || []).push(lyr);
      },
    });

    let districtLayer = null, thanaLayer = null, home = () => map.flyTo([23.7, 90.35], 7);
    const BN = '০১২৩৪৫৬৭৮৯', bn = (n) => String(n).replace(/\d/g, d => BN[d]);
    const labels = { d: L.layerGroup(), a: L.layerGroup() };
    // A label that stays on the zone with the most orders (one for Bangladesh, one for Dhaka's thanas)
    const markTop = (type) => {
      const p = top[type] && counts[type][top[type]], layers = p && zones[type][p.key];
      if (!layers) return;
      const biggest = layers.reduce((a, b) => (b.getBounds().getNorthEast().distanceTo(b.getBounds().getSouthWest()) > a.getBounds().getNorthEast().distanceTo(a.getBounds().getSouthWest()) ? b : a));
      const html = `<div class="top-dot"></div><div class="top-label"><span class="tl-k">🏆 সবচেয়ে বেশি অর্ডার</span><b>${p.bn}</b> · ${bn(p.orders)}টি অর্ডার</div>`;
      L.marker(biggest.getBounds().getCenter(), { icon: L.divIcon({ className: 'top-label-wrap', html, iconSize: null }), interactive: false, keyboard: false }).addTo(labels[type]);
    };
    const showLabels = () => {
      box.classList.toggle('thanas-on', thanasOn);
      if (thanasOn) { map.removeLayer(labels.d); labels.a.addTo(map); } else { map.removeLayer(labels.a); labels.d.addTo(map); }
    };
    const sync = () => {
      const zoomed = map.getZoom() >= 10;
      if (thanaLayer && zoomed !== thanasOn) {
        thanasOn = zoomed;
        if (zoomed) thanaLayer.addTo(map); else map.removeLayer(thanaLayer);
        districtLayer && districtLayer.setStyle((f) => style('d', f));
      }
      showLabels();
      document.querySelectorAll('[data-view]').forEach(b => {
        const on = (b.dataset.view === 'dhaka') === zoomed;
        b.className = 'px-3 py-1.5 rounded-lg ' + (on ? 'bg-wood-800 text-white' : 'text-wood-800');
      });
    };
    const load = (url) => fetch(url, { cache: 'force-cache' }).then(r => { if (!r.ok) throw new Error(url); return r.json(); });
    Promise.all([load('../assets/geo/bd-districts.geojson'), load('../assets/geo/dhaka-thanas.geojson')]).then(([d, a]) => {
      districtLayer = build('d', d).addTo(map);
      const country = districtLayer.getBounds();
      map.fitBounds(country, { padding: [10, 10] });
      map.setMaxBounds(country.pad(.15));
      home = () => map.flyToBounds(country, { padding: [10, 10] });
      thanaLayer = build('a', a);
      markTop('d'); markTop('a');
      sync();
    }).catch(() => {
      box.insertAdjacentHTML('beforeend', '<p class="absolute inset-x-3 bottom-3 z-[500] rounded-lg bg-white/95 p-3 text-sm text-red-700">Zone shapes are missing (assets/geo). Upload the assets/geo folder with the website.</p>');
    });
    map.on('zoomend', sync);

    const goTo = (type, key) => {
      const layers = zones[type][key];
      if (!layers) return;
      if (type === 'a' && map.getZoom() < 10) { thanasOn = true; thanaLayer.addTo(map); districtLayer.setStyle((f) => style('d', f)); showLabels(); }
      map.fitBounds(L.featureGroup(layers).getBounds(), { maxZoom: type === 'a' ? 13 : 9, padding: [24, 24] });
      layers.forEach(l => { l.setStyle({ weight: 4, color: '#3d1f0e' }); setTimeout(() => l.setStyle(style(type, l.feature)), 2200); });
      box.scrollIntoView({ behavior: 'smooth', block: 'center' });
    };
    document.querySelectorAll('[data-view]').forEach(b => b.addEventListener('click', () => {
      if (b.dataset.view === 'dhaka') map.flyTo([23.775, 90.40], 11); else home();
    }));
    document.querySelectorAll('[data-fly]').forEach(b => b.addEventListener('click', () => {
      const [type, key] = b.dataset.fly.split(':');
      goTo(type, key);
    }));
  })();
</script>
<?php admin_footer();