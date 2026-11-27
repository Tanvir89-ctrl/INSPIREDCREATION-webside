<?php
// Payment → Bangla QR: upload the shop's merchant Bangla QR so customers can scan and pay first
// (on the website checkout and on landing pages), then enter the Transaction ID.
require __DIR__ . '/_inc.php';
require_admin();

$pdo = ic_db();
$qr = ic_bangla_qr($pdo);
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $action = (string) ($_POST['action'] ?? 'save');
    $old = $qr['image'];
    if ($action === 'remove') {
        $qr['image'] = '';
        $qr['enabled'] = 0;
    } else {
        $qr['merchant'] = ic_clean((string) ($_POST['merchant'] ?? ''), 80);
        $qr['note'] = ic_clean((string) ($_POST['note'] ?? ''), 200);
        $qr['enabled'] = empty($_POST['enabled']) ? 0 : 1;
        $file = $_FILES['image'] ?? null;
        if ($file && $file['error'] !== UPLOAD_ERR_NO_FILE) {
            $types = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
            $info = $file['error'] === UPLOAD_ERR_OK ? @getimagesize($file['tmp_name']) : false;
            if (!$info || !isset($types[$info[2]])) {
                $error = 'Please upload the QR as a JPG, PNG or WEBP picture (a screenshot of it works too).';
            } elseif ($file['size'] > 5 * 1024 * 1024) {
                $error = 'The QR picture is larger than 5 MB.';
            } else {
                $dir = IC_ROOT . '/assets/payment';
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                $name = 'bangla-qr-' . bin2hex(random_bytes(6)) . '.' . $types[$info[2]];
                if (move_uploaded_file($file['tmp_name'], "$dir/$name")) {
                    $qr['image'] = "assets/payment/$name";
                } else {
                    $error = 'The picture could not be saved. Please try again.';
                }
            }
        }
        if (!$error && $qr['enabled'] && $qr['image'] === '') {
            $error = 'Upload the QR picture first, then turn it on.';
            $qr['enabled'] = 0;
        }
    }
    if ($old !== '' && $old !== $qr['image'] && strpos($old, 'assets/payment/') === 0) {
        @unlink(IC_ROOT . '/assets/payment/' . basename($old)); // the old picture is replaced or removed
    }
    if (!$error || $qr['image'] !== $old) {
        ic_set_setting($pdo, 'bangla_qr', json_encode($qr, JSON_UNESCAPED_UNICODE));
    }
    if (!$error) {
        flash($action === 'remove' ? 'Bangla QR removed.' : (ic_bangla_qr_active($qr) ? 'Bangla QR is live — customers can scan and pay.' : 'Saved (Bangla QR is off).'));
        redirect('payments.php');
    }
}

$active = ic_bangla_qr_active($qr);
admin_header('Bangla QR', 'payments.php');
?>
<div class="flex flex-wrap items-end justify-between gap-3 mb-6">
  <div>
    <p class="text-xs uppercase tracking-[0.25em] text-gold-600">Payment</p>
    <h1 class="font-serif text-3xl text-wood-900 mt-1">Bangla QR</h1>
    <p class="text-sm text-stone-500 mt-1">Customers scan your QR with any bank, bKash or Nagad app, pay, and type the Transaction ID with their order.</p>
  </div>
  <span class="card px-4 py-2 text-sm inline-flex items-center gap-2">
    <span class="w-2.5 h-2.5 rounded-full <?= $active ? 'bg-emerald-500 animate-pulse' : 'bg-stone-300' ?>"></span><?= $active ? 'Live on checkout & landing pages' : 'Off' ?>
  </span>
</div>

<?php if ($error): ?>
  <div class="mb-6 rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800"><?= h($error) ?></div>
<?php endif; ?>

