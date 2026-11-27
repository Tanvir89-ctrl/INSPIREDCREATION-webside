<?php
// Public product landing page: landing.php?p=<product id>
// Content and design (one of 15 templates) are set in admin → Landing Pages. Orders go to api/orders.php.
declare(strict_types=1);
require __DIR__ . '/api/bootstrap.php';

function lp_e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// 1500 → ১,৫০০
function lp_bn($number): string
{
    return ic_english_to_bangla_digits(number_format((int) $number));
}

function lp_icon(string $name, string $class = 'ic'): string
{
    static $paths = [
        'check'   => '<path d="M5 12.5l4.2 4.2L19 7"/>',
        'truck'   => '<path d="M3 6h11v9H3zM14 9h4l3 3v3h-7"/><circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/>',
        'cash'    => '<rect x="3" y="6" width="18" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6 9h.01M18 15h.01"/>',
        'shield'  => '<path d="M12 3l7 3v6c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6z"/><path d="M9 12l2 2 4-4"/>',
        'chat'    => '<path d="M4 5h16v11H9l-5 4z"/><path d="M8 10h8M8 13h5"/>',
        'gift'    => '<rect x="3" y="8" width="18" height="4" rx="1"/><path d="M5 12v9h14v-9M12 8v13M12 8C10 4 6 4 6 6.5S9 8 12 8zm0 0c2-4 6-4 6-1.5S15 8 12 8z"/>',
        'star'    => '<path d="M12 3l2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1L3.2 9.5l6.1-.9z"/>',
        'phone'   => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 005 5L15 13l5 2v4a1 1 0 01-1 1A16 16 0 014 5a1 1 0 011-1z"/>',
        'box'     => '<path d="M3 7l9-4 9 4v10l-9 4-9-4z"/><path d="M3 7l9 4 9-4M12 11v10"/>',
        'clock'   => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'arrow'   => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'lock'    => '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 018 0v4"/>',
        'sparkle' => '<path d="M12 3l1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8zM19 16l.8 2.2L22 19l-2.2.8L19 22l-.8-2.2L16 19l2.2-.8z"/>',
        'pen'     => '<path d="M4 20l4-1 11-11-3-3L5 16zM14 6l3 3"/>',
        'minus'   => '<path d="M5 12h14"/>',
        'plus'    => '<path d="M12 5v14M5 12h14"/>',
        'chevron' => '<path d="M6 9l6 6 6-6"/>',
        'cart'    => '<circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M2 3h3l2.6 12.2a1 1 0 001 .8h9.7a1 1 0 001-.8L21 7H6"/>',
        'arrowCircle' => '<circle cx="12" cy="12" r="9"/><path d="M10.5 8.5L14 12l-3.5 3.5"/>',
        'left'    => '<path d="M15 6l-6 6 6 6"/>',
        'right'   => '<path d="M9 6l6 6-6 6"/>',
        'whatsapp' => '<path d="M4 20l1.3-4A8 8 0 1112 20a8 8 0 01-3.9-1z"/><path d="M9 8.5c0 3 2.5 6 6 6.5l1-1.5-2-1-1 1c-1-.5-2-1.5-2.5-2.5l1-1-1-2z"/>',
    ];
    return '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($paths[$name] ?? '') . '</svg>';
}

$pdo = ic_db();
ic_migrate($pdo);

// The shop's admin can preview pages that are off, unsaved edits (POST from the editor) and other templates
$isAdmin = false;
if (!empty($_COOKIE['ic_admin'])) {
    session_name('ic_admin');
    session_start(['read_and_close' => true]);
    $isAdmin = !empty($_SESSION['admin_id']);
}
$preview = $isAdmin && (isset($_GET['preview']) || $_SERVER['REQUEST_METHOD'] === 'POST');

$productId = (string) ($_GET['p'] ?? '');
$stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
$stmt->execute([$productId]);
$product = $stmt->fetch();
if (!$product || (!$product['is_active'] && !$preview)) {
    header('Location: index.html#products');
    exit;
}

