<?php
require __DIR__ . '/_inc.php';
require_admin();

$pdo = ic_db();
if (isset($_GET['code'])) {
    $stmt = $pdo->prepare('SELECT * FROM orders WHERE code = ?');
    $stmt->execute([(string) $_GET['code']]);
} else {
    $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
    $stmt->execute([(int) ($_GET['id'] ?? 0)]);
}
$order = $stmt->fetch();
if (!$order) {
    http_response_code(404);
    admin_header('Order not found', 'orders.php');
    echo '<p class="card p-6">This order does not exist. <a class="underline" href="orders.php">Back to orders</a></p>';
    admin_footer();
    exit;
}
$id = (int) $order['id'];

$filesStmt = $pdo->prepare('SELECT * FROM order_files WHERE order_id = ? ORDER BY id');
$filesStmt->execute([$id]);
$files = $filesStmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    if (($_POST['action'] ?? '') === 'delete') {
        foreach ($files as $file) {
            @unlink(ic_uploads_dir() . '/' . basename($file['stored_name']));
        }
        $pdo->prepare('DELETE FROM order_files WHERE order_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM orders WHERE id = ?')->execute([$id]);
        flash("Order {$order['code']} was deleted.");
        redirect('orders.php');
    }
    $action = (string) ($_POST['action'] ?? 'update');

    if ($action === 'confirm') {
        ic_set_status($pdo, $id, 'confirmed');
        flash('Order confirmed.');
    } elseif ($action === 'not_fake') {
        ic_set_status($pdo, $id, 'new');
        if ($order['status'] === 'fake') {
            ic_meta_order_event_from_row($pdo, $order); // Facebook now learns from this real order
        }
        flash('Moved back to New orders.');
    } elseif ($action === 'mark_fake') {
        ic_set_status($pdo, $id, 'fake');
        flash('Marked as a fake order.');
    } elseif ($action === 'rescore') {
        $check = ic_score_order($pdo, $id);
        flash("Delivery chance rechecked: {$check['score']}%.");
    } elseif ($action === 'courier') {
        $courier = isset(IC_COURIERS[$_POST['courier_key'] ?? '']) ? $_POST['courier_key'] : ic_default_courier($pdo);
        $label = $courier ? IC_COURIERS[$courier]['label'] : 'Courier';
        try {
            ic_courier_create($pdo, $order, max(0, (int) ($_POST['cod_bdt'] ?? 0)), $courier, [
                'city' => (int) ($_POST['pathao_city'] ?? 0), 'zone' => (int) ($_POST['pathao_zone'] ?? 0), 'area' => (int) ($_POST['pathao_area'] ?? 0),
                'redx_area_id' => (int) ($_POST['redx_area_id'] ?? 0), 'redx_area_name' => (string) ($_POST['redx_area_name'] ?? ''),
                'weight' => max(0.1, (float) ($_POST['weight'] ?? 0.5)),
            ]);
            $booked = $pdo->prepare('SELECT consignment_id, tracking_code FROM orders WHERE id = ?');
            $booked->execute([$id]);
            $bookedRow = $booked->fetch() ?: [];
            $newId = (string) (($bookedRow['consignment_id'] ?? '') ?: ($bookedRow['tracking_code'] ?? ''));
            flash("Parcel booked with $label" . ($newId !== '' ? " — Consignment ID $newId" : '') . '. The order is now in Shipping.');
        } catch (Throwable $e) {
            flash("$label: " . $e->getMessage());
        }
    } elseif ($action === 'tracking') {
        // Manual courier entry (any courier): saving a tracking code moves the order to Shipping
        $tracking = ic_clean((string) ($_POST['tracking_code'] ?? ''), 60);
        $courier = ic_clean((string) ($_POST['courier'] ?? ''), 30);
        if ($tracking !== '') {
            $now = ic_now();
            $pdo->prepare('UPDATE orders SET courier = ?, tracking_code = ?, cod_bdt = ?, shipped_at = COALESCE(shipped_at, ?), updated_at = ? WHERE id = ?')
                ->execute([$courier !== '' ? $courier : 'Courier', $tracking, max(0, (int) ($_POST['cod_bdt'] ?? 0)), $now, $now, $id]);
            if (in_array($order['status'], ['new', 'confirmed', 'hold', 'in_production'], true)) {
                ic_set_status($pdo, $id, 'shipping');
            }
            flash('Tracking saved — the order is now in Shipping.');
        }
    } elseif ($action === 'sync') {
        ic_set_setting($pdo, 'last_courier_sync', '0');
        $moved = ic_courier_sync($pdo);
        flash($moved ? "Courier status updated ($moved order" . ($moved === 1 ? '' : 's') . ' moved).' : 'Checked the courier — no change yet.');
    } elseif ($action === 'important') {
        $date = (string) ($_POST['reminder_date'] ?? '');
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : null;
        $important = !empty($_POST['is_important']) ? 1 : 0;
        $pdo->prepare('UPDATE orders SET is_important = ?, reminder_date = ?, reminder_note = ?, reminder_sent_at = ?, updated_at = ? WHERE id = ?')
            ->execute([
                $important, $important ? $date : null, ic_clean((string) ($_POST['reminder_note'] ?? ''), 255),
                $date === $order['reminder_date'] ? $order['reminder_sent_at'] : null, ic_now(), $id,
            ]);
        flash($important ? 'Saved as important' . ($date ? ' — reminder on ' . date('d M Y', strtotime($date)) . '.' : '.') : 'Removed from important.');
    } else {
        $newStatus = isset(IC_ORDER_STATUSES[$_POST['status'] ?? '']) ? $_POST['status'] : $order['status'];
        $total = trim((string) ($_POST['total_bdt'] ?? ''));
        $total = $total === '' ? null : max(0, (int) $total);
        $advance = trim((string) ($_POST['advance_bdt'] ?? ''));
        $advance = $advance === '' ? null : max(0, (int) $advance);
        $address = ic_clean((string) ($_POST['address'] ?? ''), 500);
        $pdo->prepare('UPDATE orders SET total_bdt = ?, advance_bdt = ?, address = ?, admin_note = ?, updated_at = ? WHERE id = ?')
            ->execute([$total, $advance, $address !== '' ? $address : null, ic_clean((string) ($_POST['admin_note'] ?? ''), 5000), ic_now(), $id]);
        if ($newStatus !== $order['status']) {
            ic_set_status($pdo, $id, $newStatus);
        }
        flash('Order updated.');
    }
    redirect("order.php?id=$id");
}

