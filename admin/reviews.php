<?php
// Product → Reviews: approve or hide what customers wrote. Only approved reviews show on the website, and the
// product's star rating is worked out from them.
require __DIR__ . '/_inc.php';
require_admin();

$pdo = ic_db();
$tabs = ['pending' => 'Waiting', 'approved' => 'On website', 'rejected' => 'Hidden'];
$tab = isset($tabs[$_GET['tab'] ?? '']) ? (string) $_GET['tab'] : 'pending';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $id = (int) ($_POST['id'] ?? 0);
    $action = (string) ($_POST['action'] ?? '');
    $stmt = $pdo->prepare('SELECT * FROM reviews WHERE id = ?');
    $stmt->execute([$id]);
    if ($review = $stmt->fetch()) {
        if ($action === 'approve') {
            $pdo->prepare("UPDATE reviews SET status = 'approved', approved_at = ? WHERE id = ?")->execute([ic_now(), $id]);
            flash('Review is now on the website.');
        } elseif ($action === 'reject') {
            $pdo->prepare("UPDATE reviews SET status = 'rejected' WHERE id = ?")->execute([$id]);
            flash('Review hidden from the website.');
        } elseif ($action === 'delete') {
            $pdo->prepare('DELETE FROM reviews WHERE id = ?')->execute([$id]);
            if ($review['photo'] && strpos($review['photo'], 'assets/reviews/') === 0) {
                @unlink(IC_ROOT . '/assets/reviews/' . basename($review['photo']));
            }
            flash('Review deleted.');
        }
    }
    redirect('reviews.php?tab=' . $tab);
}

$counts = array_fill_keys(array_keys($tabs), 0);
foreach ($pdo->query('SELECT status, COUNT(*) AS n FROM reviews GROUP BY status') as $row) {
    $counts[$row['status']] = (int) $row['n'];
}
$stmt = $pdo->prepare('SELECT r.*, p.title AS product, p.image AS product_image, o.code AS order_code
    FROM reviews r LEFT JOIN products p ON p.id = r.product_id LEFT JOIN orders o ON o.id = r.order_id
    WHERE r.status = ? ORDER BY r.created_at DESC');
$stmt->execute([$tab]);
$reviews = $stmt->fetchAll();
$stats = ic_review_stats($pdo);
$stars = function (int $n) { return str_repeat('★', $n) . '<span class="text-stone-300">' . str_repeat('★', 5 - $n) . '</span>'; };

admin_header('Reviews', 'reviews.php');
?>
<div class="flex flex-wrap items-end justify-between gap-3 mb-6">
  <div>
    <p class="text-xs uppercase tracking-[0.25em] text-gold-600">Product</p>
    <h1 class="font-serif text-3xl text-wood-900 mt-1">Customer reviews</h1>
    <p class="text-sm text-stone-500 mt-1">Real reviews from customers whose order was delivered. Approve the ones to show on the website — the product's stars come only from these.</p>
  </div>
</div>

<div class="flex flex-wrap gap-2 mb-5">
  <?php foreach ($tabs as $key => $label): ?>
    <a href="reviews.php?tab=<?= $key ?>" class="btn <?= $tab === $key ? 'btn-dark' : 'btn-light' ?> !py-1.5"><?= $label ?> <span class="text-xs opacity-75">(<?= $counts[$key] ?>)</span></a>
  <?php endforeach; ?>
</div>

<div class="grid lg:grid-cols-[minmax(0,1fr)_300px] gap-6 items-start">
  <div class="space-y-4">
    <?php if (!$reviews): ?>
      <div class="card p-8 text-center text-stone-500">
        <i class="fa-regular fa-comment-dots text-3xl text-gold-500"></i>
        <p class="mt-2"><?= $tab === 'pending' ? 'No reviews waiting.' : 'Nothing here yet.' ?></p>
        <p class="text-xs mt-1">Ask delivered customers for a review from the order page (“Ask for review on WhatsApp”). Customers who gave an email get the link automatically when the order is Delivered.</p>
      </div>
    <?php endif; ?>
    <?php foreach ($reviews as $r): ?>
      <div class="card p-4 sm:p-5 flex gap-4">
        <img src="<?= h(asset_url((string) $r['product_image'])) ?>" alt="" class="w-14 h-14 rounded-xl object-cover flex-shrink-0 hidden sm:block">
        <div class="flex-1 min-w-0">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="text-amber-500 text-lg leading-none"><?= $stars((int) $r['rating']) ?></p>
            <p class="text-xs text-stone-400"><?= h(date('d M Y, g:i a', strtotime($r['created_at']))) ?></p>
          </div>
          <p class="font-semibold text-wood-900 mt-1"><?= h($r['name']) ?><?= $r['district'] ? ' <span class="font-normal text-stone-500">· ' . h($r['district']) . '</span>' : '' ?></p>
          <p class="text-xs text-stone-500">On <strong><?= h($r['product'] ?? $r['product_id']) ?></strong> · order <a class="underline" href="orders.php?q=<?= h(rawurlencode((string) $r['order_code'])) ?>"><?= h($r['order_code']) ?></a></p>
          <?php if ($r['body']): ?><p class="mt-2 whitespace-pre-line text-stone-700"><?= h($r['body']) ?></p><?php endif; ?>
          <?php if ($r['photo']): ?><a href="<?= h(asset_url($r['photo'])) ?>" target="_blank"><img src="<?= h(asset_url($r['photo'])) ?>" alt="Customer photo" class="mt-3 w-40 h-40 object-cover rounded-xl border border-cream-300"></a><?php endif; ?>
          <form method="post" class="mt-3 flex flex-wrap gap-2">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
            <?php if ($r['status'] !== 'approved'): ?><button name="action" value="approve" class="btn btn-gold !py-1.5"><i class="fa-solid fa-check"></i> Show on website</button><?php endif; ?>
            <?php if ($r['status'] !== 'rejected'): ?><button name="action" value="reject" class="btn btn-light !py-1.5"><i class="fa-regular fa-eye-slash"></i> Hide</button><?php endif; ?>
            <button name="action" value="delete" class="btn btn-danger !py-1.5" onclick="return confirm('Delete this review for good?');"><i class="fa-regular fa-trash-can"></i></button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <aside class="card p-5 space-y-3">
    <h2 class="font-serif text-lg text-wood-900">Stars on the website</h2>
    <?php if (!$stats): ?><p class="text-sm text-stone-500">No approved reviews yet, so products show no stars.</p><?php endif; ?>
    <?php foreach ($stats as $pid => $s):
        $t = $pdo->prepare('SELECT title FROM products WHERE id = ?'); $t->execute([$pid]); ?>
      <div class="flex items-center justify-between gap-2 text-sm"><span class="truncate"><?= h((string) ($t->fetchColumn() ?: $pid)) ?></span><span class="text-amber-600 font-semibold whitespace-nowrap">★ <?= $s['rating'] ?> <span class="text-stone-400 font-normal">(<?= $s['count'] ?>)</span></span></div>
    <?php endforeach; ?>
    <p class="text-xs text-stone-400 pt-2 border-t border-cream-200">Be fair: approve honest reviews even when they are not 5 stars — that is what makes customers trust them. Hide only rude, spam or off-topic ones.</p>
  </aside>
</div>
<?php admin_footer();
