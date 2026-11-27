<?php
// Product landing pages: 15 design templates, default Bangla content for every product, and the
// helpers shared by the public page (landing.php) and the admin editor (admin/landing-edit.php).

// Each template: colours, fonts, hero layout, section order, list style and motion effects.
//  hero:     banner (text on top, big photo below) | split (photo beside text) | overlay (text over photo) | video
//  list:     checks | cards | numbered | alt (rows that alternate with product photos)
//  reveal:   how sections appear while scrolling — up | zoom | side | blur | flip
//  fx:       floating particles — none | sparkle | confetti | leaves | hearts | stars | orbs
//  cta:      button animation — pulse | shine | shake | bounce | glow
//  img:      hero photo animation — none | float | kenburns | tilt | zoom | bounce
const IC_LANDING_TEMPLATES = [
    'classic' => [
        'name' => 'Classic Offer', 'bn' => 'ক্লাসিক অফার', 'for' => 'Any product · Facebook ads',
        'desc' => 'The proven Bangladeshi offer page: big Bangla headlines, offer price box and the order form at the bottom.',
        'fonts' => ['Hind Siliguri:wght@400;500;600;700'], 'head' => 'Hind Siliguri', 'body' => 'Hind Siliguri',
        'c' => ['bg' => '#ffffff', 'bg2' => '#f2f8f4', 'card' => '#ffffff', 'ink' => '#1f2937', 'muted' => '#4b5563', 'brand' => '#0b7a3b', 'accent' => '#e11d2e', 'cta' => '#0b7a3b', 'ctaInk' => '#ffffff', 'price' => '#e11d2e', 'line' => '#d9e9df'],
        'hero' => 'banner', 'list' => 'checks', 'reveal' => 'up', 'fx' => 'none', 'cta' => 'pulse', 'img' => 'none',
        'sections' => ['hero', 'badges', 'intro', 'benefits', 'offer', 'why', 'parts', 'gift', 'video', 'gallery', 'faq', 'contact', 'order', 'related'],
    ],
    'wood' => [
        'name' => 'Wood Premium', 'bn' => 'উড প্রিমিয়াম', 'for' => 'Wooden gifts · brand look',
        'desc' => 'Warm wood and gold colours of INSPIRED CREATIONS, floating product photo and shining buttons.',
        'fonts' => ['Tiro Bangla', 'Hind Siliguri:wght@400;500;600;700'], 'head' => 'Tiro Bangla', 'body' => 'Hind Siliguri',
        'c' => ['bg' => '#fbf6ef', 'bg2' => '#f3e8da', 'card' => '#fffdf9', 'ink' => '#3d1f0e', 'muted' => '#6b4a33', 'brand' => '#733f20', 'accent' => '#b8874d', 'cta' => '#733f20', 'ctaInk' => '#fff8ee', 'price' => '#9c3d12', 'line' => '#e8d6c0'],
        'hero' => 'split', 'list' => 'cards', 'reveal' => 'up', 'fx' => 'sparkle', 'cta' => 'shine', 'img' => 'float',
        'sections' => ['hero', 'badges', 'intro', 'benefits', 'parts', 'offer', 'why', 'gift', 'video', 'gallery', 'faq', 'order', 'contact', 'related'],
    ],
    'luxury' => [
        'name' => 'Royal Black Gold', 'bn' => 'রয়্যাল ব্ল্যাক গোল্ড', 'for' => 'Premium & expensive items',
        'desc' => 'Dark luxury page with shimmering gold headlines, twinkling stars and a slow zoom on the photo.',
        'fonts' => ['Noto Serif Bengali:wght@500;700', 'Hind Siliguri:wght@400;500;600'], 'head' => 'Noto Serif Bengali', 'body' => 'Hind Siliguri',
        'c' => ['bg' => '#0d0b09', 'bg2' => '#16120e', 'card' => '#1c1712', 'ink' => '#f5ead7', 'muted' => '#c2b197', 'brand' => '#e7c27d', 'accent' => '#f2d391', 'cta' => '#d9a94e', 'ctaInk' => '#1a1309', 'price' => '#f2d391', 'line' => '#3a2f22'],
        'hero' => 'overlay', 'list' => 'numbered', 'reveal' => 'blur', 'fx' => 'stars', 'cta' => 'shine', 'img' => 'kenburns',
        'sections' => ['hero', 'badges', 'intro', 'benefits', 'gift', 'offer', 'parts', 'why', 'video', 'gallery', 'faq', 'order', 'contact', 'related'],
    ],
    'flash' => [
        'name' => 'Flash Sale', 'bn' => 'ফ্ল্যাশ সেল', 'for' => 'Discount campaigns · deadlines',
        'desc' => 'Red and yellow sale page with moving stripes, a big countdown (when you set an end time) and a shaking order button.',
        'fonts' => ['Baloo Da 2:wght@500;700;800', 'Hind Siliguri:wght@400;500;600'], 'head' => 'Baloo Da 2', 'body' => 'Hind Siliguri',
        'c' => ['bg' => '#fff8e1', 'bg2' => '#ffefb8', 'card' => '#ffffff', 'ink' => '#2b1600', 'muted' => '#6d4c00', 'brand' => '#d50000', 'accent' => '#ff6d00', 'cta' => '#d50000', 'ctaInk' => '#ffffff', 'price' => '#d50000', 'line' => '#ffd970'],
        'hero' => 'banner', 'list' => 'cards', 'reveal' => 'zoom', 'fx' => 'none', 'cta' => 'shake', 'img' => 'bounce',
        'sections' => ['hero', 'offer', 'badges', 'benefits', 'parts', 'gift', 'why', 'video', 'gallery', 'faq', 'order', 'contact', 'related'],
    ],
    'gift' => [
        'name' => 'Festive Gift', 'bn' => 'ফেস্টিভ গিফট', 'for' => 'Eid, birthday, wedding gifts',
        'desc' => 'Maroon and gold gift page with gently falling confetti, a ribbon banner and bouncing button.',
        'fonts' => ['Galada', 'Hind Siliguri:wght@400;500;600;700'], 'head' => 'Galada', 'body' => 'Hind Siliguri',
        'c' => ['bg' => '#fff6f4', 'bg2' => '#fde9e7', 'card' => '#ffffff', 'ink' => '#3b0a12', 'muted' => '#7f3a45', 'brand' => '#9f1239', 'accent' => '#c8901c', 'cta' => '#9f1239', 'ctaInk' => '#ffffff', 'price' => '#9f1239', 'line' => '#f6cfd3'],
        'hero' => 'split', 'list' => 'cards', 'reveal' => 'flip', 'fx' => 'confetti', 'cta' => 'bounce', 'img' => 'float',
        'sections' => ['hero', 'gift', 'badges', 'intro', 'benefits', 'offer', 'parts', 'why', 'video', 'gallery', 'faq', 'order', 'contact', 'related'],
    ],
    'minimal' => [
        'name' => 'Clean Minimal', 'bn' => 'ক্লিন মিনিমাল', 'for' => 'Modern décor · clean look',
        'desc' => 'Black and white, lots of space, huge photo that zooms as you scroll, soft fade-ins.',
        'fonts' => ['Anek Bangla:wght@400;500;600;700'], 'head' => 'Anek Bangla', 'body' => 'Anek Bangla',
        'c' => ['bg' => '#ffffff', 'bg2' => '#f5f5f4', 'card' => '#ffffff', 'ink' => '#111111', 'muted' => '#5f5f5f', 'brand' => '#111111', 'accent' => '#8a6a3b', 'cta' => '#111111', 'ctaInk' => '#ffffff', 'price' => '#111111', 'line' => '#e5e5e5'],
        'hero' => 'banner', 'list' => 'numbered', 'reveal' => 'blur', 'fx' => 'none', 'cta' => 'glow', 'img' => 'zoom',
        'sections' => ['hero', 'intro', 'benefits', 'parts', 'offer', 'badges', 'why', 'gift', 'video', 'gallery', 'faq', 'order', 'contact', 'related'],
    ],
    'corporate' => [
        'name' => 'Corporate Blue', 'bn' => 'কর্পোরেট ব্লু', 'for' => 'Corporate & bulk orders',
        'desc' => 'Professional navy page for office gifts: cards slide in from the side, 3D tilt photo.',
        'fonts' => ['Noto Sans Bengali:wght@400;500;600;700'], 'head' => 'Noto Sans Bengali', 'body' => 'Noto Sans Bengali',
        'c' => ['bg' => '#f5f8fc', 'bg2' => '#e8f0fa', 'card' => '#ffffff', 'ink' => '#0f1e36', 'muted' => '#4a5b75', 'brand' => '#0b3b75', 'accent' => '#0284c7', 'cta' => '#0b3b75', 'ctaInk' => '#ffffff', 'price' => '#0b3b75', 'line' => '#d3e0f0'],
        'hero' => 'split', 'list' => 'cards', 'reveal' => 'side', 'fx' => 'none', 'cta' => 'shine', 'img' => 'tilt',
        'sections' => ['hero', 'badges', 'intro', 'benefits', 'parts', 'contact', 'offer', 'why', 'video', 'gallery', 'faq', 'order', 'related'],
    ],
    'nature' => [
        'name' => 'Eco Green', 'bn' => 'ইকো গ্রিন', 'for' => 'Natural wood · eco products',
        'desc' => 'Fresh green page with drifting leaves and wave dividers between sections.',
        'fonts' => ['Hind Siliguri:wght@400;500;600;700'], 'head' => 'Hind Siliguri', 'body' => 'Hind Siliguri',
        'c' => ['bg' => '#f6fbf2', 'bg2' => '#e6f2dc', 'card' => '#ffffff', 'ink' => '#1d3015', 'muted' => '#4d6343', 'brand' => '#2f6b1f', 'accent' => '#6aa531', 'cta' => '#2f6b1f', 'ctaInk' => '#ffffff', 'price' => '#2f6b1f', 'line' => '#cfe5bf'],
        'hero' => 'split', 'list' => 'checks', 'reveal' => 'up', 'fx' => 'leaves', 'cta' => 'pulse', 'img' => 'float',
        'sections' => ['hero', 'badges', 'intro', 'benefits', 'offer', 'parts', 'why', 'gift', 'video', 'gallery', 'faq', 'order', 'contact', 'related'],
    ],
    'pastel' => [
        'name' => 'Soft Pastel', 'bn' => 'সফট প্যাস্টেল', 'for' => 'Gifts for her · cute items',
        'desc' => 'Pink and lavender page with floating hearts, soft blobs and a gradient button.',
        'fonts' => ['Atma:wght@500;600;700', 'Hind Siliguri:wght@400;500;600'], 'head' => 'Atma', 'body' => 'Hind Siliguri',
        'c' => ['bg' => '#fff7fb', 'bg2' => '#f5ecff', 'card' => '#ffffff', 'ink' => '#3a2440', 'muted' => '#7a5f82', 'brand' => '#b0428f', 'accent' => '#8b5cf6', 'cta' => '#c0468f', 'ctaInk' => '#ffffff', 'price' => '#b0428f', 'line' => '#f0d5ea'],
        'hero' => 'banner', 'list' => 'cards', 'reveal' => 'zoom', 'fx' => 'hearts', 'cta' => 'bounce', 'img' => 'float',
        'sections' => ['hero', 'badges', 'gift', 'intro', 'benefits', 'offer', 'parts', 'why', 'video', 'gallery', 'faq', 'order', 'contact', 'related'],
    ],
    'neon' => [
        'name' => 'Modern Gradient', 'bn' => 'মডার্ন গ্রেডিয়েন্ট', 'for' => 'Young buyers · trendy look',
        'desc' => 'Dark page with moving purple-blue glow, glass cards, glowing button and 3D tilt photo.',
        'fonts' => ['Anek Bangla:wght@400;500;600;800'], 'head' => 'Anek Bangla', 'body' => 'Anek Bangla',
        'c' => ['bg' => '#0b1020', 'bg2' => '#11182f', 'card' => 'rgba(255,255,255,0.06)', 'ink' => '#eef2ff', 'muted' => '#a9b4e8', 'brand' => '#c4b5fd', 'accent' => '#22d3ee', 'cta' => '#7c3aed', 'ctaInk' => '#ffffff', 'price' => '#22d3ee', 'line' => 'rgba(255,255,255,0.12)'],
        'hero' => 'split', 'list' => 'cards', 'reveal' => 'zoom', 'fx' => 'orbs', 'cta' => 'glow', 'img' => 'tilt',
        'sections' => ['hero', 'badges', 'benefits', 'offer', 'intro', 'parts', 'why', 'gift', 'video', 'gallery', 'faq', 'order', 'contact', 'related'],
    ],
    'islamic' => [
        'name' => 'Islamic Elegant', 'bn' => 'ইসলামিক এলিগ্যান্ট', 'for' => 'Calligraphy · Islamic décor',
        'desc' => 'Emerald and gold with a slowly turning geometric star pattern and golden twinkles.',
        'fonts' => ['Noto Serif Bengali:wght@500;700', 'Hind Siliguri:wght@400;500;600'], 'head' => 'Noto Serif Bengali', 'body' => 'Hind Siliguri',
        'c' => ['bg' => '#f8f4e9', 'bg2' => '#ecf2e8', 'card' => '#fffdf6', 'ink' => '#12261d', 'muted' => '#4f5f55', 'brand' => '#0f5132', 'accent' => '#b8892e', 'cta' => '#0f5132', 'ctaInk' => '#fdf6e3', 'price' => '#0f5132', 'line' => '#dfe3cf'],
        'hero' => 'banner', 'list' => 'checks', 'reveal' => 'up', 'fx' => 'stars', 'cta' => 'shine', 'img' => 'float',
        'sections' => ['hero', 'badges', 'intro', 'benefits', 'offer', 'parts', 'why', 'gift', 'video', 'gallery', 'faq', 'order', 'contact', 'related'],
    ],
    'story' => [
        'name' => 'Storytelling', 'bn' => 'স্টোরি টেলিং', 'for' => 'Products that need explaining',
        'desc' => 'Long story page: each point gets its own row with a photo, sliding in from left and right, with parallax.',
        'fonts' => ['Tiro Bangla', 'Hind Siliguri:wght@400;500;600;700'], 'head' => 'Tiro Bangla', 'body' => 'Hind Siliguri',
        'c' => ['bg' => '#fffdf8', 'bg2' => '#f5efe4', 'card' => '#ffffff', 'ink' => '#2a2118', 'muted' => '#65574a', 'brand' => '#8a4b1d', 'accent' => '#c2410c', 'cta' => '#c2410c', 'ctaInk' => '#ffffff', 'price' => '#c2410c', 'line' => '#eadfcd'],
        'hero' => 'overlay', 'list' => 'alt', 'reveal' => 'side', 'fx' => 'none', 'cta' => 'pulse', 'img' => 'kenburns',
        'sections' => ['hero', 'intro', 'benefits', 'badges', 'parts', 'offer', 'why', 'gift', 'video', 'gallery', 'faq', 'order', 'contact', 'related'],
    ],
    'video' => [
        'name' => 'Video First', 'bn' => 'ভিডিও ফার্স্ট', 'for' => 'When you have a product video',
        'desc' => 'Dark cinema page that opens with your YouTube/Facebook video, then the offer. Shows the photo if no video is added.',
        'fonts' => ['Hind Siliguri:wght@400;500;600;700'], 'head' => 'Hind Siliguri', 'body' => 'Hind Siliguri',
        'c' => ['bg' => '#111827', 'bg2' => '#1a2333', 'card' => '#1f2937', 'ink' => '#f9fafb', 'muted' => '#a3acba', 'brand' => '#fbbf24', 'accent' => '#f87171', 'cta' => '#f59e0b', 'ctaInk' => '#111827', 'price' => '#fbbf24', 'line' => '#334155'],
        'hero' => 'video', 'list' => 'checks', 'reveal' => 'up', 'fx' => 'none', 'cta' => 'glow', 'img' => 'none',
        'sections' => ['hero', 'offer', 'badges', 'benefits', 'parts', 'why', 'gift', 'gallery', 'faq', 'order', 'contact', 'related'],
    ],
    'compact' => [
        'name' => 'Quick Order', 'bn' => 'কুইক অর্ডার', 'for' => 'Low-price items · fast buyers',
        'desc' => 'Short page: photo, price and the order form right away, then the details. Sticky order bar on mobile.',
        'fonts' => ['Noto Sans Bengali:wght@400;500;600;700'], 'head' => 'Noto Sans Bengali', 'body' => 'Noto Sans Bengali',
        'c' => ['bg' => '#f3f4f6', 'bg2' => '#ffffff', 'card' => '#ffffff', 'ink' => '#111827', 'muted' => '#6b7280', 'brand' => '#1d4ed8', 'accent' => '#dc2626', 'cta' => '#16a34a', 'ctaInk' => '#ffffff', 'price' => '#dc2626', 'line' => '#e5e7eb'],
        'hero' => 'split', 'list' => 'checks', 'reveal' => 'up', 'fx' => 'none', 'cta' => 'pulse', 'img' => 'none',
        'sections' => ['hero', 'order', 'badges', 'benefits', 'parts', 'gift', 'why', 'video', 'gallery', 'faq', 'contact', 'related'],
    ],
    'sunset' => [
        'name' => 'Warm Sunset', 'bn' => 'ওয়ার্ম সানসেট', 'for' => 'Bold, eye-catching campaigns',
        'desc' => 'Orange-pink sunset colours, rotating sun rays behind the photo, sparkles and bouncy cards.',
        'fonts' => ['Baloo Da 2:wght@500;700;800', 'Hind Siliguri:wght@400;500;600'], 'head' => 'Baloo Da 2', 'body' => 'Hind Siliguri',
        'c' => ['bg' => '#fff7ed', 'bg2' => '#ffeddc', 'card' => '#ffffff', 'ink' => '#431407', 'muted' => '#8a3a12', 'brand' => '#c2410c', 'accent' => '#db2777', 'cta' => '#ea580c', 'ctaInk' => '#ffffff', 'price' => '#c2410c', 'line' => '#fed7aa'],
        'hero' => 'banner', 'list' => 'cards', 'reveal' => 'zoom', 'fx' => 'sparkle', 'cta' => 'bounce', 'img' => 'bounce',
        'sections' => ['hero', 'badges', 'intro', 'benefits', 'offer', 'parts', 'gift', 'why', 'video', 'gallery', 'faq', 'order', 'contact', 'related'],
    ],
    'greenbar' => [
        'name' => 'Green Offer', 'bn' => 'গ্রিন অফার', 'for' => 'Popular BD style · food & daily items',
        'desc' => 'Green full-width section bars, bordered photo, big countdown, crossed-out price with the offer price circled, red order buttons and a boxed order form.',
        'fonts' => ['Hind Siliguri:wght@400;500;600;700'], 'head' => 'Hind Siliguri', 'body' => 'Hind Siliguri',
        'c' => ['bg' => '#ffffff', 'bg2' => '#ffffff', 'card' => '#ffffff', 'ink' => '#1f2937', 'muted' => '#4b5563', 'brand' => '#12a012', 'accent' => '#e3141c', 'cta' => '#e3141c', 'ctaInk' => '#ffffff', 'price' => '#e3141c', 'line' => '#e5e7eb'],
        'hero' => 'banner', 'list' => 'checks', 'reveal' => 'up', 'fx' => 'none', 'cta' => 'pulse', 'img' => 'none',
        'sections' => ['hero', 'benefits', 'offer', 'why', 'parts', 'gift', 'video', 'photo', 'gallery', 'faq', 'contact', 'order', 'related'],
        // Layout extras: intro text sits under the benefits bar, "why" points get arrow icons, the contact
        // part is a full-width bar, buttons carry a cart icon, and the price box uses these words
        'introInBenefits' => true, 'whyIcon' => 'arrowCircle', 'contactBand' => true, 'ctaIcon' => 'cart',
        'labels' => ['regular' => 'রেগুলার মূল্য', 'offer' => 'বর্তমান ডিসকাউন্ট মূল্য', 'plain' => 'মূল্য'],
    ],
    'power' => [
        'name' => 'Power Sell', 'bn' => 'পাওয়ার সেল', 'for' => 'One product · built to sell the most',
        'desc' => 'Colours picked automatically from your product photo. Quick 1/2/3-piece cards, real “ordered this week” count, floating badges, a helpful “before you go” quick-order box, scroll bar, morphing glow and magnetic buttons.',
        'fonts' => ['Anek Bangla:wght@500;600;700;800', 'Hind Siliguri:wght@400;500;600;700'], 'head' => 'Anek Bangla', 'body' => 'Hind Siliguri',
        // Used only when the photo's colour cannot be read
        'c' => ['bg' => '#f3faf9', 'bg2' => '#e3f3f1', 'card' => '#ffffff', 'ink' => '#0f2522', 'muted' => '#48605c', 'brand' => '#0f5f57', 'accent' => '#d9480f', 'cta' => '#0f766e', 'ctaInk' => '#ffffff', 'price' => '#c2410c', 'line' => '#cfe6e2'],
        'hero' => 'split', 'list' => 'cards', 'reveal' => 'zoom', 'fx' => 'sparkle', 'cta' => 'shine', 'img' => 'tilt',
        'sections' => ['hero', 'badges', 'benefits', 'gallery', 'video', 'offer', 'why', 'parts', 'gift', 'faq', 'order', 'contact', 'related'],
        'autoColor' => true, 'tiers' => true, 'floatBadges' => true, 'proof' => true, 'exitBox' => true, 'progress' => true, 'magnetic' => true,
    ],
];

