<?php
// Landing page editor for one product: design, Bangla text, photos, video, offer and order form, with live preview
require __DIR__ . '/_inc.php';
require_admin();

$pdo = ic_db();
$stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
$stmt->execute([(string) ($_GET['id'] ?? '')]);
$product = $stmt->fetch();
if (!$product) {
    flash('That product no longer exists.');
    redirect('landing.php');
}
$lp = ic_landing_for($pdo, $product);
$data = $lp['data'];
$errors = [];

// Re-shapes a multiple file input into a simple list
$uploads = function (string $key): array {
    if (empty($_FILES[$key]) || !is_array($_FILES[$key]['name'])) {
        return [];
    }
    $list = [];
    foreach ($_FILES[$key]['name'] as $i => $name) {
        if ($_FILES[$key]['error'][$i] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $list[] = ['name' => (string) $name, 'tmp_name' => $_FILES[$key]['tmp_name'][$i], 'size' => (int) $_FILES[$key]['size'][$i], 'error' => (int) $_FILES[$key]['error'][$i]];
    }
    return $list;
};
// Only photos uploaded here (assets/landing/) are ever deleted from disk
$forget = function (string $path): void {
    if (strpos($path, 'assets/landing/') === 0) {
        @unlink(IC_ROOT . '/assets/landing/' . basename($path));
    }
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $action = (string) ($_POST['action'] ?? 'save');
    if ($action === 'reset') {
        foreach (array_merge((array) $data['images'], (array) $data['customer_photos']) as $path) {
            $forget((string) $path);
        }
        ic_landing_save($pdo, $product['id'], ['data_json' => null]);
        flash('The page is back to the ready-made Bangla text. Your design choice is kept.');
        redirect('landing-edit.php?id=' . rawurlencode($product['id']));
    }

    $new = ic_landing_from_post((array) ($_POST['lp'] ?? []), $data);
    foreach (['images' => 10, 'customer_photos' => 16] as $key => $max) {
        $remove = array_map('strval', (array) ($_POST['remove_' . $key] ?? []));
        $kept = [];
        foreach ((array) $data[$key] as $path) {
            if (in_array($path, $remove, true)) {
                $forget($path);
            } else {
                $kept[] = $path;
            }
        }
        foreach ($uploads('new_' . $key) as $file) {
            if (count($kept) >= $max) {
                $errors[] = "Only $max photos fit here — the rest were not added.";
                break;
            }
            [$path, $error] = ic_landing_store_image($file);
            if ($path) {
                $kept[] = $path;
            } else {
                $errors[] = $error;
            }
        }
        $new[$key] = $kept;
    }
    $template = isset(IC_LANDING_TEMPLATES[$_POST['template'] ?? '']) ? $_POST['template'] : $lp['template'];
    ic_landing_save($pdo, $product['id'], [
        'is_enabled' => empty($_POST['enabled']) ? 0 : 1,
        'template'   => $template,
        'data_json'  => json_encode($new, JSON_UNESCAPED_UNICODE),
    ]);
    flash('Landing page saved.' . ($errors ? ' ' . implode(' ', $errors) : ''));
    redirect('landing-edit.php?id=' . rawurlencode($product['id']) . '&saved=1');
}

$v = function (string $key) use ($data): string {
    return h($data[$key] ?? '');
};
$field = function (string $key, string $label, string $placeholder = '', string $hint = '', int $rows = 0) use ($v): void {
    $max = IC_LANDING_TEXT_FIELDS[$key] ?? 200;
    echo '<div><label class="label" for="f_' . $key . '">' . h($label) . '</label>';
    if ($rows) {
        echo '<textarea class="input" id="f_' . $key . '" name="lp[' . $key . ']" rows="' . $rows . '" maxlength="' . $max . '" placeholder="' . h($placeholder) . '">' . $v($key) . '</textarea>';
    } else {
        echo '<input class="input" id="f_' . $key . '" name="lp[' . $key . ']" maxlength="' . $max . '" value="' . $v($key) . '" placeholder="' . h($placeholder) . '">';
    }
    if ($hint !== '') {
        echo '<p class="text-xs text-stone-400 mt-1">' . $hint . '</p>';
    }
    echo '</div>';
};
$step = function (int $n, string $icon, string $title, string $hint = ''): void {
    echo '<div class="flex items-start gap-3 mb-4"><span class="w-9 h-9 rounded-xl bg-wood-800 text-gold-200 flex items-center justify-center flex-shrink-0 text-sm font-bold">' . $n . '</span>'
        . '<div><h2 class="font-serif text-lg text-wood-900"><i class="fa-solid ' . $icon . ' text-gold-600 text-sm mr-1"></i> ' . h($title) . '</h2>'
        . ($hint !== '' ? '<p class="text-xs text-stone-500">' . $hint . '</p>' : '') . '</div></div>';
};
$switch = function (string $name, bool $on, string $title, string $hint): void {
    echo '<label class="flex items-center justify-between gap-4 cursor-pointer rounded-xl bg-cream-50 border border-cream-300 p-3.5">'
        . '<span><span class="block text-sm font-semibold text-wood-900">' . h($title) . '</span><span class="block text-xs text-stone-500">' . h($hint) . '</span></span>'
        . '<span class="relative flex-shrink-0"><input type="checkbox" name="' . h($name) . '" value="1" class="peer sr-only" ' . ($on ? 'checked' : '') . '>'
        . '<span class="block w-11 h-6 rounded-full bg-stone-300 transition peer-checked:bg-emerald-600"></span>'
        . '<span class="absolute top-0.5 left-0.5 w-5 h-5 rounded-full bg-white shadow transition peer-checked:translate-x-5"></span></span></label>';
};
$photoGrid = function (string $key, array $paths, string $empty) {
    echo '<div class="grid grid-cols-3 sm:grid-cols-4 gap-3">';
    if (!$paths) {
        echo '<p class="col-span-full text-sm text-stone-400">' . $empty . '</p>';
    }
    foreach ($paths as $path) {
        echo '<label class="relative block rounded-xl overflow-hidden border border-cream-300 cursor-pointer group">'
            . '<img src="' . h(asset_url($path)) . '" alt="" class="w-full aspect-square object-cover">'
            . '<input type="checkbox" name="remove_' . $key . '[]" value="' . h($path) . '" class="peer sr-only">'
            . '<span class="absolute inset-0 bg-red-600/0 peer-checked:bg-red-600/60 transition flex items-center justify-center text-white text-xs font-semibold opacity-0 peer-checked:opacity-100"><i class="fa-solid fa-trash mr-1"></i> Will be removed</span>'
            . '<span class="absolute top-1.5 right-1.5 w-7 h-7 rounded-full bg-white/90 text-red-600 flex items-center justify-center shadow text-xs" title="Remove"><i class="fa-solid fa-xmark"></i></span></label>';
    }
    echo '</div>';
};
$previewUrl = '../' . ic_landing_url($product['id']) . '&preview=1';
// Products that can be offered as add-ons (on sale, with a price, not this one)
$addonChoices = $pdo->prepare('SELECT id, title, image, price_bdt, category FROM products WHERE is_active = 1 AND price_bdt IS NOT NULL AND id <> ? ORDER BY sort_order, title');
$addonChoices->execute([$product['id']]);
$addonChoices = $addonChoices->fetchAll();
$pickedAddons = array_values(array_filter(explode(',', (string) $data['addon_ids']), 'strlen'));

admin_header('Edit landing page', 'landing.php');
?>
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
  <div class="flex items-center gap-4 min-w-0">
    <img src="<?= h(asset_url($product['image'])) ?>" alt="" class="w-14 h-14 rounded-xl object-cover flex-shrink-0">
    <div class="min-w-0">
      <a href="landing.php" class="text-xs text-gold-700 font-semibold hover:underline"><i class="fa-solid fa-arrow-left"></i> All landing pages</a>
      <h1 class="font-serif text-2xl sm:text-3xl text-wood-900 truncate"><?= h($product['title']) ?></h1>
    </div>
  </div>
  <div class="flex flex-wrap gap-2">
    <a href="<?= h($previewUrl) ?>" target="_blank" class="btn btn-light"><i class="fa-solid fa-arrow-up-right-from-square"></i> Open page</a>
    <button type="button" class="btn btn-light" id="copyLink" data-copy="<?= h(ic_landing_url($product['id'])) ?>"><i class="fa-regular fa-copy"></i> Copy link for ads</button>
  </div>
</div>

<div class="grid lg:grid-cols-[minmax(0,1fr)_420px] gap-6 items-start">
  <form method="post" enctype="multipart/form-data" id="lpForm" class="space-y-6 pb-24">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">

    <section class="card p-5 sm:p-6">
      <?php $step(1, 'fa-swatchbook', 'Design', 'Pick one of ' . count(IC_LANDING_TEMPLATES) . ' designs. The preview on the right changes instantly.'); ?>
      <div class="mb-4"><?php $switch('enabled', $lp['enabled'], 'Landing page on', 'When off, the shop shows the normal product page instead'); ?></div>
      <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
        <?php foreach (IC_LANDING_TEMPLATES as $key => $t): ?>
          <label class="relative cursor-pointer rounded-xl border-2 border-cream-300 p-2.5 transition hover:border-gold-500 has-[:checked]:border-wood-800 has-[:checked]:shadow-lg">
            <input type="radio" name="template" value="<?= h($key) ?>" class="sr-only peer" <?= $lp['template'] === $key ? 'checked' : '' ?>>
            <span class="block h-16 rounded-lg relative overflow-hidden" style="background:<?= h($t['c']['bg']) ?>">
              <span class="absolute left-2 top-2 right-8 h-2.5 rounded-full" style="background:<?= h($t['c']['brand']) ?>"></span>
              <span class="absolute left-2 top-6 w-1/2 h-2 rounded-full" style="background:<?= h($t['c']['accent']) ?>"></span>
              <span class="absolute left-2 bottom-2 w-14 h-4 rounded-full" style="background:<?= h($t['c']['cta']) ?>"></span>
              <span class="absolute right-2 bottom-2 w-8 h-8 rounded-md opacity-80" style="background:<?= h($t['c']['bg2']) ?>;border:1px solid <?= h($t['c']['line']) ?>"></span>
            </span>
            <span class="block mt-2 text-sm font-semibold text-wood-900 leading-tight"><?= h($t['name']) ?></span>
            <span class="block text-[11px] text-stone-500 leading-tight"><?= h($t['for']) ?></span>
            <span class="absolute top-1.5 right-1.5 w-6 h-6 rounded-full bg-wood-800 text-white text-xs items-center justify-center hidden peer-checked:flex"><i class="fa-solid fa-check"></i></span>
          </label>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="card p-5 sm:p-6 space-y-4">
      <?php $step(2, 'fa-heading', 'Top of the page', 'The first thing visitors from your ad see. Write in Bangla.'); ?>
      <?php $field('top_bar', 'Moving announcement bar (one line each)', "✨ ছবিতে যা দেখছেন, বাস্তবে ঠিক তাই পাবেন ✨\n🚚 সারা বাংলাদেশে ক্যাশ অন ডেলিভারি", 'Scrolls across the top. Leave empty to hide it.', 3); ?>
      <?php $field('offer_label', 'Offer label', 'সীমিত সময়ের জন্য ধামাকা অফার!'); ?>
      <?php $field('headline', 'Main headline', 'ডেস্ক বদলান, ইমপ্রেশন বদলে যাবে!', 'Big, bold sentence about what the customer gets.'); ?>
      <?php $field('highlight', 'Coloured line under the headline', 'ঘড়ি + কার্ড হোল্ডার + পেন হোল্ডার — সব একসাথে!'); ?>
      <?php $field('subheadline', 'Short description', 'একজন প্রফেশনাল মানুষের ডেস্কই তার ব্যক্তিত্ব প্রকাশ করে…', '', 3); ?>
    </section>

    <section class="card p-5 sm:p-6 space-y-4">
      <?php $step(3, 'fa-tags', 'Price & offer'); ?>
      <div class="grid sm:grid-cols-2 gap-4">
        <div>
          <span class="label">Offer price (product price)</span>
          <div class="input bg-cream-50 flex items-center justify-between">
            <strong class="text-wood-900"><?= $product['price_bdt'] === null ? 'No price' : money($product['price_bdt']) ?></strong>
            <a href="product.php?id=<?= h(rawurlencode($product['id'])) ?>" class="text-xs text-gold-700 font-semibold hover:underline">Change</a>
          </div>
          <p class="text-xs text-stone-400 mt-1">Orders always use this price.</p>
        </div>
        <div>
          <label class="label" for="f_regular_price">Regular price (shown crossed out)</label>
          <input class="input" id="f_regular_price" name="lp[regular_price]" inputmode="numeric" value="<?= h((string) ($data['regular_price'] ?? '')) ?>" placeholder="e.g. 1500">
          <p class="text-xs mt-1" id="discountHint" data-price="<?= (int) $product['price_bdt'] ?>"></p>
        </div>
      </div>
      <?php if ($product['price_bdt'] === null): ?>
        <p class="rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-sm p-3"><i class="fa-solid fa-triangle-exclamation"></i> This product has no price, so the page shows Call / WhatsApp buttons instead of the order form. Add a price in Products to turn the order form on.</p>
      <?php endif; ?>
      <?php $field('stock_note', 'Stock or urgency note (optional)', 'যেমন: স্টক সীমিত — আজই অর্ডার করুন', 'Only write what is true.'); ?>
      <?php
      $running = $product['price_bdt'] === null ? null : ic_landing_offer($data, (int) $product['price_bdt']);
      $endedAt = (!$running && !empty($data['offer_price']) && $data['countdown_end'] !== '') ? strtotime($data['countdown_end']) : null;
      ?>
      <div class="rounded-2xl border-2 border-dashed border-gold-300 bg-cream-50 p-4 space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <p class="text-sm font-semibold text-wood-900"><i class="fa-solid fa-stopwatch text-gold-600"></i> Timed offer with countdown <span class="font-normal text-stone-400">(optional)</span></p>
          <?php if ($running): ?>
            <span class="rounded-full bg-emerald-100 text-emerald-800 px-3 py-1 text-xs font-semibold"><span class="inline-block w-2 h-2 rounded-full bg-emerald-500 animate-pulse mr-1"></span> Running — ends <?= h(date('d M, g:i a', $running['end'])) ?></span>
          <?php elseif ($endedAt && $endedAt < time()): ?>
            <span class="rounded-full bg-stone-200 text-stone-700 px-3 py-1 text-xs font-semibold">Ended <?= h(date('d M, g:i a', $endedAt)) ?> — normal price is back</span>
          <?php endif; ?>
        </div>
        <p class="text-xs text-stone-500">While the offer runs, the page <strong>and the order</strong> use the offer price and the countdown shows. When the time is up, the offer really ends: the countdown disappears and the normal price comes back by itself. Customers can trust the timer because it is true.</p>
        <div class="grid sm:grid-cols-2 gap-3">
          <div>
            <label class="label" for="f_offer_price">Offer price (৳)</label>
            <input class="input" id="f_offer_price" name="lp[offer_price]" inputmode="numeric" value="<?= h((string) ($data['offer_price'] ?? '')) ?>" placeholder="<?= $product['price_bdt'] === null ? 'Add a product price first' : 'Lower than ' . h(money($product['price_bdt'])) ?>" <?= $product['price_bdt'] === null ? 'disabled' : '' ?>>
            <p class="text-xs mt-1" id="offerHint"></p>
          </div>
          <div>
            <label class="label" for="f_countdown_end">Offer ends at</label>
            <input class="input" type="datetime-local" id="f_countdown_end" name="lp[countdown_end]" value="<?= $v('countdown_end') ?>">
          </div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <span class="text-xs text-stone-500">Start the offer now for:</span>
          <?php foreach ([5 => '5 hours', 24 => '24 hours', 72 => '3 days', 168 => '7 days'] as $hours => $label): ?>
            <button type="button" class="btn btn-light !py-1.5 !px-3 text-xs" data-offer-hours="<?= $hours ?>"><?= $label ?></button>
          <?php endforeach; ?>
          <button type="button" class="btn btn-light !py-1.5 !px-3 text-xs text-red-700" data-offer-stop>Stop offer</button>
        </div>
      </div>
    </section>

    <section class="card p-5 sm:p-6 space-y-4">
      <?php $step(4, 'fa-images', 'Photos & video', 'Several photos make a sliding gallery. Without photos here, the product photo is used.'); ?>
      <?php $photoGrid('images', (array) $data['images'], 'No extra photos yet — the page uses the main product photo.'); ?>
      <label class="flex flex-col items-center justify-center gap-1 rounded-xl border-2 border-dashed border-cream-300 bg-cream-50 p-5 text-center cursor-pointer hover:border-gold-500">
        <i class="fa-solid fa-cloud-arrow-up text-2xl text-gold-600"></i>
        <span class="text-sm font-semibold text-wood-900">Add photos</span>
        <span class="text-xs text-stone-500">JPG, PNG or WEBP · up to 5 MB each · max 10 photos</span>
        <input type="file" name="new_images[]" accept="image/jpeg,image/png,image/webp" multiple class="sr-only" data-count>
        <span class="text-xs text-emerald-700 font-semibold" data-count-text></span>
      </label>
      <?php $field('video_url', 'YouTube or Facebook video link (optional)', 'https://www.youtube.com/watch?v=…', 'Paste the normal video link. The “Video First” design puts it at the very top.'); ?>
    </section>

    <section class="card p-5 sm:p-6 space-y-4">
      <?php $step(5, 'fa-list-check', 'Selling points', 'One point per line. Empty boxes are hidden on the page.'); ?>
      <?php $field('intro', 'Intro paragraph (optional)', 'আপনার অফিসের মান বাড়াবে, কিন্তু বাজেটের বাইরে নয়!', '', 3); ?>
      <div class="grid sm:grid-cols-2 gap-4">
        <div class="space-y-2"><?php $field('benefits_title', 'Benefits — heading'); ?><?php $field('benefits', 'Benefits — points', "১০০% ন্যাচারাল কাঠের তৈরি\nঅফিস, বাসা বা গিফট হিসেবে পারফেক্ট", '', 6); ?></div>
        <div class="space-y-2"><?php $field('why_title', 'Why buy from us — heading'); ?><?php $field('why', 'Why buy from us — points', "সারা বাংলাদেশ ক্যাশ অন ডেলিভারি\nপ্রিমিয়াম ফিনিশিং", '', 6); ?></div>
        <div class="space-y-2"><?php $field('parts_title', 'What is in the box — heading'); ?><?php $field('parts', 'What is in the box — items', "কার্ড হোল্ডার\nমোবাইল হোল্ডার\nপেন হোল্ডার", '', 4); ?></div>
        <div class="space-y-2"><?php $field('badges', 'Trust badges (4 short lines)', "ক্যাশ অন ডেলিভারি\nসারা দেশে হোম ডেলিভারি", 'Shown as small cards with icons.', 4); ?><?php $field('gift', 'Free gift / bonus (optional)', 'সাথে পাচ্ছেন একটি ব্যাটারি একদম ফ্রি!'); ?></div>
      </div>
    </section>

    <section class="card p-5 sm:p-6 space-y-4">
      <?php $step(6, 'fa-camera-retro', 'Customer photos (optional)', 'Real photos or screenshots your customers sent you. They build trust — never use photos that are not from real customers.'); ?>
      <?php $field('gallery_title', 'Heading'); ?>
      <?php $photoGrid('customer_photos', (array) $data['customer_photos'], 'No customer photos yet — this part stays hidden.'); ?>
      <label class="flex items-center justify-center gap-3 rounded-xl border-2 border-dashed border-cream-300 bg-cream-50 p-4 cursor-pointer hover:border-gold-500">
        <i class="fa-solid fa-plus text-gold-600"></i><span class="text-sm font-semibold text-wood-900">Add customer photos</span>
        <input type="file" name="new_customer_photos[]" accept="image/jpeg,image/png,image/webp" multiple class="sr-only" data-count>
        <span class="text-xs text-emerald-700 font-semibold" data-count-text></span>
      </label>
    </section>

    <section class="card p-5 sm:p-6 space-y-4">
      <?php $step(7, 'fa-circle-question', 'Questions & answers', 'Write each as: Question | Answer. {dhaka} and {outside} become your delivery charges.'); ?>
      <?php $field('faq_title', 'Heading'); ?>
      <?php $field('faq', 'Questions', 'ডেলিভারি চার্জ কত? | ঢাকার ভিতরে {dhaka} টাকা, ঢাকার বাইরে {outside} টাকা।', '', 6); ?>
    </section>

    <section class="card p-5 sm:p-6 space-y-4">
      <?php $step(8, 'fa-cart-shopping', 'Order form & contact'); ?>
      <div class="grid sm:grid-cols-2 gap-4">
        <?php $field('form_title', 'Form heading'); ?>
        <?php $field('cta_text', 'Order button text', 'এখনই অর্ডার করুন'); ?>
      </div>
      <?php $field('form_note', 'Text above the form', '', '', 2); ?>
      <div class="grid sm:grid-cols-2 gap-4">
        <?php $field('phone', 'Call number', '01705525009'); ?>
        <?php $field('whatsapp', 'WhatsApp / bKash number', '01321744476'); ?>
      </div>
      <div>
        <span class="label">Payment on this page</span>
        <div class="grid sm:grid-cols-2 gap-3">
          <?php foreach (['cod' => ['Cash on Delivery only', 'Easiest for customers — most orders'], 'both' => ['Cash on Delivery + pay first', 'Customer can also pay by bKash / Nagad / Rocket / Bangla QR and enter the Transaction ID']] as $key => [$title, $hint]): ?>
            <label class="flex gap-3 cursor-pointer rounded-xl border-2 border-cream-300 p-3 has-[:checked]:border-wood-800">
              <input type="radio" name="lp[payment]" value="<?= $key ?>" class="mt-1 accent-[#5C3118]" <?= $data['payment'] === $key ? 'checked' : '' ?>>
              <span><span class="block text-sm font-semibold text-wood-900"><?= $title ?></span><span class="block text-xs text-stone-500"><?= $hint ?></span></span>
            </label>
          <?php endforeach; ?>
        </div>
        <div class="flex flex-wrap items-center gap-2 mt-3">
          <span class="text-xs text-stone-500">Pay-first options you accept:</span>
          <?php $banks = explode(',', (string) $data['banking']); $qrReady = ic_bangla_qr_active(ic_bangla_qr($pdo)); foreach (IC_PAY_CHANNELS as $key => $name): ?>
            <label class="inline-flex items-center gap-2 rounded-lg border border-cream-300 bg-white px-3 py-1.5 text-sm cursor-pointer has-[:checked]:border-wood-800">
              <input type="checkbox" name="lp[banking][]" value="<?= $key ?>" class="accent-[#5C3118]" <?= in_array($key, $banks, true) ? 'checked' : '' ?>> <?= $name ?><?php if ($key === 'banglaqr' && !$qrReady): ?> <a href="payments.php" class="text-[11px] text-amber-700 underline" title="Shows on the page only after you upload your QR">upload in Payment → Bangla QR</a><?php endif; ?>
            </label>
          <?php endforeach; ?>
          <span class="text-xs text-stone-400">(customer sends money to your WhatsApp / bKash number above)</span>
        </div>
      </div>
      <div class="rounded-2xl border border-cream-300 bg-cream-50 p-4 space-y-3" id="addonBox">
        <div>
          <p class="text-sm font-semibold text-wood-900"><i class="fa-solid fa-cart-plus text-gold-600"></i> “সাথে নিতে পারেন” — add-ons in the order form</p>
          <p class="text-xs text-stone-500">Customers tick these to add them to the same order (one parcel, one delivery charge).</p>
        </div>
        <div class="grid sm:grid-cols-3 gap-2">
          <?php foreach (['auto' => ['Automatic', '3 products from the same category'], 'pick' => ['I choose', 'Only the products you tick below'], 'off' => ['Off', 'No add-ons on this page']] as $key => [$title, $hint]): ?>
            <label class="flex gap-2 cursor-pointer rounded-xl border-2 border-cream-300 bg-white p-2.5 has-[:checked]:border-wood-800">
              <input type="radio" name="lp[addon_mode]" value="<?= $key ?>" class="mt-1 accent-[#5C3118]" <?= $data['addon_mode'] === $key ? 'checked' : '' ?>>
              <span><span class="block text-sm font-semibold text-wood-900"><?= $title ?></span><span class="block text-[11px] text-stone-500"><?= $hint ?></span></span>
            </label>
          <?php endforeach; ?>
        </div>
        <div id="addonPicker" class="space-y-2" <?= $data['addon_mode'] === 'pick' ? '' : 'hidden' ?>>
          <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="text-xs text-stone-500">Tick up to <?= IC_LANDING_MAX_ADDONS ?> products — they show in the order you tick them. <strong id="addonCount" class="text-wood-900"></strong></p>
            <input type="search" id="addonSearch" class="input !py-1.5 !w-48 text-xs" placeholder="Search products…">
          </div>
          <div id="addonInputs"><?php foreach ($pickedAddons as $id): ?><input type="hidden" name="lp[addon_ids][]" value="<?= h($id) ?>"><?php endforeach; ?></div>
          <?php if (!$addonChoices): ?>
            <p class="text-sm text-stone-400">No other products with a price yet.</p>
          <?php endif; ?>
          <div class="grid sm:grid-cols-2 gap-2 max-h-80 overflow-y-auto pr-1">
            <?php foreach ($addonChoices as $a): ?>
              <button type="button" class="addon-pick relative flex items-center gap-3 rounded-xl border-2 border-cream-300 bg-white p-2 text-left hover:border-gold-500" data-addon="<?= h($a['id']) ?>" data-title="<?= h(strtolower($a['title'])) ?>">
                <img src="<?= h(asset_url($a['image'])) ?>" alt="" class="w-12 h-12 rounded-lg object-cover flex-shrink-0" loading="lazy">
                <span class="min-w-0 flex-1"><span class="block text-sm font-semibold text-wood-900 truncate"><?= h($a['title']) ?></span><span class="block text-xs text-stone-500"><?= money($a['price_bdt']) ?></span></span>
                <span class="addon-num w-7 h-7 rounded-full border-2 border-cream-300 flex items-center justify-center text-xs font-bold flex-shrink-0"></span>
              </button>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <?php $switch('lp[show_related]', !empty($data['show_related']), 'Show “আরও দেখুন” (You may also like)', 'Other products at the bottom of the page'); ?>
      <?php if (!empty($product['is_customizable'])): ?>
        <?php $switch('lp[show_customize]', !empty($data['show_customize']), 'Show “Customize” button', 'Opens the design chart for this product on the shop'); ?>
      <?php else: ?>
        <input type="hidden" name="lp[show_customize]" value="<?= empty($data['show_customize']) ? '' : '1' ?>">
      <?php endif; ?>
    </section>

    <div class="fixed bottom-0 inset-x-0 z-30 bg-white/95 backdrop-blur border-t border-cream-300 py-3">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 flex flex-wrap items-center gap-3 justify-between">
        <span class="text-xs text-stone-500" id="dirtyNote"><?= $lp['custom'] ? 'Using your own text' : 'Using the ready-made Bangla text' ?></span>
        <div class="flex gap-2">
          <?php if ($lp['custom']): ?>
            <button type="submit" form="resetForm" class="btn btn-light" title="Go back to the ready-made text"><i class="fa-solid fa-rotate-left"></i> Reset text</button>
          <?php endif; ?>
          <button class="btn btn-gold !px-8"><i class="fa-regular fa-floppy-disk"></i> Save landing page</button>
        </div>
      </div>
    </div>
  </form>

  <aside class="lg:sticky lg:top-4">
    <div class="card p-3">
      <div class="flex items-center justify-between gap-2 mb-2 px-1">
        <span class="text-sm font-semibold text-wood-900"><i class="fa-solid fa-eye text-gold-600"></i> Live preview</span>
        <span class="text-[11px] text-stone-400" id="previewState">Updates as you type</span>
      </div>
      <div class="mx-auto rounded-[28px] border-[10px] border-wood-950 overflow-hidden bg-white shadow-xl" style="width:100%;max-width:400px">
        <div id="previewBox" class="relative overflow-hidden" style="height:min(72vh,720px)">
          <iframe name="lpPreview" id="lpPreview" src="<?= h($previewUrl) ?>" title="Landing page preview" class="absolute top-0 left-0 origin-top-left border-0" style="width:390px"></iframe>
        </div>
      </div>
      <p class="text-[11px] text-stone-400 text-center mt-2">New photos appear after you save.</p>
    </div>
  </aside>
</div>

<form method="post" id="resetForm" onsubmit="return confirm('Replace your text with the ready-made Bangla text? Photos you added here are removed.');">
  <?= csrf_field() ?><input type="hidden" name="action" value="reset">
</form>

<script>
  (() => {
    const form = document.getElementById('lpForm');
    const frame = document.getElementById('lpPreview');
    const box = document.getElementById('previewBox');
    const state = document.getElementById('previewState');
    const previewAction = <?= json_encode($previewUrl) ?>;

    // Phone-sized preview scaled to fit the panel
    const fit = () => {
      const scale = box.clientWidth / 390;
      frame.style.transform = `scale(${scale})`;
      frame.style.height = (box.clientHeight / scale) + 'px';
    };
    addEventListener('resize', fit); fit();

    // Sends the unsaved form to the preview (files are left out until saved)
    let timer;
    const refresh = () => {
      const files = [...form.querySelectorAll('input[type=file]')];
      files.forEach(f => { f.disabled = true; });
      // (setAttribute: the hidden input named "action" hides form.action)
      form.setAttribute('action', previewAction); form.setAttribute('target', 'lpPreview');
      HTMLFormElement.prototype.submit.call(form);
      form.removeAttribute('action'); form.removeAttribute('target');
      files.forEach(f => { f.disabled = false; });
      state.textContent = 'Preview updated';
    };
    const later = () => { state.textContent = 'Updating…'; clearTimeout(timer); timer = setTimeout(refresh, 700); };
    form.addEventListener('input', (e) => { if (e.target.type !== 'file' && e.target.id !== 'addonSearch') later(); });
    form.addEventListener('change', (e) => { if (e.target.type === 'radio' || e.target.type === 'checkbox') later(); });

    // Discount hint
    const reg = document.getElementById('f_regular_price'), hint = document.getElementById('discountHint');
    const showDiscount = () => {
      const price = +hint.dataset.price, r = +reg.value.replace(/\D/g, '');
      if (!price || !r) { hint.textContent = 'Leave empty if there is no discount.'; hint.className = 'text-xs mt-1 text-stone-400'; return; }
      if (r <= price) { hint.textContent = 'Must be higher than the offer price to show a discount.'; hint.className = 'text-xs mt-1 text-amber-700'; return; }
      hint.textContent = `Shows ${Math.round((r - price) / r * 100)}% off — customer saves ৳${(r - price).toLocaleString()}`;
      hint.className = 'text-xs mt-1 text-emerald-700 font-semibold';
    };
    reg.addEventListener('input', showDiscount); showDiscount();

    // Timed offer: hint + quick start buttons (end time = now + N hours, in the shop's local time)
    const offer = document.getElementById('f_offer_price'), offerHint = document.getElementById('offerHint'), end = document.getElementById('f_countdown_end');
    const showOffer = () => {
      const price = +hint.dataset.price, o = +offer.value.replace(/\D/g, '');
      if (!o) { offerHint.textContent = 'Empty = no timed offer.'; offerHint.className = 'text-xs mt-1 text-stone-400'; return; }
      if (o >= price) { offerHint.textContent = 'Must be lower than the normal price.'; offerHint.className = 'text-xs mt-1 text-amber-700'; return; }
      offerHint.textContent = `${Math.round((price - o) / price * 100)}% off while the offer runs`;
      offerHint.className = 'text-xs mt-1 text-emerald-700 font-semibold';
    };
    offer.addEventListener('input', showOffer); showOffer();
    const pad = (n) => String(n).padStart(2, '0');
    const setEnd = (value) => { end.value = value; end.dispatchEvent(new Event('input', { bubbles: true })); };
    document.querySelectorAll('[data-offer-hours]').forEach(b => b.addEventListener('click', () => {
      if (!offer.value) { offer.focus(); offerHint.textContent = 'Write the offer price first.'; offerHint.className = 'text-xs mt-1 text-amber-700'; return; }
      const d = new Date(Date.now() + b.dataset.offerHours * 3600000);
      setEnd(`${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`);
    }));
    document.querySelector('[data-offer-stop]').addEventListener('click', () => setEnd(''));

    // Add-on picker: order of ticking is kept in hidden inputs; preview refreshes after each change
    const picker = document.getElementById('addonPicker'), inputs = document.getElementById('addonInputs'), maxAddons = <?= IC_LANDING_MAX_ADDONS ?>;
    const picked = () => [...inputs.querySelectorAll('input')].map(i => i.value);
    const paintAddons = () => {
      const list = picked();
      picker.querySelectorAll('.addon-pick').forEach(b => {
        const n = list.indexOf(b.dataset.addon) + 1;
        const num = b.querySelector('.addon-num');
        num.textContent = n || '';
        num.className = 'addon-num w-7 h-7 rounded-full border-2 flex items-center justify-center text-xs font-bold flex-shrink-0 ' + (n ? 'bg-wood-800 border-wood-800 text-white' : 'border-cream-300');
        b.classList.toggle('!border-wood-800', !!n);
      });
      document.getElementById('addonCount').textContent = `${list.length} / ${maxAddons} chosen`;
    };
    picker.addEventListener('click', (e) => {
      const b = e.target.closest('.addon-pick');
      if (!b) return;
      const existing = [...inputs.querySelectorAll('input')].find(i => i.value === b.dataset.addon);
      if (existing) existing.remove();
      else if (picked().length < maxAddons) {
        const i = document.createElement('input'); i.type = 'hidden'; i.name = 'lp[addon_ids][]'; i.value = b.dataset.addon; inputs.appendChild(i);
      } else { alert(`You can choose up to ${maxAddons} add-ons.`); return; }
      paintAddons(); dirty = true; later();
    });
    form.querySelectorAll('input[name="lp[addon_mode]"]').forEach(r => r.addEventListener('change', () => {
      picker.hidden = form.querySelector('input[name="lp[addon_mode]"]:checked').value !== 'pick';
    }));
    document.getElementById('addonSearch').addEventListener('input', (e) => {
      const q = e.target.value.trim().toLowerCase();
      picker.querySelectorAll('.addon-pick').forEach(b => { b.hidden = q !== '' && !b.dataset.title.includes(q); });
    });
    paintAddons();

    // Chosen file count
    form.querySelectorAll('[data-count]').forEach(input => input.addEventListener('change', () => {
      input.parentElement.querySelector('[data-count-text]').textContent = input.files.length ? `${input.files.length} photo(s) ready — press Save` : '';
    }));

    // Warn before leaving with unsaved changes
    let dirty = false;
    form.addEventListener('input', () => { dirty = true; document.getElementById('dirtyNote').textContent = 'Unsaved changes'; });
    form.addEventListener('submit', () => { dirty = false; });
    addEventListener('beforeunload', (e) => { if (dirty) { e.preventDefault(); e.returnValue = ''; } });

    document.getElementById('copyLink').addEventListener('click', async (e) => {
      const btn = e.currentTarget, url = new URL('../' + btn.dataset.copy, location.href).href;
      try { await navigator.clipboard.writeText(url); } catch (err) { prompt('Copy this link:', url); return; }
      const old = btn.innerHTML; btn.innerHTML = '<i class="fa-solid fa-check"></i> Copied'; setTimeout(() => { btn.innerHTML = old; }, 1600);
    });
  })();
</script>
<?php admin_footer();
