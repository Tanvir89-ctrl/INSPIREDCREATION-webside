<?php
// Printable courier labels (parcel stickers) — one click from the orders list or an order page.
// labels.php?ids=1,2,3  |  labels.php?status=shipping  |  labels.php?booked=today  (add &print=1 to open the print dialog)
require __DIR__ . '/_inc.php';
require_admin();

$pdo = ic_db();
$where = '';
$params = [];
$heading = 'Selected orders';
if (!empty($_GET['ids'])) {
    $ids = array_values(array_unique(array_filter(array_map('intval', explode(',', (string) $_GET['ids'])))));
    $ids = array_slice($ids, 0, 200);
    if ($ids) {
        $where = 'WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
        $params = $ids;
    }
} elseif (!empty($_GET['booked']) && $_GET['booked'] === 'today') {
    $where = 'WHERE shipped_at >= ?';
    $params = [date('Y-m-d 00:00:00')];
    $heading = 'Sent to courier today';
} elseif (isset(IC_ORDER_STATUSES[$_GET['status'] ?? ''])) {
    $where = 'WHERE status = ?';
    $params = [(string) $_GET['status']];
    $heading = IC_ORDER_STATUSES[$_GET['status']] . ' orders';
}
$orders = [];
if ($where !== '') {
    $stmt = $pdo->prepare("SELECT * FROM orders $where ORDER BY id LIMIT 200");
    $stmt->execute($params);
    $orders = $stmt->fetchAll();
}

// "Packed & labelled" time for the parcel status page (the QR code): kept from the first time a label is made
$markPacked = $pdo->prepare('UPDATE orders SET details_json = ? WHERE id = ?');
foreach ($orders as $o) {
    $d = json_decode((string) $o['details_json'], true) ?: [];
    if (empty($d['label_printed_at'])) {
        $d['label_printed_at'] = ic_now();
        $markPacked->execute([json_encode($d, JSON_UNESCAPED_UNICODE), $o['id']]);
    }
}

