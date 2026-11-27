<?php
require __DIR__ . '/_inc.php';
require_admin();

$pdo = ic_db();

// Bulk actions on the ticked orders
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $ids = array_values(array_filter(array_map('intval', (array) ($_POST['ids'] ?? []))));
    $action = (string) ($_POST['action'] ?? '');
    $done = 0;
    $failed = [];
    foreach ($ids as $id) {
        $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
        $stmt->execute([$id]);
        $order = $stmt->fetch();
        if (!$order) {
            continue;
        }
        if ($action === 'confirm' && in_array($order['status'], ['new', 'hold', 'fake'], true)) {
            ic_set_status($pdo, $id, 'confirmed');
            $done++;
        } elseif ($action === 'fake' && in_array($order['status'], ['new', 'hold', 'confirmed'], true)) {
            ic_set_status($pdo, $id, 'fake');
            $done++;
        } elseif ($action === 'not_fake' && $order['status'] === 'fake') {
            ic_set_status($pdo, $id, 'new');
            ic_meta_order_event_from_row($pdo, $order);
            $done++;
        } elseif ($action === 'hold' && !in_array($order['status'], IC_CLOSED_STATUSES, true)) {
            ic_set_status($pdo, $id, 'hold');
            $done++;
        } elseif ($action === 'cancel' && !in_array($order['status'], IC_CLOSED_STATUSES, true)) {
            ic_set_status($pdo, $id, 'cancelled');
            $done++;
        } elseif ($action === 'courier' && in_array($order['status'], ['confirmed', 'hold', 'in_production'], true) && !$order['consignment_id']) {
            try {
                if (!$order['address'] || $order['total_bdt'] === null) {
                    throw new RuntimeException('needs an address and a total first');
                }
                ic_courier_create($pdo, $order, max(0, (int) $order['total_bdt'] - (int) $order['advance_bdt']));
                $done++;
            } catch (Throwable $e) {
                $failed[] = "{$order['code']} ({$e->getMessage()})";
            }
        }
    }
    $defaultCourierKey = ic_default_courier($pdo);
    $labels = ['confirm' => 'confirmed', 'hold' => 'moved to Hold', 'cancel' => 'cancelled',
        'courier' => 'sent to ' . ($defaultCourierKey ? IC_COURIERS[$defaultCourierKey]['label'] : 'the courier'),
        'fake' => 'marked as fake', 'not_fake' => 'moved back to New'];
    flash("$done order" . ($done === 1 ? '' : 's') . ' ' . ($labels[$action] ?? 'updated') . '.' . ($failed ? ' Not sent: ' . implode('; ', $failed) : ''));
    redirect('orders.php?' . (string) ($_POST['return'] ?? ''));
}

