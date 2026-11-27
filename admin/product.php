<?php
// Add a new product (no ?id) or edit an existing one (?id=...)
require __DIR__ . '/_inc.php';
require_admin();

$pdo = ic_db();
$shipDefaults = ic_shipping_defaults($pdo);
$id = (string) ($_GET['id'] ?? '');
$product = null;
if ($id !== '') {
    $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->execute([$id]);
    $product = $stmt->fetch();
    if (!$product) {
        flash('That product no longer exists.');
        redirect('products.php');
    }
}

$error = null;
$form = $product ?: [
    'title' => '', 'category' => 'home', 'price_bdt' => null, 'price_usd' => null, 'badge' => '', 'image' => '',
    'wood' => '', 'dimensions' => '', 'finish' => '', 'description' => '', 'rating' => null, 'review_count' => null,
    'sort_order' => 0, 'is_active' => 1, 'is_customizable' => 0, 'ship_dhaka' => null, 'ship_outside' => null, 'free_shipping' => 0,
];

$numberOrNull = function (string $key, bool $decimal = false) {
    $value = trim((string) ($_POST[$key] ?? ''));
    if ($value === '') {
        return null;
    }
    return $decimal ? round(max(0, (float) $value), 2) : max(0, (int) $value);
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $form = [
        'title'           => ic_clean((string) ($_POST['title'] ?? ''), 200),
        'category'        => isset(IC_CATEGORIES[$_POST['category'] ?? '']) ? $_POST['category'] : 'home',
        'price_bdt'       => $numberOrNull('price_bdt'),
        'price_usd'       => $numberOrNull('price_usd', true),
        'badge'           => ic_clean((string) ($_POST['badge'] ?? ''), 60),
        'image'           => $product['image'] ?? '',
        'wood'            => ic_clean((string) ($_POST['wood'] ?? ''), 255),
        'dimensions'      => ic_clean((string) ($_POST['dimensions'] ?? ''), 255),
        'finish'          => ic_clean((string) ($_POST['finish'] ?? ''), 120),
        'description'     => ic_clean((string) ($_POST['description'] ?? ''), 2000),
        'rating'          => $numberOrNull('rating', true),
        'review_count'    => $numberOrNull('review_count'),
        'sort_order'      => (int) ($_POST['sort_order'] ?? 0),
        'is_active'       => empty($_POST['is_active']) ? 0 : 1,
        'is_customizable' => empty($_POST['is_customizable']) ? 0 : 1,
        'ship_dhaka'      => $numberOrNull('ship_dhaka'),
        'ship_outside'    => $numberOrNull('ship_outside'),
        'free_shipping'   => empty($_POST['free_shipping']) ? 0 : 1,
    ];
    if ($form['rating'] !== null) {
        $form['rating'] = min(5, $form['rating']);
    }

    // New photo: images only, checked by content (not just the file name), stored under a random name
    $upload = $_FILES['image'] ?? null;
    if ($upload && $upload['error'] !== UPLOAD_ERR_NO_FILE) {
        $types = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
        $info = $upload['error'] === UPLOAD_ERR_OK ? @getimagesize($upload['tmp_name']) : false;
        if (!$info || !isset($types[$info[2]])) {
            $error = 'Please upload a JPG, PNG or WEBP photo.';
        } elseif ($upload['size'] > 5 * 1024 * 1024) {
            $error = 'Please upload a photo smaller than 5 MB.';
        } else {
            $dir = IC_ROOT . '/assets/products';
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $name = bin2hex(random_bytes(10)) . '.' . $types[$info[2]];
            if (move_uploaded_file($upload['tmp_name'], "$dir/$name")) {
                if (strpos($form['image'], 'assets/products/') === 0) {
                    @unlink($dir . '/' . basename($form['image']));
                }
                $form['image'] = "assets/products/$name";
            } else {
                $error = 'The photo could not be saved. Please try again.';
            }
        }
    }

    if (!$error && $form['title'] === '') {
        $error = 'Please enter a product name.';
    } elseif (!$error && $form['image'] === '') {
        $error = 'Please upload a product photo.';
    } elseif (!$error && $form['description'] === '') {
        $error = 'Please write a short description.';
    }

    if (!$error) {
        $values = [
            $form['title'], $form['category'], $form['price_bdt'], $form['price_usd'], $form['badge'], $form['image'],
            $form['wood'], $form['dimensions'], $form['finish'], $form['description'], $form['rating'], $form['review_count'],
            $form['sort_order'], $form['is_active'], $form['is_customizable'], $form['ship_dhaka'], $form['ship_outside'],
            $form['free_shipping'], ic_now(),
        ];
        if ($product) {
            $values[] = $product['id'];
            $pdo->prepare('UPDATE products SET title = ?, category = ?, price_bdt = ?, price_usd = ?, badge = ?, image = ?, wood = ?,
                dimensions = ?, finish = ?, description = ?, rating = ?, review_count = ?, sort_order = ?, is_active = ?,
                is_customizable = ?, ship_dhaka = ?, ship_outside = ?, free_shipping = ?, updated_at = ? WHERE id = ?')->execute($values);
            flash("“{$form['title']}” was saved.");
        } else {
            $slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($form['title'])), '-');
            $newId = substr($slug !== '' ? $slug : 'product', 0, 45) . '-' . bin2hex(random_bytes(2));
            array_unshift($values, $newId);
            $pdo->prepare('INSERT INTO products (id, title, category, price_bdt, price_usd, badge, image, wood, dimensions, finish,
                description, rating, review_count, sort_order, is_active, is_customizable, ship_dhaka, ship_outside, free_shipping, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute($values);
            flash("“{$form['title']}” was added to the website.");
        }
        redirect('products.php');
    }
}