$landing = ic_landing_for($pdo, $product);
if (!$landing['enabled'] && !$preview) {
    header('Location: index.html#/product/' . rawurlencode($product['id']));
    exit;
}
$data = $landing['data'];
$tplKey = $landing['template'];
if ($preview && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals((string) ($_SESSION['csrf'] ?? ''), (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('Preview expired — refresh the editor.');
    }
    $data = ic_landing_from_post((array) ($_POST['lp'] ?? []), $data);
    $tplKey = (string) ($_POST['template'] ?? $tplKey);
} elseif ($preview && isset($_GET['tpl'])) {
    $tplKey = (string) $_GET['tpl'];
}
$tpl = ic_landing_template($tplKey);
$c = $tpl['c'];

if (!$preview) {
    try {
        ic_landing_count_view($pdo, $product['id']);
    } catch (Throwable $e) {
        error_log('Landing view count failed: ' . $e->getMessage());
    }
}

/* ---------- Everything the page shows ---------- */
$price = $product['price_bdt'] === null ? null : (int) $product['price_bdt'];
$normalPrice = $price;
// A running timed offer: the normal price is shown crossed out and the offer price is what the customer pays
$timed = ic_landing_offer($data, $price);
if ($timed) {
    $regular = $price;
    $price = $timed['price'];
} else {
    $regular = ($price !== null && $data['regular_price'] !== null && (int) $data['regular_price'] > $price) ? (int) $data['regular_price'] : null;
}
$offerOnly = $timed ? ' offer-only' : ''; // hidden the moment the offer ends
$discount = $regular ? (int) round(($regular - $price) / $regular * 100) : 0;
$ship = ic_shipping_defaults($pdo);
$fee = function (array $p, string $region) use ($ship): int {
    if (!empty($p['free_shipping'])) {
        return 0;
    }
    $own = $region === 'outside' ? $p['ship_outside'] : $p['ship_dhaka'];
    return $own === null ? $ship[$region] : (int) $own;
};
$feeDhaka = $fee($product, 'dhaka');
$feeOutside = $fee($product, 'outside');

$images = array_values(array_filter((array) $data['images'], 'is_string'));
if (!$images) {
    $images = [$product['image']];
}
$customerPhotos = array_values(array_filter((array) $data['customer_photos'], 'is_string'));
// Templates that match the product: colours come from the first photo
if (!empty($tpl['autoColor'])) {
    try {
        $c = ic_landing_auto_palette($pdo, $images[0]) ?? $c;
    } catch (Throwable $e) {
        error_log('Landing palette failed: ' . $e->getMessage());
    }
}
// Real orders from this page in the last 7 days (shown only from 3 orders up)
$proofCount = !empty($tpl['proof']) && $product['price_bdt'] !== null ? ic_landing_recent_orders($pdo, $product['id']) : 0;

$related = $pdo->prepare('SELECT * FROM products WHERE is_active = 1 AND id <> ? AND price_bdt IS NOT NULL
    ORDER BY CASE WHEN category = ? THEN 0 ELSE 1 END, sort_order, title LIMIT 6');
$related->execute([$product['id'], $product['category']]);
$related = $related->fetchAll();
$addons = ic_landing_addons($pdo, $product, $data);

$countdownEnd = $timed ? $timed['end'] : null;
$video = $data['video_url'] !== '' ? ic_landing_video_embed($data['video_url']) : null;
$faq = [];
foreach (ic_lines($data['faq']) as $line) {
    $parts = array_map('trim', explode('|', $line, 2));
    if ($parts[0] !== '' && isset($parts[1]) && $parts[1] !== '') {
        $faq[] = [$parts[0], strtr($parts[1], ['{dhaka}' => lp_bn($feeDhaka), '{outside}' => lp_bn($feeOutside)])];
    }
}
$phone = $data['phone'] !== '' ? $data['phone'] : '01705525009';
$waLocal = $data['whatsapp'] !== '' ? $data['whatsapp'] : '01321744476';
$waIntl = preg_replace('/^\+?(88)?/', '88', $waLocal);
$waLink = 'https://wa.me/' . $waIntl . '?text=' . rawurlencode('আসসালামু আলাইকুম, আমি "' . $product['title'] . '" সম্পর্কে জানতে চাই।');
$customize = !empty($product['is_customizable']) && !empty($data['show_customize']);
$customizeLink = 'index.html#/product/' . rawurlencode($product['id']) . '/customize';
$meta = ic_meta_settings($pdo);
// The shop's Bangla QR (admin → Payment → Bangla QR); offered only when uploaded and switched on
$qr = ic_bangla_qr($pdo);
$qrOn = ic_bangla_qr_active($qr);
$pixel = ic_meta_active($meta) && !$preview ? ['id' => $meta['pixel_id'], 'server' => $meta['capi_token'] !== ''] : null;

$publicProduct = function (array $p) use ($fee): array {
    return [
        'id' => $p['id'], 'title' => $p['title'], 'price' => (int) $p['price_bdt'], 'image' => $p['image'],
        'dhaka' => $fee($p, 'dhaka'), 'outside' => $fee($p, 'outside'),
    ];
};
$mainProduct = $price === null ? null : ['price' => $price] + $publicProduct($product);
$config = [
    'product'  => $mainProduct,
    'normal'   => $timed ? $normalPrice : null,
    'addons'   => array_map($publicProduct, $addons),
    'freeMin'  => $ship['freeMin'],
    'payment'  => $data['payment'],
    'wa'       => $waIntl,
    'pixel'    => $pixel,
    'preview'  => $preview,
    'end'      => $countdownEnd ? $countdownEnd * 1000 : null,
    'fx'       => $tpl['fx'],
    'img'      => $tpl['img'],
    'landing'  => $product['id'],
    'magnetic' => !empty($tpl['magnetic']),
];

$fontUrl = 'https://fonts.googleapis.com/css2?family=' . implode('&family=', array_map(function ($f) {
    return str_replace(' ', '+', $f);
}, array_merge($tpl['fonts'], ['Cinzel:wght@700']))) . '&display=swap';
$heroLayout = $tpl['hero'] === 'video' && !$video ? 'banner' : $tpl['hero'];
$sliderInHero = in_array($heroLayout, ['banner', 'split'], true);

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
if ($preview) {
    header('Cache-Control: no-store');
}

/* ---------- Building blocks ---------- */
$cta = function (string $extra = '') use ($data, $tpl): string {
    $text = '<span>' . lp_e($data['cta_text'] ?: 'অর্ডার করুন') . '</span>';
    $inner = !empty($tpl['ctaIcon']) ? lp_icon($tpl['ctaIcon']) . $text : $text . lp_icon('arrow');
    return '<a href="#order" class="cta cta-' . $tpl['cta'] . ' ' . $extra . '">' . $inner . '</a>';
};
$slider = function () use ($images, $discount, $tpl, $product, $offerOnly): string {
    $html = '<div class="slider img-' . $tpl['img'] . '" data-slider>';
    if ($tpl['key'] === 'gift') {
        $html .= '<span class="ribbon">উপহার</span>';
    }
    $html .= '<div class="slides">';
    foreach ($images as $i => $src) {
        $html .= '<div class="slide' . ($i === 0 ? ' on' : '') . '"><img src="' . lp_e($src) . '" alt="' . lp_e($product['title']) . '"' . ($i ? ' loading="lazy"' : '') . '></div>';
    }
    $html .= '</div>';
    if ($discount > 0) {
        $html .= '<span class="off-badge' . $offerOnly . '">-' . lp_bn($discount) . '%</span>';
    }
    if (count($images) > 1) {
        $html .= '<button type="button" class="nav prev" aria-label="আগের ছবি">' . lp_icon('left') . '</button>'
            . '<button type="button" class="nav next" aria-label="পরের ছবি">' . lp_icon('right') . '</button><div class="dots">';
        foreach ($images as $i => $src) {
            $html .= '<button type="button" class="' . ($i === 0 ? 'on' : '') . '" aria-label="ছবি ' . ($i + 1) . '"></button>';
        }
        $html .= '</div>';
    }
    return $html . '</div>';
};
$priceBox = function (bool $label = true) use ($price, $regular, $data, $offerOnly, $timed, $tpl): string {
    $words = ($tpl['labels'] ?? []) + ['regular' => 'রেগুলার প্রাইস', 'offer' => 'অফার প্রাইস', 'plain' => 'মূল্য'];
    if ($price === null) {
        return '<div class="price-box"><div class="pb-offer">দাম জানতে কল বা WhatsApp করুন</div></div>';
    }
    $html = '<div class="price-box">';
    if ($label && $data['offer_label'] !== '') {
        $html .= '<div class="pb-label">' . lp_e($data['offer_label']) . '</div>';
    }
    if ($regular) {
        $html .= '<div class="pb-regular' . $offerOnly . '">' . $words['regular'] . ' <s>৳' . lp_bn($regular) . '</s></div>';
    }
    $html .= '<div class="pb-offer"><span data-price-word>' . ($regular ? $words['offer'] : $words['plain']) . '</span> <strong class="count" data-main-price data-count="' . $price . '">৳' . lp_bn($price) . '</strong></div>';
    if ($regular) {
        $html .= '<div class="pb-save' . $offerOnly . '">আপনি সাশ্রয় করছেন ৳' . lp_bn($regular - $price) . '</div>';
    }
    if ($timed) {
        $html .= '<div class="pb-ended" hidden>এই অফারের সময় শেষ — এখন নিয়মিত মূল্য</div>';
    }
    if ($data['stock_note'] !== '') {
        $html .= '<div class="pb-stock"><span class="dot"></span>' . lp_e($data['stock_note']) . '</div>';
    }
    return $html . '</div>';
};
$countdown = function () use ($countdownEnd): string {
    if (!$countdownEnd) {
        return '';
    }
    $box = function (string $unit, string $label) {
        return '<div><b data-cd="' . $unit . '">০০</b><span>' . $label . '</span></div>';
    };
    return '<div class="countdown"><p>' . lp_icon('clock') . ' অফার শেষ হতে বাকি</p><div class="cd-boxes">'
        . $box('d', 'দিন') . $box('h', 'ঘণ্টা') . $box('m', 'মিনিট') . $box('s', 'সেকেন্ড') . '</div></div>';
};
$listIcons = ['star', 'shield', 'sparkle', 'gift', 'box', 'check'];
$list = function (array $items, string $style, string $icon = 'check') use ($listIcons, $images): string {
    if ($style === 'cards') {
        $html = '<div class="cards">';
        foreach ($items as $i => $item) {
            $html .= '<div class="card rv" style="--d:' . ($i % 4) . '"><span class="card-ic">' . lp_icon($listIcons[$i % count($listIcons)]) . '</span><p>' . lp_e($item) . '</p></div>';
        }
        return $html . '</div>';
    }
    if ($style === 'numbered') {
        $html = '<ol class="numbered">';
        foreach ($items as $i => $item) {
            $html .= '<li class="rv" style="--d:' . ($i % 4) . '"><span class="num">' . ic_english_to_bangla_digits(str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT)) . '</span><p>' . lp_e($item) . '</p></li>';
        }
        return $html . '</ol>';
    }
    if ($style === 'alt') {
        $html = '<div class="alt-rows">';
        foreach ($items as $i => $item) {
            $html .= '<div class="alt-row' . ($i % 2 ? ' flip' : '') . '"><div class="alt-img rv from-' . ($i % 2 ? 'right' : 'left') . '"><img src="' . lp_e($images[$i % count($images)]) . '" alt="" loading="lazy"></div>'
                . '<div class="alt-text rv from-' . ($i % 2 ? 'left' : 'right') . '"><span class="num">' . ic_english_to_bangla_digits((string) ($i + 1)) . '</span><p>' . lp_e($item) . '</p></div></div>';
        }
        return $html . '</div>';
    }
    $html = '<ul class="checks">';
    foreach ($items as $i => $item) {
        $html .= '<li class="rv" style="--d:' . ($i % 4) . '">' . lp_icon($icon) . '<span>' . lp_e($item) . '</span></li>';
    }
    return $html . '</ul>';
};

// Each section returns its inner HTML ('' = skip). $alt alternates the background colour.
$sections = [];
$sections['badges'] = function () use ($data): string {
    $items = ic_lines($data['badges']);
    if (!$items) {
        return '';
    }
    $icons = ['cash', 'truck', 'shield', 'chat', 'star', 'gift'];
    $html = '<div class="wrap"><div class="badges">';
    foreach ($items as $i => $item) {
        $html .= '<div class="badge rv" style="--d:' . ($i % 4) . '"><span>' . lp_icon($icons[$i % count($icons)]) . '</span><b>' . lp_e($item) . '</b></div>';
    }
    return $html . '</div></div>';
};
$sections['intro'] = function () use ($data, $tpl): string {
    return ($data['intro'] === '' || !empty($tpl['introInBenefits'])) ? '' : '<div class="wrap narrow"><div class="intro rv">' . nl2br(lp_e($data['intro'])) . '</div></div>';
};
$sections['benefits'] = function () use ($data, $tpl, $list): string {
    $items = ic_lines($data['benefits']);
    $lead = (!empty($tpl['introInBenefits']) && $data['intro'] !== '') ? '<p class="intro-lead rv">' . nl2br(lp_e($data['intro'])) . '</p>' : '';
    return $items ? '<div class="wrap"><h2 class="sec-title rv">' . lp_e($data['benefits_title']) . '</h2>' . $lead . $list($items, $tpl['list']) . '</div>' : '';
};
$sections['why'] = function () use ($data, $tpl, $list): string {
    $items = ic_lines($data['why']);
    $style = $tpl['list'] === 'alt' ? 'checks' : $tpl['list'];
    return $items ? '<div class="wrap"><h2 class="sec-title rv">' . lp_e($data['why_title']) . '</h2>' . $list($items, $style, $tpl['whyIcon'] ?? 'check') . '</div>' : '';
};
$sections['parts'] = function () use ($data): string {
    $items = ic_lines($data['parts']);
    if (!$items) {
        return '';
    }
    $html = '<div class="wrap narrow"><h2 class="sec-title rv">' . lp_e($data['parts_title']) . '</h2><div class="chips">';
    foreach ($items as $i => $item) {
        $html .= '<span class="chip rv" style="--d:' . ($i % 4) . '">' . lp_icon('check') . lp_e($item) . '</span>';
    }
    return $html . '</div></div>';
};
$sections['gift'] = function () use ($data): string {
    return $data['gift'] === '' ? '' : '<div class="wrap narrow"><div class="gift rv"><span class="gift-ic">' . lp_icon('gift') . '</span><p>' . lp_e($data['gift']) . '</p></div></div>';
};
$sections['offer'] = function () use ($priceBox, $countdown, $cta, $slider, $sliderInHero, $customize, $customizeLink, $price, $phone): string {
    $html = '<div class="wrap narrow"><div class="offer rv">';
    if (!$sliderInHero) {
        $html .= $slider();
    }
    $html .= $priceBox() . $countdown();
    $html .= $price === null
        ? '<a class="cta cta-pulse" href="tel:' . lp_e($phone) . '">' . lp_icon('phone') . '<span>কল করুন</span></a>'
        : $cta('big');
    if ($customize) {
        $html .= '<a class="ghost-btn" href="' . lp_e($customizeLink) . '">' . lp_icon('pen') . ' নিজের ডিজাইনে কাস্টমাইজ করুন</a>';
    }
    return $html . '</div></div>';
};
$sections['video'] = function () use ($video, $tpl): string {
    if (!$video || $tpl['hero'] === 'video') {
        return '';
    }
    return '<div class="wrap narrow"><h2 class="sec-title rv">ভিডিওতে দেখুন</h2><div class="video rv"><iframe src="' . lp_e($video) . '" title="Product video" loading="lazy" allow="autoplay; encrypted-media; picture-in-picture" allowfullscreen></iframe></div></div>';
};
$sections['gallery'] = function () use ($customerPhotos, $data): string {
    if (!$customerPhotos) {
        return '';
    }
    $html = '<div class="wrap"><h2 class="sec-title rv">' . lp_e($data['gallery_title']) . '</h2><div class="gallery">';
    foreach ($customerPhotos as $i => $src) {
        $html .= '<button type="button" class="g-item rv" style="--d:' . ($i % 4) . '" data-full="' . lp_e($src) . '"><img src="' . lp_e($src) . '" alt="" loading="lazy"></button>';
    }
    return $html . '</div></div>';
};
$sections['faq'] = function () use ($faq, $data): string {
    if (!$faq) {
        return '';
    }
    $html = '<div class="wrap narrow"><h2 class="sec-title rv">' . lp_e($data['faq_title']) . '</h2><div class="faq">';
    foreach ($faq as $i => [$q, $a]) {
        $html .= '<details class="rv"' . ($i === 0 ? ' open' : '') . '><summary>' . lp_e($q) . lp_icon('chevron') . '</summary><p>' . lp_e($a) . '</p></details>';
    }
    return $html . '</div></div>';
};
$sections['photo'] = function () use ($images, $product): string {
    $src = $images[1] ?? $images[0];
    return '<div class="wrap narrow"><div class="photo-repeat rv"><img src="' . lp_e($src) . '" alt="' . lp_e($product['title']) . '" loading="lazy"></div></div>';
};
$sections['contact'] = function () use ($phone, $waLink, $tpl): string {
    if (!empty($tpl['contactBand'])) {
        return '<a class="contact-band" href="tel:' . lp_e($phone) . '" data-contact>যে কোন প্রয়োজনে যোগাযোগ করুন ' . lp_e(ic_english_to_bangla_digits($phone)) . '</a>';
    }
    return '<div class="wrap narrow"><div class="contact rv"><p class="head">কোনো প্রশ্ন আছে? সরাসরি কথা বলুন</p><div class="contact-btns">'
        . '<a class="c-call" href="tel:' . lp_e($phone) . '" data-contact>' . lp_icon('phone') . ' ' . lp_e(ic_english_to_bangla_digits($phone)) . '</a>'
        . '<a class="c-wa" href="' . lp_e($waLink) . '" target="_blank" rel="noopener" data-contact>' . lp_icon('whatsapp') . ' WhatsApp করুন</a></div></div></div>';
};
$sections['related'] = function () use ($related, $data): string {
    if (!$related || empty($data['show_related'])) {
        return '';
    }
    $html = '<div class="wrap"><h2 class="sec-title rv">আরও দেখুন</h2><div class="related">';
    foreach ($related as $i => $r) {
        $html .= '<a class="rel rv" style="--d:' . ($i % 4) . '" href="' . lp_e(ic_landing_url($r['id'])) . '"><img src="' . lp_e($r['image']) . '" alt="" loading="lazy">'
            . '<span class="rel-t">' . lp_e($r['title']) . '</span><span class="rel-p">৳' . lp_bn($r['price_bdt']) . '</span></a>';
    }
    return $html . '</div></div>';
};
$sections['order'] = function () use ($data, $product, $price, $regular, $offerOnly, $addons, $feeDhaka, $feeOutside, $waLocal, $phone, $waLink, $customize, $customizeLink, $qr, $qrOn): string {
    ob_start(); ?>
  <div class="wrap">
    <h2 class="sec-title rv"><?= lp_e($data['form_title']) ?></h2>
    <?php if ($price === null): ?>
      <div class="contact rv"><p class="head">এই পণ্যের দাম আপনার পছন্দ (সাইজ, ডিজাইন) অনুযায়ী ঠিক হয়।</p><p>অর্ডার বা দাম জানতে কল অথবা WhatsApp করুন।</p>
        <div class="contact-btns"><a class="c-call" href="tel:<?= lp_e($phone) ?>" data-contact><?= lp_icon('phone') ?> কল করুন</a><a class="c-wa" href="<?= lp_e($waLink) ?>" target="_blank" rel="noopener" data-contact><?= lp_icon('whatsapp') ?> WhatsApp করুন</a></div>
        <?php if ($customize): ?><a class="ghost-btn" href="<?= lp_e($customizeLink) ?>"><?= lp_icon('pen') ?> নিজের ডিজাইনে কাস্টমাইজ করুন</a><?php endif; ?>
      </div>
    <?php else: ?>
    <?php if ($data['form_note'] !== ''): ?><p class="form-note rv"><?= lp_e($data['form_note']) ?></p><?php endif; ?>
    <form id="orderForm" class="order-form" novalidate>
      <input type="text" name="_honey" class="honey" tabindex="-1" autocomplete="off" aria-hidden="true">
      <div class="of-col">
        <div class="of-box">
          <h3>আপনার পণ্য</h3>
          <div class="item main-item">
            <img src="<?= lp_e($product['image']) ?>" alt="">
            <div class="item-info">
              <b><?= lp_e($product['title']) ?></b>
              <span class="item-price"><span data-main-price>৳<?= lp_bn($price) ?></span><?php if ($regular): ?> <s class="<?= trim($offerOnly) ?>">৳<?= lp_bn($regular) ?></s><?php endif; ?></span>
            </div>
            <div class="qty" aria-label="পরিমাণ">
              <button type="button" data-qty="-1" aria-label="কমান"><?= lp_icon('minus') ?></button>
              <output id="qty">১</output>
              <button type="button" data-qty="1" aria-label="বাড়ান"><?= lp_icon('plus') ?></button>
            </div>
          </div>
          <?php if ($addons): ?>
            <p class="addon-title"><?= lp_icon('sparkle') ?> সাথে নিতে পারেন <small>(টিক দিলেই অর্ডারে যোগ হবে)</small></p>
            <?php foreach ($addons as $a): ?>
              <label class="item addon">
                <input type="checkbox" name="addon" value="<?= lp_e($a['id']) ?>">
                <span class="tick"><?= lp_icon('check') ?></span>
                <img src="<?= lp_e($a['image']) ?>" alt="" loading="lazy">
                <span class="item-info"><b><?= lp_e($a['title']) ?></b><span class="item-price">+ ৳<?= lp_bn($a['price_bdt']) ?></span></span>
              </label>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
        <div class="of-box">
          <h3>গ্রাহকের তথ্য</h3>
          <p class="remembered" id="remembered" hidden><?= lp_icon('check') ?> আগের অর্ডারের তথ্য বসানো হয়েছে <button type="button" id="forgetMe">মুছে ফেলুন</button></p>
          <label class="field"><span>আপনার নাম <i>*</i></span><input name="name" autocomplete="name" placeholder="যেমন: মোঃ রহিম উদ্দিন" maxlength="120"><em></em></label>
          <label class="field"><span>মোবাইল নম্বর <i>*</i></span><input name="whatsapp" type="tel" inputmode="tel" autocomplete="tel" placeholder="01XXXXXXXXX" maxlength="16"><em></em></label>
          <label class="field"><span>ইমেইল <small class="opt">(ঐচ্ছিক — অর্ডারের কনফার্মেশন পাঠানো হবে)</small></span><input name="email" type="email" inputmode="email" autocomplete="email" placeholder="you@example.com" maxlength="190"><em></em></label>
          <label class="field"><span>সম্পূর্ণ ঠিকানা <i>*</i></span><textarea name="address" rows="2" autocomplete="street-address" placeholder="বাসা/রোড নং, এলাকা, থানা, জেলা" maxlength="500"></textarea><em></em></label>
        </div>
      </div>
      <div class="of-col">
        <div class="of-box">
          <h3>ডেলিভারি এরিয়া</h3>
          <div class="radios" data-group="region">
            <label class="radio"><input type="radio" name="region" value="dhaka"><span class="r-dot"></span><span class="r-text">ঢাকার ভিতরে</span><b data-fee="dhaka"><?= $feeDhaka ? '৳' . lp_bn($feeDhaka) : 'ফ্রি' ?></b></label>
            <label class="radio"><input type="radio" name="region" value="outside"><span class="r-dot"></span><span class="r-text">ঢাকার বাইরে</span><b data-fee="outside"><?= $feeOutside ? '৳' . lp_bn($feeOutside) : 'ফ্রি' ?></b></label>
          </div>
          <em class="group-err" data-err="region"></em>
        </div>
        <div class="of-box">
          <h3>পেমেন্ট পদ্ধতি</h3>
          <div class="radios">
            <label class="radio"><input type="radio" name="payment_method" value="cod" checked><span class="r-dot"></span><span class="r-text">ক্যাশ অন ডেলিভারি<small>পণ্য হাতে পেয়ে টাকা দিন</small></span><?= lp_icon('cash') ?></label>
            <?php if ($data['payment'] === 'both'):
                $bankNames = ['bkash' => 'বিকাশ', 'nagad' => 'নগদ', 'rocket' => 'রকেট', 'banglaqr' => 'বাংলা কিউআর'];
                foreach (array_intersect(explode(',', (string) $data['banking']), array_keys(IC_PAY_CHANNELS)) as $ch):
                    if ($ch === 'banglaqr' && !$qrOn) {
                        continue; // no QR uploaded yet
                    } ?>
              <label class="radio"><input type="radio" name="payment_method" value="<?= $ch ?>" data-bank="<?= $bankNames[$ch] ?>"><span class="r-dot"></span><span class="r-text"><?= $bankNames[$ch] ?><small><?= $ch === 'banglaqr' ? 'যেকোনো ব্যাংক/বিকাশ/নগদ অ্যাপে স্ক্যান' : 'আগে পেমেন্ট করে অর্ডার' ?></small></span><span class="bank bank-<?= $ch ?>"><?= IC_PAY_CHANNELS[$ch] ?></span></label>
            <?php endforeach; endif; ?>
          </div>
          <?php if ($data['payment'] === 'both'): ?>
            <div class="bkash" hidden>
              <p data-pay-number>মোট <b data-total-text>৳০</b> টাকা <b><?= lp_e(ic_english_to_bangla_digits($waLocal)) ?></b> নম্বরে <b data-bank-name>বিকাশ</b> থেকে <b>Send Money</b> করুন, তারপর Transaction ID লিখুন।</p>
              <?php if ($qrOn): ?>
                <div class="qr-pay" data-pay-qr hidden>
                  <p class="qr-tag">BANGLA QR</p>
                  <img src="<?= lp_e($qr['image']) ?>" alt="Bangla QR">
                  <?php if ($qr['merchant'] !== ''): ?><p class="qr-name"><?= lp_e($qr['merchant']) ?></p><?php endif; ?>
                  <p>যেকোনো ব্যাংক / বিকাশ / নগদ অ্যাপ দিয়ে QR স্ক্যান করে মোট <b data-total-text>৳০</b> পাঠান, তারপর Transaction ID লিখুন।</p>
                  <?php if ($qr['note'] !== ''): ?><p class="qr-note"><?= lp_e($qr['note']) ?></p><?php endif; ?>
                  <a class="qr-save" href="<?= lp_e($qr['image']) ?>" download="bangla-qr-inspired-creations"><?= lp_icon('arrow') ?> QR সেভ করুন</a>
                </div>
              <?php endif; ?>
              <label class="field"><span>Transaction ID <i>*</i></span><input name="trx_id" autocomplete="off" placeholder="যেমন: 9A7B6C5D4E" maxlength="40"><em></em></label>
            </div>
          <?php endif; ?>
          <details class="note-box"><summary>অতিরিক্ত কিছু জানাতে চান? (ঐচ্ছিক)</summary><textarea name="notes" rows="2" maxlength="1000" placeholder="যেমন: কোন সময় ডেলিভারি দিলে সুবিধা, উপহারের বার্তা…"></textarea></details>
        </div>
        <div class="of-box summary">
          <h3>আপনার অর্ডার</h3>
          <div class="sum-row"><span>পণ্যের দাম</span><b id="sumSubtotal">৳০</b></div>
          <div class="sum-row"><span>ডেলিভারি চার্জ</span><b id="sumDelivery">এরিয়া সিলেক্ট করুন</b></div>
          <div class="sum-row total"><span>সর্বমোট</span><b id="sumTotal">৳০</b></div>
          <div class="free-bar" id="freeBar" hidden><p></p><div class="fb-track"><i></i></div></div>
        </div>
        <p class="form-error" id="formError" role="alert" hidden></p>
        <button type="submit" class="cta cta-pulse submit" id="submitBtn"><span class="btn-text">অর্ডার কনফার্ম করুন · <b data-total-text>৳০</b></span><span class="spinner" aria-hidden="true"></span></button>
        <p class="secure"><?= lp_icon('lock') ?> আপনার তথ্য সম্পূর্ণ নিরাপদ · অর্ডারের পর আমরা ফোন করে কনফার্ম করবো</p>
      </div>
    </form>
    <?php endif; ?>
  </div>
    <?php
    return (string) ob_get_clean();
};

// Hero
// Quick quantity cards (1 / 2 / 3 pieces) — the order form follows the choice
$tiers = function () use ($tpl, $price, $regular, $ship, $offerOnly): string {
    if (empty($tpl['tiers']) || $price === null) {
        return '';
    }
    $html = '<div class="tiers" aria-label="কয়টি নেবেন?">';
    foreach ([1, 2, 3] as $q) {
        $free = $ship['freeMin'] > 0 && $price * $q >= $ship['freeMin'];
        $html .= '<button type="button" class="tier' . ($q === 1 ? ' on' : '') . '" data-tier-qty="' . $q . '" aria-pressed="' . ($q === 1 ? 'true' : 'false') . '">'
            . '<b>' . lp_bn($q) . ' পিস</b><span class="tier-price">৳' . lp_bn($price * $q) . '</span>'
            . ($regular ? '<s class="' . trim($offerOnly) . '">৳' . lp_bn($regular * $q) . '</s>' : '')
            . '<em class="tier-free"' . ($free ? '' : ' hidden') . '>' . lp_icon('truck') . ' ফ্রি ডেলিভারি</em></button>';
    }
    return $html . '</div>';
};
$floatBadges = function () use ($tpl, $data, $discount, $offerOnly): string {
    if (empty($tpl['floatBadges'])) {
        return '';
    }
    $lines = array_slice(ic_lines($data['badges']), 0, 2);
    $html = '';
    foreach ($lines as $i => $line) {
        $html .= '<span class="float-badge fb' . ($i + 1) . '">' . lp_icon($i ? 'truck' : 'cash') . lp_e($line) . '</span>';
    }
    if ($discount > 0) {
        $html .= '<span class="float-badge fb3' . $offerOnly . '">' . lp_bn($discount) . '% ছাড়</span>';
    }
    return $html;
};
$proof = function () use ($proofCount): string {
    return $proofCount >= 3 ? '<p class="proof"><span class="dot"></span> গত ৭ দিনে <b>' . lp_bn($proofCount) . ' জন</b> এই পেজ থেকে অর্ডার করেছেন</p>' : '';
};
$hero = function () use ($data, $tpl, $heroLayout, $slider, $priceBox, $countdown, $cta, $images, $video, $customize, $customizeLink, $price, $phone, $tiers, $floatBadges, $proof): string {
    $text = '<div class="hero-text">';
    if ($tpl['key'] === 'flash') {
        $text .= '<div class="flash-tag">⚡ ফ্ল্যাশ সেল ⚡</div>';
    }
    if ($data['offer_label'] !== '' && $heroLayout !== 'banner') {
        $text .= '<span class="kicker">' . lp_e($data['offer_label']) . '</span>';
    }
    $text .= '<h1 class="headline">' . lp_e($data['headline']) . '</h1>';
    if ($data['highlight'] !== '') {
        $text .= '<p class="highlight">' . lp_e($data['highlight']) . '</p>';
    }
    if ($data['subheadline'] !== '') {
        $text .= '<p class="sub">' . nl2br(lp_e($data['subheadline'])) . '</p>';
    }
    $actions = $price === null
        ? '<a class="cta cta-pulse" href="tel:' . lp_e($phone) . '">' . lp_icon('phone') . '<span>দাম জানতে কল করুন</span></a>'
        : $cta('hero-cta');
    if ($customize) {
        $actions .= '<a class="ghost-btn" href="' . lp_e($customizeLink) . '">' . lp_icon('pen') . ' কাস্টমাইজ করুন</a>';
    }

    if ($heroLayout === 'split') {
        return '<section class="hero hero-split"><div class="wrap hero-grid"><div class="hero-media">' . $slider() . $floatBadges() . '</div>'
            . $text . $priceBox(false) . $countdown() . $tiers() . '<div class="hero-actions">' . $actions . '</div>' . $proof() . '</div></div></section>';
    }
    if ($heroLayout === 'overlay') {
        return '<section class="hero hero-overlay"><div class="hero-bg"><img src="' . lp_e($images[0]) . '" alt=""></div><div class="wrap">'
            . $text . '<div class="hero-actions">' . $actions . '</div></div></div></section>';
    }
    if ($heroLayout === 'video') {
        return '<section class="hero hero-video"><div class="wrap narrow"><div class="video play-ring"><iframe src="' . lp_e($video) . '" title="Product video" allow="autoplay; encrypted-media; picture-in-picture" allowfullscreen></iframe></div>'
            . $text . '<div class="hero-actions">' . $actions . '</div></div></div></section>';
    }
    // banner: offer label on top, headlines, big photo, then price and button
    $label = $data['offer_label'] !== '' ? '<span class="kicker">' . lp_e($data['offer_label']) . '</span>' : '';
    return '<section class="hero hero-banner"><div class="wrap">' . $label . $text . '</div>'
        . '<div class="hero-media">' . $slider() . '</div>' . $priceBox(false) . $countdown() . '<div class="hero-actions">' . $actions . '</div></div></section>';
};

?><!DOCTYPE html>
<html lang="bn" class="no-js">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= lp_e($product['title']) ?> — INSPIRED CREATIONS</title>
  <meta name="description" content="<?= lp_e(ic_clean($data['headline'] . ' — ' . $product['title'], 160)) ?>">
  <meta property="og:title" content="<?= lp_e($product['title']) ?>">
  <meta property="og:description" content="<?= lp_e($data['headline']) ?>">
  <meta property="og:image" content="<?= lp_e($images[0]) ?>">
  <meta property="og:type" content="product">
  <?php if ($preview): ?><meta name="robots" content="noindex"><?php endif; ?>
  <link rel="icon" type="image/png" href="assets/logo-mark.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="<?= lp_e($fontUrl) ?>" rel="stylesheet">
  <link rel="preload" as="image" href="<?= lp_e($images[0]) ?>">
  <script>document.documentElement.classList.replace('no-js', 'js');</script>
  <style>
    :root {
      --bg: <?= $c['bg'] ?>; --bg2: <?= $c['bg2'] ?>; --card: <?= $c['card'] ?>; --ink: <?= $c['ink'] ?>; --muted: <?= $c['muted'] ?>;
      --brand: <?= $c['brand'] ?>; --accent: <?= $c['accent'] ?>; --cta: <?= $c['cta'] ?>; --cta-ink: <?= $c['ctaInk'] ?>;
      --price: <?= $c['price'] ?>; --line: <?= $c['line'] ?>;
      --head: '<?= $tpl['head'] ?>'; --body: '<?= $tpl['body'] ?>';
      --radius: 18px;
    }
    *, *::before, *::after { box-sizing: border-box; }
    html { scroll-behavior: smooth; -webkit-text-size-adjust: 100%; }
    body { margin: 0; background: var(--bg); color: var(--ink); font-family: var(--body), 'Hind Siliguri', system-ui, sans-serif; font-size: 17px; line-height: 1.7; -webkit-font-smoothing: antialiased; overflow-x: hidden; }
    img { max-width: 100%; display: block; }
    a { color: inherit; text-decoration: none; }
    button { font: inherit; color: inherit; }
    h1, h2, h3, .head { font-family: var(--head), var(--body), serif; line-height: 1.35; margin: 0; font-weight: 700; }
    .ic { width: 1.15em; height: 1.15em; flex-shrink: 0; }
    .wrap { max-width: 1100px; margin: 0 auto; padding: 0 16px; position: relative; }
    .narrow { max-width: 780px; }
    main { position: relative; z-index: 1; }
    .sec { padding: 56px 0; position: relative; }
    .sec.alt { background: var(--bg2); }
    .sec-title { font-size: clamp(23px, 4.4vw, 34px); text-align: center; color: var(--brand); margin-bottom: 28px; }

    /* Top announcement bar + brand */
    .topbar { background: var(--cta); color: var(--cta-ink); overflow: hidden; white-space: nowrap; font-weight: 600; font-size: 15px; position: relative; z-index: 2; }
    .marquee { display: inline-flex; gap: 56px; padding: 9px 0; animation: marquee 32s linear infinite; padding-left: 56px; }
    .marquee span { display: inline-block; }
    @keyframes marquee { to { transform: translateX(-50%); } }
    .brand { display: flex; align-items: center; justify-content: center; gap: 10px; padding: 14px 16px 4px; position: relative; z-index: 2; }
    .brand img { width: 38px; height: 38px; border-radius: 50%; background: #140e0c; padding: 4px; }
    .brand span { font-family: 'Cinzel', var(--head), serif; letter-spacing: .14em; font-weight: 700; font-size: 14px; color: var(--brand); }

    /* Hero */
    .hero { padding: 26px 0 50px; position: relative; overflow: hidden; }
    .hero-text { position: relative; }
    .kicker { display: inline-block; background: var(--accent); color: #fff; padding: 5px 16px; border-radius: 99px; font-weight: 700; font-size: 14px; letter-spacing: .02em; }
    .headline { font-size: clamp(28px, 6vw, 50px); color: var(--brand); margin: 14px 0 6px; }
    .highlight { font-size: clamp(19px, 3.4vw, 25px); color: var(--accent); font-weight: 700; margin: 0; font-family: var(--head), var(--body), serif; }
    .sub { color: var(--muted); margin: 12px 0 0; }
    .hero-banner { text-align: center; }
    .hero-banner .sub { max-width: 700px; margin-left: auto; margin-right: auto; }
    .hero-banner .hero-media { max-width: 860px; margin: 28px auto 0; }
    .hero-actions { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; margin-top: 22px; }
    .hero-banner .hero-actions, .hero-overlay .hero-actions, .hero-video .hero-actions { justify-content: center; }
    .hero-grid { display: grid; gap: 28px; align-items: center; }
    @media (min-width: 880px) {
      .hero-grid { grid-template-columns: 1.05fr 1fr; gap: 48px; }
      .hero-split .hero-media { grid-row: span 4; }
    }
    .hero-split .price-box { margin: 20px 0 0; }
    .hero-overlay { min-height: min(88vh, 760px); display: flex; align-items: flex-end; text-align: center; padding: 0 0 56px; color: #fff; }
    .hero-overlay .wrap { width: 100%; }
    .hero-bg { position: absolute; inset: 0; overflow: hidden; }
    .hero-bg img { width: 100%; height: 100%; object-fit: cover; animation: kenburns 18s ease-in-out infinite alternate; }
    .hero-bg::after { content: ''; position: absolute; inset: 0; background: linear-gradient(180deg, rgba(0,0,0,.05) 20%, rgba(0,0,0,.78) 100%); }
    .hero-overlay .headline { color: #fff; text-shadow: 0 2px 18px rgba(0,0,0,.45); }
    .hero-overlay .highlight { color: var(--accent); filter: brightness(1.25); }
    .hero-overlay .sub { color: rgba(255,255,255,.88); max-width: 680px; margin-left: auto; margin-right: auto; }
    .hero-video { text-align: center; }
    .hero-video .video { margin-bottom: 26px; }

    /* Photo slider */
    .slider { position: relative; border-radius: var(--radius); overflow: hidden; background: var(--card); box-shadow: 0 24px 60px -24px rgba(0,0,0,.35); }
    .slides { position: relative; aspect-ratio: 1 / 1; }
    @media (min-width: 640px) { .slides { aspect-ratio: 4 / 3; } }
    .slide { position: absolute; inset: 0; opacity: 0; transition: opacity .7s ease; }
    .slide.on { opacity: 1; }
    .slide img { width: 100%; height: 100%; object-fit: cover; }
    .off-badge { position: absolute; top: 14px; left: 14px; background: var(--price); color: #fff; font-weight: 800; padding: 6px 14px; border-radius: 99px; font-size: 16px; box-shadow: 0 6px 16px rgba(0,0,0,.2); animation: wobble 3s ease-in-out infinite; }
    .nav { position: absolute; top: 50%; transform: translateY(-50%); width: 42px; height: 42px; border-radius: 50%; border: 0; background: rgba(255,255,255,.88); color: #222; display: grid; place-items: center; cursor: pointer; box-shadow: 0 4px 12px rgba(0,0,0,.18); }
    .nav.prev { left: 10px; } .nav.next { right: 10px; }
    .dots { position: absolute; bottom: 12px; left: 0; right: 0; display: flex; justify-content: center; gap: 7px; }
    .dots button { width: 9px; height: 9px; border-radius: 99px; border: 0; padding: 0; background: rgba(255,255,255,.6); cursor: pointer; transition: width .3s; }
    .dots button.on { width: 26px; background: #fff; }
    .img-float .slides { animation: float 6s ease-in-out infinite; }
    .img-kenburns .slide.on img { animation: kenburns 9s ease-out forwards; }
    .img-bounce { animation: bounceIn 1s cubic-bezier(.2,1.4,.4,1) both; }
    .img-zoom .slides { transform: scale(var(--z, 1)); transition: transform .1s linear; }
    .img-tilt { transition: transform .25s ease; transform-style: preserve-3d; will-change: transform; }
    .ribbon { position: absolute; z-index: 2; top: 18px; right: -40px; transform: rotate(45deg); background: var(--accent); color: #fff; padding: 4px 48px; font-weight: 700; font-size: 14px; box-shadow: 0 4px 10px rgba(0,0,0,.2); }
    @keyframes float { 50% { transform: translateY(-10px); } }
    @keyframes kenburns { from { transform: scale(1); } to { transform: scale(1.12); } }
    @keyframes bounceIn { 0% { opacity: 0; transform: scale(.7); } 100% { opacity: 1; transform: scale(1); } }
    @keyframes wobble { 0%, 100% { transform: rotate(0); } 10% { transform: rotate(-8deg) scale(1.08); } 20% { transform: rotate(6deg); } 30% { transform: rotate(0); } }

    /* Price box + countdown */
    .price-box { margin: 26px auto 0; max-width: 460px; border: 2px dashed var(--price); border-radius: var(--radius); padding: 16px 20px; background: var(--card); text-align: center; position: relative; }
    .pb-label { display: inline-block; font-weight: 700; color: var(--accent); margin-bottom: 4px; }
    .pb-regular { color: var(--muted); font-size: 17px; }
    .pb-regular s { color: var(--muted); }
    .pb-offer { font-size: 20px; font-weight: 600; color: var(--ink); }
    .pb-offer strong { display: inline-block; font-size: clamp(34px, 7vw, 46px); color: var(--price); line-height: 1.15; font-family: var(--head), var(--body), sans-serif; }
    .pb-save { display: inline-block; margin-top: 6px; background: var(--price); color: #fff; padding: 3px 14px; border-radius: 99px; font-size: 15px; font-weight: 600; }
    .pb-ended { margin-top: 6px; font-size: 15px; font-weight: 600; color: var(--muted); }
    .offer-ended .offer-only { display: none !important; }
    .pb-stock { margin-top: 8px; font-size: 15px; color: var(--accent); font-weight: 600; display: flex; align-items: center; justify-content: center; gap: 8px; }
    .dot { width: 9px; height: 9px; border-radius: 50%; background: var(--accent); box-shadow: 0 0 0 0 var(--accent); animation: ping 1.6s infinite; }
    @keyframes ping { 70% { box-shadow: 0 0 0 10px transparent; } 100% { box-shadow: 0 0 0 0 transparent; } }
    .countdown { margin: 18px auto 0; text-align: center; }
    .countdown p { margin: 0 0 8px; font-weight: 700; color: var(--accent); display: flex; align-items: center; justify-content: center; gap: 6px; }
    .cd-boxes { display: flex; justify-content: center; gap: 10px; }
    .cd-boxes div { background: var(--brand); color: var(--bg); min-width: 68px; border-radius: 14px; padding: 8px 6px; }
    .cd-boxes b { display: block; font-size: 28px; line-height: 1.1; font-family: var(--head), var(--body), sans-serif; }
    .cd-boxes span { font-size: 13px; opacity: .85; }
    .hero-split .countdown, .hero-split .countdown p, .hero-split .cd-boxes { justify-content: flex-start; text-align: left; margin-left: 0; }

    /* Buttons */
    .cta { position: relative; overflow: hidden; display: inline-flex; align-items: center; justify-content: center; gap: 10px; background: var(--cta); color: var(--cta-ink); font-weight: 700; font-size: 19px; padding: 15px 34px; border-radius: 99px; border: 0; cursor: pointer; box-shadow: 0 12px 28px -10px var(--cta); transition: transform .2s, filter .2s; font-family: var(--body), sans-serif; }
    .cta:hover { transform: translateY(-2px); filter: brightness(1.08); }
    .cta.big { font-size: 21px; padding: 17px 42px; margin-top: 20px; }
    .cta-pulse { animation: pulse 2s infinite; }
    @keyframes pulse { 0% { box-shadow: 0 0 0 0 color-mix(in srgb, var(--cta) 55%, transparent); } 70% { box-shadow: 0 0 0 18px transparent; } 100% { box-shadow: 0 0 0 0 transparent; } }
    .cta-shine::after { content: ''; position: absolute; top: 0; left: -60%; width: 40%; height: 100%; background: linear-gradient(105deg, transparent, rgba(255,255,255,.55), transparent); transform: skewX(-20deg); animation: shine 3.2s ease-in-out infinite; }
    @keyframes shine { 0% { left: -60%; } 45%, 100% { left: 130%; } }
    .cta-shake { animation: shake 3.5s ease-in-out infinite; }
    @keyframes shake { 0%, 84%, 100% { transform: translateX(0) rotate(0); } 86% { transform: translateX(-5px) rotate(-2deg); } 88% { transform: translateX(5px) rotate(2deg); } 90% { transform: translateX(-5px) rotate(-1deg); } 92% { transform: translateX(5px) rotate(1deg); } 94% { transform: translateX(0); } }
    .cta-bounce { animation: hop 2.4s ease-in-out infinite; }
    @keyframes hop { 0%, 60%, 100% { transform: translateY(0); } 70% { transform: translateY(-9px); } 80% { transform: translateY(0); } 88% { transform: translateY(-4px); } }
    .cta-glow { background: linear-gradient(90deg, var(--cta), var(--accent), var(--cta)); background-size: 200% 100%; animation: glow 3s linear infinite; }
    @keyframes glow { 0% { background-position: 0 0; box-shadow: 0 0 18px -2px var(--cta); } 50% { box-shadow: 0 0 32px 2px var(--accent); } 100% { background-position: 200% 0; box-shadow: 0 0 18px -2px var(--cta); } }
    .cta .ic { transition: transform .2s; }
    .cta:hover .ic { transform: translateX(4px); }
    .ghost-btn { display: inline-flex; align-items: center; gap: 8px; padding: 12px 22px; border-radius: 99px; border: 2px solid var(--brand); color: var(--brand); font-weight: 600; font-size: 16px; margin-top: 14px; }
    .hero-actions .ghost-btn { margin-top: 0; }
    .hero-overlay .ghost-btn { border-color: #fff; color: #fff; }

    /* Content blocks */
    .badges { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }
    @media (min-width: 800px) { .badges { grid-template-columns: repeat(4, 1fr); } }
    .badge { display: flex; align-items: center; gap: 10px; background: var(--card); border: 1px solid var(--line); border-radius: 14px; padding: 12px 14px; font-size: 15px; line-height: 1.4; }
    .badge > span { width: 40px; height: 40px; border-radius: 12px; background: color-mix(in srgb, var(--brand) 12%, transparent); color: var(--brand); display: grid; place-items: center; flex-shrink: 0; }
    .badge b { font-weight: 600; }
    .intro { font-size: 19px; text-align: center; color: var(--ink); padding: 22px 24px; border-left: 4px solid var(--accent); border-right: 4px solid var(--accent); border-radius: 12px; background: var(--card); }
    .checks { list-style: none; padding: 0; margin: 0 auto; max-width: 760px; display: grid; gap: 12px; }
    .checks li { display: flex; gap: 12px; align-items: flex-start; background: var(--card); border: 1px solid var(--line); border-radius: 14px; padding: 13px 16px; font-size: 18px; }
    .checks .ic { width: 26px; height: 26px; padding: 4px; border-radius: 50%; background: var(--brand); color: var(--bg); stroke-width: 3; margin-top: 2px; }
    .cards { display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); }
    .card { background: var(--card); border: 1px solid var(--line); border-radius: var(--radius); padding: 22px; text-align: center; box-shadow: 0 10px 30px -18px rgba(0,0,0,.25); transition: transform .3s; }
    .card:hover { transform: translateY(-5px); }
    .card p { margin: 10px 0 0; font-size: 17px; }
    .card-ic { width: 56px; height: 56px; border-radius: 18px; margin: 0 auto; display: grid; place-items: center; background: linear-gradient(135deg, var(--brand), var(--accent)); color: #fff; }
    .card-ic .ic { width: 26px; height: 26px; }
    .numbered { list-style: none; margin: 0 auto; padding: 0; max-width: 780px; display: grid; gap: 4px; }
    .numbered li { display: flex; gap: 18px; align-items: center; padding: 16px 4px; border-bottom: 1px solid var(--line); }
    .numbered .num { font-family: var(--head), serif; font-size: 40px; line-height: 1; color: transparent; -webkit-text-stroke: 1.5px var(--brand); min-width: 64px; }
    .numbered p { margin: 0; font-size: 18px; }
    .alt-rows { display: grid; gap: 36px; }
    .alt-row { display: grid; gap: 18px; align-items: center; }
    @media (min-width: 760px) { .alt-row { grid-template-columns: 1fr 1fr; gap: 44px; } .alt-row.flip .alt-img { order: 2; } }
    .alt-img img { border-radius: var(--radius); aspect-ratio: 4/3; object-fit: cover; width: 100%; box-shadow: 0 20px 40px -24px rgba(0,0,0,.4); }
    .alt-text .num { display: inline-grid; place-items: center; width: 44px; height: 44px; border-radius: 50%; background: var(--accent); color: #fff; font-weight: 700; font-size: 20px; }
    .alt-text p { font-size: 21px; margin: 12px 0 0; font-family: var(--head), serif; }
    .chips { display: flex; flex-wrap: wrap; justify-content: center; gap: 10px; }
    .chip { display: inline-flex; align-items: center; gap: 8px; background: var(--card); border: 2px solid var(--brand); color: var(--brand); border-radius: 99px; padding: 8px 18px; font-weight: 600; }
    .gift { display: flex; align-items: center; gap: 16px; background: linear-gradient(135deg, var(--accent), var(--brand)); color: #fff; padding: 18px 22px; border-radius: var(--radius); font-size: 19px; font-weight: 700; box-shadow: 0 16px 34px -18px var(--accent); }
    .gift p { margin: 0; }
    .gift-ic { width: 54px; height: 54px; border-radius: 50%; background: rgba(255,255,255,.2); display: grid; place-items: center; flex-shrink: 0; animation: wobble 2.6s ease-in-out infinite; }
    .gift-ic .ic { width: 28px; height: 28px; }
    .offer { text-align: center; }
    .offer .slider { margin-bottom: 6px; }
    .video { position: relative; aspect-ratio: 16 / 9; border-radius: var(--radius); overflow: hidden; background: #000; box-shadow: 0 20px 50px -24px rgba(0,0,0,.5); }
    .video iframe { position: absolute; inset: 0; width: 100%; height: 100%; border: 0; }
    .gallery { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }
    @media (min-width: 760px) { .gallery { grid-template-columns: repeat(4, 1fr); } }
    .g-item { padding: 0; border: 0; border-radius: 14px; overflow: hidden; cursor: zoom-in; background: var(--card); }
    .g-item img { width: 100%; aspect-ratio: 1; object-fit: cover; transition: transform .4s; }
    .g-item:hover img { transform: scale(1.06); }
    .faq { display: grid; gap: 10px; }
    .faq details { background: var(--card); border: 1px solid var(--line); border-radius: 14px; padding: 0 18px; }
    .faq summary { list-style: none; cursor: pointer; padding: 15px 0; font-weight: 700; display: flex; justify-content: space-between; gap: 12px; align-items: center; }
    .faq summary::-webkit-details-marker { display: none; }
    .faq summary .ic { transition: transform .3s; color: var(--brand); }
    .faq details[open] summary .ic { transform: rotate(180deg); }
    .faq details p { margin: 0 0 16px; color: var(--muted); }
    .contact { text-align: center; background: var(--card); border: 1px solid var(--line); border-radius: var(--radius); padding: 24px 18px; }
    .contact .head { font-size: 21px; color: var(--brand); }
    .contact-btns { display: flex; flex-wrap: wrap; gap: 12px; justify-content: center; margin-top: 14px; }
    .contact-btns a { display: inline-flex; align-items: center; gap: 8px; padding: 12px 24px; border-radius: 99px; font-weight: 700; color: #fff; }
    .c-call { background: var(--brand); } .c-wa { background: #1fa855; }
    .related { display: grid; grid-template-columns: repeat(2, 1fr); gap: 14px; }
    @media (min-width: 760px) { .related { grid-template-columns: repeat(3, 1fr); } }
    .rel { background: var(--card); border: 1px solid var(--line); border-radius: 16px; overflow: hidden; display: flex; flex-direction: column; transition: transform .3s, box-shadow .3s; }
    .rel:hover { transform: translateY(-4px); box-shadow: 0 16px 30px -18px rgba(0,0,0,.35); }
    .rel img { aspect-ratio: 1; object-fit: cover; width: 100%; }
    .rel-t { padding: 10px 12px 0; font-weight: 600; font-size: 15px; line-height: 1.4; }
    .rel-p { padding: 2px 12px 12px; color: var(--price); font-weight: 700; }

    /* Order form */
    .form-note { text-align: center; color: var(--muted); max-width: 640px; margin: -14px auto 24px; }
    .order-form { display: grid; gap: 16px; max-width: 1000px; margin: 0 auto; }
    @media (min-width: 880px) { .order-form { grid-template-columns: 1fr 1fr; align-items: start; } }
    .of-col { display: grid; gap: 16px; }
    .of-box { background: var(--card); border: 1px solid var(--line); border-radius: var(--radius); padding: 18px; box-shadow: 0 10px 30px -22px rgba(0,0,0,.35); }
    .of-box h3 { font-size: 18px; margin-bottom: 12px; color: var(--brand); }
    .item { display: flex; align-items: center; gap: 12px; }
    .item img { width: 64px; height: 64px; border-radius: 12px; object-fit: cover; flex-shrink: 0; }
    .item-info { flex: 1; min-width: 0; display: flex; flex-direction: column; line-height: 1.35; }
    .item-info b { font-size: 15px; font-weight: 600; }
    .item-price { color: var(--price); font-weight: 700; }
    .item-price s { color: var(--muted); font-weight: 400; font-size: 14px; }
    .qty { display: flex; align-items: center; border: 1px solid var(--line); border-radius: 99px; overflow: hidden; }
    .qty button { width: 38px; height: 38px; border: 0; background: transparent; cursor: pointer; display: grid; place-items: center; }
    .qty button:hover { background: var(--bg2); }
    .qty output { min-width: 28px; text-align: center; font-weight: 700; }
    .addon-title { margin: 16px 0 8px; font-weight: 700; display: flex; align-items: center; gap: 6px; color: var(--accent); font-size: 15px; flex-wrap: wrap; }
    .addon-title small { color: var(--muted); font-weight: 400; }
    .addon { padding: 8px; border: 1px dashed var(--line); border-radius: 14px; margin-top: 8px; cursor: pointer; transition: border-color .2s, background .2s; }
    .addon img { width: 48px; height: 48px; }
    .addon input { position: absolute; opacity: 0; pointer-events: none; }
    .tick { width: 24px; height: 24px; border-radius: 7px; border: 2px solid var(--line); display: grid; place-items: center; color: transparent; flex-shrink: 0; transition: all .2s; }
    .addon input:checked ~ .tick { background: var(--cta); border-color: var(--cta); color: var(--cta-ink); }
    .addon:has(input:checked) { border-style: solid; border-color: var(--cta); background: color-mix(in srgb, var(--cta) 7%, transparent); }
    .addon input:focus-visible ~ .tick { outline: 3px solid var(--accent); }
    .field { display: block; margin-top: 12px; }
    .field:first-of-type { margin-top: 0; }
    .field > span { display: block; font-weight: 600; font-size: 15px; margin-bottom: 5px; }
    .field i { color: var(--price); font-style: normal; }
    .field .opt { font-weight: 400; color: var(--muted); font-size: 13px; }
    .field input, .field textarea, .note-box textarea { width: 100%; font: inherit; font-size: 17px; color: #1f1f1f; background: #fff; border: 1.5px solid #d6d3d1; border-radius: 12px; padding: 12px 14px; outline: none; transition: border-color .2s, box-shadow .2s; }
    .field input:focus, .field textarea:focus, .note-box textarea:focus { border-color: var(--cta); box-shadow: 0 0 0 4px color-mix(in srgb, var(--cta) 18%, transparent); }
    .field.bad input, .field.bad textarea { border-color: #dc2626; animation: nudge .35s; }
    .field em, .group-err { display: block; color: #dc2626; font-style: normal; font-size: 14px; min-height: 0; }
    @keyframes nudge { 25% { transform: translateX(-4px); } 75% { transform: translateX(4px); } }
    .radios { display: grid; gap: 10px; }
    .radio { display: flex; align-items: center; gap: 12px; border: 1.5px solid var(--line); border-radius: 14px; padding: 12px 14px; cursor: pointer; transition: border-color .2s, background .2s; position: relative; }
    .radio input { position: absolute; opacity: 0; pointer-events: none; }
    .r-dot { width: 22px; height: 22px; border-radius: 50%; border: 2px solid #a8a29e; flex-shrink: 0; display: grid; place-items: center; }
    .r-dot::after { content: ''; width: 10px; height: 10px; border-radius: 50%; background: var(--cta); transform: scale(0); transition: transform .2s; }
    .radio:has(input:checked) { border-color: var(--cta); background: color-mix(in srgb, var(--cta) 8%, transparent); }
    .radio input:checked + .r-dot { border-color: var(--cta); }
    .radio input:checked + .r-dot::after { transform: scale(1); }
    .radio input:focus-visible + .r-dot { outline: 3px solid var(--accent); outline-offset: 2px; }
    .r-text { flex: 1; font-weight: 600; line-height: 1.35; }
    .r-text small { display: block; font-weight: 400; color: var(--muted); font-size: 13px; }
    .radio > b { color: var(--price); }
    .radio > .ic { color: var(--brand); width: 24px; height: 24px; }
    .free-bar { margin-top: 12px; background: color-mix(in srgb, var(--cta) 8%, #fff); border: 1px dashed color-mix(in srgb, var(--cta) 45%, transparent); border-radius: 12px; padding: 10px 12px; }
    .free-bar p { margin: 0 0 8px; font-size: 14px; color: var(--ink); }
    .fb-track { height: 8px; border-radius: 99px; background: color-mix(in srgb, var(--cta) 15%, #fff); overflow: hidden; }
    .fb-track i { display: block; height: 100%; width: 0; border-radius: 99px; background: linear-gradient(90deg, var(--cta), var(--accent)); transition: width .6s cubic-bezier(.2,.8,.2,1); }
    .free-bar.done { border-style: solid; }
    .remembered { margin: -4px 0 10px; font-size: 13px; color: #15803d; display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
    .remembered button { border: 0; background: none; color: var(--muted); text-decoration: underline; cursor: pointer; font-size: 13px; padding: 0; }
    .tiers { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-top: 18px; }
    .tier { position: relative; border: 2px solid var(--line); background: var(--card); border-radius: 16px; padding: 12px 6px 10px; cursor: pointer; display: flex; flex-direction: column; align-items: center; gap: 1px; transition: transform .2s, border-color .2s, box-shadow .2s; font-family: var(--body), sans-serif; }
    .tier b { font-size: 16px; color: var(--ink); }
    .tier .tier-price { font-size: 19px; font-weight: 800; color: var(--price); font-family: var(--head), var(--body), sans-serif; }
    .tier s { font-size: 12px; color: var(--muted); }
    .tier-free { font-style: normal; font-size: 11px; font-weight: 700; color: #15803d; display: inline-flex; gap: 3px; align-items: center; margin-top: 3px; }
    .tier-free[hidden] { display: none; }
    .tier-free .ic { width: 13px; height: 13px; }
    .tier:hover { transform: translateY(-3px); }
    .tier.on { border-color: var(--cta); box-shadow: 0 12px 26px -14px var(--cta); background: color-mix(in srgb, var(--cta) 6%, var(--card)); }
    .tier.on::after { content: '✓'; position: absolute; top: -9px; right: -7px; width: 24px; height: 24px; border-radius: 50%; background: var(--cta); color: var(--cta-ink); font-size: 13px; font-weight: 800; display: grid; place-items: center; animation: bounceIn .4s both; }
    .proof { display: flex; align-items: center; gap: 8px; margin: 14px 0 0; font-size: 15px; color: var(--muted); }
    .proof b { color: var(--ink); }
    .float-badge { position: absolute; z-index: 3; display: inline-flex; align-items: center; gap: 6px; background: rgba(255,255,255,.95); color: var(--ink); font-weight: 700; font-size: 13px; padding: 7px 12px; border-radius: 99px; box-shadow: 0 12px 28px -12px rgba(0,0,0,.35); animation: bob 4s ease-in-out infinite; white-space: nowrap; }
    .float-badge .ic { color: var(--cta); width: 16px; height: 16px; }
    .fb1 { left: -6px; bottom: 18%; } .fb2 { right: -6px; bottom: 6%; animation-delay: -1.5s; } .fb3 { right: 10px; top: 14px; background: var(--price); color: #fff; font-size: 15px; animation: wobble 3s ease-in-out infinite; }
    @keyframes bob { 50% { transform: translateY(-8px); } }
    .scroll-progress { position: fixed; top: 0; left: 0; right: 0; height: 4px; z-index: 70; pointer-events: none; }
    .scroll-progress i { display: block; height: 100%; background: linear-gradient(90deg, var(--cta), var(--accent)); transform-origin: left; transform: scaleX(0); }
    .leave { position: fixed; inset: 0; z-index: 62; background: rgba(10, 10, 10, .62); display: grid; place-items: center; padding: 14px; backdrop-filter: blur(3px); }
    .leave[hidden] { display: none; }
    .leave-card { position: relative; width: 100%; max-width: 430px; max-height: 94vh; overflow: auto; background: #fff; color: #1f2937; border-radius: 22px; padding: 20px 18px; animation: bounceIn .5s cubic-bezier(.2,1.4,.4,1) both; border-top: 6px solid var(--cta); }
    .leave-x { position: absolute; top: 10px; right: 12px; width: 32px; height: 32px; border-radius: 50%; border: 0; background: #f3f4f6; cursor: pointer; font-size: 15px; }
    .leave-top { display: flex; gap: 12px; align-items: center; padding-right: 28px; }
    .leave-top img { width: 78px; height: 78px; border-radius: 14px; object-fit: cover; flex-shrink: 0; }
    .leave-kicker { margin: 0; color: var(--price); font-weight: 800; font-size: 15px; }
    .leave-top h3 { font-size: 18px; color: #111; font-family: var(--body), sans-serif; }
    .leave-price { margin: 2px 0 0; } .leave-price b { color: var(--price); font-size: 20px; } .leave-price s { color: #9ca3af; }
    .leave-points { list-style: none; padding: 0; margin: 12px 0; display: grid; gap: 6px; font-size: 14px; }
    .leave-points li { display: flex; gap: 8px; align-items: flex-start; } .leave-points .ic { color: #16a34a; margin-top: 3px; }
    #leaveForm { display: grid; gap: 8px; }
    #leaveForm input:not([type=radio]) { font: inherit; font-size: 16px; border: 1.5px solid #d6d3d1; border-radius: 12px; padding: 10px 12px; outline: none; }
    #leaveForm input:focus { border-color: var(--cta); }
    .leave-region { display: flex; gap: 14px; font-size: 15px; } .leave-region label { display: flex; gap: 6px; align-items: center; cursor: pointer; }
    .leave-err { margin: 0; color: #dc2626; font-size: 14px; font-weight: 600; }
    #leaveForm .cta { width: 100%; font-size: 17px; padding: 13px 16px; }
    .leave-alt { display: flex; gap: 10px; margin-top: 10px; }
    .leave-alt a { flex: 1; display: inline-flex; justify-content: center; align-items: center; gap: 6px; border-radius: 12px; padding: 9px; font-weight: 700; font-size: 14px; border: 1.5px solid #e5e7eb; }
    .bank { font: 700 12px/1 system-ui, sans-serif; color: #fff; padding: 5px 8px; border-radius: 6px; letter-spacing: .02em; }
    .bank-bkash { background: #e2136e; } .bank-nagad { background: #f6921e; } .bank-rocket { background: #8c3494; } .bank-banglaqr { background: #00843d; }
    .qr-pay { text-align: center; background: #fff; border: 2px solid #00843d; border-radius: 14px; padding: 12px; margin-bottom: 10px; color: #1f2937; }
    .qr-pay img { width: 100%; max-width: 230px; margin: 4px auto 6px; border-radius: 8px; }
    .qr-pay .qr-tag { margin: 0; font: 800 12px/1 system-ui, sans-serif; letter-spacing: .18em; color: #00843d; }
    .qr-pay .qr-name { margin: 0; font-weight: 700; }
    .qr-pay .qr-note { color: #6b7280; font-size: 14px; }
    .qr-save { display: inline-flex; align-items: center; gap: 6px; margin-top: 4px; padding: 7px 14px; border-radius: 99px; background: #00843d; color: #fff; font-weight: 700; font-size: 14px; }
    .qr-save .ic { transform: rotate(90deg); width: 16px; height: 16px; }
    .intro-lead { text-align: center; color: var(--accent); font-weight: 600; max-width: 760px; margin: -8px auto 18px; }
    .photo-repeat img { width: 100%; border-radius: var(--radius); box-shadow: 0 20px 50px -26px rgba(0,0,0,.45); }
    .contact-band { display: block; text-align: center; background: var(--brand); color: #fff; font-weight: 700; font-size: clamp(18px, 3.6vw, 26px); padding: 12px 16px; }
    .bkash { margin-top: 12px; background: #fdf2f8; border: 1px solid #fbcfe8; color: #4a1030; border-radius: 12px; padding: 12px; font-size: 15px; }
    .bkash p { margin: 0 0 8px; }
    .note-box { margin-top: 12px; }
    .note-box summary { cursor: pointer; color: var(--muted); font-size: 15px; }
    .note-box textarea { margin-top: 8px; }
    .summary .sum-row { display: flex; justify-content: space-between; padding: 6px 0; color: var(--muted); }
    .summary .sum-row b { color: var(--ink); }
    .summary .total { border-top: 2px dashed var(--line); margin-top: 6px; padding-top: 12px; font-size: 20px; color: var(--ink); font-weight: 700; }
    .summary .total b { color: var(--price); font-size: 24px; }
    .form-error { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; border-radius: 12px; padding: 10px 14px; margin: 0; font-weight: 600; }
    .submit { width: 100%; font-size: 20px; padding: 17px 20px; }
    .submit .spinner { display: none; width: 22px; height: 22px; border: 3px solid currentColor; border-right-color: transparent; border-radius: 50%; animation: spin .7s linear infinite; }
    .submit.busy .spinner { display: block; } .submit.busy .btn-text { opacity: .7; }
    .submit.busy { pointer-events: none; animation: none; }
    @keyframes spin { to { transform: rotate(360deg); } }
    .secure { text-align: center; color: var(--muted); font-size: 14px; display: flex; gap: 6px; justify-content: center; align-items: center; margin: 0; }
    .honey { position: absolute; left: -9999px; width: 1px; height: 1px; opacity: 0; }

    /* Footer, sticky bar, floating buttons, success, lightbox */
    footer { text-align: center; padding: 26px 16px 110px; color: var(--muted); font-size: 14px; position: relative; z-index: 1; }
    footer a { text-decoration: underline; }
    .sticky { position: fixed; left: 0; right: 0; bottom: 0; z-index: 40; display: flex; align-items: center; gap: 12px; padding: 10px 14px calc(10px + env(safe-area-inset-bottom)); background: var(--card); border-top: 1px solid var(--line); box-shadow: 0 -10px 30px -12px rgba(0,0,0,.3); transform: translateY(110%); transition: transform .35s ease; }
    .sticky.show { transform: none; }
    .sticky .st-price { line-height: 1.2; }
    .sticky .st-price small { display: block; color: var(--muted); font-size: 12px; }
    .sticky .st-price b { color: var(--price); font-size: 20px; }
    .sticky .cta { flex: 1; padding: 12px 16px; font-size: 17px; }
    @media (min-width: 880px) { .sticky { left: auto; right: 24px; bottom: 24px; border-radius: 99px; border: 1px solid var(--line); padding: 8px 8px 8px 22px; } .sticky .cta { flex: none; } }
    .float-btns { position: fixed; right: 14px; bottom: 96px; z-index: 39; display: grid; gap: 10px; }
    .float-btns a { width: 52px; height: 52px; border-radius: 50%; display: grid; place-items: center; color: #fff; box-shadow: 0 10px 22px -8px rgba(0,0,0,.45); }
    .float-btns .ic { width: 26px; height: 26px; }
    .float-btns .f-wa { background: #1fa855; animation: hop 3s ease-in-out infinite; }
    .float-btns .f-call { background: var(--brand); }
    @media (min-width: 880px) { .float-btns { bottom: 110px; right: 28px; } }
    .done { position: fixed; inset: 0; z-index: 60; background: rgba(15, 12, 10, .6); display: grid; place-items: center; padding: 16px; backdrop-filter: blur(4px); }
    .done[hidden] { display: none; }
    .done-card { background: #fff; color: #1f2937; border-radius: 24px; padding: 30px 22px; max-width: 440px; width: 100%; text-align: center; animation: bounceIn .6s cubic-bezier(.2,1.4,.4,1) both; max-height: 92vh; overflow: auto; }
    .done-card h3 { font-size: 24px; color: #15803d; margin: 10px 0 6px; font-family: var(--body), sans-serif; }
    .done-card p { margin: 6px 0; color: #4b5563; }
    .done-code { display: inline-block; margin: 6px 0; padding: 8px 16px; border-radius: 12px; background: #f3f4f6; font-weight: 700; font-size: 20px; letter-spacing: .04em; color: #111; }
    .done-card .contact-btns a { font-size: 16px; }
    .done-card .back { display: inline-block; margin-top: 14px; color: #6b7280; text-decoration: underline; font-size: 15px; }
    .check-anim { width: 84px; height: 84px; margin: 0 auto; }
    .check-anim circle { stroke: #22c55e; stroke-width: 3; fill: #dcfce7; stroke-dasharray: 240; stroke-dashoffset: 240; animation: draw .6s ease forwards; }
    .check-anim path { stroke: #16a34a; stroke-width: 5; fill: none; stroke-linecap: round; stroke-linejoin: round; stroke-dasharray: 60; stroke-dashoffset: 60; animation: draw .4s .45s ease forwards; }
    @keyframes draw { to { stroke-dashoffset: 0; } }
    .lightbox { position: fixed; inset: 0; z-index: 70; background: rgba(0,0,0,.88); display: grid; place-items: center; padding: 16px; cursor: zoom-out; }
    .lightbox[hidden] { display: none; }
    .lightbox img { max-height: 90vh; border-radius: 12px; }
    .preview-tag { position: fixed; top: 10px; left: 10px; z-index: 80; background: #111; color: #fde68a; font: 600 12px/1 system-ui, sans-serif; padding: 7px 10px; border-radius: 8px; letter-spacing: .08em; }
    .burst { position: fixed; top: -10px; width: 9px; height: 14px; z-index: 65; pointer-events: none; animation: burst 2.6s ease-in forwards; }
    @keyframes burst { to { transform: translateY(105vh) rotate(720deg); opacity: .2; } }

    /* Scroll reveal (only when JavaScript runs; off for people who prefer less motion) */
    .js .rv { opacity: 0; transition: opacity .8s ease, transform .8s cubic-bezier(.2,.7,.2,1), filter .8s ease; transition-delay: calc(var(--d, 0) * 90ms); }
    .js .reveal-up .rv { transform: translateY(34px); }
    .js .reveal-zoom .rv { transform: scale(.88); }
    .js .reveal-side .rv { transform: translateX(-46px); }
    .js .reveal-side .rv.from-right { transform: translateX(46px); }
    .js .reveal-blur .rv { filter: blur(12px); transform: translateY(16px); }
    .js .reveal-flip .rv { transform: perspective(700px) rotateX(28deg) translateY(24px); transform-origin: top; }
    .js .rv.in { opacity: 1; transform: none; filter: none; }

    /* Floating particles */
    .fx { position: fixed; inset: 0; pointer-events: none; z-index: 30; overflow: hidden; }
    .fx i { position: absolute; display: block; }
    .fx-sparkle i { bottom: -20px; width: var(--s); height: var(--s); background: var(--accent); clip-path: polygon(50% 0, 61% 39%, 100% 50%, 61% 61%, 50% 100%, 39% 61%, 0 50%, 39% 39%); animation: rise linear infinite; }
    @keyframes rise { 0% { transform: translateY(0) rotate(0); opacity: 0; } 10% { opacity: .75; } 90% { opacity: .6; } 100% { transform: translateY(-110vh) rotate(200deg); opacity: 0; } }
    .fx-confetti i { top: -20px; width: calc(var(--s) * .6); height: var(--s); border-radius: 2px; opacity: .75; animation: fall linear infinite; }
    @keyframes fall { 0% { transform: translate(0, 0) rotate(0); } 50% { transform: translate(24px, 55vh) rotate(360deg); } 100% { transform: translate(-12px, 110vh) rotate(720deg); } }
    .fx-leaves i { top: -30px; width: var(--s); height: calc(var(--s) * .6); background: var(--accent); border-radius: 0 100% 0 100%; opacity: .5; animation: leaf linear infinite; }
    @keyframes leaf { 0% { transform: translate(0, 0) rotate(0); } 25% { transform: translate(34px, 27vh) rotate(90deg); } 50% { transform: translate(-22px, 55vh) rotate(180deg); } 75% { transform: translate(28px, 82vh) rotate(270deg); } 100% { transform: translate(0, 112vh) rotate(360deg); } }
    .fx-hearts i { bottom: -30px; font-style: normal; font-size: var(--s); color: var(--brand); opacity: .35; animation: rise linear infinite; }
    .fx-hearts i::before { content: '♥'; }
    .fx-stars i { width: var(--s); height: var(--s); background: var(--accent); clip-path: polygon(50% 0, 61% 39%, 100% 50%, 61% 61%, 50% 100%, 39% 61%, 0 50%, 39% 39%); animation: twinkle ease-in-out infinite; }
    @keyframes twinkle { 0%, 100% { opacity: 0; transform: scale(.3) rotate(0); } 50% { opacity: .85; transform: scale(1) rotate(45deg); } }
    .fx-orbs { z-index: 0; }
    .fx-orbs i { width: var(--s); height: var(--s); border-radius: 50%; filter: blur(70px); opacity: .45; animation: orb ease-in-out infinite alternate; }
    @keyframes orb { to { transform: translate(18vw, 14vh) scale(1.2); } }

    @media (prefers-reduced-motion: reduce) {
      *, *::before, *::after { animation: none !important; transition: none !important; }
      .js .rv { opacity: 1; transform: none; filter: none; }
      .marquee { white-space: normal; }
    }

    /* ---------- Template: <?= $tpl['name'] ?> ---------- */
<?php switch ($tpl['key']):
case 'classic': ?>
    .hero-banner .headline { color: var(--brand); }
    .hero-banner .highlight { color: var(--accent); }
    .hero-banner .slider { border: 4px solid var(--brand); }
    .sec-title { position: relative; padding-bottom: 12px; }
    .sec-title::after { content: ''; position: absolute; left: 50%; bottom: 0; width: 80px; height: 4px; border-radius: 4px; background: var(--accent); transform: translateX(-50%); }
<?php break; case 'wood': ?>
    body { background-image: repeating-linear-gradient(97deg, rgba(115,63,32,.025) 0 2px, transparent 2px 9px), repeating-linear-gradient(90deg, rgba(115,63,32,.02) 0 1px, transparent 1px 23px); }
    .hero-split { background: radial-gradient(ellipse at 20% 0%, #fff7ea, transparent 60%); }
    .slider { border: 8px solid #fffdf9; box-shadow: 0 30px 60px -28px rgba(61,31,14,.55); }
    .card-ic { background: linear-gradient(135deg, #8a5430, #c79a5f); }
    .sec-title::before { content: '❦'; display: block; color: var(--accent); font-size: 22px; margin-bottom: 4px; }
    .headline, .sec-title, .highlight, .of-box h3 { font-weight: 400; }
<?php break; case 'luxury': ?>
    .headline, .sec-title { background: linear-gradient(90deg, #b8862f, #f8e3a3, #c9993f, #f8e3a3, #b8862f); background-size: 250% 100%; -webkit-background-clip: text; background-clip: text; color: transparent !important; animation: goldShift 6s linear infinite; }
    @keyframes goldShift { to { background-position: 250% 0; } }
    .hero-overlay .headline { text-shadow: none; }
    .price-box { background: #14100b; border-color: #d9a94e; }
    .slider, .of-box, .card, .badge, .checks li, .faq details, .contact, .rel { border-color: #3a2f22; }
    .field input, .field textarea, .note-box textarea { background: #fffaf0; }
    .cta { background: linear-gradient(135deg, #b8862f, #f2d391 50%, #b8862f); }
    .pb-save, .off-badge, .kicker { color: #1a1309; }
    .numbered .num { -webkit-text-stroke-color: #d9a94e; }
<?php break; case 'flash': ?>
    .hero { background: repeating-linear-gradient(-45deg, #ffe082 0 22px, #ffd54f 22px 44px); background-size: 200% 200%; animation: stripes 12s linear infinite; }
    @keyframes stripes { to { background-position: 100% 100%; } }
    .flash-tag { display: inline-block; background: #111; color: #ffeb3b; font-weight: 800; padding: 6px 22px; border-radius: 10px; transform: rotate(-3deg); font-size: 20px; letter-spacing: .04em; animation: blink 1.2s steps(2) infinite; font-family: var(--head), sans-serif; }
    @keyframes blink { 50% { color: #fff; } }
    .headline { text-shadow: 3px 3px 0 #fff; }
    .price-box { border: 4px solid #d50000; background: #fff; transform: rotate(-1deg); }
    .cd-boxes div { background: #111; color: #ffeb3b; }
    .cd-boxes b { font-size: 34px; }
    .slider { border: 5px solid #fff; }
<?php break; case 'gift': ?>
    .hero-split { background: radial-gradient(circle at 80% 10%, #ffe4e6, transparent 55%), radial-gradient(circle at 10% 90%, #fef3c7, transparent 50%); }
    .headline { font-weight: 400; font-size: clamp(32px, 6.4vw, 56px); }
    .sec-title { font-weight: 400; }
    .slider { border: 6px solid #fff; box-shadow: 0 0 0 3px var(--accent), 0 28px 60px -26px rgba(159,18,57,.6); }
    .gift { font-size: 21px; }
<?php break; case 'minimal': ?>
    .headline { font-size: clamp(34px, 7.5vw, 68px); letter-spacing: -.01em; font-weight: 700; }
    .kicker { background: transparent; color: var(--muted); border: 1px solid var(--line); }
    .highlight { color: var(--muted); font-weight: 500; }
    .slider { border-radius: 6px; box-shadow: none; }
    .price-box { border: 1px solid var(--line); border-radius: 6px; }
    .pb-save, .off-badge { background: #111; }
    .card, .badge, .checks li, .of-box, .faq details, .contact, .rel { border-radius: 6px; box-shadow: none; }
    .cta { border-radius: 6px; box-shadow: none; }
    .card-ic { background: #111; border-radius: 6px; }
<?php break; case 'corporate': ?>
    .hero-split { background-color: #eef4fb; background-image: radial-gradient(rgba(11,59,117,.12) 1.2px, transparent 1.2px); background-size: 18px 18px; }
    .kicker { background: var(--brand); }
    .slider { border-radius: 14px; }
    .card { text-align: left; } .card-ic { margin: 0; border-radius: 14px; background: linear-gradient(135deg, #0b3b75, #0284c7); }
    .sec-title { text-align: left; border-left: 6px solid var(--accent); padding-left: 14px; }
    .narrow .sec-title { text-align: center; border-left: 0; padding-left: 0; }
<?php break; case 'nature': ?>
    .sec.alt::before { content: ''; position: absolute; left: 0; right: 0; top: -38px; height: 40px; background: var(--bg2); -webkit-mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 1200 40' preserveAspectRatio='none'%3E%3Cpath d='M0 40 C200 0 400 0 600 20 S1000 40 1200 10 V40Z'/%3E%3C/svg%3E") center / 100% 100%; mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 1200 40' preserveAspectRatio='none'%3E%3Cpath d='M0 40 C200 0 400 0 600 20 S1000 40 1200 10 V40Z'/%3E%3C/svg%3E") center / 100% 100%; }
    .hero-split { background: radial-gradient(ellipse at 90% 10%, #dcf0cc, transparent 55%); }
    .slider { border-radius: 40px 8px 40px 8px; }
<?php break; case 'pastel': ?>
    .hero::before, .hero::after { content: ''; position: absolute; width: 340px; height: 340px; border-radius: 50%; filter: blur(60px); opacity: .55; z-index: 0; animation: blob 12s ease-in-out infinite alternate; }
    .hero::before { background: #fbcfe8; top: -80px; left: -80px; }
    .hero::after { background: #ddd6fe; bottom: -100px; right: -60px; animation-delay: -6s; }
    @keyframes blob { to { transform: translate(60px, 40px) scale(1.2); } }
    .hero .wrap, .hero .hero-media, .hero .price-box, .hero .hero-actions { position: relative; z-index: 1; }
    .cta { background: linear-gradient(135deg, #ec4899, #8b5cf6); }
    .slider { border-radius: 32px; border: 6px solid #fff; }
    .card { border-radius: 26px; } .card-ic { border-radius: 50%; background: linear-gradient(135deg, #f472b6, #a78bfa); }
<?php break; case 'neon': ?>
    body { background: radial-gradient(circle at 15% 10%, rgba(124,58,237,.35), transparent 40%), radial-gradient(circle at 85% 30%, rgba(6,182,212,.25), transparent 40%), var(--bg); background-attachment: fixed; }
    .sec.alt { background: rgba(255,255,255,.03); }
    .card, .badge, .checks li, .of-box, .faq details, .contact, .rel, .price-box { backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px); }
    .of-box, .price-box { background: rgba(255,255,255,.07); }
    .headline { background: linear-gradient(90deg, #c4b5fd, #22d3ee, #f0abfc); -webkit-background-clip: text; background-clip: text; color: transparent; }
    .slider { box-shadow: 0 0 0 1px rgba(255,255,255,.15), 0 30px 80px -20px rgba(124,58,237,.6); }
    .card-ic { background: linear-gradient(135deg, #7c3aed, #06b6d4); }
    .kicker, .pb-save, .off-badge { color: #0b1020; }
    .chip { background: rgba(255,255,255,.06); }
<?php break; case 'islamic': ?>
    .hero-banner::before { content: ''; position: absolute; top: 50%; left: 50%; width: 900px; height: 900px; margin: -450px 0 0 -450px; opacity: .08; background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Cg fill='none' stroke='%230f5132' stroke-width='1'%3E%3Crect x='20' y='20' width='60' height='60'/%3E%3Crect x='20' y='20' width='60' height='60' transform='rotate(45 50 50)'/%3E%3Ccircle cx='50' cy='50' r='18'/%3E%3Ccircle cx='50' cy='50' r='44'/%3E%3C/g%3E%3C/svg%3E") center / contain no-repeat; animation: turn 90s linear infinite; z-index: 0; }
    @keyframes turn { to { transform: rotate(360deg); } }
    .hero-banner > * { position: relative; z-index: 1; }
    .slider { border: 3px solid var(--accent); border-radius: 160px 160px 18px 18px; }
    .sec-title::before, .sec-title::after { content: '۞'; color: var(--accent); margin: 0 10px; font-size: .8em; }
<?php break; case 'story': ?>
    .hero-overlay { min-height: 92vh; }
    .hero-overlay .headline { font-size: clamp(32px, 7vw, 62px); }
    .headline, .sec-title, .highlight, .of-box h3, .alt-text p { font-weight: 400; }
    .intro { border: 0; background: transparent; font-size: 22px; font-family: var(--head), serif; }
    .intro::before { content: '“'; display: block; font-size: 70px; line-height: .6; color: var(--accent); }
<?php break; case 'video': ?>
    .play-ring { box-shadow: 0 0 0 0 rgba(245,158,11,.6); animation: ringPulse 2.4s infinite; }
    @keyframes ringPulse { 70% { box-shadow: 0 0 0 22px rgba(245,158,11,0); } 100% { box-shadow: 0 0 0 0 rgba(245,158,11,0); } }
    .price-box { background: #0f172a; }
    .field input, .field textarea, .note-box textarea { background: #f8fafc; }
<?php break; case 'compact': ?>
    .hero { padding: 16px 0 28px; }
    .headline { font-size: clamp(24px, 5vw, 38px); }
    .sec { padding: 40px 0; }
    .slider { border-radius: 14px; }
    .hero-split .sub { display: none; }
    @media (min-width: 880px) { .hero-split .sub { display: block; } }
<?php break; case 'sunset': ?>
    .hero { background: linear-gradient(180deg, #ffedd5, var(--bg)); }
    .hero-media { position: relative; }
    .hero-media::before { content: ''; position: absolute; inset: -18%; background: repeating-conic-gradient(from 0deg, rgba(251,146,60,.28) 0 10deg, transparent 10deg 20deg); border-radius: 50%; animation: turn 40s linear infinite; z-index: 0; }
    @keyframes turn { to { transform: rotate(360deg); } }
    .hero-media .slider { z-index: 1; }
    .headline { color: var(--brand); text-shadow: 2px 2px 0 #fed7aa; }
    .cta { background: linear-gradient(135deg, #f97316, #db2777); }
    .card-ic { background: linear-gradient(135deg, #f97316, #db2777); border-radius: 50%; }
<?php break; case 'power': ?>
    /* Colours come from the product photo; the glow behind the photo morphs, the price box shimmers */
    .hero-split { background: radial-gradient(ellipse at 15% 10%, color-mix(in srgb, var(--cta) 16%, transparent), transparent 55%), radial-gradient(ellipse at 95% 80%, color-mix(in srgb, var(--accent) 14%, transparent), transparent 50%); }
    .hero-media { position: relative; padding: 14px; }
    .hero-media::before { content: ''; position: absolute; inset: 0; background: linear-gradient(135deg, var(--cta), var(--accent)); opacity: .9; border-radius: 42% 58% 63% 37% / 41% 44% 56% 59%; animation: morph 9s ease-in-out infinite; filter: blur(2px); }
    @keyframes morph { 0%, 100% { border-radius: 42% 58% 63% 37% / 41% 44% 56% 59%; transform: rotate(0); } 33% { border-radius: 70% 30% 46% 54% / 30% 39% 61% 70%; transform: rotate(4deg); } 66% { border-radius: 33% 67% 38% 62% / 63% 32% 68% 37%; transform: rotate(-3deg); } }
    .hero-media .slider { border: 6px solid #fff; border-radius: 26px; }
    .hero-media .off-badge { display: none; } /* the floating "% ছাড়" badge replaces it */
    @media (min-width: 880px) { .hero-grid { align-items: start; } .hero-split .hero-media { position: sticky; top: 24px; } }
    .kicker { background: linear-gradient(90deg, var(--price), var(--accent)); box-shadow: 0 8px 18px -8px var(--price); }
    .headline { font-weight: 800; letter-spacing: -.01em; font-size: clamp(30px, 6.2vw, 52px); }
    .headline::after { content: ''; display: block; width: 70px; height: 5px; border-radius: 5px; margin-top: 10px; background: linear-gradient(90deg, var(--cta), var(--accent)); }
    .price-box { border: 0; background: var(--card); box-shadow: 0 18px 40px -24px rgba(0,0,0,.4); overflow: hidden; text-align: left; display: flex; flex-wrap: wrap; align-items: baseline; gap: 4px 16px; }
    .price-box::after { content: ''; position: absolute; top: 0; left: -60%; width: 40%; height: 100%; background: linear-gradient(105deg, transparent, rgba(255,255,255,.7), transparent); transform: skewX(-20deg); animation: shine 4s ease-in-out infinite; }
    .pb-offer { order: -1; } .pb-save { border-radius: 8px; }
    .offer .price-box { margin-left: auto; margin-right: auto; justify-content: center; text-align: center; }
    .hero-actions .cta { flex: 1 1 260px; font-size: 20px; padding: 16px 28px; }
    .cta { background: linear-gradient(135deg, var(--cta), color-mix(in srgb, var(--cta) 70%, var(--accent))); transition: transform .2s, translate .15s, filter .2s; }
    .card { border: 0; box-shadow: 0 16px 36px -22px rgba(0,0,0,.35); }
    .card-ic { background: linear-gradient(135deg, var(--cta), var(--accent)); border-radius: 50%; }
    .badge { border: 0; box-shadow: 0 10px 24px -18px rgba(0,0,0,.4); }
    .sec-title { font-weight: 800; }
    .of-box { border: 0; box-shadow: 0 16px 40px -26px rgba(0,0,0,.4); }
    .submit { font-size: 21px; }
<?php break; case 'greenbar': ?>
    body { font-size: 16px; }
    .sec { padding: 0 0 36px; }
    .sec-title { background: var(--brand); color: #fff; margin: 0 calc(50% - 50vw) 22px; padding: 12px 16px; font-size: clamp(21px, 4vw, 30px); }
    [data-sec="offer"], [data-sec="photo"] { padding-top: 8px; }
    .hero { padding: 18px 0 36px; }
    .hero-banner > .wrap { display: flex; flex-direction: column; align-items: center; }
    .hero-banner .hero-text { order: 0; } .hero-banner .hero-media { order: 1; width: 100%; max-width: 640px; margin-top: 14px; }
    .hero-banner .countdown { order: 2; } .hero-banner .price-box { order: 3; } .hero-banner .hero-actions { order: 4; }
    .headline { color: var(--brand); font-size: clamp(26px, 5.4vw, 44px); margin-top: 0; }
    .highlight { color: var(--ink); font-size: clamp(17px, 3vw, 22px); }
    .slider, .photo-repeat img { border: 3px solid var(--brand); padding: 3px; border-radius: 2px; box-shadow: none; background: #fff; }
    .slide img { border-radius: 0; }
    .countdown { margin-top: 16px; }
    .countdown p { display: none; }
    .cd-boxes { gap: 8px; }
    .cd-boxes div { background: var(--brand); color: #fff; border-radius: 6px; min-width: 76px; padding: 10px 6px; }
    .cd-boxes b { font-size: clamp(28px, 6vw, 40px); font-family: system-ui, sans-serif; font-weight: 500; }
    .price-box { border: 0; background: none; max-width: none; display: flex; flex-wrap: wrap; justify-content: center; align-items: center; gap: 6px 22px; padding: 6px 0; margin-top: 16px; }
    .pb-label { flex-basis: 100%; color: var(--brand); font-size: 20px; }
    .pb-regular { font-size: 20px; font-weight: 700; color: var(--ink); }
    .pb-regular s { text-decoration: none; position: relative; color: var(--ink); padding: 0 4px; }
    .pb-regular s::before, .pb-regular s::after { content: ''; position: absolute; left: -2px; right: -2px; top: 50%; height: 3px; border-radius: 2px; background: var(--accent); transform: rotate(-16deg); }
    .pb-regular s::after { transform: rotate(16deg); }
    .pb-offer { font-size: 20px; font-weight: 700; color: var(--brand); }
    .pb-offer strong { font-size: 26px; color: var(--accent); border: 3px solid var(--accent); border-radius: 50%; padding: 2px 12px; line-height: 1.2; }
    .pb-save { background: none; color: var(--accent); padding: 0; flex-basis: 100%; }
    .cta { border-radius: 4px; font-size: 17px; padding: 10px 24px; box-shadow: none; }
    .cta .ic { width: 18px; height: 18px; }
    .cta.big { font-size: 18px; padding: 12px 28px; margin-top: 10px; }
    .hero-actions { margin-top: 14px; }
    .checks { max-width: 820px; gap: 0; }
    .checks li { background: none; border: 0; border-bottom: 1px solid var(--line); border-radius: 0; padding: 8px 2px; font-size: 16px; line-height: 1.55; }
    .checks .ic { width: 22px; height: 22px; padding: 0; background: none; color: var(--brand); stroke-width: 3.5; margin-top: 1px; }
    [data-sec="why"] .checks .ic { stroke-width: 2.4; }
    .chip { border-radius: 4px; }
    .faq details, .contact { border-radius: 6px; }
    .contact-band { margin-top: 0; }
    section[data-sec="contact"] { padding: 0; }
    [data-sec="order"] { padding-top: 28px; }
    [data-sec="order"] > .wrap { border: 2px solid var(--brand); border-radius: 12px; padding: 20px 14px; max-width: 1000px; width: calc(100% - 24px); }
    [data-sec="order"] .sec-title { background: none; color: var(--brand); margin: 0 0 18px; padding: 0; }
    .of-box { box-shadow: none; border-color: #ececec; border-radius: 8px; }
    .of-box h3 { color: var(--ink); font-size: 16px; }
    .submit { background: #f26522; border-radius: 6px; box-shadow: none; animation: none; }
    .sticky .cta { background: var(--cta); }
<?php break; endswitch; ?>
  </style>
</head>
<body class="t-<?= lp_e($tpl['key']) ?> reveal-<?= lp_e($tpl['reveal']) ?>">
  <?php if ($preview): ?><div class="preview-tag">PREVIEW · <?= lp_e($tpl['name']) ?><?= $landing['enabled'] ? '' : ' · OFF' ?></div><?php endif; ?>
  <?php $bar = ic_lines($data['top_bar']); if ($bar): ?>
    <div class="topbar"><div class="marquee"><?php for ($i = 0; $i < 2; $i++): foreach ($bar as $line): ?><span><?= lp_e($line) ?></span><?php endforeach; endfor; ?></div></div>
  <?php endif; ?>
  <a class="brand" href="index.html"><img src="assets/logo-mark.png" alt=""><span>INSPIRED CREATIONS</span></a>

  <main>
    <?= $hero() ?>
    <?php
    $alt = true;
    foreach ($tpl['sections'] as $key) {
        if ($key === 'hero' || !isset($sections[$key])) {
            continue;
        }
        $html = $sections[$key]();
        if ($html === '') {
            continue;
        }
        echo '<section class="sec' . ($alt ? ' alt' : '') . '"' . ($key === 'order' ? ' id="order"' : '') . ' data-sec="' . $key . '">' . $html . '</section>';
        $alt = !$alt;
    }
    ?>
  </main>

  <footer>
    © <?= date('Y') ?> INSPIRED CREATIONS · হাতে তৈরি কাঠের উপহার · <a href="index.html">আমাদের সব পণ্য দেখুন</a>
  </footer>

  <?php if ($price !== null): ?>
    <div class="sticky" id="sticky">
      <div class="st-price"><small data-price-word><?= $regular ? 'অফার প্রাইস' : 'মূল্য' ?></small><b data-main-price>৳<?= lp_bn($price) ?></b></div>
      <a href="#order" class="cta cta-<?= lp_e($tpl['cta']) ?>"><span><?= lp_e($data['cta_text'] ?: 'অর্ডার করুন') ?></span><?= lp_icon('arrow') ?></a>
    </div>
  <?php endif; ?>
  <div class="float-btns">
    <a class="f-wa" href="<?= lp_e($waLink) ?>" target="_blank" rel="noopener" aria-label="WhatsApp" data-contact><?= lp_icon('whatsapp') ?></a>
    <a class="f-call" href="tel:<?= lp_e($phone) ?>" aria-label="কল করুন" data-contact><?= lp_icon('phone') ?></a>
  </div>

  <div class="done" id="done" hidden>
    <div class="done-card" role="dialog" aria-modal="true" aria-labelledby="doneTitle">
      <svg class="check-anim" viewBox="0 0 84 84"><circle cx="42" cy="42" r="38"/><path d="M26 43l11 11 21-23"/></svg>
      <h3 id="doneTitle">ধন্যবাদ! অর্ডারটি সফল হয়েছে</h3>
      <p>আপনার অর্ডার নম্বর</p>
      <span class="done-code" id="doneCode"></span>
      <p id="doneTotal"></p>
      <p>আমাদের প্রতিনিধি খুব শীঘ্রই ফোন করে আপনার অর্ডারটি কনফার্ম করবেন। ফোনটি কাছে রাখুন।</p>
      <div class="contact-btns"><a class="c-wa" id="doneWa" href="#" target="_blank" rel="noopener"><?= lp_icon('whatsapp') ?> WhatsApp-এ জানান</a></div>
      <a class="back" href="index.html">আরও পণ্য দেখুন</a>
    </div>
  </div>
  <div class="lightbox" id="lightbox" hidden><img alt=""></div>
  <?php if (!empty($tpl['progress'])): ?><div class="scroll-progress" aria-hidden="true"><i></i></div><?php endif; ?>
  <?php if (!empty($tpl['exitBox']) && $price !== null): ?>
    <!-- "Before you go": a quick 3-field order for visitors about to leave (shown once per visit) -->
    <div class="leave" id="leaveBox" hidden>
      <div class="leave-card" role="dialog" aria-modal="true" aria-labelledby="leaveTitle">
        <button type="button" class="leave-x" data-leave-close aria-label="বন্ধ করুন">✕</button>
        <div class="leave-top">
          <img src="<?= lp_e($images[0]) ?>" alt="">
          <div>
            <p class="leave-kicker">যাওয়ার আগে একটু দাঁড়ান!</p>
            <h3 id="leaveTitle"><?= lp_e($product['title']) ?></h3>
            <p class="leave-price"><b data-main-price>৳<?= lp_bn($price) ?></b><?php if ($regular): ?> <s class="<?= trim($offerOnly) ?>">৳<?= lp_bn($regular) ?></s><?php endif; ?></p>
          </div>
        </div>
        <ul class="leave-points">
          <li><?= lp_icon('check') ?> কোনো অগ্রিম টাকা লাগবে না — পণ্য হাতে পেয়ে টাকা দিন</li>
          <li><?= lp_icon('check') ?> শুধু নাম, নম্বর আর ঠিকানা দিলেই অর্ডার হয়ে যাবে</li>
        </ul>
        <form id="leaveForm" novalidate>
          <input name="name" placeholder="আপনার নাম" autocomplete="name" maxlength="120">
          <input name="whatsapp" type="tel" inputmode="tel" placeholder="মোবাইল নম্বর (01XXXXXXXXX)" autocomplete="tel" maxlength="16">
          <input name="address" placeholder="সম্পূর্ণ ঠিকানা (এলাকা, থানা, জেলা)" autocomplete="street-address" maxlength="500">
          <div class="leave-region">
            <label><input type="radio" name="leave_region" value="dhaka"> ঢাকার ভিতরে</label>
            <label><input type="radio" name="leave_region" value="outside"> ঢাকার বাইরে</label>
          </div>
          <p class="leave-err" hidden></p>
          <button class="cta cta-pulse" type="submit"><?= lp_icon('cart') ?><span>ক্যাশ অন ডেলিভারিতে অর্ডার করুন</span></button>
        </form>
        <div class="leave-alt">
          <a href="<?= lp_e($waLink) ?>" target="_blank" rel="noopener" data-contact><?= lp_icon('whatsapp') ?> WhatsApp-এ অর্ডার</a>
          <a href="tel:<?= lp_e($phone) ?>" data-contact><?= lp_icon('phone') ?> কল করুন</a>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <script>
  (() => {
    const LP = <?= json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
    const $ = (s, el = document) => el.querySelector(s);
    const $$ = (s, el = document) => [...el.querySelectorAll(s)];
    const BN = '০১২৩৪৫৬৭৮৯';
    const bn = (n) => Math.round(n).toLocaleString('en-US').replace(/\d/g, d => BN[d]);
    const toEn = (s) => String(s).replace(/[০-৯]/g, d => BN.indexOf(d));

    /* ---- Meta Pixel (same events and IDs as the main website) ---- */
    const pixelOn = !!LP.pixel;
    const cookies = () => Object.fromEntries(document.cookie.split('; ').map(c => c.split('=')).filter(([k]) => k === '_fbp' || k === '_fbc').map(([k, v]) => [k.slice(1), v]));
    function track(event, params = {}, eventId = null) {
      if (!pixelOn || typeof window.fbq !== 'function') return;
      const id = eventId || `${event}-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 8)}`;
      fbq('track', event, params, { eventID: id });
      if (LP.pixel.server && !eventId && navigator.sendBeacon) {
        navigator.sendBeacon('api/event.php', new Blob([JSON.stringify({ event, event_id: id, params, url: location.href, ...cookies() })], { type: 'application/json' }));
      }
    }
    if (pixelOn) {
      !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
      n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
      n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
      t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,
      document,'script','https://connect.facebook.net/en_US/fbevents.js');
      fbq('init', String(LP.pixel.id));
      track('PageView');
      if (LP.product) track('ViewContent', { content_ids: [LP.product.id], content_name: LP.product.title, content_type: 'product', value: LP.product.price, currency: 'BDT' });
    }
    $$('[data-contact]').forEach(a => a.addEventListener('click', () => track('Contact')));

    /* ---- Scroll reveal ---- */
    const io = 'IntersectionObserver' in window ? new IntersectionObserver((entries) => {
      entries.forEach(en => { if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); countUp(en.target); } });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' }) : null;
    $$('.rv').forEach(el => io ? io.observe(el) : el.classList.add('in'));

    // Prices count up when they appear
    function countUp(root) {
      $$('.count[data-count]', root).forEach(el => {
        const target = +el.dataset.count;
        if (reduce || el.dataset.done) return;
        el.dataset.done = 1;
        const start = performance.now();
        const step = (t) => { const p = Math.min(1, (t - start) / 1100); el.textContent = '৳' + bn(target * (1 - Math.pow(1 - p, 3))); if (p < 1) requestAnimationFrame(step); };
        requestAnimationFrame(step);
      });
    }
    $$('.price-box').forEach(pb => { if (!pb.closest('.rv')) { const o = new IntersectionObserver(([en]) => { if (en.isIntersecting) { countUp(pb); o.disconnect(); } }); o.observe(pb); } });

    /* ---- Photo sliders ---- */
    $$('[data-slider]').forEach(slider => {
      const slides = $$('.slide', slider), dots = $$('.dots button', slider);
      if (slides.length < 2) return;
      let i = 0, timer;
      const go = (n) => {
        slides[i].classList.remove('on'); dots[i]?.classList.remove('on');
        i = (n + slides.length) % slides.length;
        slides[i].classList.add('on'); dots[i]?.classList.add('on');
        const img = $('img', slides[i]); img.loading = 'eager';
        restart();
      };
      const restart = () => { clearInterval(timer); timer = setInterval(() => go(i + 1), 4200); };
      $('.prev', slider).addEventListener('click', () => go(i - 1));
      $('.next', slider).addEventListener('click', () => go(i + 1));
      dots.forEach((d, n) => d.addEventListener('click', () => go(n)));
      let x0 = null;
      slider.addEventListener('touchstart', e => { x0 = e.touches[0].clientX; }, { passive: true });
      slider.addEventListener('touchend', e => { if (x0 === null) return; const dx = e.changedTouches[0].clientX - x0; if (Math.abs(dx) > 40) go(i + (dx < 0 ? 1 : -1)); x0 = null; });
      restart();
    });

    /* ---- Motion: 3D tilt, zoom on scroll, parallax ---- */
    if (!reduce && matchMedia('(pointer: fine)').matches) {
      $$('.img-tilt').forEach(el => {
        el.addEventListener('pointermove', e => {
          const r = el.getBoundingClientRect(), x = (e.clientX - r.left) / r.width - .5, y = (e.clientY - r.top) / r.height - .5;
          el.style.transform = `perspective(900px) rotateY(${x * 12}deg) rotateX(${-y * 12}deg) scale(1.02)`;
        });
        el.addEventListener('pointerleave', () => { el.style.transform = ''; });
      });
    }
    if (!reduce) {
      const zooms = $$('.img-zoom'), bg = $('.hero-bg img');
      const onScroll = () => {
        zooms.forEach(el => { const r = el.getBoundingClientRect(); const p = Math.max(0, Math.min(1, 1 - r.top / innerHeight)); el.style.setProperty('--z', (1 + p * .08).toFixed(3)); });
        if (bg && scrollY < innerHeight) bg.parentElement.style.transform = `translateY(${scrollY * .3}px)`;
      };
      addEventListener('scroll', onScroll, { passive: true }); onScroll();
    }

    /* ---- Reading progress bar and magnetic buttons ---- */
    const progress = $('.scroll-progress i');
    if (progress) {
      const step = () => { progress.style.transform = `scaleX(${Math.min(1, scrollY / Math.max(1, document.body.scrollHeight - innerHeight))})`; };
      addEventListener('scroll', step, { passive: true }); step();
    }
    if (LP.magnetic && !reduce && matchMedia('(pointer: fine)').matches) {
      $$('.cta').forEach(btn => {
        btn.addEventListener('pointermove', (e) => {
          const r = btn.getBoundingClientRect();
          btn.style.translate = `${(e.clientX - r.left - r.width / 2) * .18}px ${(e.clientY - r.top - r.height / 2) * .3}px`;
        });
        btn.addEventListener('pointerleave', () => { btn.style.translate = ''; });
      });
    }

    /* ---- Floating particles ---- */
    if (!reduce && LP.fx !== 'none') {
      const layer = document.createElement('div');
      layer.className = 'fx fx-' + LP.fx;
      const small = innerWidth < 700;
      const count = { sparkle: 16, confetti: 20, leaves: 10, hearts: 12, stars: 24, orbs: 3 }[LP.fx] || 0;
      const css = getComputedStyle(document.documentElement);
      const colors = ['--brand', '--accent', '--cta', '--price'].map(v => css.getPropertyValue(v).trim()).concat(['#f5c542']);
      for (let n = 0; n < Math.round(count * (small ? .6 : 1)); n++) {
        const p = document.createElement('i');
        const dur = LP.fx === 'stars' ? 2 + Math.random() * 3 : LP.fx === 'orbs' ? 14 + Math.random() * 10 : 9 + Math.random() * 9;
        p.style.left = Math.random() * 100 + '%';
        if (LP.fx === 'stars' || LP.fx === 'orbs') p.style.top = Math.random() * 100 + '%';
        p.style.animationDuration = dur + 's';
        p.style.animationDelay = -(Math.random() * dur) + 's';
        p.style.setProperty('--s', (LP.fx === 'orbs' ? 260 + Math.random() * 200 : LP.fx === 'hearts' ? 14 + Math.random() * 16 : 7 + Math.random() * 9) + 'px');
        if (LP.fx === 'confetti' || LP.fx === 'orbs') p.style.background = colors[n % colors.length];
        layer.appendChild(p);
      }
      document.body.appendChild(layer);
    }

    /* ---- Countdown: a real offer — at zero the offer ends and the normal price comes back ---- */
    let onPriceChange = () => {};
    const endOffer = () => {
      $$('.countdown').forEach(c => c.remove());
      if (!LP.normal || !LP.product) return;
      LP.product.price = LP.normal;
      document.body.classList.add('offer-ended');
      $$('[data-main-price]').forEach(el => { el.dataset.done = 1; el.textContent = '৳' + bn(LP.normal); });
      $$('[data-price-word]').forEach(el => { el.textContent = 'মূল্য'; });
      $$('.pb-ended').forEach(el => { el.hidden = false; });
      onPriceChange();
    };
    if (LP.end) {
      const pad = (n) => String(n).padStart(2, '0').replace(/\d/g, d => BN[d]);
      const tick = () => {
        const left = Math.max(0, LP.end - Date.now());
        if (!left) { endOffer(); return; }
        const s = Math.floor(left / 1000);
        const parts = { d: Math.floor(s / 86400), h: Math.floor(s % 86400 / 3600), m: Math.floor(s % 3600 / 60), s: s % 60 };
        $$('[data-cd]').forEach(el => { el.textContent = pad(parts[el.dataset.cd]); });
        setTimeout(tick, 1000);
      };
      tick();
    }

    /* ---- Customer photo lightbox ---- */
    const lb = $('#lightbox');
    $$('.g-item').forEach(b => b.addEventListener('click', () => { $('img', lb).src = b.dataset.full; lb.hidden = false; }));
    lb.addEventListener('click', () => { lb.hidden = true; });

    /* ---- Sticky order bar: shows once the first button scrolls away, hides at the form ---- */
    const sticky = $('#sticky'), order = $('#order');
    if (sticky && order && 'IntersectionObserver' in window) {
      let pastHero = false, atForm = false;
      const update = () => sticky.classList.toggle('show', pastHero && !atForm);
      const heroBtn = $('.hero-actions') || $('.hero');
      new IntersectionObserver(([en]) => { pastHero = !en.isIntersecting && en.boundingClientRect.top < 0; update(); }).observe(heroBtn);
      new IntersectionObserver(([en]) => { atForm = en.isIntersecting; update(); }, { rootMargin: '0px 0px -30% 0px' }).observe(order);
    }

    /* ---- Order form ---- */
    const form = $('#orderForm');
    if (!form || !LP.product) return;
    let qty = 1, started = false, ordered = false;
    const field = (name) => form.elements[name];
    const chosenAddons = () => $$('input[name="addon"]:checked', form).map(cb => LP.addons.find(a => a.id === cb.value)).filter(Boolean);
    const region = () => (form.querySelector('input[name="region"]:checked') || {}).value || '';
    // Same rule as the server: one parcel pays the highest charge among its products; free above the shop's minimum
    function totals() {
      const lines = [{ p: LP.product, qty }, ...chosenAddons().map(p => ({ p, qty: 1 }))];
      const subtotal = lines.reduce((s, l) => s + l.p.price * l.qty, 0);
      const r = region();
      let delivery = null;
      if (r) delivery = (LP.freeMin > 0 && subtotal >= LP.freeMin) ? 0 : Math.max(...lines.map(l => l.p[r]));
      return { lines, subtotal, delivery, total: subtotal + (delivery || 0) };
    }
    function render() {
      const t = totals();
      $('#qty').textContent = bn(qty);
      $('#sumSubtotal').textContent = '৳' + bn(t.subtotal);
      $('#sumDelivery').textContent = t.delivery === null ? 'এরিয়া সিলেক্ট করুন' : (t.delivery ? '৳' + bn(t.delivery) : 'ফ্রি');
      $('#sumTotal').textContent = '৳' + bn(t.total);
      $$('[data-total-text]').forEach(el => { el.textContent = '৳' + bn(t.total); });
      const pay = (form.querySelector('input[name="payment_method"]:checked') || {}).value;
      const bk = $('.bkash', form);
      if (bk) {
        bk.hidden = !pay || pay === 'cod';
        const chosen = form.querySelector('input[name="payment_method"]:checked');
        $('[data-bank-name]', bk).textContent = (chosen && chosen.dataset.bank) || 'বিকাশ';
        // Bangla QR: show the QR to scan instead of the "send money to this number" line
        const qrBox = $('[data-pay-qr]', bk);
        if (qrBox) qrBox.hidden = pay !== 'banglaqr';
        $('[data-pay-number]', bk).hidden = pay === 'banglaqr';
      }
      // Quick quantity cards follow the form (prices too, so they stay right when an offer ends)
      $$('[data-tier-qty]').forEach(b => {
        const q = +b.dataset.tierQty, on = q === qty;
        b.classList.toggle('on', on); b.setAttribute('aria-pressed', String(on));
        $('.tier-price', b).textContent = '৳' + bn(LP.product.price * q);
        const free = $('.tier-free', b); if (free) free.hidden = !(LP.freeMin > 0 && LP.product.price * q >= LP.freeMin);
      });
      // Free-delivery progress: how much more for free delivery (the shop's real rule)
      const bar = $('#freeBar');
      const alwaysFree = t.lines.every(l => !l.p.dhaka && !l.p.outside);
      if (bar) {
        bar.hidden = !(LP.freeMin > 0) || alwaysFree;
        if (!bar.hidden) {
          const left = LP.freeMin - t.subtotal;
          $('p', bar).innerHTML = left > 0 ? `আর মাত্র <b>৳${bn(left)}</b> এর পণ্য যোগ করলে <b>ডেলিভারি ফ্রি!</b>` : '🎉 অভিনন্দন! আপনি <b>ফ্রি ডেলিভারি</b> পাচ্ছেন';
          $('i', bar).style.width = Math.min(100, t.subtotal / LP.freeMin * 100) + '%';
          bar.classList.toggle('done', left <= 0);
        }
      }
    }
    document.addEventListener('click', (e) => {
      const b = e.target.closest('[data-tier-qty]');
      if (!b) return;
      qty = +b.dataset.tierQty; begin(); render();
      track('AddToCart', { content_ids: [LP.product.id], content_name: LP.product.title, content_type: 'product', value: LP.product.price * qty, currency: 'BDT', num_items: qty });
    });

    // Returning customers: details from their last order on this device fill in by themselves
    const MEMORY = 'ic_customer';
    const remembered = $('#remembered');
    const recall = () => {
      let saved = null;
      try { saved = JSON.parse(localStorage.getItem(MEMORY) || 'null'); } catch (err) {}
      if (!saved || !saved.phone) return;
      ['name', 'whatsapp', 'address', 'email'].forEach(k => { const v = saved[k === 'whatsapp' ? 'phone' : k]; if (v && field(k) && !field(k).value) field(k).value = v; });
      const r = saved.region && form.querySelector(`input[name="region"][value="${saved.region}"]`); if (r) r.checked = true;
      if (remembered) remembered.hidden = false;
    };
    $('#forgetMe')?.addEventListener('click', () => {
      try { localStorage.removeItem(MEMORY); } catch (err) {}
      ['name', 'whatsapp', 'address', 'email'].forEach(k => { if (field(k)) field(k).value = ''; });
      $$('input[name="region"]', form).forEach(r => { r.checked = false; });
      remembered.hidden = true; render();
    });
    const begin = () => {
      if (started) return; started = true;
      track('InitiateCheckout', { content_ids: [LP.product.id], content_type: 'product', value: totals().total, currency: 'BDT', num_items: qty });
    };
    form.addEventListener('focusin', begin);
    form.addEventListener('click', (e) => {
      const b = e.target.closest('[data-qty]');
      if (!b) return;
      qty = Math.max(1, Math.min(50, qty + +b.dataset.qty));
      begin(); render();
    });
    form.addEventListener('change', (e) => {
      if (e.target.name === 'addon' && e.target.checked) {
        const a = LP.addons.find(x => x.id === e.target.value);
        if (a) track('AddToCart', { content_ids: [a.id], content_name: a.title, content_type: 'product', value: a.price, currency: 'BDT' });
      }
      if (e.target.name === 'region') $('[data-err="region"]').textContent = '';
      render();
    });
    // Bangla digits typed on a Bangla keyboard become 0–9
    field('whatsapp').addEventListener('input', (e) => { const v = toEn(e.target.value); if (v !== e.target.value) e.target.value = v; });

    const setErr = (name, msg) => {
      const el = field(name); const wrap = el && el.closest('.field');
      if (!wrap) return;
      wrap.classList.toggle('bad', !!msg); $('em', wrap).textContent = msg || '';
    };
    form.addEventListener('input', (e) => { if (e.target.closest('.field.bad')) setErr(e.target.name, ''); });
    const phoneOf = (v) => { let d = toEn(v).replace(/\D/g, ''); if (d.startsWith('880')) d = d.slice(2); else if (d.startsWith('88')) d = d.slice(2); return d; };

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const errBox = $('#formError'); errBox.hidden = true;
      const name = field('name').value.trim(), phone = phoneOf(field('whatsapp').value), address = field('address').value.trim();
      const pay = (form.querySelector('input[name="payment_method"]:checked') || {}).value || 'cod';
      const trx = field('trx_id') ? field('trx_id').value.trim() : '';
      let first = null;
      const check = (ok, name, msg) => { setErr(name, ok ? '' : msg); if (!ok && !first) first = field(name); };
      check(name.length >= 2, 'name', 'আপনার নাম লিখুন');
      check(/^01[3-9]\d{8}$/.test(phone), 'whatsapp', 'সঠিক ১১ সংখ্যার মোবাইল নম্বর লিখুন (যেমন 01712345678)');
      check(address.length >= 10, 'address', 'সম্পূর্ণ ঠিকানা লিখুন (এলাকা, থানা, জেলা)');
      const email = field('email') ? field('email').value.trim() : '';
      check(!email || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email), 'email', 'ইমেইল ঠিকানাটি সঠিক নয় — ঠিক করুন অথবা খালি রাখুন');
      if (!region()) { $('[data-err="region"]').textContent = 'ঢাকার ভিতরে না বাইরে — সিলেক্ট করুন'; if (!first) first = form.querySelector('input[name="region"]'); }
      const prepaid = pay !== 'cod';
      if (prepaid) check(trx.length >= 6, 'trx_id', 'পেমেন্টের Transaction ID লিখুন');
      if (first) { first.closest('.of-box').scrollIntoView({ behavior: 'smooth', block: 'center' }); setTimeout(() => first.focus({ preventScroll: true }), 400); return; }
      if (LP.preview) { errBox.textContent = 'এটি প্রিভিউ — এখানে অর্ডার জমা হয় না।'; errBox.hidden = false; return; }

      const t = totals();
      const fd = new FormData();
      fd.append('type', 'cart');
      fd.append('items', JSON.stringify(t.lines.map(l => ({ id: l.p.id, qty: l.qty }))));
      fd.append('region', region());
      fd.append('payment_method', prepaid ? 'bkash' : 'cod');
      fd.append('pay_channel', prepaid ? pay : '');
      fd.append('trx_id', trx);
      fd.append('name', name);
      fd.append('whatsapp', phone);
      fd.append('email', email);
      fd.append('address', address);
      fd.append('notes', field('notes').value.trim());
      fd.append('landing', LP.landing);
      fd.append('_honey', field('_honey').value);
      const btn = $('#submitBtn'); btn.classList.add('busy');
      try {
        const res = await fetch('api/orders.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (!data.ok) throw new Error(data.error || 'error');
        if (data.track !== false) {
          track('Purchase', { content_ids: t.lines.map(l => l.p.id), contents: t.lines.map(l => ({ id: l.p.id, quantity: l.qty })), content_type: 'product', value: data.total, currency: 'BDT', num_items: t.lines.reduce((s, l) => s + l.qty, 0) }, data.code);
        }
        $('#doneCode').textContent = data.code;
        $('#doneTotal').textContent = 'সর্বমোট: ৳' + bn(data.total) + (prepaid ? ' (' + (form.querySelector('input[name="payment_method"]:checked') || {}).dataset?.bank + ' — পেমেন্ট যাচাই করা হবে)' : ' (ক্যাশ অন ডেলিভারি)');
        $('#doneWa').href = `https://wa.me/${LP.wa}?text=` + encodeURIComponent(`আমি একটি অর্ডার করেছি। অর্ডার নম্বর: ${data.code}`);
        $('#done').hidden = false;
        ordered = true; const lbx = $('#leaveBox'); if (lbx) lbx.hidden = true;
        try { localStorage.setItem(MEMORY, JSON.stringify({ name, phone, address, email, region: region() })); } catch (err) {}
        form.reset(); qty = 1; recall(); render();
        if (!reduce) burst();
      } catch (err) {
        errBox.textContent = (err.message && err.message !== 'error' && !/Failed to fetch|JSON/i.test(err.message)) ? err.message : 'দুঃখিত, অর্ডারটি পাঠানো যায়নি। আবার চেষ্টা করুন অথবা WhatsApp-এ অর্ডার করুন।';
        errBox.hidden = false;
      } finally {
        btn.classList.remove('busy');
      }
    });
    onPriceChange = render;
    recall();

    /* ---- "Before you go" box: once per visit, only for visitors who have not started ordering ---- */
    const leave = $('#leaveBox');
    if (leave && !LP.preview) {
      let shown = false;
      try { shown = sessionStorage.getItem('ic_leave_' + LP.landing) === '1'; } catch (err) {}
      const since = Date.now();
      const open = () => {
        if (shown || started || ordered || Date.now() - since < 8000 || !$('#done').hidden) return;
        shown = true; try { sessionStorage.setItem('ic_leave_' + LP.landing, '1'); } catch (err) {}
        leave.hidden = false;
      };
      document.addEventListener('mouseout', (e) => { if (!e.relatedTarget && e.clientY <= 0) open(); });
      // Phones: a quick swipe back up after reading most of the page
      let lastY = scrollY, lastT = Date.now(), deepest = 0;
      addEventListener('scroll', () => {
        const now = Date.now(), y = scrollY;
        deepest = Math.max(deepest, y / Math.max(1, document.body.scrollHeight - innerHeight));
        if (deepest > .4 && lastY - y > 500 && now - lastT < 700) open();
        if (now - lastT > 700) { lastY = y; lastT = now; }
      }, { passive: true });
      const close = () => { leave.hidden = true; };
      leave.addEventListener('click', (e) => { if (e.target === leave || e.target.closest('[data-leave-close]')) close(); });
      document.addEventListener('keydown', (e) => { if (e.key === 'Escape') close(); });
      const lf = $('#leaveForm'), lerr = $('.leave-err', leave);
      lf.elements.whatsapp.addEventListener('input', (e) => { const v = toEn(e.target.value); if (v !== e.target.value) e.target.value = v; });
      lf.addEventListener('submit', (e) => {
        e.preventDefault();
        const n = lf.elements.name.value.trim(), p = phoneOf(lf.elements.whatsapp.value), a = lf.elements.address.value.trim();
        const r = (lf.querySelector('input[name="leave_region"]:checked') || {}).value;
        const msg = n.length < 2 ? 'আপনার নাম লিখুন' : !/^01[3-9]\d{8}$/.test(p) ? 'সঠিক ১১ সংখ্যার মোবাইল নম্বর লিখুন' : a.length < 10 ? 'সম্পূর্ণ ঠিকানা লিখুন' : !r ? 'ঢাকার ভিতরে না বাইরে — সিলেক্ট করুন' : '';
        lerr.textContent = msg; lerr.hidden = !msg;
        if (msg) return;
        // Same order as the main form (cash on delivery), so prices, checks and tracking stay identical
        field('name').value = n; field('whatsapp').value = p; field('address').value = a;
        form.querySelector(`input[name="region"][value="${r}"]`).checked = true;
        form.querySelector('input[name="payment_method"][value="cod"]').checked = true;
        render(); close(); form.requestSubmit();
      });
    }
    $('#done').addEventListener('click', (e) => { if (e.target.id === 'done') e.target.hidden = true; });
    function burst() {
      const colors = ['#22c55e', '#f59e0b', '#ef4444', '#3b82f6', '#a855f7', '#ec4899'];
      for (let n = 0; n < 70; n++) {
        const p = document.createElement('i');
        p.className = 'burst';
        p.style.left = Math.random() * 100 + 'vw';
        p.style.background = colors[n % colors.length];
        p.style.animationDelay = Math.random() * .6 + 's';
        p.style.animationDuration = 1.8 + Math.random() * 1.6 + 's';
        document.body.appendChild(p);
        setTimeout(() => p.remove(), 4500);
      }
    }
    render();
  })();
  </script>
</body>
</html>
