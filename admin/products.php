<?php
require __DIR__ . '/_inc.php';
require_admin();

$pdo = ic_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->execute([(string) ($_POST['id'] ?? '')]);
    $product = $stmt->fetch();
    if ($product) {
        if (($_POST['action'] ?? '') === 'toggle') {
            $pdo->prepare('UPDATE products SET is_active = ?, updated_at = ? WHERE id = ?')
                ->execute([$product['is_active'] ? 0 : 1, ic_now(), $product['id']]);
            flash($product['is_active'] ? "“{$product['title']}” is now hidden from the website." : "“{$product['title']}” is now visible on the website.");
        } elseif (($_POST['action'] ?? '') === 'delete') {
            $pdo->prepare('DELETE FROM products WHERE id = ?')->execute([$product['id']]);
            // Only images uploaded through the admin panel live in assets/products/
            if (strpos($product['image'], 'assets/products/') === 0) {
                @unlink(IC_ROOT . '/assets/products/' . basename($product['image']));
            }
            flash("“{$product['title']}” was deleted.");
        }
    }
    redirect('products.php');
}

$products = $pdo->query('SELECT * FROM products ORDER BY sort_order, title')->fetchAll();

admin_header('Products', 'products.php');
?>
<div class="flex flex-wrap items-end justify-between gap-3 mb-6">
  <h1 class="font-serif text-3xl text-wood-900">Products <span class="text-base font-sans text-stone-400">(<?= count($products) ?>)</span></h1>
  <a href="product.php" class="btn btn-gold"><i class="fa-solid fa-plus"></i> Add product</a>
</div>

<div class="card overflow-hidden">
  <div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-cream-200">
      <thead class="bg-cream-50">
        <tr><th class="th">Product</th><th class="th">Category</th><th class="th">Price</th><th class="th">Order</th><th class="th">Website</th><th class="th text-right">Actions</th></tr>
      </thead>
      <tbody class="divide-y divide-cream-200">
        <?php foreach ($products as $p): ?>
          <tr class="hover:bg-cream-50 <?= $p['is_active'] ? '' : 'opacity-60' ?>">
            <td class="td">
              <div class="flex items-center gap-3">
                <img src="<?= h(asset_url($p['image'])) ?>" alt="" class="w-14 h-14 rounded-lg object-cover bg-cream-100 flex-shrink-0">
                <span>
                  <a href="product.php?id=<?= h(rawurlencode($p['id'])) ?>" class="font-semibold text-wood-900 hover:underline"><?= h($p['title']) ?></a>
                  <span class="block text-xs text-stone-400"><?= h($p['badge']) ?></span>
                  <?php if (!empty($p['is_customizable'])): ?>
                    <span class="inline-flex items-center gap-1 mt-1 rounded-full bg-gold-200/60 px-2 py-0.5 text-[10px] font-semibold text-gold-700"><i class="fa-solid fa-pen-ruler"></i> Customizable</span>
                  <?php endif; ?>
                </span>
              </div>
            </td>
            <td class="td"><?= h(IC_CATEGORIES[$p['category']] ?? $p['category']) ?></td>
            <td class="td whitespace-nowrap"><?= $p['price_bdt'] === null ? '<span class="text-stone-400">On request</span>' : money($p['price_bdt']) ?></td>
            <td class="td"><?= (int) $p['sort_order'] ?></td>
            <td class="td">
              <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= h($p['id']) ?>">
                <input type="hidden" name="action" value="toggle">
                <button class="btn !px-3 !py-1 text-xs <?= $p['is_active'] ? 'bg-emerald-100 text-emerald-800' : 'bg-stone-200 text-stone-600' ?>">
                  <?= $p['is_active'] ? 'Visible' : 'Hidden' ?>
                </button>
              </form>
            </td>
            <td class="td text-right whitespace-nowrap">
              <a href="product.php?id=<?= h(rawurlencode($p['id'])) ?>" class="btn btn-light !px-3 !py-1.5"><i class="fa-regular fa-pen-to-square"></i> Edit</a>
              <a href="landing-edit.php?id=<?= h(rawurlencode($p['id'])) ?>" class="btn btn-light !px-3 !py-1.5" title="Landing page"><i class="fa-solid fa-wand-magic-sparkles"></i> Page</a>
              <a href="customers.php?product=<?= h(rawurlencode($p['id'])) ?>" class="btn btn-light !px-3 !py-1.5" title="Customers who bought this"><i class="fa-solid fa-users"></i></a>
              <form method="post" class="inline" onsubmit="return confirm('Delete “<?= h(addslashes($p['title'])) ?>” permanently?');">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= h($p['id']) ?>">
                <input type="hidden" name="action" value="delete">
                <button class="btn btn-danger !px-3 !py-1.5"><i class="fa-regular fa-trash-can"></i></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php admin_footer();