// A small on/off switch used throughout this form
$switch = function (string $name, bool $on, string $title, string $hint) {
    echo '<label class="flex items-start justify-between gap-4 cursor-pointer">'
        . '<span><span class="block text-sm font-semibold text-wood-900">' . h($title) . '</span>'
        . '<span class="block text-xs text-stone-500 mt-0.5">' . h($hint) . '</span></span>'
        . '<span class="relative flex-shrink-0 mt-0.5">'
        . '<input type="checkbox" name="' . h($name) . '" value="1" class="peer sr-only" ' . ($on ? 'checked' : '') . '>'
        . '<span class="block w-11 h-6 rounded-full bg-stone-300 transition peer-checked:bg-emerald-600 peer-focus-visible:ring-2 peer-focus-visible:ring-gold-300"></span>'
        . '<span class="absolute top-0.5 left-0.5 w-5 h-5 rounded-full bg-white shadow transition peer-checked:translate-x-5"></span>'
        . '</span></label>';
};
$step = function (int $n, string $title, string $hint = '') {
    echo '<div class="flex items-center gap-3 mb-4"><span class="w-7 h-7 rounded-full bg-wood-800 text-gold-200 text-xs font-bold flex items-center justify-center">' . $n . '</span>'
        . '<div><h2 class="font-serif text-lg text-wood-900 leading-tight">' . h($title) . '</h2>'
        . ($hint !== '' ? '<p class="text-xs text-stone-500">' . h($hint) . '</p>' : '') . '</div></div>';
};

admin_header($product ? 'Edit product' : 'Add product', 'products.php');
?>
<div class="flex flex-wrap items-end justify-between gap-3 mb-6">
  <div>
    <a href="products.php" class="text-sm font-semibold text-gold-700 hover:text-wood-900">← All products</a>
    <h1 class="font-serif text-3xl text-wood-900 mt-1"><?= $product ? 'Edit product' : 'Add a new product' ?></h1>
    <p class="text-sm text-stone-500 mt-1"><?= $product ? 'Changes go live on the website as soon as you save.' : 'Fill in the details — the preview on the right shows how customers will see it.' ?></p>
  </div>
  <?php if ($product): ?>
    <a href="../#/product/<?= h(rawurlencode($product['id'])) ?>" target="_blank" class="btn btn-light"><i class="fa-solid fa-arrow-up-right-from-square"></i> View on website</a>
  <?php endif; ?>