// "Pay first" choices on landing pages (Bangla QR shows only once its QR image is uploaded in admin → Payment)
const IC_PAY_CHANNELS = ['bkash' => 'bKash', 'nagad' => 'Nagad', 'rocket' => 'Rocket', 'banglaqr' => 'Bangla QR'];

// The shop's Bangla QR (the merchant QR its bank / bKash / Nagad merchant account gave it)
function ic_bangla_qr(PDO $pdo): array
{
    $saved = json_decode((string) ic_setting($pdo, 'bangla_qr', '{}'), true) ?: [];
    return $saved + ['enabled' => 0, 'image' => '', 'merchant' => '', 'note' => ''];
}

function ic_bangla_qr_active(array $qr): bool
{
    return !empty($qr['enabled']) && $qr['image'] !== '' && is_file(IC_ROOT . '/' . $qr['image']);
}

const IC_LANDING_DEFAULT_TEMPLATE = 'classic';
const IC_LANDING_MAX_ADDONS = 6;

// Products shown under "সাথে নিতে পারেন" in the order form: the ones the shop picked (in its order, only
// those still on sale with a price), up to 3 suggestions from the same category, or none
function ic_landing_addons(PDO $pdo, array $product, array $data): array
{
    $mode = $data['addon_mode'] ?? 'auto';
    if ($mode === 'off') {
        return [];
    }
    $picked = array_filter(explode(',', (string) ($data['addon_ids'] ?? '')), 'strlen');
    if ($mode === 'pick') {
        $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ? AND id <> ? AND is_active = 1 AND price_bdt IS NOT NULL');
        $rows = [];
        foreach (array_slice($picked, 0, IC_LANDING_MAX_ADDONS) as $id) {
            $stmt->execute([$id, $product['id']]);
            if ($row = $stmt->fetch()) {
                $rows[] = $row;
            }
        }
        return $rows;
    }
    $stmt = $pdo->prepare('SELECT * FROM products WHERE is_active = 1 AND id <> ? AND price_bdt IS NOT NULL
        ORDER BY CASE WHEN category = ? THEN 0 ELSE 1 END, sort_order, title LIMIT 3');
    $stmt->execute([$product['id'], $product['category']]);
    return $stmt->fetchAll();
}

// Bangla headline used until the shop writes its own, by product category
const IC_LANDING_HEADLINES = [
    'boxes'     => 'প্রিয়জনকে দিন স্মরণীয় একটি উপহার',
    'wall'      => 'আপনার দেয়ালে আনুন কাঠের রাজকীয় সৌন্দর্য',
    'home'      => 'আপনার ঘর ও ডেস্ককে করুন আরও সুন্দর ও গোছানো',
    'keepsakes' => 'প্রিয় স্মৃতিকে ধরে রাখুন চিরদিনের জন্য',
    'corporate' => 'আপনার ব্র্যান্ডকে তুলে ধরুন প্রিমিয়াম কাঠের উপহারে',
];

// Text fields and their maximum length (lists are one item per line)
const IC_LANDING_TEXT_FIELDS = [
    'top_bar' => 600, 'offer_label' => 120, 'headline' => 200, 'highlight' => 250, 'subheadline' => 700, 'intro' => 2000,
    'benefits_title' => 150, 'benefits' => 2500, 'why_title' => 150, 'why' => 2500, 'parts_title' => 150, 'parts' => 1200,
    'gift' => 200, 'badges' => 400, 'stock_note' => 150, 'video_url' => 300, 'faq_title' => 150, 'faq' => 5000,
    'gallery_title' => 150, 'form_title' => 150, 'form_note' => 500, 'cta_text' => 60, 'phone' => 20, 'whatsapp' => 20,
    'countdown_end' => 16,
];

function ic_landing_template(?string $key): array
{
    $key = isset(IC_LANDING_TEMPLATES[$key]) ? $key : IC_LANDING_DEFAULT_TEMPLATE;
    return ['key' => $key] + IC_LANDING_TEMPLATES[$key];
}

// Default content for a product's landing page (used until the shop saves its own)
function ic_landing_defaults(array $product): array
{
    $benefits = [];
    if (trim((string) $product['wood']) !== '') {
        $benefits[] = 'উপকরণ: ' . trim($product['wood']);
    }
    if (trim((string) $product['finish']) !== '') {
        $benefits[] = 'ফিনিশ: ' . trim($product['finish']);
    }
    if (trim((string) $product['dimensions']) !== '') {
        $benefits[] = 'সাইজ: ' . trim($product['dimensions']);
    }
    $benefits[] = 'নিখুঁত লেজার কাটিং ও যত্নে হাতে করা ফিনিশিং';
    $benefits[] = 'অফিস, বাসা বা উপহার হিসেবে একদম পারফেক্ট';

    return [
        'top_bar'        => "✨ ছবিতে যা দেখছেন, বাস্তবে ঠিক তাই পাবেন ✨\n🚚 সারা বাংলাদেশে ক্যাশ অন ডেলিভারি\n🎁 উপহার হিসেবে পারফেক্ট",
        'offer_label'    => 'সীমিত সময়ের বিশেষ অফার',
        'headline'       => IC_LANDING_HEADLINES[$product['category']] ?? 'হাতে তৈরি প্রিমিয়াম কাঠের পণ্য',
        'highlight'      => (string) $product['title'],
        'subheadline'    => (string) $product['description'],
        'intro'          => '',
        'regular_price'  => null,
        'offer_price'    => null,
        'benefits_title' => 'কেন এই প্রোডাক্টটি আপনার জন্য পারফেক্ট?',
        'benefits'       => implode("\n", $benefits),
        'why_title'      => 'কেন আমাদের কাছ থেকে কিনবেন?',
        'why'            => "সারা বাংলাদেশে ক্যাশ অন ডেলিভারি — পণ্য হাতে পেয়ে টাকা দিন\nছবিতে যা দেখছেন, ঠিক তাই পাবেন\nঅর্ডারের পর আমাদের প্রতিনিধি ফোন করে কনফার্ম করবেন\nযেকোনো প্রশ্নে WhatsApp-এ সরাসরি সাপোর্ট",
        'parts_title'    => 'প্যাকেজে যা যা পাচ্ছেন',
        'parts'          => '',
        'gift'           => '',
        'badges'         => "ক্যাশ অন ডেলিভারি\nসারা দেশে হোম ডেলিভারি\nপ্রিমিয়াম কাঠের তৈরি\nWhatsApp সাপোর্ট",
        'stock_note'     => '',
        'countdown_end'  => '',
        'video_url'      => '',
        'faq_title'      => 'সাধারণ প্রশ্ন ও উত্তর',
        'faq'            => "কিভাবে অর্ডার করবো? | নিচের ফর্মে আপনার নাম, মোবাইল নম্বর ও সম্পূর্ণ ঠিকানা লিখে \"অর্ডার কনফার্ম করুন\" বাটনে চাপ দিন। আমরা ফোন করে অর্ডার কনফার্ম করবো।\nটাকা কখন দিতে হবে? | ক্যাশ অন ডেলিভারি — পণ্য হাতে পেয়ে ডেলিভারি ম্যানকে টাকা দিন।\nডেলিভারি চার্জ কত? | ঢাকার ভিতরে {dhaka} টাকা, ঢাকার বাইরে {outside} টাকা।",
        'gallery_title'  => 'আমাদের কাস্টমারদের পাঠানো ছবি',
        'form_title'     => 'অর্ডার করতে নিচের ফর্মটি পূরণ করুন',
        'form_note'      => 'আপনার নাম, মোবাইল নম্বর ও সম্পূর্ণ ঠিকানা লিখে "অর্ডার কনফার্ম করুন" বাটনে ক্লিক করুন।',
        'cta_text'       => 'এখনই অর্ডার করুন',
        'phone'          => '01705525009',
        'whatsapp'       => '01321744476',
        'payment'        => 'cod',
        'banking'        => 'bkash,nagad,banglaqr',
        'addon_mode'     => 'auto', // "সাথে নিতে পারেন" in the order form: auto suggestions | pick (addon_ids) | off
        'addon_ids'      => '',
        'show_related'   => 1,
        'show_customize' => 1,
        'images'         => [],
        'customer_photos' => [],
    ];
}

// The landing page row for a product (null when it was never saved or visited)
function ic_landing_row(PDO $pdo, string $productId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM landing_pages WHERE product_id = ?');
    $stmt->execute([$productId]);
    return $stmt->fetch() ?: null;
}

// Everything needed to show a product's landing page: content (saved or default), template and on/off
function ic_landing_for(PDO $pdo, array $product, ?array $row = null): array
{
    $row = $row ?? ic_landing_row($pdo, $product['id']);
    $defaults = ic_landing_defaults($product);
    $saved = $row && $row['data_json'] ? (json_decode($row['data_json'], true) ?: []) : [];
    return [
        'enabled'  => $row ? (bool) $row['is_enabled'] : ic_landing_auto_on($pdo),
        'template' => ($row && $row['template']) ? $row['template'] : ic_landing_default_template($pdo),
        'data'     => array_merge($defaults, array_intersect_key($saved, $defaults)),
        'custom'   => (bool) $saved,
        'views'    => $row ? (int) $row['views'] : 0,
    ];
}

function ic_landing_default_template(PDO $pdo): string
{
    $key = (string) ic_setting($pdo, 'landing_default_template', IC_LANDING_DEFAULT_TEMPLATE);
    return isset(IC_LANDING_TEMPLATES[$key]) ? $key : IC_LANDING_DEFAULT_TEMPLATE;
}

// New products get a landing page automatically unless the shop turned that off
function ic_landing_auto_on(PDO $pdo): bool
{
    return ic_setting($pdo, 'landing_auto_on', '1') === '1';
}

function ic_landing_save(PDO $pdo, string $productId, array $fields): void
{
    $row = ic_landing_row($pdo, $productId);
    $values = array_merge([
        'is_enabled' => $row ? (int) $row['is_enabled'] : (ic_landing_auto_on($pdo) ? 1 : 0),
        'template'   => $row['template'] ?? null,
        'data_json'  => $row['data_json'] ?? null,
        'views'      => $row ? (int) $row['views'] : 0,
    ], $fields);
    if ($row) {
        $pdo->prepare('UPDATE landing_pages SET is_enabled = ?, template = ?, data_json = ?, views = ?, updated_at = ? WHERE product_id = ?')
            ->execute([$values['is_enabled'], $values['template'], $values['data_json'], $values['views'], ic_now(), $productId]);
    } else {
        $pdo->prepare('INSERT INTO landing_pages (product_id, is_enabled, template, data_json, views, updated_at) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$productId, $values['is_enabled'], $values['template'], $values['data_json'], $values['views'], ic_now()]);
    }
}

function ic_landing_count_view(PDO $pdo, string $productId): void
{
    $stmt = $pdo->prepare('UPDATE landing_pages SET views = views + 1 WHERE product_id = ?');
    $stmt->execute([$productId]);
    if ($stmt->rowCount() === 0) {
        ic_landing_save($pdo, $productId, ['views' => 1]);
    }
}

// Editor form (lp[...]) → clean landing content. Photos are handled separately by the editor.
function ic_landing_from_post(array $post, array $current): array
{
    $data = $current;
    foreach (IC_LANDING_TEXT_FIELDS as $field => $max) {
        if (array_key_exists($field, $post)) {
            $value = str_replace("\r\n", "\n", (string) $post[$field]);
            $data[$field] = ic_clean($value, $max);
        }
    }
    $regular = preg_replace('/\D/', '', ic_bangla_digits((string) ($post['regular_price'] ?? '')));
    $data['regular_price'] = $regular === '' ? null : min(10000000, (int) $regular);
    $offer = preg_replace('/\D/', '', ic_bangla_digits((string) ($post['offer_price'] ?? '')));
    $data['offer_price'] = $offer === '' ? null : min(10000000, (int) $offer);
    $data['phone'] = preg_replace('/[^\d+]/', '', ic_bangla_digits($data['phone']));
    $data['whatsapp'] = preg_replace('/[^\d+]/', '', ic_bangla_digits($data['whatsapp']));
    if ($data['countdown_end'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $data['countdown_end'])) {
        $data['countdown_end'] = '';
    }
    if ($data['video_url'] !== '' && ic_landing_video_embed($data['video_url']) === null) {
        $data['video_url'] = '';
    }
    $data['payment'] = ($post['payment'] ?? 'cod') === 'both' ? 'both' : 'cod';
    $banking = array_values(array_intersect(array_keys(IC_PAY_CHANNELS), array_map('strval', (array) ($post['banking'] ?? []))));
    $data['banking'] = implode(',', $banking ?: ['bkash', 'nagad']);
    // Chosen add-on products, in the order they were ticked (up to 6; checked against the shop when shown)
    $addonIds = array_values(array_unique(array_filter(array_map('strval', (array) ($post['addon_ids'] ?? [])), function ($id) {
        return preg_match('/^[\w-]{1,60}$/', $id) === 1;
    })));
    $data['addon_ids'] = implode(',', array_slice($addonIds, 0, IC_LANDING_MAX_ADDONS));
    $data['addon_mode'] = in_array($post['addon_mode'] ?? '', ['auto', 'pick', 'off'], true) ? $post['addon_mode'] : 'auto';
    $data['show_related'] = empty($post['show_related']) ? 0 : 1;
    $data['show_customize'] = empty($post['show_customize']) ? 0 : 1;
    return $data;
}

function ic_bangla_digits(string $text): string
{
    return strtr($text, ['০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4', '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9']);
}

function ic_english_to_bangla_digits(string $text): string
{
    return strtr($text, ['0' => '০', '1' => '১', '2' => '২', '3' => '৩', '4' => '৪', '5' => '৫', '6' => '৬', '7' => '৭', '8' => '৮', '9' => '৯']);
}

// A real, time-limited offer: while it runs, the page AND the order use the offer price; when the time is up the
// offer ends by itself and the normal price is back, so the countdown is always true.
// $grace: extra seconds an order may still get the offer (a customer who pressed Order just before the end).
// Returns ['price' => offer price, 'end' => unix time] or null when there is no running offer.
function ic_landing_offer(array $data, ?int $price, int $grace = 0): ?array
{
    if ($price === null || empty($data['offer_price']) || ($data['countdown_end'] ?? '') === '') {
        return null;
    }
    $end = strtotime((string) $data['countdown_end']);
    $offer = (int) $data['offer_price'];
    if (!$end || time() > $end + $grace || $offer <= 0 || $offer >= $price) {
        return null;
    }
    return ['price' => $offer, 'end' => $end];
}

// YouTube or Facebook video link → embeddable player URL (null when not recognised)
function ic_landing_video_embed(string $url): ?string
{
    $url = trim($url);
    if (preg_match('~(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/)|youtu\.be/)([\w-]{11})~', $url, $m)) {
        return 'https://www.youtube-nocookie.com/embed/' . $m[1] . '?rel=0&modestbranding=1';
    }
    if (preg_match('~^https://(?:www\.|m\.|web\.)?(?:facebook\.com|fb\.watch)/~', $url)) {
        return 'https://www.facebook.com/plugins/video.php?href=' . rawurlencode($url) . '&show_text=false';
    }
    return null;
}

// "one\ntwo" → ['one', 'two'] (empty lines dropped)
function ic_lines(string $text): array
{
    return array_values(array_filter(array_map('trim', explode("\n", $text)), 'strlen'));
}

// Saves an uploaded landing photo (JPG/PNG/WEBP, max 5 MB) under assets/landing/; returns its path or an error
function ic_landing_store_image(array $file): array
{
    $types = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
    $info = $file['error'] === UPLOAD_ERR_OK ? @getimagesize($file['tmp_name']) : false;
    if (!$info || !isset($types[$info[2]])) {
        return [null, '“' . $file['name'] . '” is not a JPG, PNG or WEBP photo.'];
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        return [null, '“' . $file['name'] . '” is larger than 5 MB.'];
    }
    $dir = IC_ROOT . '/assets/landing';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $name = bin2hex(random_bytes(10)) . '.' . $types[$info[2]];
    if (!move_uploaded_file($file['tmp_name'], "$dir/$name")) {
        return [null, '“' . $file['name'] . '” could not be saved.'];
    }
    return ["assets/landing/$name", null];
}

// Colour palette that matches the product photo: finds the photo's main colourful hue and builds light
// backgrounds, dark headings and a strong button colour from it. Cached per photo; null if it can't be read.
function ic_landing_auto_palette(PDO $pdo, string $imagePath): ?array
{
    $file = IC_ROOT . '/' . ltrim($imagePath, '/');
    if (preg_match('#^(https?:|data:)#', $imagePath) || strpos($imagePath, '..') !== false || !is_file($file) || !function_exists('imagecreatefromstring')) {
        return null;
    }
    $key = 'lp_pal_' . md5($imagePath . '|' . filemtime($file));
    $cached = ic_setting($pdo, $key);
    if ($cached !== null) {
        return json_decode($cached, true) ?: null;
    }
    $img = @imagecreatefromstring((string) file_get_contents($file));
    if (!$img) {
        return null;
    }
    $small = imagecreatetruecolor(48, 48);
    imagecopyresampled($small, $img, 0, 0, 0, 0, 48, 48, imagesx($img), imagesy($img));
    imagedestroy($img);
    // Weight each hue (24 buckets of 15°) by how colourful its pixels are; skip whites, blacks and greys
    $buckets = [];
    for ($y = 0; $y < 48; $y++) {
        for ($x = 0; $x < 48; $x++) {
            $rgb = imagecolorat($small, $x, $y);
            [$h, $s, $l] = ic_rgb_to_hsl(($rgb >> 16) & 255, ($rgb >> 8) & 255, $rgb & 255);
            if ($s < 0.22 || $l < 0.14 || $l > 0.88) {
                continue;
            }
            $b = (int) floor($h / 15) % 24;
            $buckets[$b]['w'] = ($buckets[$b]['w'] ?? 0) + $s;
            $buckets[$b]['s'] = ($buckets[$b]['s'] ?? 0) + $s;
            $buckets[$b]['h'] = ($buckets[$b]['h'] ?? 0) + $h;
            $buckets[$b]['n'] = ($buckets[$b]['n'] ?? 0) + 1;
        }
    }
    imagedestroy($small);
    $palette = null;
    if ($buckets) {
        uasort($buckets, function ($a, $b) { return $b['w'] <=> $a['w']; });
        $top = reset($buckets);
        if ($top['n'] >= 40) { // enough colourful pixels to trust
            $h = $top['h'] / $top['n'];
            $s = max(0.45, min(0.8, $top['s'] / $top['n']));
            $hex = function (float $hh, float $ss, float $ll): string { return ic_hsl_to_hex(fmod($hh + 360, 360), $ss, $ll); };
            $palette = [
                'bg' => $hex($h, 0.5, 0.97), 'bg2' => $hex($h, 0.42, 0.925), 'card' => '#ffffff',
                'ink' => $hex($h, 0.3, 0.12), 'muted' => $hex($h, 0.14, 0.36),
                'brand' => $hex($h, $s, 0.27), 'accent' => $hex($h - 28, 0.85, 0.44),
                'cta' => $hex($h, min(0.9, $s + 0.15), 0.38), 'ctaInk' => '#ffffff',
                'price' => $hex($h - 18, 0.85, 0.4), 'line' => $hex($h, 0.35, 0.86),
            ];
        }
    }
    ic_set_setting($pdo, $key, json_encode($palette));
    return $palette;
}

function ic_rgb_to_hsl(int $r, int $g, int $b): array
{
    $r /= 255; $g /= 255; $b /= 255;
    $max = max($r, $g, $b); $min = min($r, $g, $b);
    $l = ($max + $min) / 2;
    if ($max === $min) {
        return [0.0, 0.0, $l];
    }
    $d = $max - $min;
    $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);
    if ($max === $r) {
        $h = ($g - $b) / $d + ($g < $b ? 6 : 0);
    } elseif ($max === $g) {
        $h = ($b - $r) / $d + 2;
    } else {
        $h = ($r - $g) / $d + 4;
    }
    return [$h * 60, $s, $l];
}

function ic_hsl_to_hex(float $h, float $s, float $l): string
{
    $c = (1 - abs(2 * $l - 1)) * $s;
    $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
    $m = $l - $c / 2;
    $sector = (int) floor($h / 60) % 6;
    [$r, $g, $b] = [[$c, $x, 0], [$x, $c, 0], [0, $c, $x], [0, $x, $c], [$x, 0, $c], [$c, 0, $x]][$sector];
    return sprintf('#%02x%02x%02x', (int) round(($r + $m) * 255), (int) round(($g + $m) * 255), (int) round(($b + $m) * 255));
}

// Real number of orders placed on a landing page in the last 7 days (fake-flagged and cancelled ones not counted)
function ic_landing_recent_orders(PDO $pdo, string $productId): int
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE details_json LIKE ? AND created_at >= ? AND status NOT IN ('fake', 'cancelled')");
    $stmt->execute(['%"landing":"' . $productId . '"%', date('Y-m-d H:i:s', strtotime('-7 days'))]);
    return (int) $stmt->fetchColumn();
}

function ic_landing_url(string $productId): string
{
    return 'landing.php?p=' . rawurlencode($productId);
}

// Orders that came from each landing page: [product_id => count]
function ic_landing_order_counts(PDO $pdo): array
{
    $counts = [];
    foreach ($pdo->query("SELECT details_json FROM orders WHERE details_json LIKE '%\"landing\"%'") as $row) {
        $id = json_decode($row['details_json'], true)['landing'] ?? null;
        if (is_string($id)) {
            $counts[$id] = ($counts[$id] ?? 0) + 1;
        }
    }
    return $counts;
}