<div class="grid lg:grid-cols-[minmax(0,1fr)_340px] gap-6 items-start">
  <form method="post" enctype="multipart/form-data" class="card p-5 sm:p-6 space-y-5">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <div>
      <span class="label">QR picture</span>
      <label class="flex flex-col items-center justify-center gap-1 rounded-xl border-2 border-dashed border-cream-300 bg-cream-50 p-6 text-center cursor-pointer hover:border-gold-500">
        <i class="fa-solid fa-qrcode text-3xl text-gold-600"></i>
        <span class="text-sm font-semibold text-wood-900"><?= $qr['image'] !== '' ? 'Upload a new QR picture' : 'Upload your Bangla QR' ?></span>
        <span class="text-xs text-stone-500">JPG, PNG or WEBP · a clear photo or screenshot of the QR from your bank / merchant app</span>
        <input type="file" name="image" accept="image/jpeg,image/png,image/webp" class="sr-only" id="qrFile">
        <span class="text-xs text-emerald-700 font-semibold" id="qrFileName"></span>
      </label>
    </div>
    <div class="grid sm:grid-cols-2 gap-4">
      <div>
        <label class="label" for="merchant">Name shown on the QR (optional)</label>
        <input class="input" id="merchant" name="merchant" value="<?= h($qr['merchant']) ?>" placeholder="e.g. INSPIRED CREATIONS">
        <p class="text-xs text-stone-400 mt-1">Customers see this so they know they are paying the right shop.</p>
      </div>
      <div>
        <label class="label" for="note">Extra line for customers (optional)</label>
        <input class="input" id="note" name="note" value="<?= h($qr['note']) ?>" placeholder="যেমন: পেমেন্টের পর স্ক্রিনশট WhatsApp করুন">
      </div>
    </div>
    <label class="flex items-center justify-between gap-4 cursor-pointer rounded-xl bg-cream-50 border border-cream-300 p-4">
      <span><span class="block text-sm font-semibold text-wood-900">Bangla QR on</span><span class="block text-xs text-stone-500">Adds “Bangla QR” to the website checkout and to landing pages that take payment first</span></span>
      <span class="relative flex-shrink-0">
        <input type="checkbox" name="enabled" value="1" class="peer sr-only" <?= !empty($qr['enabled']) ? 'checked' : '' ?>>
        <span class="block w-11 h-6 rounded-full bg-stone-300 transition peer-checked:bg-emerald-600"></span>
        <span class="absolute top-0.5 left-0.5 w-5 h-5 rounded-full bg-white shadow transition peer-checked:translate-x-5"></span>
      </span>
    </label>
    <div class="flex flex-wrap gap-2">
      <button class="btn btn-gold !px-8"><i class="fa-regular fa-floppy-disk"></i> Save</button>
      <?php if ($qr['image'] !== ''): ?>
        <button class="btn btn-danger" name="action" value="remove" formnovalidate onclick="return confirm('Remove the Bangla QR?');"><i class="fa-regular fa-trash-can"></i> Remove QR</button>
      <?php endif; ?>
    </div>
    <div class="rounded-xl bg-cream-50 border border-cream-300 p-4 text-xs text-stone-600 space-y-1">
      <p class="font-semibold text-wood-900">Where it shows</p>
      <p>• Website checkout: a third payment choice, <strong>Bangla QR</strong>.</p>
      <p>• Landing pages: when the page's payment is “Cash on Delivery + pay first” and <strong>Bangla QR</strong> is ticked (Edit page → Order form &amp; contact).</p>
      <p>• Orders paid this way show “Bangla QR” with the Transaction ID — check it in your bank / merchant app before confirming.</p>
    </div>
  </form>

  <aside class="card p-5 text-center">
    <h2 class="font-serif text-lg text-wood-900 mb-3">What customers see</h2>
    <?php if ($qr['image'] !== ''): ?>
      <div class="rounded-2xl border-2 border-[#00843d] p-3 bg-white">
        <p class="text-xs font-bold tracking-widest text-[#00843d] mb-2">BANGLA QR</p>
        <img src="<?= h(asset_url($qr['image'])) ?>" alt="Bangla QR" class="mx-auto w-full max-w-[240px] rounded-lg">
        <?php if ($qr['merchant'] !== ''): ?><p class="mt-2 font-semibold text-wood-900"><?= h($qr['merchant']) ?></p><?php endif; ?>
        <p class="text-xs text-stone-500 mt-1">যেকোনো ব্যাংক / বিকাশ / নগদ অ্যাপ দিয়ে স্ক্যান করে মোট টাকা পাঠান, তারপর Transaction ID লিখুন।</p>
        <?php if ($qr['note'] !== ''): ?><p class="text-xs text-stone-500 mt-1"><?= h($qr['note']) ?></p><?php endif; ?>
      </div>
    <?php else: ?>
      <div class="rounded-2xl border-2 border-dashed border-cream-300 p-10 text-stone-400"><i class="fa-solid fa-qrcode text-5xl"></i><p class="text-sm mt-2">Your QR will show here</p></div>
    <?php endif; ?>
  </aside>
</div>
<script>
  document.getElementById('qrFile').addEventListener('change', (e) => {
    document.getElementById('qrFileName').textContent = e.target.files.length ? e.target.files[0].name + ' — press Save' : '';
  });
</script>
<?php admin_footer();