$items = (array) json_decode($order['items_json'], true);
$details = (array) json_decode($order['details_json'], true);
$marks = $details['marks'] ?? [];
$markedPhoto = null;
foreach ($files as $file) {
    if ($file['original_name'] === 'marked-design.jpg') {
        $markedPhoto = $file;
    }
}
$isImage = function (array $file) {
    return in_array(strtolower(pathinfo($file['stored_name'], PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
};

$firstName = explode(' ', $order['customer_name'])[0];
$waTemplates = [
    'Confirm order'   => "Hello $firstName, thank you for your order {$order['code']} with INSPIRED CREATIONS. We've received it and will confirm the details shortly.",
    'Send quote'      => "Hello $firstName, here is the price for your order {$order['code']}: ৳___ . To start, please send a 30% advance (৳___) by bKash/Nagad to 01321-744476 and reply with the Transaction ID.",
    'Advance received'=> "Hello $firstName, we've received your advance for order {$order['code']}. Your piece is now in production — we'll update you soon.",
    'On its way'      => "Hello $firstName, your order {$order['code']} is on its way!"
        . ($order['tracking_code'] ? " {$order['courier']} tracking: {$order['tracking_code']}." : '')
        . ' See your parcel status here: ' . ic_track_url($pdo, $order)
        . ' Thank you for choosing INSPIRED CREATIONS.',
];

admin_header($order['code'], 'orders.php');
?>
<a href="orders.php" class="text-sm font-semibold text-gold-700 hover:text-wood-900">← All orders</a>
<div class="flex flex-wrap items-end justify-between gap-3 mt-2 mb-6">
  <div>
    <h1 class="font-serif text-3xl text-wood-900"><?= h($order['code']) ?> <?= status_badge($order['status']) ?></h1>
    <p class="text-sm text-stone-500 mt-1"><?= $order['type'] === 'custom' ? 'Custom Order Studio' : 'Cart checkout' ?> · placed <?= h(date('d M Y, g:i a', strtotime($order['created_at']))) ?></p>
  </div>
  <div class="flex flex-wrap gap-2">
    <?php if (in_array($order['status'], ['new', 'hold', 'fake'], true)): ?>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="confirm">
        <button class="btn bg-sky-700 text-white hover:bg-sky-800"><i class="fa-solid fa-circle-check"></i> Confirm order</button>
      </form>
    <?php endif; ?>
    <a href="<?= h(whatsapp_link($order['whatsapp'])) ?>" target="_blank" rel="noopener" class="btn bg-emerald-700 text-white hover:bg-emerald-800"><i class="fa-brands fa-whatsapp text-lg"></i> Message on WhatsApp</a>
    <?php if ($order['address']): ?>
      <a href="labels.php?ids=<?= $id ?>&amp;print=1" target="_blank" class="btn btn-light"><i class="fa-solid fa-print"></i> Print label</a>
    <?php endif; ?>
    <a href="<?= h(ic_track_url($pdo, $order)) ?>" target="_blank" rel="noopener" class="btn btn-light" title="The page the QR code on the label opens"><i class="fa-solid fa-qrcode"></i> Parcel status page</a>
  </div>
</div>
<?php if ($order['is_important'] && $order['reminder_date'] && $order['reminder_date'] <= date('Y-m-d') && !in_array($order['status'], IC_CLOSED_STATUSES, true)): ?>
  <div class="mb-6 rounded-xl bg-amber-50 border border-amber-300 px-4 py-3 text-sm text-amber-900">
    <i class="fa-solid fa-bell"></i> <strong>Reminder due:</strong> <?= h($order['reminder_note'] ?: 'Important order') ?>
    <?php if ($order['needed_by']): ?> — customer needs it by <strong><?= h(date('d M Y', strtotime($order['needed_by']))) ?></strong><?php endif; ?>
  </div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
  <div class="lg:col-span-2 space-y-6">

    <section class="card overflow-hidden">
      <h2 class="font-serif text-lg text-wood-900 px-5 py-4 border-b border-cream-300">Items</h2>
      <table class="min-w-full divide-y divide-cream-200">
        <thead class="bg-cream-50"><tr><th class="th">Product</th><th class="th">Qty</th><th class="th">Unit</th><th class="th text-right">Line total</th></tr></thead>
        <tbody class="divide-y divide-cream-200">
          <?php foreach ($items as $item): ?>
            <tr>
              <td class="td"><?= h($item['title']) ?></td>
              <td class="td"><?= (int) $item['qty'] ?></td>
              <td class="td whitespace-nowrap"><?= $item['unit_bdt'] === null ? 'Quote' : money($item['unit_bdt']) ?></td>
              <td class="td text-right whitespace-nowrap"><?= $item['unit_bdt'] === null ? '—' : money($item['unit_bdt'] * $item['qty']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <dl class="px-5 py-4 bg-cream-50 text-sm space-y-1.5">
        <?php if ($order['delivery_bdt'] !== null): ?>
          <div class="flex justify-between"><dt>Subtotal</dt><dd><?= money($order['subtotal_bdt']) ?></dd></div>
          <div class="flex justify-between"><dt>Delivery (<?= h($order['region'] === 'outside' ? 'outside Dhaka' : 'inside Dhaka') ?>)</dt><dd><?= $order['delivery_bdt'] ? money($order['delivery_bdt']) : 'Free' ?></dd></div>
        <?php endif; ?>
        <div class="flex justify-between font-semibold text-wood-900 text-base"><dt>Total</dt><dd><?= $order['total_bdt'] === null ? 'Price on request' : money($order['total_bdt']) ?></dd></div>
        <?php if ($order['advance_bdt'] !== null): ?>
          <div class="flex justify-between text-gold-700 font-semibold"><dt>Advance (30%)</dt><dd><?= money($order['advance_bdt']) ?></dd></div>
          <div class="flex justify-between"><dt>Due on delivery</dt><dd><?= money((int) $order['total_bdt'] - (int) $order['advance_bdt']) ?></dd></div>
        <?php endif; ?>
      </dl>
    </section>

    <?php if ($order['type'] === 'custom'): ?>
    <section class="card p-5 space-y-4">
      <h2 class="font-serif text-lg text-wood-900">Customer's design</h2>
      <?php if ($markedPhoto): ?>
        <a href="file.php?id=<?= (int) $markedPhoto['id'] ?>&amp;inline=1" target="_blank">
          <img src="file.php?id=<?= (int) $markedPhoto['id'] ?>&amp;inline=1" alt="Product photo with the customer's marks" class="rounded-xl max-h-96 border border-cream-300">
        </a>
      <?php endif; ?>
      <dl class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm">
        <div class="rounded-xl bg-cream-50 p-3"><dt class="label">Engraving text</dt><dd class="text-wood-900 font-medium"><?= h($details['engraving_text'] ?? '') ?: '—' ?></dd></div>
        <div class="rounded-xl bg-cream-50 p-3"><dt class="label">Font</dt><dd><?= h($details['font'] ?? '—') ?></dd></div>
        <div class="rounded-xl bg-cream-50 p-3"><dt class="label">Occasion</dt><dd><?= h($details['occasion'] ?? '—') ?></dd></div>
      </dl>
      <?php if ($marks): ?>
        <div>
          <p class="label">Marked spots</p>
          <ol class="space-y-1.5 text-sm">
            <?php foreach ($marks as $i => $mark): ?>
              <li class="flex gap-2"><span class="w-6 h-6 flex-shrink-0 rounded-full bg-gold-300 text-wood-900 text-xs font-bold flex items-center justify-center"><?= $i + 1 ?></span><?= h($mark['note'] !== '' ? $mark['note'] : '(no note)') ?></li>
            <?php endforeach; ?>
          </ol>
        </div>
      <?php endif; ?>
      <?php if (!empty($details['notes'])): ?>
        <div><p class="label">Customer's idea</p><p class="text-sm whitespace-pre-line rounded-xl bg-cream-50 p-3"><?= h($details['notes']) ?></p></div>
      <?php endif; ?>
    </section>
    <?php elseif (!empty($details['notes'])): ?>
    <section class="card p-5"><p class="label">Customer's note</p><p class="text-sm whitespace-pre-line"><?= h($details['notes']) ?></p></section>
    <?php endif; ?>

    <?php if ($files): ?>
    <section class="card p-5">
      <h2 class="font-serif text-lg text-wood-900 mb-4">Files (<?= count($files) ?>)</h2>
      <ul class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <?php foreach ($files as $file): ?>
          <li class="flex items-center gap-3 rounded-xl border border-cream-300 p-3">
            <?php if ($isImage($file)): ?>
              <img src="file.php?id=<?= (int) $file['id'] ?>&amp;inline=1" alt="" class="w-14 h-14 rounded-lg object-cover bg-cream-100">
            <?php else: ?>
              <span class="w-14 h-14 rounded-lg bg-cream-100 flex items-center justify-center text-wood-700"><i class="fa-regular fa-file-lines text-xl"></i></span>
            <?php endif; ?>
            <span class="min-w-0 flex-1">
              <span class="block truncate text-sm font-medium text-wood-900"><?= h($file['original_name']) ?></span>
              <span class="block text-xs text-stone-400"><?= number_format($file['size_bytes'] / 1024, 0) ?> KB</span>
            </span>
            <a href="file.php?id=<?= (int) $file['id'] ?>" class="btn btn-light !px-3 !py-2" title="Download"><i class="fa-solid fa-download"></i></a>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>
    <?php endif; ?>
  </div>

  <aside class="space-y-6">
    <section class="card p-5 space-y-3 text-sm">
      <h2 class="font-serif text-lg text-wood-900">Customer</h2>
      <p><span class="label">Name</span><?= h($order['customer_name']) ?></p>
      <p><span class="label">WhatsApp</span><a class="text-emerald-700 hover:underline" href="<?= h(whatsapp_link($order['whatsapp'])) ?>" target="_blank" rel="noopener"><?= h($order['whatsapp']) ?></a></p>
      <?php if (!empty($order['customer_email'])): ?><p><span class="label">Email</span><a class="text-sky-700 hover:underline break-all" href="mailto:<?= h($order['customer_email']) ?>"><?= h($order['customer_email']) ?></a>
        <?php $mailState = (json_decode((string) $order['details_json'], true) ?: [])['email_sent'] ?? null; ?>
        <span class="block text-xs <?= $mailState === 'failed' ? 'text-red-600' : 'text-stone-400' ?>"><?= $mailState === 'failed' ? 'The confirmation email could not be sent (check the hosting email settings)' : ($mailState ? 'Order confirmation emailed ' . h(date('d M, g:i a', strtotime($mailState))) : '') ?></span></p><?php endif; ?>
      <?php if ($order['address']): ?><p><span class="label">Address</span><span class="whitespace-pre-line"><?= h($order['address']) ?></span></p><?php endif; ?>
      <p><span class="label">Payment</span><?= h($order['payment_method'] ?? '—') ?>
        <?php if ($order['trx_id']): ?><span class="block font-mono text-wood-900 mt-0.5">Trx ID: <?= h($order['trx_id']) ?></span><?php endif; ?></p>
    </section>

    <?php
    $score = $order['delivery_score'] === null ? null : (int) $order['delivery_score'];
    $scoreReasons = (array) json_decode((string) $order['score_reasons'], true);
    $threshold = (int) (ic_config()['fake_threshold'] ?? 40);
    ?>
    <section class="card p-5 space-y-4 <?= $order['status'] === 'fake' ? 'ring-2 ring-red-300' : '' ?>">
      <h2 class="font-serif text-lg text-wood-900"><i class="fa-solid fa-shield-halved text-gold-500"></i> Delivery chance</h2>
      <?php if ($score === null): ?>
        <p class="text-sm text-stone-500">Not checked yet.</p>
      <?php else:
          $tone = $score >= 70 ? 'text-emerald-700' : ($score >= $threshold ? 'text-amber-600' : 'text-red-700');
          $bar = $score >= 70 ? 'bg-emerald-500' : ($score >= $threshold ? 'bg-amber-500' : 'bg-red-500'); ?>
        <div>
          <p class="font-serif text-4xl <?= $tone ?>"><?= $score ?>%</p>
          <p class="text-xs text-stone-500">chance the customer accepts the parcel<?= $score < $threshold ? " — below $threshold%, so it was flagged as fake" : '' ?></p>
          <div class="mt-2 h-2 rounded-full bg-cream-200 overflow-hidden"><div class="h-full <?= $bar ?>" style="width: <?= $score ?>%"></div></div>
        </div>
        <ul class="space-y-1.5 text-sm">
          <?php foreach ($scoreReasons as $reason): ?>
            <li class="flex items-start justify-between gap-3">
              <span><?= h($reason['label']) ?></span>
              <?php if ($reason['points'] !== null): ?>
                <span class="flex-shrink-0 font-semibold <?= $reason['points'] >= 0 ? 'text-emerald-700' : 'text-red-700' ?>"><?= $reason['points'] >= 0 ? '+' : '' ?><?= (int) $reason['points'] ?></span>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <div class="flex flex-wrap gap-2">
        <?php if ($order['status'] === 'fake'): ?>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="not_fake">
            <button class="btn btn-light !py-1.5"><i class="fa-solid fa-user-check text-emerald-600"></i> Not fake — move to New</button></form>
        <?php elseif (in_array($order['status'], ['new', 'hold', 'confirmed'], true)): ?>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="mark_fake">
            <button class="btn btn-light !py-1.5"><i class="fa-solid fa-user-secret text-red-600"></i> Mark fake</button></form>
        <?php endif; ?>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="rescore">
          <button class="btn btn-light !py-1.5"><i class="fa-solid fa-rotate"></i> Recheck</button></form>
      </div>
    </section>

    <?php
    $shipped = (bool) $order['courier'];
    $suggestedCod = max(0, (int) $order['total_bdt'] - (int) $order['advance_bdt']);
    ?>
    <section class="card p-5 space-y-4">
      <h2 class="font-serif text-lg text-wood-900"><i class="fa-solid fa-truck-fast text-gold-500"></i> Courier</h2>
      <?php if ($shipped): ?>
        <?php $mainId = (string) ($order['consignment_id'] ?: $order['tracking_code']); ?>
        <?php if ($mainId !== ''): ?>
          <div class="rounded-2xl border-2 border-indigo-300 bg-indigo-50 p-4 text-center">
            <p class="text-xs font-semibold uppercase tracking-wider text-indigo-600"><?= h($order['courier']) ?> · <?= $order['consignment_id'] ? 'Consignment ID' : 'Tracking ID' ?></p>
            <p class="mt-1 font-mono text-2xl font-extrabold text-indigo-900 break-all select-all" id="consignmentId"><?= h($mainId) ?></p>
            <div class="mt-3 grid grid-cols-2 gap-2">
              <button type="button" class="btn btn-light !py-1.5" id="copyConsignment" data-copy="<?= h($mainId) ?>"><i class="fa-regular fa-copy"></i> <span>Copy</span></button>
              <a href="labels.php?ids=<?= $id ?>&amp;print=1" target="_blank" class="btn bg-indigo-700 text-white hover:bg-indigo-800 !py-1.5"><i class="fa-solid fa-print"></i> Print label</a>
            </div>
          </div>
          <script>
            document.getElementById('copyConsignment').addEventListener('click', async (e) => {
              const btn = e.currentTarget;
              try { await navigator.clipboard.writeText(btn.dataset.copy); } catch (err) {
                const t = document.createElement('textarea'); t.value = btn.dataset.copy; document.body.appendChild(t); t.select(); document.execCommand('copy'); t.remove();
              }
              btn.querySelector('span').textContent = 'Copied ✓';
              setTimeout(() => { btn.querySelector('span').textContent = 'Copy'; }, 1500);
            });
          </script>
        <?php endif; ?>
        <dl class="text-sm space-y-1.5">
          <div class="flex justify-between gap-3"><dt class="text-stone-500">Courier</dt><dd class="font-semibold"><?= h($order['courier']) ?></dd></div>
          <?php if ($order['tracking_code'] && $order['tracking_code'] !== $mainId): ?><div class="flex justify-between gap-3"><dt class="text-stone-500">Tracking</dt><dd class="font-mono"><?= h($order['tracking_code']) ?></dd></div><?php endif; ?>
          <?php if ($order['courier_status']): ?><div class="flex justify-between gap-3"><dt class="text-stone-500">Courier status</dt><dd><?= h(str_replace('_', ' ', $order['courier_status'])) ?></dd></div><?php endif; ?>
          <div class="flex justify-between gap-3"><dt class="text-stone-500">COD</dt><dd><?= money($order['cod_bdt']) ?></dd></div>
          <?php if ($order['shipped_at']): ?><div class="flex justify-between gap-3"><dt class="text-stone-500">Booked</dt><dd><?= h(date('d M Y, g:i a', strtotime($order['shipped_at']))) ?></dd></div><?php endif; ?>
        </dl>
        <?php if ($order['consignment_id'] && ic_courier_key_from_label($order['courier']) && in_array($order['status'], ['shipping', 'waiting'], true)): ?>
          <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="sync">
            <button class="btn btn-light w-full"><i class="fa-solid fa-rotate"></i> Check <?= h($order['courier']) ?> status now</button>
          </form>
        <?php endif; ?>
      <?php else: ?>
        <?php $connectedCouriers = ic_connected_couriers($pdo); $defaultCourier = ic_default_courier($pdo); ?>
        <?php if ($connectedCouriers): ?>
          <form method="post" class="space-y-3 rounded-xl bg-indigo-50/60 border border-indigo-100 p-3" id="sendForm">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="courier">
            <div class="grid grid-cols-<?= count($connectedCouriers) ?> gap-2">
              <?php foreach ($connectedCouriers as $c): ?>
                <label class="cursor-pointer">
                  <input type="radio" name="courier_key" value="<?= $c ?>" class="peer sr-only" <?= $c === $defaultCourier ? 'checked' : '' ?>>
                  <span class="block rounded-lg border border-indigo-200 bg-white px-2 py-2 text-center text-xs font-semibold text-indigo-900 peer-checked:bg-indigo-700 peer-checked:text-white peer-checked:border-indigo-700"><?= h(IC_COURIERS[$c]['label']) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
            <div class="grid grid-cols-2 gap-2">
              <div>
                <label class="label" for="cod_courier">COD (৳)</label>
                <input class="input" id="cod_courier" name="cod_bdt" type="number" min="0" value="<?= $suggestedCod ?>">
              </div>
              <div>
                <label class="label" for="weight">Weight (kg)</label>
                <input class="input" id="weight" name="weight" type="number" min="0.1" step="0.1" value="0.5">
              </div>
            </div>
            <div data-for="pathao" class="hidden space-y-2">
              <p class="text-xs text-stone-500">Pathao location (optional, helps fast delivery)</p>
              <select class="input" name="pathao_city" id="pathaoCity"><option value="">City…</option></select>
              <select class="input" name="pathao_zone" id="pathaoZone"><option value="">Zone…</option></select>
              <select class="input" name="pathao_area" id="pathaoArea"><option value="">Area…</option></select>
            </div>
            <div data-for="redx" class="hidden space-y-2">
              <p class="text-xs text-stone-500">RedX delivery area — leave empty to pick it from the address automatically</p>
              <select class="input" id="redxDistrict">
                <?php foreach (['Dhaka', 'Gazipur', 'Narayanganj', 'Chattogram', 'Sylhet', 'Rajshahi', 'Khulna', 'Barishal', 'Rangpur', 'Mymensingh', 'Cumilla', 'Bogura'] as $d): ?>
                  <option <?= $d === 'Dhaka' && $order['region'] !== 'outside' ? 'selected' : '' ?>><?= $d ?></option>
                <?php endforeach; ?>
              </select>
              <select class="input" name="redx_area_id" id="redxArea"><option value="">Area: auto from address</option></select>
              <input type="hidden" name="redx_area_name" id="redxAreaName">
            </div>
            <button class="btn bg-indigo-700 text-white hover:bg-indigo-800 w-full"><i class="fa-solid fa-paper-plane"></i> <span id="sendLabel">Send to courier</span></button>
            <p class="text-xs text-stone-500">One click books the parcel and moves the order to Shipping. Its status then updates by itself.</p>
          </form>
          <script>
            (() => {
              const form = document.getElementById('sendForm');
              const names = <?= json_encode(array_map(function ($c) { return IC_COURIERS[$c]['label']; }, array_combine($connectedCouriers, $connectedCouriers))) ?>;
              const load = async (select, type, id, placeholder, extra = '') => {
                select.innerHTML = `<option value="">Loading…</option>`;
                try {
                  const res = await fetch(`courier-lookup.php?type=${type}&id=${id || ''}${extra}`);
                  const data = await res.json();
                  if (!data.ok) throw new Error(data.error);
                  select.innerHTML = `<option value="">${placeholder}</option>` + data.items.map(([v, n]) => `<option value="${v}">${n.replace(/</g, '&lt;')}</option>`).join('');
                } catch (e) {
                  select.innerHTML = `<option value="">Could not load (${String(e.message).slice(0, 60)})</option>`;
                }
              };
              const loaded = {};
              const refresh = () => {
                const courier = form.querySelector('input[name="courier_key"]:checked')?.value;
                document.getElementById('sendLabel').textContent = courier ? `Send to ${names[courier]}` : 'Send to courier';
                form.querySelectorAll('[data-for]').forEach(el => el.classList.toggle('hidden', el.dataset.for !== courier));
                if (courier === 'pathao' && !loaded.pathao) { loaded.pathao = true; load(document.getElementById('pathaoCity'), 'cities', 0, 'City…'); }
                if (courier === 'redx' && !loaded.redx) { loaded.redx = true; loadRedx(); }
              };
              const loadRedx = () => load(document.getElementById('redxArea'), 'redx_areas', 0, 'Area: auto from address', '&district=' + encodeURIComponent(document.getElementById('redxDistrict').value));
              form.querySelectorAll('input[name="courier_key"]').forEach(r => r.addEventListener('change', refresh));
              document.getElementById('pathaoCity').addEventListener('change', (e) => load(document.getElementById('pathaoZone'), 'zones', e.target.value, 'Zone…'));
              document.getElementById('pathaoZone').addEventListener('change', (e) => load(document.getElementById('pathaoArea'), 'areas', e.target.value, 'Area…'));
              document.getElementById('redxDistrict').addEventListener('change', loadRedx);
              document.getElementById('redxArea').addEventListener('change', (e) => {
                document.getElementById('redxAreaName').value = e.target.value ? e.target.selectedOptions[0].textContent : '';
              });
              refresh();
            })();
          </script>
          <p class="text-center text-xs text-stone-400">— or enter tracking by hand —</p>
        <?php else: ?>
          <a href="couriers.php" class="btn btn-light w-full"><i class="fa-solid fa-plug"></i> Connect a courier for one-click sending</a>
          <p class="text-center text-xs text-stone-400">— or enter tracking by hand —</p>
        <?php endif; ?>
        <form method="post" class="space-y-3">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="tracking">
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="label" for="courier">Courier</label>
              <select class="input" id="courier" name="courier">
                <?php foreach (['Steadfast', 'Pathao', 'RedX', 'Sundarban', 'SA Paribahan', 'Other'] as $name): ?>
                  <option><?= $name ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label class="label" for="cod_manual">COD ৳</label>
              <input class="input" id="cod_manual" name="cod_bdt" type="number" min="0" value="<?= $suggestedCod ?>">
            </div>
          </div>
          <div>
            <label class="label" for="tracking_code">Tracking / consignment ID</label>
            <input class="input" id="tracking_code" name="tracking_code" required>
          </div>
          <button class="btn btn-light w-full"><i class="fa-solid fa-barcode"></i> Save tracking &amp; ship</button>
        </form>
      <?php endif; ?>
    </section>

    <form method="post" class="card p-5 space-y-3">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="important">
      <h2 class="font-serif text-lg text-wood-900"><i class="fa-solid fa-star text-amber-500"></i> Important &amp; reminder</h2>
      <label class="flex items-center gap-2 text-sm">
        <input type="checkbox" name="is_important" value="1" <?= $order['is_important'] ? 'checked' : '' ?> class="rounded">
        Mark as important
      </label>
      <?php if ($order['needed_by']): ?>
        <p class="text-xs text-stone-500">Customer needs it by <strong><?= h(date('d M Y', strtotime($order['needed_by']))) ?></strong></p>
      <?php endif; ?>
      <div>
        <label class="label" for="reminder_date">Remind me on</label>
        <input class="input" id="reminder_date" name="reminder_date" type="date" value="<?= h($order['reminder_date']) ?>">
      </div>
      <div>
        <label class="label" for="reminder_note">Reminder note</label>
        <input class="input" id="reminder_note" name="reminder_note" value="<?= h($order['reminder_note']) ?>" placeholder="e.g. Call customer about the logo">
      </div>
      <?php if ($order['reminder_sent_at']): ?>
        <p class="text-xs text-stone-500"><i class="fa-regular fa-envelope"></i> Reminder email sent <?= h(date('d M, g:i a', strtotime($order['reminder_sent_at']))) ?></p>
      <?php endif; ?>
      <button class="btn btn-light w-full">Save reminder</button>
    </form>

    <form method="post" class="card p-5 space-y-4">
      <?= csrf_field() ?>
      <h2 class="font-serif text-lg text-wood-900">Update order</h2>
      <div>
        <label class="label" for="status">Status</label>
        <select class="input" id="status" name="status">
          <?php foreach (IC_ORDER_STATUSES as $key => $label): ?>
            <option value="<?= $key ?>" <?= $order['status'] === $key ? 'selected' : '' ?>><?= h($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="label" for="total_bdt">Total (৳)</label>
          <input class="input" id="total_bdt" name="total_bdt" type="number" min="0" value="<?= h($order['total_bdt']) ?>" placeholder="Quote">
        </div>
        <div>
          <label class="label" for="advance_bdt">Advance (৳)</label>
          <input class="input" id="advance_bdt" name="advance_bdt" type="number" min="0" value="<?= h($order['advance_bdt']) ?>">
        </div>
      </div>
      <div>
        <label class="label" for="address">Delivery address</label>
        <textarea class="input" id="address" name="address" rows="2"><?= h($order['address']) ?></textarea>
      </div>
      <div>
        <label class="label" for="admin_note">Private note</label>
        <textarea class="input" id="admin_note" name="admin_note" rows="3" placeholder="Only admins see this"><?= h($order['admin_note']) ?></textarea>
      </div>
      <button class="btn btn-dark w-full">Save changes</button>
    </form>

    <section class="card p-5 space-y-2">
      <h2 class="font-serif text-lg text-wood-900 mb-1">Quick WhatsApp messages</h2>
      <?php foreach ($waTemplates as $label => $text): ?>
        <a href="<?= h(whatsapp_link($order['whatsapp'], $text)) ?>" target="_blank" rel="noopener" class="btn btn-light w-full !justify-start"><i class="fa-brands fa-whatsapp text-emerald-700"></i> <?= h($label) ?></a>
      <?php endforeach; ?>
      <?php if (in_array($order['status'], ['delivered', 'partial'], true) && ($reviewUrl = ic_review_url(ic_db(), $order))): ?>
        <!-- Real reviews: only delivered customers get this private link -->
        <a href="<?= h(whatsapp_link($order['whatsapp'], ic_review_whatsapp_text(ic_db(), $order))) ?>" target="_blank" rel="noopener" class="btn btn-gold w-full !justify-start"><i class="fa-solid fa-star"></i> Ask for review on WhatsApp</a>
        <button type="button" class="btn btn-light w-full !justify-start" onclick="navigator.clipboard.writeText(<?= h(json_encode($reviewUrl)) ?>).then(() => { this.textContent = 'Review link copied ✓'; })"><i class="fa-regular fa-copy"></i> Copy review link</button>
      <?php elseif (!in_array($order['status'], ['cancelled', 'fake', 'returned'], true)): ?>
        <p class="text-xs text-stone-400"><i class="fa-regular fa-star"></i> A review link appears here once the order is Delivered.</p>
      <?php endif; ?>
    </section>

    <form method="post" onsubmit="return confirm('Delete order <?= h($order['code']) ?> and its files permanently?');">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="delete">
      <button class="btn btn-danger w-full"><i class="fa-regular fa-trash-can"></i> Delete order</button>
    </form>
  </aside>
</div>
<?php admin_footer();