$config = ic_config();
$shopName = 'INSPIRED CREATIONS';
$shopPhone = (string) ($config['shop_phone'] ?? '01705525009');
$shopAddress = (string) ($config['shop_address'] ?? 'Jurain, Dhaka');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Courier labels (<?= count($orders) ?>) — <?= h($shopName) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;600;700&family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jsbarcode/3.11.6/JsBarcode.all.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcode-generator/1.4.4/qrcode.min.js"></script>
  <style id="pageSize">@page { size: A4; margin: 8mm; }</style>
  <style>
    * { box-sizing: border-box; }
    body { margin: 0; background: #e7e2dc; color: #111; font-family: 'Plus Jakarta Sans', 'Hind Siliguri', Arial, sans-serif; }
    .bn { font-family: 'Hind Siliguri', 'Plus Jakarta Sans', Arial, sans-serif; }
    .bar { position: sticky; top: 0; z-index: 5; display: flex; flex-wrap: wrap; align-items: center; gap: 10px; padding: 12px 16px; background: #3D1F0E; color: #fff; }
    .bar h1 { font-size: 16px; margin: 0 auto 0 0; }
    .bar label { font-size: 13px; display: flex; align-items: center; gap: 6px; }
    .bar select, .bar button, .bar a { font: inherit; font-size: 13px; border-radius: 8px; border: 0; padding: 8px 12px; }
    .bar button { background: #D4A24C; color: #24130A; font-weight: 800; cursor: pointer; }
    .bar a { background: rgba(255,255,255,.12); color: #fff; text-decoration: none; }
    .empty { max-width: 520px; margin: 40px auto; background: #fff; padding: 24px; border-radius: 14px; font-size: 15px; }
    .sheet { display: grid; gap: 6mm; padding: 16px; justify-content: center; }
    .label { background: #fff; border: 1.4px dashed #333; padding: 4mm; overflow: hidden; break-inside: avoid; page-break-inside: avoid; display: flex; flex-direction: column; gap: 2.2mm; font-size: 3.2mm; line-height: 1.3; }
    .row { display: flex; justify-content: space-between; align-items: flex-start; gap: 3mm; }
    .from { font-size: 2.8mm; color: #333; }
    .from b { display: block; font-size: 3.6mm; color: #000; letter-spacing: .3px; }
    .courier { border: 1.6px solid #000; border-radius: 2mm; padding: 1mm 2.5mm; font-weight: 800; font-size: 3.4mm; text-transform: uppercase; white-space: nowrap; }
    .courier.none { border-style: dashed; color: #666; font-weight: 600; text-transform: none; }
    .code { text-align: center; border-top: 1px solid #000; border-bottom: 1px solid #000; padding: 1.5mm 0; }
    .code svg { display: block; margin: 0 auto; max-width: 100%; height: 14mm; }
    .code .id { font-family: Consolas, 'Courier New', monospace; font-weight: 800; font-size: 4.6mm; letter-spacing: .6px; }
    .code .sub { font-size: 2.7mm; color: #333; }
    .to small { display: block; font-size: 2.6mm; text-transform: uppercase; letter-spacing: .6px; color: #555; }
    .to .name { font-size: 4.2mm; font-weight: 700; }
    .to .phone { font-size: 5.6mm; font-weight: 800; letter-spacing: .4px; }
    .to .addr { font-size: 3.5mm; white-space: pre-line; max-height: 15mm; overflow: hidden; }
    .to-row { display: flex; gap: 3mm; align-items: flex-start; }
    .to-row .to { flex: 1; min-width: 0; }
    .qr { flex-shrink: 0; width: 21mm; text-align: center; }
    .qr .qr-img { width: 21mm; height: 21mm; }
    .qr .qr-img svg { display: block; width: 100%; height: 100%; }
    .qr small { display: block; font-size: 2.2mm; line-height: 1.15; color: #222; font-weight: 600; margin-top: .6mm; }
    body[data-size="4x6"] .qr, body[data-size="4x6"] .qr .qr-img { width: 25mm; }
    body[data-size="4x6"] .qr .qr-img { height: 25mm; }
    body[data-size="3x4"] .qr, body[data-size="3x4"] .qr .qr-img { width: 17mm; }
    body[data-size="3x4"] .qr .qr-img { height: 17mm; }
    body[data-size="3x4"] .qr small { font-size: 1.9mm; }
    .zone { display: inline-block; margin-top: 1mm; padding: .5mm 2mm; border-radius: 1.5mm; background: #000; color: #fff; font-weight: 700; font-size: 2.8mm; }
    .money { display: flex; align-items: center; justify-content: space-between; border: 1.8px solid #000; border-radius: 2mm; padding: 1.5mm 3mm; }
    .money span { font-size: 3mm; font-weight: 700; }
    .money b { font-size: 6.2mm; }
    .money.paid b { font-size: 5mm; }
    .items { font-size: 2.9mm; color: #222; border-top: 1px dotted #777; padding-top: 1.5mm; }
    .items .meta { display: flex; justify-content: space-between; font-weight: 700; color: #000; }
    .foot { margin-top: auto; text-align: center; font-size: 2.5mm; color: #555; }
    /* Sizes */
    body[data-size="a4"] .sheet { grid-template-columns: repeat(2, 94mm); }
    body[data-size="a4"] .label { width: 94mm; height: 132mm; }
    body[data-size="a4"] .to .addr { max-height: 22mm; }
    body[data-size="4x6"] .sheet { grid-template-columns: 100mm; }
    body[data-size="4x6"] .label { width: 100mm; height: 150mm; font-size: 3.8mm; gap: 3.5mm; padding: 5mm; }
    body[data-size="4x6"] .code svg { height: 20mm; }
    body[data-size="4x6"] .to .addr { max-height: 30mm; font-size: 4.2mm; }
    body[data-size="4x6"] .to .phone { font-size: 7mm; }
    body[data-size="4x6"] .money b { font-size: 8mm; }
    body[data-size="3x4"] .sheet { grid-template-columns: 76mm; }
    body[data-size="3x4"] .label { width: 76mm; height: 101mm; padding: 3mm; gap: 1.8mm; font-size: 3mm; }
    body[data-size="3x4"] .items, body[data-size="3x4"] .code .sub { display: none; }
    body[data-size="3x4"] .code svg { height: 11mm; }
    body[data-size="3x4"] .to .addr { max-height: 13.5mm; font-size: 3.2mm; }
    @media print {
      body { background: #fff; }
      .bar, .empty { display: none !important; }
      .sheet { padding: 0; gap: 4mm; }
      body[data-size="4x6"] .sheet, body[data-size="3x4"] .sheet { gap: 0; }
      body[data-size="4x6"] .label, body[data-size="3x4"] .label { border: 0; page-break-after: always; break-after: page; }
      body[data-size="a4"] .label:nth-child(4n) { page-break-after: always; break-after: page; }
    }
  </style>
</head>
<body data-size="a4">
  <div class="bar">
    <h1><i>🏷️</i> <?= count($orders) ?> courier label<?= count($orders) === 1 ? '' : 's' ?> · <?= h($heading) ?></h1>
    <label>Paper
      <select id="size">
        <option value="a4">A4 paper — 4 labels per page</option>
        <option value="4x6">Sticker printer 4×6 inch (100×150 mm)</option>
        <option value="3x4">Sticker printer 3×4 inch (75×100 mm)</option>
      </select>
    </label>
    <button type="button" id="printBtn" <?= $orders ? '' : 'disabled' ?>>🖨️ Print labels</button>
    <a href="orders.php">← Back to orders</a>
  </div>

<?php if (!$orders): ?>
  <div class="empty">No orders to print. Go back to <a href="orders.php">Orders</a>, tick the orders and press <b>Print labels</b>.</div>
<?php else: ?>
  <div class="sheet">
  <?php foreach ($orders as $o):
      $items = (array) json_decode((string) $o['items_json'], true);
      $booked = (string) ($o['consignment_id'] ?: $o['tracking_code']);
      $barcodeValue = $booked !== '' ? $booked : $o['code'];
      $cod = $o['cod_bdt'] !== null ? (int) $o['cod_bdt'] : max(0, (int) $o['total_bdt'] - (int) $o['advance_bdt']);
      $qty = array_sum(array_map(function ($i) { return (int) ($i['qty'] ?? 1); }, $items)); ?>
    <div class="label">
      <div class="row">
        <div class="from">From<b><?= h($shopName) ?></b><?= h($shopPhone) ?> · <?= h($shopAddress) ?></div>
        <?php if ($o['courier']): ?>
          <div class="courier"><?= h($o['courier']) ?></div>
        <?php else: ?>
          <div class="courier none">Courier: not booked</div>
        <?php endif; ?>
      </div>

      <div class="code">
        <svg class="barcode" data-value="<?= h($barcodeValue) ?>"></svg>
        <div class="id"><?= h($barcodeValue) ?></div>
        <div class="sub"><?php if ($booked !== ''): ?>Consignment ID<?= $o['tracking_code'] && $o['tracking_code'] !== $booked ? ' · Tracking ' . h($o['tracking_code']) : '' ?><?php else: ?>Order code (send to courier to get a consignment ID)<?php endif; ?></div>
      </div>

      <div class="to-row">
        <div class="to bn">
          <small>To</small>
          <div class="name"><?= h($o['customer_name']) ?></div>
          <div class="phone"><?= h($o['whatsapp']) ?></div>
          <div class="addr"><?= h($o['address'] ?: '(no address yet)') ?></div>
          <span class="zone"><?= $o['region'] === 'outside' ? 'Outside Dhaka' : 'Inside Dhaka' ?></span>
        </div>
        <?php if ($trackUrl = ic_track_url($pdo, $o)): ?>
          <div class="qr bn"><div class="qr-img" data-qr="<?= h($trackUrl) ?>"></div><small>স্ক্যান করে পার্সেলের অবস্থা দেখুন</small></div>
        <?php endif; ?>
      </div>

      <div class="money <?= $cod > 0 ? '' : 'paid' ?>">
        <span><?= $cod > 0 ? 'COLLECT (COD)' : 'PAYMENT' ?></span>
        <b><?= $cod > 0 ? '৳' . number_format($cod) : 'PAID ✓' ?></b>
      </div>

      <div class="items bn">
        <div class="meta"><span><?= h($o['code']) ?></span><span><?= h(date('d M Y', strtotime($o['created_at']))) ?> · <?= $qty ?> pc</span></div>
        <?php foreach (array_slice($items, 0, 3) as $item): ?>
          <div><?= h(mb_strimwidth((string) $item['title'], 0, 48, '…', 'UTF-8')) ?> × <?= (int) $item['qty'] ?></div>
        <?php endforeach; ?>
        <?php if (count($items) > 3): ?><div>+<?= count($items) - 3 ?> more item(s)</div><?php endif; ?>
        <?php if ($o['needed_by']): ?><div><b>Needed by <?= h(date('d M', strtotime($o['needed_by']))) ?></b></div><?php endif; ?>
      </div>

      <div class="foot">Handmade wooden gift — please handle with care · ধন্যবাদ</div>
    </div>
  <?php endforeach; ?>
  </div>
<?php endif; ?>

<script>
  (() => {
    const sizes = { a4: 'A4; margin: 8mm', '4x6': '100mm 150mm; margin: 0', '3x4': '76mm 101mm; margin: 0' };
    const select = document.getElementById('size');
    const apply = (value) => {
      document.body.dataset.size = value;
      document.getElementById('pageSize').textContent = `@page { size: ${sizes[value]}; }`;
      try { localStorage.setItem('ic_label_size', value); } catch (e) {}
    };
    let saved = 'a4';
    try { saved = localStorage.getItem('ic_label_size') || 'a4'; } catch (e) {}
    if (!sizes[saved]) saved = 'a4';
    select.value = saved;
    apply(saved);
    select.addEventListener('change', () => apply(select.value));

    document.querySelectorAll('svg.barcode').forEach((svg) => {
      try {
        JsBarcode(svg, svg.dataset.value, { format: 'CODE128', displayValue: false, margin: 0, height: 60, width: 2 });
      } catch (e) { svg.remove(); } // the ID text below the barcode is still printed
    });
    // QR code → the parcel status page (packed, labelled, on the way)
    document.querySelectorAll('[data-qr]').forEach((box) => {
      try {
        const qr = qrcode(0, 'M');
        qr.addData(box.dataset.qr);
        qr.make();
        box.innerHTML = qr.createSvgTag({ cellSize: 4, margin: 0, scalable: true });
      } catch (e) { box.closest('.qr').remove(); }
    });
    document.getElementById('printBtn').addEventListener('click', () => window.print());
    // One click from the orders page: open the print window right away
    <?php if ($orders && !empty($_GET['print'])): ?>
    window.addEventListener('load', () => setTimeout(() => window.print(), 400));
    <?php endif; ?>
  })();
</script>
</body>
</html>
