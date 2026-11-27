<?php
require __DIR__ . '/_inc.php';
require __DIR__ . '/_health.php';
require_admin();

$pdo = ic_db();
$cfg = ic_config();
$today = date('Y-m-d');
$todayStart = "$today 00:00:00";
$monthStart = date('Y-m-01 00:00:00');
$closed = "'" . implode("','", IC_CLOSED_STATUSES) . "'";
$waitingAlertDays = (int) ($cfg['waiting_alert_days'] ?? 5);

$byStatus = array_fill_keys(array_keys(IC_ORDER_STATUSES), 0);
foreach ($pdo->query('SELECT status, COUNT(*) AS n FROM orders GROUP BY status') as $row) {
    $byStatus[$row['status']] = (int) $row['n'];
}

$stat = function (string $sql, array $params = []) use ($pdo) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch();
};

$month = $stat("SELECT COUNT(*) AS n, COALESCE(SUM(total_bdt), 0) AS value FROM orders WHERE created_at >= ? AND status NOT IN ('cancelled', 'returned', 'fake')", [$monthStart]);
$fakeToday = $stat("SELECT COUNT(*) AS n FROM orders WHERE status = 'fake' AND created_at >= ?", [$todayStart]);
$todayNew = $stat('SELECT COUNT(*) AS n, COALESCE(SUM(total_bdt), 0) AS value FROM orders WHERE created_at >= ?', [$todayStart]);
$important = $stat("SELECT COUNT(*) AS n, SUM(CASE WHEN reminder_date IS NOT NULL AND reminder_date <= ? THEN 1 ELSE 0 END) AS due
    FROM orders WHERE is_important = 1 AND status NOT IN ($closed)", [$today]);
$waitingLate = $stat("SELECT COUNT(*) AS n FROM orders WHERE status = 'waiting' AND COALESCE(shipped_at, status_changed_at) < ?",
    [date('Y-m-d H:i:s', strtotime("-$waitingAlertDays days"))]);
$deliveredMonth = $stat("SELECT COUNT(*) AS n FROM orders WHERE status = 'delivered' AND status_changed_at >= ?", [$monthStart]);

// The 10 dashboard sections, in the order the shop works through them
$cards = [
    ['This month', (int) $month['n'] . ' orders', money((int) $month['value']) . ' total', 'fa-calendar-days', 'bg-wood-800 text-cream-50', 'orders.php?period=month'],
    ['Today new', (int) $todayNew['n'] . ' orders', money((int) $todayNew['value']) . ' placed today', 'fa-sun', 'bg-white', 'orders.php?period=today'],
    ['Hold', $byStatus['hold'], 'Missed shipping — moved here automatically', 'fa-hand', 'bg-orange-50', 'orders.php?status=hold'],
    ['Important', (int) $important['n'], ((int) $important['due']) . ' reminder' . ((int) $important['due'] === 1 ? '' : 's') . ' due', 'fa-star', 'bg-amber-50', 'orders.php?important=1'],
    ['Confirm', $byStatus['confirmed'], $byStatus['new'] . ' new waiting to confirm', 'fa-circle-check', 'bg-sky-50', 'orders.php?status=new'],
    ['Shipping', $byStatus['shipping'], 'Booked with the courier', 'fa-truck-ramp-box', 'bg-indigo-50', 'orders.php?status=shipping'],
    ['Waiting', $byStatus['waiting'], ((int) $waitingLate['n']) . " over $waitingAlertDays days — check these", 'fa-warehouse', 'bg-yellow-50', 'orders.php?status=waiting'],
    ['Delivery', $byStatus['delivered'], ((int) $deliveredMonth['n']) . ' this month', 'fa-house-circle-check', 'bg-emerald-50', 'orders.php?status=delivered'],
    ['Partial', $byStatus['partial'], 'Customer paid delivery charge only', 'fa-scale-unbalanced', 'bg-teal-50', 'orders.php?status=partial'],
    ['Return', $byStatus['returned'], 'Cancelled at the door', 'fa-rotate-left', 'bg-rose-50', 'orders.php?status=returned'],
    ['Fake order', $byStatus['fake'], ((int) $fakeToday['n']) . ' today · under ' . (int) ($cfg['fake_threshold'] ?? 40) . '% chance to accept', 'fa-user-secret', 'bg-red-50', 'orders.php?status=fake'],
];

$reminders = $pdo->prepare("SELECT * FROM orders WHERE is_important = 1 AND status NOT IN ($closed)
    ORDER BY CASE WHEN reminder_date IS NULL THEN 1 ELSE 0 END, reminder_date, id LIMIT 8");
$reminders->execute();
$reminders = $reminders->fetchAll();

$late = $pdo->prepare("SELECT * FROM orders WHERE status = 'waiting' AND COALESCE(shipped_at, status_changed_at) < ? ORDER BY shipped_at LIMIT 8");
$late->execute([date('Y-m-d H:i:s', strtotime("-$waitingAlertDays days"))]);
$late = $late->fetchAll();

$recent = $pdo->query('SELECT * FROM orders ORDER BY id DESC LIMIT 8')->fetchAll();
$lastSync = (int) ic_setting($pdo, 'last_courier_sync', '0');

admin_header('Dashboard', 'index.php');
?>
<div class="flex flex-wrap items-end justify-between gap-3 mb-6">
  <div>
    <p class="text-xs uppercase tracking-[0.25em] text-gold-600">Welcome back, <?= h($_SESSION['admin_name'] ?? '') ?></p>
    <h1 class="font-serif text-3xl text-wood-900 mt-1">Dashboard</h1>
  </div>
  <div class="flex flex-wrap items-center gap-3 text-xs text-stone-500">
    <?php if ($connectedNames = array_map(function ($c) { return IC_COURIERS[$c]['label']; }, ic_connected_couriers($pdo))): ?>
      <span><i class="fa-solid fa-truck-fast text-gold-600"></i> <?= h(implode(', ', $connectedNames)) ?> synced <?= $lastSync ? h(date('g:i a', $lastSync)) : 'not yet' ?></span>
    <?php else: ?>
      <a href="couriers.php" class="hover:underline"><i class="fa-solid fa-plug-circle-xmark text-stone-400"></i> No courier connected — connect one</a>
    <?php endif; ?>
    <a href="orders.php?status=new" class="btn btn-dark"><i class="fa-solid fa-inbox"></i> New orders (<?= $byStatus['new'] ?>)</a>
  </div>
</div>

<?php $todo = array_filter(ic_security_checks($pdo), function ($c) { return !$c[1]; }); ?>
<?php if ($todo): ?>
  <!-- Safety to-do: backup, HTTPS, cron … (Settings → Security check) -->
  <div class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 p-4">
    <div class="flex flex-wrap items-center justify-between gap-2">
      <p class="font-semibold text-amber-900"><i class="fa-solid fa-shield-halved"></i> <?= count($todo) ?> safety step<?= count($todo) === 1 ? '' : 's' ?> to do</p>
      <a href="security.php" class="text-sm font-semibold text-amber-900 underline">Open security check</a>
    </div>
    <ul class="mt-2 grid sm:grid-cols-2 gap-x-6 gap-y-1 text-sm text-amber-900">
      <?php foreach ($todo as [$id, $ok, $title, $text, $link]): ?>
        <li><a href="<?= h($link) ?>" class="hover:underline"><strong><?= h($title) ?>:</strong> <?= h($text) ?></a></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-4">
  <?php foreach ($cards as $i => [$label, $value, $hint, $icon, $tone, $link]): ?>
    <a href="<?= h($link) ?>" class="card p-4 sm:p-5 hover:shadow-md hover:-translate-y-0.5 transition <?= $tone ?>">
      <div class="flex items-center justify-between gap-2">
        <p class="text-[11px] font-semibold uppercase tracking-wider <?= $i === 0 ? 'text-gold-200' : 'text-stone-500' ?>"><?= $i + 1 ?>. <?= $label ?></p>
        <i class="fa-solid <?= $icon ?> <?= $i === 0 ? 'text-gold-300' : 'text-gold-500' ?>"></i>
      </div>
      <p class="font-serif text-2xl sm:text-3xl mt-2 <?= $i === 0 ? 'text-white' : 'text-wood-900' ?>"><?= h($value) ?></p>
      <p class="text-[11px] mt-1 leading-snug <?= $i === 0 ? 'text-cream-200' : 'text-stone-500' ?>"><?= h($hint) ?></p>
    </a>
  <?php endforeach; ?>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
  <section class="card overflow-hidden">
    <div class="flex items-center justify-between px-5 py-4 border-b border-cream-300">
      <h2 class="font-serif text-lg text-wood-900"><i class="fa-solid fa-bell text-gold-500 mr-1"></i> Important &amp; reminders</h2>
      <a href="orders.php?important=1" class="text-sm font-semibold text-gold-700 hover:text-wood-900">See all →</a>
    </div>
    <?php if (!$reminders): ?>
      <p class="p-5 text-sm text-stone-500">No important orders. Orders with a customer date are marked important automatically.</p>
    <?php else: ?>
      <ul class="divide-y divide-cream-200">
        <?php foreach ($reminders as $o):
            $due = $o['reminder_date'] && $o['reminder_date'] <= $today; ?>
          <li class="flex items-center justify-between gap-3 px-5 py-3 text-sm <?= $due ? 'bg-amber-50' : '' ?>">
            <span class="min-w-0">
              <a href="order.php?id=<?= (int) $o['id'] ?>" class="font-semibold text-wood-900 hover:underline"><?= h($o['code']) ?></a> · <?= h($o['customer_name']) ?>
              <span class="block text-xs text-stone-500 truncate"><?= h($o['reminder_note'] ?: 'Important order') ?></span>
            </span>
            <span class="text-right flex-shrink-0">
              <?php if ($o['reminder_date']): ?>
                <span class="block text-xs font-semibold <?= $due ? 'text-red-700' : 'text-stone-600' ?>"><i class="fa-regular fa-bell"></i> <?= h(date('d M', strtotime($o['reminder_date']))) ?><?= $due ? ' · due' : '' ?></span>
              <?php endif; ?>
              <?= status_badge($o['status']) ?>
            </span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

  <section class="card overflow-hidden">
    <div class="flex items-center justify-between px-5 py-4 border-b border-cream-300">
      <h2 class="font-serif text-lg text-wood-900"><i class="fa-solid fa-triangle-exclamation text-yellow-600 mr-1"></i> Waiting over <?= $waitingAlertDays ?> days</h2>
      <a href="orders.php?status=waiting" class="text-sm font-semibold text-gold-700 hover:text-wood-900">All waiting →</a>
    </div>
    <?php if (!$late): ?>
      <p class="p-5 text-sm text-stone-500">No parcels stuck with the courier. 👍</p>
    <?php else: ?>
      <ul class="divide-y divide-cream-200">
        <?php foreach ($late as $o): ?>
          <li class="flex items-center justify-between gap-3 px-5 py-3 text-sm">
            <span>
              <a href="order.php?id=<?= (int) $o['id'] ?>" class="font-semibold text-wood-900 hover:underline"><?= h($o['code']) ?></a> · <?= h($o['customer_name']) ?>
              <span class="block text-xs font-mono text-stone-500"><?= h($o['courier'] ?? '') ?> <?= h($o['tracking_code'] ?: $o['consignment_id'] ?: '') ?></span>
            </span>
            <span class="text-xs font-semibold text-red-700"><?= (int) floor((time() - strtotime($o['shipped_at'] ?: $o['status_changed_at'])) / 86400) ?> days</span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
</div>

<section class="card overflow-hidden mt-6">
  <div class="flex items-center justify-between px-5 py-4 border-b border-cream-300">
    <h2 class="font-serif text-lg text-wood-900">Latest orders</h2>
    <a href="orders.php" class="text-sm font-semibold text-gold-700 hover:text-wood-900">See all →</a>
  </div>
  <?php if (!$recent): ?>
    <p class="p-6 text-sm text-stone-500">No orders yet. They'll appear here as soon as customers order from the website.</p>
  <?php else: ?>
  <div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-cream-200">
      <tbody class="divide-y divide-cream-200">
        <?php foreach ($recent as $o): ?>
          <tr class="hover:bg-cream-50">
            <td class="td"><a href="order.php?id=<?= (int) $o['id'] ?>" class="font-semibold text-wood-900 hover:underline"><?= h($o['code']) ?></a>
              <?php if ($o['is_important']): ?><i class="fa-solid fa-star text-amber-500 text-xs" title="Important"></i><?php endif; ?>
              <span class="block text-xs text-stone-400"><?= h(date('d M, g:i a', strtotime($o['created_at']))) ?></span></td>
            <td class="td"><?= h($o['customer_name']) ?><span class="block text-xs text-stone-400"><?= h($o['whatsapp']) ?></span></td>
            <td class="td whitespace-nowrap"><?= money($o['total_bdt']) ?></td>
            <td class="td"><?= status_badge($o['status']) ?> <?= score_badge($o['delivery_score']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</section>
<?php admin_footer();