</div>

<?php if ($error): ?>
  <div class="mb-6 rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800"><?= h($error) ?></div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="grid grid-cols-1 lg:grid-cols-3 gap-6" id="productForm">
  <?= csrf_field() ?>
  <div class="lg:col-span-2 space-y-6">

    <section class="card p-5 sm:p-6">
      <?php $step(1, 'Basic information', 'Name, category and an optional label'); ?>
      <div class="space-y-4">
        <div>
          <label class="label" for="title">Product name *</label>
          <input class="input text-base" id="title" name="title" required maxlength="200" value="<?= h($form['title']) ?>" placeholder="e.g. Engraved Walnut Name Plate">
        </div>
        <div>
          <p class="label">Category</p>
          <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
            <?php foreach (IC_CATEGORIES as $key => $label): ?>
              <label class="cursor-pointer">
                <input type="radio" name="category" value="<?= $key ?>" class="peer sr-only" <?= $form['category'] === $key ? 'checked' : '' ?>>
                <span class="block rounded-xl border border-cream-300 bg-white px-3 py-2.5 text-sm text-center transition peer-checked:border-wood-800 peer-checked:bg-wood-800 peer-checked:text-white hover:border-gold-500"><?= h($label) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>
        <div>
          <label class="label" for="badge">Badge on the photo (optional)</label>
          <input class="input" id="badge" name="badge" maxlength="60" value="<?= h($form['badge']) ?>" placeholder="e.g. New, Corporate, Islamic Decor">
        </div>
      </div>
    </section>

    <section class="card p-5 sm:p-6">
      <?php $step(2, 'Photo', 'JPG, PNG or WEBP up to 5 MB — landscape (4:3) looks best'); ?>
      <label id="dropZone" for="imageInput" class="flex flex-col items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-cream-300 bg-cream-50 p-6 text-center cursor-pointer transition hover:border-gold-500">
        <img id="imagePreview" src="<?= $form['image'] !== '' ? h(asset_url($form['image'])) : '' ?>" alt="" class="<?= $form['image'] !== '' ? '' : 'hidden' ?> max-h-64 rounded-xl object-contain">
        <i class="fa-solid fa-cloud-arrow-up text-3xl text-gold-500"></i>
        <span class="text-sm font-semibold text-wood-900"><?= $form['image'] !== '' ? 'Drop a new photo or click to replace' : 'Drop a photo here or click to choose' ?></span>
      </label>
      <input type="file" name="image" id="imageInput" accept="image/jpeg,image/png,image/webp" class="sr-only">
    </section>

    <section class="card p-5 sm:p-6">
      <?php $step(3, 'Price', 'Leave Taka empty to show “Price on request” with a WhatsApp quote button'); ?>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label class="label" for="price_bdt">Price in Taka</label>
          <div class="relative"><span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-stone-400">৳</span>
            <input class="input !pl-8" id="price_bdt" name="price_bdt" type="number" min="0" value="<?= h($form['price_bdt']) ?>" placeholder="Price on request"></div>
        </div>
        <div>
          <label class="label" for="price_usd">Price in USD</label>
          <div class="relative"><span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-stone-400">$</span>
            <input class="input !pl-8" id="price_usd" name="price_usd" type="number" min="0" step="0.01" value="<?= h($form['price_usd']) ?>"></div>
        </div>
      </div>
    </section>

    <section class="card p-5 sm:p-6">
      <?php $step(4, 'Description & details'); ?>
      <div class="space-y-4">
        <div>
          <div class="flex justify-between"><label class="label" for="description">Description *</label><span id="descCount" class="text-xs text-stone-400"></span></div>
          <textarea class="input" id="description" name="description" rows="4" required maxlength="2000" placeholder="What makes this piece special — materials, use, who it's for…"><?= h($form['description']) ?></textarea>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div><label class="label" for="wood">Material</label><input class="input" id="wood" name="wood" value="<?= h($form['wood']) ?>" placeholder="e.g. Solid walnut"></div>
          <div><label class="label" for="dimensions">Size</label><input class="input" id="dimensions" name="dimensions" value="<?= h($form['dimensions']) ?>" placeholder="e.g. 8&quot; x 6&quot;"></div>
          <div><label class="label" for="finish">Finish</label><input class="input" id="finish" name="finish" value="<?= h($form['finish']) ?>" placeholder="e.g. Hand-finished"></div>
        </div>
      </div>
    </section>

    <section class="card p-5 sm:p-6">
      <?php $step(5, 'Delivery charge', "Leave empty to use the shop default (৳{$shipDefaults['dhaka']} Dhaka / ৳{$shipDefaults['outside']} outside)"); ?>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label class="label" for="ship_dhaka">Inside Dhaka</label>
          <div class="relative"><span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-stone-400">৳</span>
            <input class="input !pl-8" id="ship_dhaka" name="ship_dhaka" type="number" min="0" value="<?= h($form['ship_dhaka']) ?>" placeholder="<?= $shipDefaults['dhaka'] ?> (default)"></div>
        </div>
        <div>
          <label class="label" for="ship_outside">Outside Dhaka</label>
          <div class="relative"><span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-stone-400">৳</span>
            <input class="input !pl-8" id="ship_outside" name="ship_outside" type="number" min="0" value="<?= h($form['ship_outside']) ?>" placeholder="<?= $shipDefaults['outside'] ?> (default)"></div>
        </div>
      </div>
      <div class="mt-4 rounded-xl bg-emerald-50/60 border border-emerald-100 p-4">
        <?php $switch('free_shipping', (bool) $form['free_shipping'], 'Free delivery', 'No delivery charge for this product, with an animated “Free Delivery” card on the website.'); ?>
      </div>
    </section>

    <section class="card p-5 sm:p-6">
      <?php $step(6, 'Customer reviews', 'Only from real customers — leave empty to hide the stars'); ?>
      <div class="grid grid-cols-2 gap-4">
        <div><label class="label" for="rating">Rating (1–5)</label><input class="input" id="rating" name="rating" type="number" min="1" max="5" step="0.1" value="<?= h($form['rating']) ?>"></div>
        <div><label class="label" for="review_count">Number of reviews</label><input class="input" id="review_count" name="review_count" type="number" min="0" value="<?= h($form['review_count']) ?>"></div>
      </div>
    </section>
  </div>

  <aside class="space-y-6 lg:sticky lg:top-6 self-start">
    <section class="card overflow-hidden">
      <p class="px-5 pt-4 text-[10px] font-semibold uppercase tracking-[0.2em] text-stone-400">Live preview</p>
      <div class="m-4 rounded-3xl border border-cream-300 bg-cream-50 overflow-hidden shadow-sm">
        <div class="relative aspect-[4/3] bg-cream-200">
          <img id="pvImage" src="<?= $form['image'] !== '' ? h(asset_url($form['image'])) : '' ?>" alt="" class="w-full h-full object-cover <?= $form['image'] !== '' ? '' : 'hidden' ?>">
          <span id="pvBadge" class="absolute top-3 left-3 bg-cream-50/90 text-wood-800 text-[10px] font-bold px-3 py-1 rounded-full uppercase tracking-[0.15em] <?= $form['badge'] !== '' ? '' : 'hidden' ?>"><?= h($form['badge']) ?></span>
          <span id="pvFree" class="absolute bottom-3 left-3 bg-emerald-600 text-white text-[10px] font-bold px-3 py-1 rounded-full uppercase tracking-[0.12em] <?= $form['free_shipping'] ? '' : 'hidden' ?>"><i class="fa-solid fa-truck-fast"></i> Free Delivery</span>
        </div>
        <div class="p-4">
          <p id="pvCategory" class="text-[10px] font-semibold uppercase tracking-[0.2em] text-gold-700"></p>
          <p id="pvTitle" class="font-serif text-lg text-wood-900 mt-1">Product name</p>
          <p id="pvDesc" class="text-xs text-stone-500 mt-1 line-clamp-2"></p>
          <div class="mt-3 pt-3 border-t border-cream-300 flex items-center justify-between">
            <span id="pvPrice" class="font-semibold text-wood-900"></span>
            <span id="pvCustom" class="text-[11px] font-semibold text-gold-700 <?= $form['is_customizable'] ? '' : 'hidden' ?>"><i class="fa-solid fa-pen-ruler"></i> Customize</span>
          </div>
        </div>
      </div>
    </section>

    <section class="card p-5 space-y-5">
      <?php
      $switch('is_active', (bool) $form['is_active'], 'Online', 'Visible in the shop. Turn off to hide it without deleting.');
      $switch('is_customizable', (bool) $form['is_customizable'], 'Customizable', 'Shows the Customize button and design chart (marks, logo, text).');
      ?>
      <div>
        <label class="label" for="sort_order">Display order</label>
        <input class="input" id="sort_order" name="sort_order" type="number" value="<?= h($form['sort_order']) ?>">
        <p class="text-xs text-stone-400 mt-1">Lower numbers show first in the shop.</p>
      </div>
      <button class="btn btn-gold w-full !py-3"><i class="fa-regular fa-floppy-disk"></i> <?= $product ? 'Save changes' : 'Publish product' ?></button>
    </section>
  </aside>
