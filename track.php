<?php
// Parcel status page: track.php?o=<order code>&k=<signed key>. The QR code on every courier label opens it,
// so anyone holding the parcel (customer, rider, shop staff) can see that it is packed, labelled and on its way.
// Shows only a masked name/phone and the area — never the full address.
declare(strict_types=1);
require __DIR__ . '/api/bootstrap.php';

function tr_e($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

function tr_bn(string $s): string
{
    return strtr($s, ['0' => '০', '1' => '১', '2' => '২', '3' => '৩', '4' => '৪', '5' => '৫', '6' => '৬', '7' => '৭', '8' => '৮', '9' => '৯']);
}

function tr_when(?string $at): string
{
    if (!$at || !($t = strtotime($at))) {
        return '';
    }
    $months = ['জানুয়ারি', 'ফেব্রুয়ারি', 'মার্চ', 'এপ্রিল', 'মে', 'জুন', 'জুলাই', 'আগস্ট', 'সেপ্টেম্বর', 'অক্টোবর', 'নভেম্বর', 'ডিসেম্বর'];
    $h = (int) date('G', $t);
    $part = $h < 5 ? 'রাত' : ($h < 12 ? 'সকাল' : ($h < 15 ? 'দুপুর' : ($h < 18 ? 'বিকাল' : ($h < 20 ? 'সন্ধ্যা' : 'রাত'))));
    return tr_bn(date('j', $t)) . ' ' . $months[(int) date('n', $t) - 1] . ', ' . $part . ' ' . tr_bn(date('g:i', $t));
}

$pdo = ic_db();
ic_migrate($pdo);
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

$code = (string) ($_GET['o'] ?? '');
$key = (string) ($_GET['k'] ?? '');
$order = null;
if ($code !== '' && $key !== '') {
    $stmt = $pdo->prepare('SELECT * FROM orders WHERE code = ?');
    $stmt->execute([$code]);
    $order = $stmt->fetch() ?: null;
    if ($order && !hash_equals(ic_track_token($pdo, $order['code']), $key)) {
        $order = null;
    }
}

// Shop staff who are logged in to the admin panel get a shortcut to the order
$isAdmin = false;
if ($order && !empty($_COOKIE['ic_admin'])) {
    session_name('ic_admin');
    session_start(['read_and_close' => true]);
    $isAdmin = !empty($_SESSION['admin_id']);
}

$steps = [];
$current = 0;
$closed = null;
if ($order) {
    $details = json_decode((string) $order['details_json'], true) ?: [];
    $times = (array) ($details['times'] ?? []);
    $s = $order['status'];
    $shipped = (bool) ($order['shipped_at'] || $order['courier']);
    $delivered = in_array($s, ['delivered', 'partial'], true);
    $labelAt = $details['label_printed_at'] ?? null;
    $packed = $shipped || $labelAt || $delivered;
    $confirmed = $packed || in_array($s, ['confirmed', 'in_production', 'shipping', 'waiting'], true);
    if (in_array($s, ['cancelled', 'returned', 'fake'], true)) {
        $closed = $s === 'returned' ? 'returned' : 'cancelled';
    }

    $booking = (string) ($order['consignment_id'] ?: $order['tracking_code']);
    $steps = [
        ['icon' => '🧾', 'title' => 'অর্ডার গ্রহণ করা হয়েছে', 'done' => true, 'at' => $order['created_at'], 'note' => ''],
        ['icon' => '✅', 'title' => 'অর্ডার কনফার্ম করা হয়েছে', 'done' => $confirmed, 'at' => $times['confirmed'] ?? null,
            'note' => $s === 'in_production' ? 'আপনার পণ্যটি যত্ন করে তৈরি করা হচ্ছে' : ''],
        ['icon' => '📦', 'title' => 'প্যাকিং সম্পন্ন ও লেবেল লাগানো হয়েছে', 'done' => $packed,
            'at' => $labelAt && $order['shipped_at'] ? min($labelAt, $order['shipped_at']) : ($labelAt ?: $order['shipped_at']), 'note' => ''],
        ['icon' => '🚚', 'title' => 'কুরিয়ারে দেওয়া হয়েছে — ডেলিভারির পথে', 'done' => $shipped || $delivered, 'at' => $order['shipped_at'],
            'note' => $order['courier'] ? $order['courier'] . ($booking !== '' ? ' · কনসাইনমেন্ট আইডি ' . $booking : '') : ''],
        ['icon' => '🎁', 'title' => $s === 'partial' ? 'আংশিক ডেলিভারি সম্পন্ন' : 'ডেলিভারি সম্পন্ন', 'done' => $delivered,
            'at' => $delivered ? ($times[$s] ?? $order['status_changed_at']) : null, 'note' => ''],
    ];
    foreach ($steps as $i => $step) {
        if ($step['done']) {
            $current = $i;
        }
    }
    $headlines = [
        0 => ['আমরা আপনার অর্ডারটি পেয়েছি', 'খুব শীঘ্রই ফোন করে অর্ডারটি কনফার্ম করা হবে।'],
        1 => ['অর্ডার কনফার্ম — প্যাকিং চলছে', 'আপনার পণ্যটি যত্ন করে প্যাক করা হচ্ছে।'],
        2 => ['পার্সেল প্যাক করে লেবেল লাগানো হয়েছে', 'শীঘ্রই কুরিয়ারের কাছে হস্তান্তর করা হবে।'],
        3 => ['আপনার পার্সেল ডেলিভারির পথে', 'কুরিয়ার প্রতিনিধি ডেলিভারির আগে আপনাকে ফোন করবেন।'],
        4 => ['পার্সেল ডেলিভারি সম্পন্ন হয়েছে', 'INSPIRED CREATIONS থেকে কেনার জন্য ধন্যবাদ!'],
    ];
    [$headline, $subline] = $headlines[$current];
    if ($s === 'hold' && !$packed) {
        $subline = 'অর্ডারটি সাময়িকভাবে অপেক্ষায় আছে — আমরা আপনাকে ফোন করবো।';
    }

    // Masked customer details (the label already shows them on the box; this page should not spread them further)
    $name = trim((string) $order['customer_name']);
    $first = (string) strtok($name, ' ');
    $maskedName = mb_substr($first, 0, 2, 'UTF-8') . str_repeat('•', max(2, min(5, mb_strlen($first, 'UTF-8') - 2))) . ($first !== $name ? ' …' : '');
    $digits = preg_replace('/\D/', '', (string) $order['whatsapp']);
    $maskedPhone = strlen($digits) >= 8 ? substr($digits, 0, 3) . '•••••' . substr($digits, -3) : '•••';
    $items = (array) json_decode((string) $order['items_json'], true);
    $cod = $order['cod_bdt'] !== null ? (int) $order['cod_bdt'] : max(0, (int) $order['total_bdt'] - (int) $order['advance_bdt']);
    $place = ic_geo_locate((string) $order['address'], (string) $order['region']);
    $area = $place && empty($place['guessed'])
        ? ($place['area'] && isset(IC_DHAKA_AREAS[$place['area']]) ? IC_DHAKA_AREAS[$place['area']][1] . ', ঢাকা' : IC_BD_DISTRICTS[$place['district']][1])
        : ($order['region'] === 'outside' ? 'ঢাকার বাইরে' : 'ঢাকা');
    $liveTrack = $order['courier'] === 'Steadfast' && $order['tracking_code'] ? 'https://steadfast.com.bd/t/' . rawurlencode((string) $order['tracking_code']) : null;
}
$waShop = 'https://wa.me/8801321744476?text=' . rawurlencode('আসসালামু আলাইকুম, আমার অর্ডার ' . ($order['code'] ?? '') . ' সম্পর্কে জানতে চাই।');
?>
<!doctype html>
<html lang="bn">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title><?= $order ? 'পার্সেল ' . tr_e($order['code']) : 'পার্সেল খুঁজে পাওয়া যায়নি' ?> — INSPIRED CREATIONS</title>
  <link rel="icon" type="image/png" href="assets/logo-mark.png">
  <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root { --wood: #733f20; --dark: #24130a; --cream: #f7f1e8; --gold: #b8874d; --ink: #2a1c12; --ok: #16a34a; }
    * { box-sizing: border-box; }
    body { margin: 0; background: var(--cream); font-family: 'Hind Siliguri', system-ui, sans-serif; color: var(--ink); font-size: 16px; line-height: 1.55; }
    .wrap { max-width: 560px; margin: 0 auto; padding: 0 16px 28px; }
    .top { background: var(--dark); color: var(--cream); text-align: center; padding: 16px; border-radius: 0 0 22px 22px; }
    .top img { width: 42px; height: 42px; border-radius: 50%; background: #140e0c; padding: 4px; }
    .top b { display: block; letter-spacing: .2em; margin-top: 4px; font-size: 14px; }
    .card { background: #fff; border-radius: 20px; padding: 18px; margin-top: 14px; box-shadow: 0 14px 34px -24px rgba(0,0,0,.4); }
    .hero { text-align: center; padding: 22px 18px; }
    .hero .big { font-size: 46px; line-height: 1; display: inline-block; animation: bob 1.8s ease-in-out infinite; }
    .hero.moving .big { animation: drive 2.4s ease-in-out infinite; }
    .hero h1 { margin: 10px 0 2px; font-size: 22px; color: var(--wood); }
    .hero p { margin: 0; color: #6b5b4b; }
    .code { display: inline-block; margin-top: 10px; padding: 4px 12px; border-radius: 99px; background: #f3ece2; font-weight: 700; letter-spacing: .04em; font-size: 14px; }
    .bar { height: 8px; border-radius: 99px; background: #eee3d6; margin: 16px 4px 0; overflow: hidden; }
    .bar i { display: block; height: 100%; border-radius: 99px; background: linear-gradient(90deg, var(--gold), var(--ok)); width: 0; transition: width 1.2s cubic-bezier(.2,.8,.2,1); }
    .steps { list-style: none; margin: 0; padding: 0; }
    .steps li { position: relative; display: flex; gap: 12px; padding: 0 0 18px; }
    .steps li:last-child { padding-bottom: 0; }
    .steps li::before { content: ''; position: absolute; left: 17px; top: 36px; bottom: 0; width: 2px; background: #e7ddd0; }
    .steps li.done::before { background: var(--ok); }
    .steps li:last-child::before { display: none; }
    .dot { flex-shrink: 0; width: 36px; height: 36px; border-radius: 50%; display: grid; place-items: center; font-size: 17px; background: #f3ece2; filter: grayscale(1); opacity: .55; position: relative; z-index: 1; }
    .done .dot { background: #dcfce7; filter: none; opacity: 1; box-shadow: 0 0 0 2px var(--ok) inset; }
    .now .dot { box-shadow: 0 0 0 2px var(--ok) inset, 0 0 0 6px rgba(22, 163, 74, .18); animation: ping 1.6s ease-out infinite; }
    .steps b { display: block; font-size: 16px; }
    .pending b { color: #9a8b7b; font-weight: 500; }
    .steps small { display: block; color: #7a6a5a; font-size: 13.5px; }
    .steps .note { color: var(--wood); font-weight: 600; }
    .label { font-size: 13px; color: #7a6a5a; margin: 0 0 2px; }
    .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    .items { margin: 6px 0 0; padding: 0; list-style: none; }
    .items li { padding: 6px 0; border-top: 1px dashed #eadfce; }
    .items li:first-child { border-top: 0; }
    .money { display: flex; justify-content: space-between; align-items: center; margin-top: 10px; padding: 10px 14px; border-radius: 14px; background: #fbf6ef; font-weight: 700; }
    .money b { font-size: 20px; color: var(--wood); }
    .btns { display: grid; gap: 10px; margin-top: 14px; }
    .btn { display: flex; justify-content: center; align-items: center; gap: 8px; padding: 13px; border-radius: 99px; font-weight: 700; text-decoration: none; font-size: 16px; }
    .btn-wa { background: #16a34a; color: #fff; }
    .btn-track { background: var(--wood); color: #fff; }
    .btn-admin { background: #1e1b4b; color: #fff; }
    .alert { background: #fef2f2; color: #991b1b; border-radius: 16px; padding: 14px; text-align: center; font-weight: 600; }
    .foot { text-align: center; color: #9a8b7b; font-size: 13px; margin-top: 18px; }
    @keyframes bob { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-6px); } }
    @keyframes drive { 0% { transform: translateX(-18px); } 50% { transform: translateX(18px); } 100% { transform: translateX(-18px); } }
    @keyframes ping { 0% { box-shadow: 0 0 0 2px var(--ok) inset, 0 0 0 0 rgba(22,163,74,.35); } 100% { box-shadow: 0 0 0 2px var(--ok) inset, 0 0 0 12px rgba(22,163,74,0); } }
    @media (prefers-reduced-motion: reduce) { .hero .big, .now .dot { animation: none; } }
  </style>
</head>
<body>
  <div class="top"><img src="assets/logo-mark.png" alt=""><b>INSPIRED CREATIONS</b></div>
  <div class="wrap">
  <?php if (!$order): ?>
    <div class="card hero">
      <div class="big">🔍</div>
      <h1>পার্সেলটি খুঁজে পাওয়া যায়নি</h1>
      <p>লেবেলের QR কোডটি আবার স্ক্যান করুন, অথবা আমাদের WhatsApp করুন।</p>
      <div class="btns"><a class="btn btn-wa" href="<?= tr_e($waShop) ?>">WhatsApp করুন</a></div>
    </div>
  <?php else: ?>
    <div class="card hero <?= $current === 3 && !$closed ? 'moving' : '' ?>">
      <?php if ($closed): ?>
        <div class="big">⚠️</div>
        <h1><?= $closed === 'returned' ? 'পার্সেলটি ফেরত এসেছে' : 'এই অর্ডারটি বাতিল করা হয়েছে' ?></h1>
        <p>কোনো প্রশ্ন থাকলে আমাদের WhatsApp করুন।</p>
      <?php else: ?>
        <div class="big"><?= $steps[$current]['icon'] ?></div>
        <h1><?= tr_e($headline) ?></h1>
        <p><?= tr_e($subline) ?></p>
      <?php endif; ?>
      <span class="code">অর্ডার <?= tr_e($order['code']) ?></span>
      <?php if (!$closed): ?><div class="bar"><i data-w="<?= (int) round($current / 4 * 100) ?>"></i></div><?php endif; ?>
    </div>

    <div class="card">
      <ol class="steps">
        <?php foreach ($steps as $i => $step):
            $cls = $step['done'] ? 'done' : 'pending';
            if ($i === $current && !$closed && $current < 4) $cls .= ' now'; ?>
          <li class="<?= $cls ?>">
            <span class="dot"><?= $step['icon'] ?></span>
            <span>
              <b><?= tr_e($step['title']) ?></b>
              <?php if ($step['done'] && $step['at']): ?><small><?= tr_e(tr_when($step['at'])) ?></small><?php endif; ?>
              <?php if ($step['done'] && $step['note'] !== ''): ?><small class="note"><?= tr_e($step['note']) ?></small><?php endif; ?>
              <?php if (!$step['done'] && $i === $current + 1 && !$closed): ?><small>পরবর্তী ধাপ</small><?php endif; ?>
            </span>
          </li>
        <?php endforeach; ?>
      </ol>
    </div>

    <div class="card">
      <div class="grid">
        <div><p class="label">প্রাপক</p><b><?= tr_e($maskedName) ?></b><br><span><?= tr_e($maskedPhone) ?></span></div>
        <div><p class="label">ডেলিভারি এলাকা</p><b><?= tr_e($area) ?></b></div>
      </div>
      <p class="label" style="margin-top:12px">পার্সেলে আছে</p>
      <ul class="items">
        <?php foreach ($items as $item): ?>
          <li><?= tr_e($item['title'] ?? '') ?> × <?= tr_e(tr_bn((string) (int) ($item['qty'] ?? 1))) ?></li>
        <?php endforeach; ?>
      </ul>
      <?php if (!$closed && $current < 4): ?>
        <div class="money"><span><?= $cod > 0 ? 'ডেলিভারির সময় পরিশোধ করবেন' : 'পেমেন্ট' ?></span><b><?= $cod > 0 ? '৳' . tr_e(tr_bn(number_format($cod))) : 'পরিশোধিত ✓' ?></b></div>
      <?php endif; ?>
      <div class="btns">
        <?php if ($liveTrack && !$closed && $current < 4): ?><a class="btn btn-track" href="<?= tr_e($liveTrack) ?>" target="_blank" rel="noopener">🚚 Steadfast-এ লাইভ ট্র্যাক করুন</a><?php endif; ?>
        <a class="btn btn-wa" href="<?= tr_e($waShop) ?>" target="_blank" rel="noopener">WhatsApp-এ যোগাযোগ করুন</a>
        <?php if ($isAdmin): ?><a class="btn btn-admin" href="admin/order.php?id=<?= (int) $order['id'] ?>">Admin: open this order</a><?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
    <p class="foot">হাতে তৈরি কাঠের উপহার · INSPIRED CREATIONS</p>
  </div>
  <script>
    requestAnimationFrame(() => document.querySelectorAll('.bar i').forEach(i => { i.style.width = i.dataset.w + '%'; }));
  </script>
</body>
</html>