$status = isset(IC_ORDER_STATUSES[$_GET['status'] ?? '']) ? $_GET['status'] : '';
$period = in_array($_GET['period'] ?? '', ['today', 'month'], true) ? $_GET['period'] : '';
$important = !empty($_GET['important']);
$q = trim((string) ($_GET['q'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 25;

$where = [];
$params = [];
if ($status !== '') {
    $where[] = 'status = ?';
    $params[] = $status;
}
if ($period !== '') {
    $where[] = 'created_at >= ?';
    $params[] = $period === 'today' ? date('Y-m-d 00:00:00') : date('Y-m-01 00:00:00');
}
if ($important) {
    $where[] = "is_important = 1 AND status NOT IN ('" . implode("','", IC_CLOSED_STATUSES) . "')";
}
if ($q !== '') {
    $where[] = '(code LIKE ? OR customer_name LIKE ? OR whatsapp LIKE ? OR trx_id LIKE ? OR tracking_code LIKE ? OR consignment_id LIKE ? OR customer_email LIKE ?)';
    array_push($params, "%$q%", "%$q%", "%$q%", "%$q%", "%$q%", "%$q%", "%$q%");
}
$sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$orderBy = $important ? 'CASE WHEN reminder_date IS NULL THEN 1 ELSE 0 END, reminder_date, id DESC' : 'id DESC';

$count = $pdo->prepare("SELECT COUNT(*) FROM orders $sqlWhere");
$count->execute($params);
$total = (int) $count->fetchColumn();
$pages = max(1, (int) ceil($total / $perPage));

$list = $pdo->prepare("SELECT * FROM orders $sqlWhere ORDER BY $orderBy LIMIT $perPage OFFSET " . (($page - 1) * $perPage));
$list->execute($params);
$orders = $list->fetchAll();

$query = array_filter(['status' => $status, 'period' => $period, 'important' => $important ? '1' : '', 'q' => $q], 'strlen');
$link = function (array $extra) use ($query) {
    return 'orders.php?' . http_build_query(array_filter(array_merge($query, $extra), 'strlen'));
};
$title = $important ? 'Important orders' : ($period === 'today' ? "Today's orders" : ($period === 'month' ? "This month's orders" : 'Orders'));
$waitingAlertDays = (int) (ic_config()['waiting_alert_days'] ?? 5);

admin_header('Orders', 'orders.php');
?>
<div class="flex flex-wrap items-end justify-between gap-3 mb-6">
  <h1 class="font-serif text-3xl text-wood-900"><?= h($title) ?> <span class="text-base font-sans text-stone-400">(<?= $total ?>)</span></h1>
  <form method="get" class="flex gap-2 w-full sm:w-auto">
    <?php foreach (['status' => $status, 'period' => $period, 'important' => $important ? '1' : ''] as $k => $v): if ($v !== ''): ?>
      <input type="hidden" name="<?= $k ?>" value="<?= h($v) ?>">
    <?php endif; endforeach; ?>
    <input class="input sm:w-80" name="q" value="<?= h($q) ?>" placeholder="Search code, name, phone, Trx or tracking ID">
    <button class="btn btn-dark"><i class="fa-solid fa-magnifying-glass"></i></button>
  </form>
</div>

<div class="flex flex-nowrap sm:flex-wrap gap-2 mb-5 overflow-x-auto pb-1 [scrollbar-width:none]">
  <a href="orders.php" class="btn <?= !$query ? 'btn-dark' : 'btn-light' ?> !py-1.5">All</a>
  <a href="orders.php?period=today" class="btn <?= $period === 'today' && $status === '' ? 'btn-dark' : 'btn-light' ?> !py-1.5">Today</a>
  <a href="orders.php?important=1" class="btn <?= $important ? 'btn-dark' : 'btn-light' ?> !py-1.5"><i class="fa-solid fa-star text-amber-500"></i> Important</a>
  <?php foreach (IC_ORDER_STATUSES as $key => $label): ?>
    <a href="orders.php?status=<?= $key ?>" class="btn <?= $status === $key ? 'btn-dark' : 'btn-light' ?> !py-1.5"><?= h($label) ?></a>
  <?php endforeach; ?>
</div>

<form method="post" class="card overflow-hidden" id="bulkForm">
  <?= csrf_field() ?>
  <input type="hidden" name="return" value="<?= h(http_build_query($query)) ?>">
  <div class="flex flex-nowrap sm:flex-wrap items-center gap-2 px-3 sm:px-4 py-3 border-b border-cream-300 bg-cream-50 text-sm overflow-x-auto [scrollbar-width:none]">
    <label class="sm:hidden flex items-center gap-1.5 text-stone-600 whitespace-nowrap"><input type="checkbox" id="selectAllMobile" class="rounded w-5 h-5"> All</label>
    <span class="text-stone-500 mr-1 whitespace-nowrap"><span id="selectedCount">0</span> selected:</span>
    <button name="action" value="confirm" class="btn btn-light !py-1.5"><i class="fa-solid fa-circle-check text-sky-600"></i> Confirm</button>
    <?php if ($defaultCourier = ic_default_courier($pdo)): ?>
      <button name="action" value="courier" class="btn btn-light !py-1.5"><i class="fa-solid fa-truck-fast text-indigo-600"></i> Send to <?= h(IC_COURIERS[$defaultCourier]['label']) ?></button>
    <?php endif; ?>
    <button name="action" value="hold" class="btn btn-light !py-1.5"><i class="fa-solid fa-hand text-orange-600"></i> Hold</button>
    <?php if ($status === 'fake'): ?>
      <button name="action" value="not_fake" class="btn btn-light !py-1.5"><i class="fa-solid fa-user-check text-emerald-600"></i> Not fake — move to New</button>
    <?php else: ?>
      <button name="action" value="fake" class="btn btn-light !py-1.5"><i class="fa-solid fa-user-secret text-red-600"></i> Mark fake</button>
    <?php endif; ?>
    <button name="action" value="cancel" class="btn btn-light !py-1.5" onclick="return confirm('Cancel the selected orders?');"><i class="fa-solid fa-ban text-stone-500"></i> Cancel</button>
  </div>
  <?php if (!$orders): ?>
    <p class="p-6 text-sm text-stone-500">No orders found.</p>
  <?php else: ?>
  <!-- Phones: one card per order (the table below is for bigger screens) -->
  <div class="sm:hidden divide-y divide-cream-200">
    <?php foreach ($orders as $o):
        $items = (array) json_decode($o['items_json'], true); ?>
      <div class="flex gap-3 p-3">
        <input type="checkbox" name="ids[]" value="<?= (int) $o['id'] ?>" class="row-check rounded mt-1 w-5 h-5 flex-shrink-0" aria-label="Select <?= h($o['code']) ?>">
        <a href="order.php?id=<?= (int) $o['id'] ?>" class="flex-1 min-w-0 block">
          <span class="flex items-start justify-between gap-2">
            <span class="min-w-0">
              <span class="font-semibold text-wood-900"><?= h($o['code']) ?></span><?php if ($o['is_important']): ?> <i class="fa-solid fa-star text-amber-500 text-xs"></i><?php endif; ?>
              <span class="block text-xs text-stone-400"><?= h(date('d M, g:i a', strtotime($o['created_at']))) ?></span>
            </span>
            <span class="text-right flex-shrink-0"><?= status_badge($o['status']) ?>
              <span class="block font-semibold text-wood-900 mt-1"><?= $o['total_bdt'] === null ? 'Quote' : money($o['total_bdt']) ?></span></span>
          </span>
          <span class="block text-sm mt-1"><?= h($o['customer_name']) ?> · <span class="text-emerald-700"><?= h($o['whatsapp']) ?></span></span>
          <span class="block text-xs text-stone-500 truncate"><?= h(($items[0]['title'] ?? '') . (isset($items[0]) ? ' × ' . (int) $items[0]['qty'] : '')) ?><?= count($items) > 1 ? ' +' . (count($items) - 1) . ' more' : '' ?></span>
          <span class="flex flex-wrap items-center gap-2 mt-1 text-xs text-stone-500">
            <?= score_badge($o['delivery_score']) ?>
            <?php if ($o['courier']): ?><span><i class="fa-solid fa-truck-fast"></i> <?= h($o['courier']) ?> <?= h($o['tracking_code'] ?: $o['consignment_id']) ?></span><?php endif; ?>
            <?php if ($o['reminder_date']): ?><span class="<?= $o['reminder_date'] <= date('Y-m-d') ? 'text-red-700 font-semibold' : '' ?>"><i class="fa-regular fa-bell"></i> <?= h(date('d M', strtotime($o['reminder_date']))) ?></span><?php endif; ?>
          </span>
        </a>
      </div>
    <?php endforeach; ?>
  </div>
  <div class="overflow-x-auto hidden sm:block">
    <table class="min-w-full divide-y divide-cream-200">
      <thead class="bg-cream-50">
        <tr>
          <th class="th w-8"><input type="checkbox" id="selectAll" class="rounded" aria-label="Select all"></th>
          <th class="th">Order</th><th class="th">Customer</th><th class="th">Items</th><th class="th">Total</th><th class="th">Payment</th><th class="th">Courier</th><th class="th">Status</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-cream-200">
        <?php foreach ($orders as $o):
            $items = (array) json_decode($o['items_json'], true);
            $waitingDays = $o['status'] === 'waiting' ? (int) floor((time() - strtotime($o['shipped_at'] ?: $o['status_changed_at'])) / 86400) : null; ?>
          <tr class="hover:bg-cream-50">
            <td class="td"><input type="checkbox" name="ids[]" value="<?= (int) $o['id'] ?>" class="row-check rounded" aria-label="Select <?= h($o['code']) ?>"></td>
            <td class="td">
              <a href="order.php?id=<?= (int) $o['id'] ?>" class="font-semibold text-wood-900 hover:underline"><?= h($o['code']) ?></a>
              <?php if ($o['is_important']): ?><i class="fa-solid fa-star text-amber-500 text-xs" title="Important"></i><?php endif; ?>
              <span class="block text-xs text-stone-400"><?= h(date('d M Y, g:i a', strtotime($o['created_at']))) ?></span>
              <?php if ($o['reminder_date']): ?>
                <span class="block text-xs <?= $o['reminder_date'] <= date('Y-m-d') ? 'text-red-700 font-semibold' : 'text-stone-500' ?>"><i class="fa-regular fa-bell"></i> <?= h(date('d M', strtotime($o['reminder_date']))) ?></span>
              <?php endif; ?>
            </td>
            <td class="td"><?= h($o['customer_name']) ?>
              <a href="<?= h(whatsapp_link($o['whatsapp'])) ?>" target="_blank" rel="noopener" class="block text-xs text-emerald-700 hover:underline"><i class="fa-brands fa-whatsapp"></i> <?= h($o['whatsapp']) ?></a></td>
            <td class="td max-w-xs">
              <?php foreach (array_slice($items, 0, 2) as $item): ?>
                <span class="block truncate"><?= h($item['title']) ?> × <?= (int) $item['qty'] ?></span>
              <?php endforeach; ?>
              <?php if (count($items) > 2): ?><span class="text-xs text-stone-400">+<?= count($items) - 2 ?> more</span><?php endif; ?>
            </td>
            <td class="td whitespace-nowrap font-semibold text-wood-900"><?= $o['total_bdt'] === null ? '<span class="font-normal text-stone-400">Quote</span>' : money($o['total_bdt']) ?></td>
            <td class="td text-xs"><?= h($o['payment_method'] ?? '—') ?><?php if ($o['trx_id']): ?><span class="block font-mono text-stone-500"><?= h($o['trx_id']) ?></span><?php endif; ?></td>
            <td class="td text-xs">
              <?php if ($o['courier']): ?>
                <?= h($o['courier']) ?><span class="block font-mono text-stone-500"><?= h($o['tracking_code'] ?: $o['consignment_id']) ?></span>
                <?php if ($o['cod_bdt'] !== null): ?><span class="block text-stone-500">COD <?= money($o['cod_bdt']) ?></span><?php endif; ?>
              <?php else: ?>—<?php endif; ?>
            </td>
            <td class="td"><?= status_badge($o['status']) ?>
              <?php if ($o['delivery_score'] !== null): ?><span class="block mt-1"><?= score_badge($o['delivery_score']) ?></span><?php endif; ?>
              <?php if ($waitingDays !== null): ?><span class="block text-xs mt-1 <?= $waitingDays > $waitingAlertDays ? 'text-red-700 font-semibold' : 'text-stone-500' ?>"><?= $waitingDays ?> day<?= $waitingDays === 1 ? '' : 's' ?></span><?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</form>

<?php if ($pages > 1): ?>
  <div class="flex justify-center gap-2 mt-6">
    <?php for ($p = 1; $p <= $pages; $p++): ?>
      <a href="<?= h($link(['page' => $p > 1 ? (string) $p : ''])) ?>" class="btn <?= $p === $page ? 'btn-dark' : 'btn-light' ?> !px-3 !py-1.5"><?= $p ?></a>
    <?php endfor; ?>
  </div>
<?php endif; ?>

<script>
  const checks = [...document.querySelectorAll('.row-check')];
  // Each order has a checkbox in the phone cards and in the table; only the visible ones count and are sent
  const visible = () => checks.filter(c => c.offsetParent !== null);
  const updateCount = () => { document.getElementById('selectedCount').textContent = visible().filter(c => c.checked).length; };
  ['selectAll', 'selectAllMobile'].forEach(id => document.getElementById(id)?.addEventListener('change', (e) => { visible().forEach(c => { c.checked = e.target.checked; }); updateCount(); }));
  checks.forEach(c => c.addEventListener('change', updateCount));
  document.getElementById('bulkForm').addEventListener('submit', (e) => {
    if (!visible().some(c => c.checked)) { e.preventDefault(); alert('Tick at least one order first.'); return; }
    checks.filter(c => c.offsetParent === null).forEach(c => { c.disabled = true; }); // never send an order twice
  });
</script>
<?php admin_footer();