</form>

<script>
  (() => {
    const $ = (id) => document.getElementById(id);
    const categories = <?= json_encode(IC_CATEGORIES, JSON_UNESCAPED_UNICODE) ?>;
    const update = () => {
      $('pvTitle').textContent = $('title').value.trim() || 'Product name';
      $('pvDesc').textContent = $('description').value.trim();
      $('descCount').textContent = `${$('description').value.length} / 2000`;
      const cat = document.querySelector('input[name="category"]:checked');
      $('pvCategory').textContent = cat ? categories[cat.value] : '';
      const badge = $('badge').value.trim();
      $('pvBadge').textContent = badge;
      $('pvBadge').classList.toggle('hidden', !badge);
      const price = $('price_bdt').value;
      $('pvPrice').textContent = price !== '' ? '৳' + Number(price).toLocaleString('en-US') : 'Price on request';
      $('pvFree').classList.toggle('hidden', !document.querySelector('input[name="free_shipping"]').checked);
      $('pvCustom').classList.toggle('hidden', !document.querySelector('input[name="is_customizable"]').checked);
    };
    $('productForm').addEventListener('input', update);
    $('productForm').addEventListener('change', update);
    update();

    // Photo: preview immediately, also when dropped onto the box
    const showPhoto = (file) => {
      if (!file || !file.type.startsWith('image/')) return;
      const url = URL.createObjectURL(file);
      [$('imagePreview'), $('pvImage')].forEach(img => { img.src = url; img.classList.remove('hidden'); });
    };
    $('imageInput').addEventListener('change', () => showPhoto($('imageInput').files[0]));
    const zone = $('dropZone');
    ['dragenter', 'dragover'].forEach(ev => zone.addEventListener(ev, (e) => { e.preventDefault(); zone.classList.add('border-gold-500', 'bg-gold-200/20'); }));
    ['dragleave', 'drop'].forEach(ev => zone.addEventListener(ev, () => zone.classList.remove('border-gold-500', 'bg-gold-200/20')));
    zone.addEventListener('drop', (e) => {
      e.preventDefault();
      if (!e.dataTransfer.files.length) return;
      const dt = new DataTransfer();
      dt.items.add(e.dataTransfer.files[0]);
      $('imageInput').files = dt.files;
      showPhoto(dt.files[0]);
    });
  })();
</script>
<?php admin_footer();
