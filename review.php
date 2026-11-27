<?php
// Customer review page: review.php?o=<order code>&t=<signed token>. Only for delivered orders.
// Reviews wait for the shop's approval (admin → Reviews) before they appear on the website.
declare(strict_types=1);
require __DIR__ . '/api/bootstrap.php';

function rv_e($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

$pdo = ic_db();
ic_migrate($pdo);
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

$code = (string) ($_GET['o'] ?? $_POST['o'] ?? '');
$token = (string) ($_GET['t'] ?? $_POST['t'] ?? '');
$stmt = $pdo->prepare('SELECT * FROM orders WHERE code = ?');
$stmt->execute([$code]);
$order = $stmt->fetch();
$valid = $order && $token !== '' && hash_equals(ic_review_token($pdo, $order['code']), $token);
$delivered = $valid && in_array($order['status'], ['delivered', 'partial'], true);

// The order's products (each can be reviewed once per order)
$items = [];
$reviewed = [];
if ($valid) {
    $ids = array_values(array_unique(array_map(function ($i) { return (string) ($i['id'] ?? ''); }, (array) json_decode((string) $order['items_json'], true))));
    foreach ($ids as $id) {
        $p = $pdo->prepare('SELECT id, title, image FROM products WHERE id = ?');
        $p->execute([$id]);
        if ($row = $p->fetch()) {
            $items[$row['id']] = $row;
        }
    }
    $r = $pdo->prepare('SELECT product_id FROM reviews WHERE order_id = ?');
    $r->execute([$order['id']]);
    $reviewed = $r->fetchAll(PDO::FETCH_COLUMN);
}
$open = array_diff_key($items, array_flip($reviewed));
$place = $valid ? ic_geo_locate((string) $order['address'], $order['region']) : null;
$district = $place && empty($place['guessed']) ? ($place['area'] && isset(IC_DHAKA_AREAS[$place['area']]) ? IC_DHAKA_AREAS[$place['area']][1] . ', ঢাকা' : IC_BD_DISTRICTS[$place['district']][1]) : '';
$firstName = $valid ? trim((string) strtok(trim($order['customer_name']), ' ')) : '';
$error = null;
$done = false;

if ($delivered && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $ip = ic_client_ip();
    $productId = (string) ($_POST['product'] ?? '');
    $rating = (int) ($_POST['rating'] ?? 0);
    $body = ic_clean(str_replace("\r\n", "\n", (string) ($_POST['body'] ?? '')), 1000);
    $name = ic_clean((string) ($_POST['name'] ?? ''), 60);
    if (ic_attempt_count($pdo, 'review_ip', $ip, 3600) >= 10) {
        $error = 'অনেকবার চেষ্টা করা হয়েছে — কিছুক্ষণ পর আবার চেষ্টা করুন।';
    } elseif (!isset($open[$productId])) {
        $error = 'কোন পণ্যের রিভিউ দিচ্ছেন, সেটি বেছে নিন।';
    } elseif ($rating < 1 || $rating > 5) {
        $error = 'স্টার দিয়ে রেটিং দিন (১ থেকে ৫)।';
    } elseif (function_exists('mb_strlen') ? mb_strlen($name) < 2 : strlen($name) < 2) {
        $error = 'আপনার নাম লিখুন।';
    } elseif (empty($_POST['consent'])) {
        $error = 'ওয়েবসাইটে দেখানোর অনুমতির ঘরে টিক দিন।';
    } else {
        // Optional photo: re-saved as a fresh JPEG (this also removes the phone's hidden GPS location data)
        $photo = null;
        $file = $_FILES['photo'] ?? null;
        if ($file && $file['error'] !== UPLOAD_ERR_NO_FILE) {
            $info = $file['error'] === UPLOAD_ERR_OK ? @getimagesize($file['tmp_name']) : false;
            if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
                $error = 'ছবিটি JPG, PNG বা WEBP হতে হবে।';
            } elseif ($file['size'] > 8 * 1024 * 1024) {
                $error = 'ছবিটি ৮ MB-এর চেয়ে ছোট হতে হবে।';
            } elseif (function_exists('imagecreatefromstring') && ($img = @imagecreatefromstring((string) file_get_contents($file['tmp_name'])))) {
                $w = imagesx($img);
                $h = imagesy($img);
                $scale = min(1, 1200 / max($w, $h));
                $out = imagecreatetruecolor((int) round($w * $scale), (int) round($h * $scale));
                imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
                imagecopyresampled($out, $img, 0, 0, 0, 0, imagesx($out), imagesy($out), $w, $h);
                $dir = IC_ROOT . '/assets/reviews';
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                $photo = 'assets/reviews/rev-' . bin2hex(random_bytes(8)) . '.jpg';
                imagejpeg($out, IC_ROOT . '/' . $photo, 84);
            } else {
                $error = 'ছবিটি পড়া যায়নি — অন্য একটি ছবি দিন অথবা ছবি ছাড়াই পাঠান।';
            }
        }
        if (!$error) {
            $pdo->prepare('INSERT INTO reviews (order_id, product_id, name, district, rating, body, photo, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([$order['id'], $productId, $name, !empty($_POST['show_district']) && $district !== '' ? $district : null, $rating, $body !== '' ? $body : null, $photo, 'pending', ic_now()]);
            ic_attempt_add($pdo, 'review_ip', $ip);
            // Let the shop know a review is waiting
            $to = ic_config()['order_email'] ?? '';
            if ($to !== '' && function_exists('mail')) {
                @mail($to, '=?UTF-8?B?' . base64_encode("New review to approve — {$order['code']} ({$rating}★)") . '?=',
                    "{$name} reviewed {$items[$productId]['title']}: {$rating}/5\n\n{$body}\n\nApprove it in admin → Product → Reviews.",
                    "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nFrom: INSPIRED CREATIONS <no-reply@" . preg_replace('/[^a-z0-9.-]/i', '', explode(':', $_SERVER['HTTP_HOST'] ?? 'localhost')[0]) . '>');
            }
            $done = true;
            unset($open[$productId]);
            $_POST = []; // the next product's form starts empty
        }
    }
}
?><!DOCTYPE html>
<html lang="bn">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex">
  <title>রিভিউ দিন — INSPIRED CREATIONS</title>
  <link rel="icon" type="image/png" href="assets/logo-mark.png">
  <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root { --wood: #733f20; --dark: #24130a; --cream: #f7f1e8; --gold: #b8874d; --ink: #2a1c12; }
    * { box-sizing: border-box; }
    body { margin: 0; background: var(--cream); font-family: 'Hind Siliguri', system-ui, sans-serif; color: var(--ink); font-size: 17px; line-height: 1.6; }
    .wrap { max-width: 560px; margin: 0 auto; padding: 16px; }
    .top { background: var(--dark); color: var(--cream); text-align: center; padding: 18px 16px; border-radius: 0 0 22px 22px; }
    .top img { width: 46px; height: 46px; border-radius: 50%; background: #140e0c; padding: 4px; }
    .top b { display: block; letter-spacing: .2em; margin-top: 6px; }
    .card { background: #fff; border-radius: 20px; padding: 20px; margin-top: 16px; box-shadow: 0 14px 34px -24px rgba(0,0,0,.4); }
    h1 { font-size: 22px; margin: 0 0 4px; color: var(--wood); }
    .muted { color: #7a6a5a; font-size: 15px; }
    .products { display: grid; gap: 10px; margin: 12px 0; }
    .prod { display: flex; align-items: center; gap: 12px; border: 2px solid #e8dccb; border-radius: 14px; padding: 8px; cursor: pointer; }
    .prod img { width: 56px; height: 56px; border-radius: 10px; object-fit: cover; }
    .prod input { accent-color: var(--wood); width: 20px; height: 20px; }
    .prod:has(input:checked) { border-color: var(--wood); background: #fbf6ef; }
    .stars { display: flex; flex-direction: row-reverse; justify-content: center; gap: 6px; margin: 8px 0 4px; }
    .stars input { position: absolute; opacity: 0; pointer-events: none; }
    .stars label { font-size: 46px; line-height: 1; color: #ddd; cursor: pointer; transition: transform .15s, color .15s; }
    .stars label:hover, .stars label:hover ~ label, .stars input:checked ~ label { color: #f59e0b; }
    .stars label:active { transform: scale(1.2); }
    .stars input:focus-visible + label { outline: 3px solid var(--gold); border-radius: 8px; }
    .star-text { text-align: center; color: #b45309; font-weight: 600; min-height: 26px; }
    label.f { display: block; font-weight: 600; margin: 14px 0 6px; }
    textarea, input[type=text] { width: 100%; font: inherit; border: 1.5px solid #d6cbbd; border-radius: 12px; padding: 12px; outline: none; }
    textarea:focus, input[type=text]:focus { border-color: var(--wood); }
    .photo { display: flex; align-items: center; gap: 12px; border: 2px dashed #d6cbbd; border-radius: 14px; padding: 12px; cursor: pointer; }
    .photo input { display: none; }
    .photo img { width: 64px; height: 64px; object-fit: cover; border-radius: 10px; display: none; }
    .check { display: flex; gap: 10px; align-items: flex-start; margin-top: 12px; font-size: 15px; }
    .check input { width: 20px; height: 20px; accent-color: var(--wood); margin-top: 3px; flex-shrink: 0; }
    button { width: 100%; margin-top: 18px; background: var(--wood); color: #fff; border: 0; border-radius: 99px; padding: 15px; font: inherit; font-weight: 700; font-size: 18px; cursor: pointer; }
    .err { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; border-radius: 12px; padding: 10px 14px; margin-top: 12px; font-weight: 600; }
    .ok { text-align: center; }
    .ok .big { font-size: 56px; }
    .wa { display: inline-block; margin-top: 12px; color: var(--wood); font-weight: 600; }
  </style>
</head>
<body>
  <div class="top"><img src="assets/logo-mark.png" alt=""><b>INSPIRED CREATIONS</b></div>
  <div class="wrap">
    <?php if (!$valid): ?>
      <div class="card ok"><div class="big">🔗</div><h1>লিংকটি সঠিক নয়</h1><p class="muted">অর্ডারের পর আমাদের পাঠানো লিংকটি পুরোটা খুলুন, অথবা WhatsApp-এ যোগাযোগ করুন।</p>
        <a class="wa" href="https://wa.me/8801321744476">WhatsApp করুন</a></div>
    <?php elseif (!$delivered): ?>
      <div class="card ok"><div class="big">📦</div><h1>অর্ডারটি এখনো পৌঁছায়নি</h1><p class="muted">পণ্য হাতে পাওয়ার পর এই লিংক থেকেই রিভিউ দিতে পারবেন। ধন্যবাদ!</p></div>
    <?php else: ?>
      <?php if ($done): ?>
        <div class="card ok"><div class="big">🙏</div><h1>অনেক ধন্যবাদ, <?= rv_e($firstName) ?>!</h1>
          <p class="muted">আপনার রিভিউ আমরা পেয়েছি। যাচাই করার পর এটি ওয়েবসাইটে দেখানো হবে।</p></div>
      <?php endif; ?>
      <?php if ($open): ?>
        <form class="card" method="post" enctype="multipart/form-data" id="rvForm">
          <input type="hidden" name="o" value="<?= rv_e($order['code']) ?>"><input type="hidden" name="t" value="<?= rv_e($token) ?>">
          <h1><?= $done ? 'আরেকটি পণ্যের রিভিউ দিন' : 'আপনার অর্ডার কেমন লাগলো?' ?></h1>
          <p class="muted">অর্ডার নম্বর <?= rv_e($order['code']) ?> · আপনার সত্যিকারের মতামত অন্য ক্রেতাদের সাহায্য করবে।</p>
          <?php if ($error): ?><div class="err"><?= rv_e($error) ?></div><?php endif; ?>

          <div class="products">
            <?php $first = true; foreach ($open as $id => $p): ?>
              <label class="prod"><input type="radio" name="product" value="<?= rv_e($id) ?>" <?= ($_POST['product'] ?? '') === $id || ($first && empty($_POST['product'])) ? 'checked' : '' ?>>
                <img src="<?= rv_e($p['image']) ?>" alt=""><span><?= rv_e($p['title']) ?></span></label>
            <?php $first = false; endforeach; ?>
          </div>

          <div class="stars" role="radiogroup" aria-label="রেটিং">
            <?php for ($s = 5; $s >= 1; $s--): ?>
              <input type="radio" name="rating" id="s<?= $s ?>" value="<?= $s ?>" <?= (int) ($_POST['rating'] ?? 0) === $s ? 'checked' : '' ?>><label for="s<?= $s ?>" title="<?= $s ?> স্টার">★</label>
            <?php endfor; ?>
          </div>
          <div class="star-text" id="starText"></div>

          <label class="f" for="body">আপনার অভিজ্ঞতা লিখুন <span class="muted">(ঐচ্ছিক)</span></label>
          <textarea id="body" name="body" rows="4" maxlength="1000" placeholder="যেমন: কাঠের মান, ফিনিশিং, প্যাকেজিং, ডেলিভারি কেমন ছিল…"><?= rv_e($_POST['body'] ?? '') ?></textarea>

          <label class="f">পণ্যের একটি ছবি দিন <span class="muted">(ঐচ্ছিক)</span></label>
          <label class="photo"><input type="file" name="photo" accept="image/jpeg,image/png,image/webp" id="photoIn"><img id="photoPrev" alt=""><span id="photoText">📷 ছবি বেছে নিন</span></label>

          <label class="f" for="name">ওয়েবসাইটে যে নাম দেখাবে</label>
          <input type="text" id="name" name="name" maxlength="60" value="<?= rv_e($_POST['name'] ?? $firstName) ?>">
          <?php if ($district !== ''): ?>
            <label class="check"><input type="checkbox" name="show_district" value="1" checked> এলাকার নাম দেখাবে: <?= rv_e($district) ?></label>
          <?php endif; ?>
          <label class="check"><input type="checkbox" name="consent" value="1" <?= !empty($_POST['consent']) ? 'checked' : '' ?>> আমার নাম, রিভিউ ও ছবি INSPIRED CREATIONS-এর ওয়েবসাইটে দেখানো যাবে।</label>
          <button type="submit">রিভিউ পাঠান</button>
        </form>
      <?php elseif (!$done): ?>
        <div class="card ok"><div class="big">✅</div><h1>ধন্যবাদ!</h1><p class="muted">এই অর্ডারের সব পণ্যের রিভিউ দেওয়া হয়ে গেছে।</p></div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
  <script>
    const words = { 1: 'খুব খারাপ', 2: 'ভালো না', 3: 'মোটামুটি', 4: 'ভালো', 5: 'অসাধারণ!' };
    const show = () => { const c = document.querySelector('input[name=rating]:checked'); const t = document.getElementById('starText'); if (t) t.textContent = c ? words[c.value] : 'স্টারে চাপ দিয়ে রেটিং দিন'; };
    document.querySelectorAll('input[name=rating]').forEach(r => r.addEventListener('change', show)); show();
    const pin = document.getElementById('photoIn');
    pin?.addEventListener('change', () => {
      const f = pin.files[0], img = document.getElementById('photoPrev');
      if (!f) return;
      img.src = URL.createObjectURL(f); img.style.display = 'block';
      document.getElementById('photoText').textContent = f.name;
    });
    document.getElementById('rvForm')?.addEventListener('submit', (e) => {
      if (!document.querySelector('input[name=rating]:checked')) { e.preventDefault(); document.getElementById('starText').textContent = 'আগে স্টার দিয়ে রেটিং দিন ⭐'; }
    });
  </script>
</body>
</html>
