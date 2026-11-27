<?php
// Marketing → Facebook (Meta) Pixel: Pixel ID for the website + Conversions API for server-side order events
require __DIR__ . '/_inc.php';
require_admin();

$pdo = ic_db();
$s = ic_meta_settings($pdo);
$testResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $action = (string) ($_POST['action'] ?? 'save');
    if ($action === 'save') {
        $pixelId = preg_replace('/\D/', '', (string) ($_POST['pixel_id'] ?? ''));
        $s['pixel_id'] = $pixelId;
        $s['enabled'] = !empty($_POST['enabled']) ? 1 : 0;
        $token = trim((string) ($_POST['capi_token'] ?? ''));
        if ($token !== '') {
            $s['capi_token'] = $token; // an empty box keeps the saved token
        }
        if (!empty($_POST['remove_token'])) {
            $s['capi_token'] = '';
        }
        $s['test_event_code'] = ic_clean((string) ($_POST['test_event_code'] ?? ''), 40);
        $version = trim((string) ($_POST['version'] ?? ''));
        $s['version'] = preg_match('/^v\d{2,3}\.\d$/', $version) ? $version : 'v25.0';
        $host = trim((string) ($_POST['api_host'] ?? ''));
        $s['api_host'] = filter_var($host, FILTER_VALIDATE_URL) ? $host : 'https://graph.facebook.com';
        ic_set_setting($pdo, 'meta_pixel', json_encode($s, JSON_UNESCAPED_UNICODE));
        if ($pixelId !== '' && strlen($pixelId) < 6) {
            flash('Saved, but the Pixel ID looks too short — copy it again from Events Manager.');
        } else {
            flash(ic_meta_active($s) ? 'Pixel is live on the website.' : 'Pixel settings saved (pixel is off).');
        }
        redirect('pixel.php');
    }
    if ($action === 'test') {
        try {
            if ($s['test_event_code'] === '') {
                throw new RuntimeException('Add a Test event code first (Events Manager → Test events), so this test does not count as a real sale.');
            }
            $reply = ic_meta_send($pdo, [[
                'event_name'    => 'PageView',
                'event_time'    => time(),
                'event_id'      => 'test-' . bin2hex(random_bytes(4)),
                'action_source' => 'website',
                'event_source_url' => (!empty($_SERVER['HTTPS']) ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/',
                'user_data'     => ['client_ip_address' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''), 'client_user_agent' => (string) ($_SERVER['HTTP_USER_AGENT'] ?? '')],
            ]]);
            $testResult = [true, 'Meta received ' . (int) ($reply['events_received'] ?? 0) . ' test event. Open Events Manager → Test events to see it.'];
            ic_meta_log($pdo, 'Test PageView', true, $testResult[1]);
        } catch (Throwable $e) {
            $testResult = [false, $e->getMessage()];
            ic_meta_log($pdo, 'Test PageView', false, $e->getMessage());
        }
    }
}

$active = ic_meta_active($s);
$log = json_decode((string) ic_setting($pdo, 'meta_capi_log', '[]'), true) ?: [];
$events = [
    ['PageView', 'fa-eye', 'Every page visit'],
    ['ViewContent', 'fa-box-open', 'Product page or quick view opened'],
    ['Search', 'fa-magnifying-glass', 'Customer searches the shop'],
    ['AddToWishlist', 'fa-heart', 'Product saved to wishlist'],
    ['AddToCart', 'fa-cart-plus', 'Product added to cart'],
    ['InitiateCheckout', 'fa-credit-card', 'Checkout opened'],
    ['CustomizeProduct', 'fa-pen-ruler', 'Design chart opened'],
    ['Purchase', 'fa-bag-shopping', 'Order placed (with value). Skipped for fake-flagged orders — sent later if you mark it “Not fake”'],
    ['Lead', 'fa-comment-dots', 'Inquiry or price-on-request order'],
    ['Contact', 'fa-whatsapp', 'WhatsApp button clicked'],
];

admin_header('Facebook Pixel', 'pixel.php');
?>
<div class="flex flex-wrap items-end justify-between gap-3 mb-6">
  <div>
    <p class="text-xs uppercase tracking-[0.25em] text-gold-600">Marketing</p>
    <h1 class="font-serif text-3xl text-wood-900 mt-1">Facebook Pixel</h1>
    <p class="text-sm text-stone-500 mt-1">Track visits, carts and orders in Meta Events Manager to run better Facebook &amp; Instagram ads.</p>
  </div>
  <span class="card px-4 py-2 text-sm inline-flex items-center gap-2">
    <span class="w-2.5 h-2.5 rounded-full <?= $active ? 'bg-emerald-500 animate-pulse' : 'bg-stone-300' ?>"></span>
    <?= $active ? 'Pixel live' : 'Pixel off' ?> · Conversions API <?= $s['capi_token'] !== '' ? 'on' : 'off' ?>
  </span>
</div>

<?php if ($testResult): ?>
  <div class="mb-6 rounded-xl px-4 py-3 text-sm <?= $testResult[0] ? 'bg-emerald-50 border border-emerald-200 text-emerald-800' : 'bg-red-50 border border-red-200 text-red-800' ?>"><?= h($testResult[1]) ?></div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
  <form method="post" class="lg:col-span-2 space-y-6">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">

    <section class="card p-5 sm:p-6 space-y-4">
      <div class="flex items-center gap-3">
        <span class="w-10 h-10 rounded-xl bg-[#1877F2] text-white flex items-center justify-center"><i class="fa-brands fa-meta text-lg"></i></span>
        <div>
          <h2 class="font-serif text-lg text-wood-900">1. Website pixel</h2>
          <p class="text-xs text-stone-500">Events Manager → Data sources → your Pixel → copy the Dataset / Pixel ID (numbers only).</p>
        </div>
      </div>
      <div>
        <label class="label" for="pixel_id">Pixel ID</label>
        <input class="input text-lg tracking-wider" id="pixel_id" name="pixel_id" inputmode="numeric" value="<?= h($s['pixel_id']) ?>" placeholder="e.g. 123456789012345">
      </div>
      <label class="flex items-center justify-between gap-4 cursor-pointer rounded-xl bg-cream-50 border border-cream-300 p-4">
        <span><span class="block text-sm font-semibold text-wood-900">Pixel on</span><span class="block text-xs text-stone-500">Loads the pixel on every page of the website.</span></span>
        <span class="relative flex-shrink-0">
          <input type="checkbox" name="enabled" value="1" class="peer sr-only" <?= !empty($s['enabled']) ? 'checked' : '' ?>>
          <span class="block w-11 h-6 rounded-full bg-stone-300 transition peer-checked:bg-emerald-600"></span>
          <span class="absolute top-0.5 left-0.5 w-5 h-5 rounded-full bg-white shadow transition peer-checked:translate-x-5"></span>
        </span>
      </label>
    </section>

    <section class="card p-5 sm:p-6 space-y-4">
      <div>
        <h2 class="font-serif text-lg text-wood-900">2. Conversions API <span class="text-xs font-sans text-stone-400">(recommended)</span></h2>
        <p class="text-xs text-stone-500">Sends every order from the server too, so sales are counted even when ad blockers or iPhone privacy block the pixel. Meta merges the two copies automatically.</p>
      </div>
      <div>
        <label class="label" for="capi_token">Access token</label>
        <input class="input" id="capi_token" name="capi_token" type="password" autocomplete="off"
          placeholder="<?= $s['capi_token'] !== '' ? 'Saved ••••' . h(substr($s['capi_token'], -4)) . ' — leave empty to keep' : 'Events Manager → Settings → Conversions API → Generate access token' ?>">
        <?php if ($s['capi_token'] !== ''): ?>
          <label class="mt-2 flex items-center gap-2 text-xs text-stone-500"><input type="checkbox" name="remove_token" value="1" class="rounded"> Remove the saved token</label>
        <?php endif; ?>
      </div>
      <div>
        <label class="label" for="test_event_code">Test event code <span class="normal-case font-normal text-stone-400">(only while testing)</span></label>
        <input class="input" id="test_event_code" name="test_event_code" value="<?= h($s['test_event_code']) ?>" placeholder="e.g. TEST12345 — from Events Manager → Test events">
        <p class="text-xs text-stone-400 mt-1">While this is filled, server events show only in “Test events”. Clear it when you go live.</p>
      </div>
      <details class="text-xs">
        <summary class="cursor-pointer text-stone-500">Advanced</summary>
        <div class="grid grid-cols-2 gap-3 mt-2">
          <div><label class="label" for="version">Graph API version</label><input class="input" id="version" name="version" value="<?= h($s['version']) ?>"></div>
          <div><label class="label" for="api_host">API address</label><input class="input" id="api_host" name="api_host" value="<?= h($s['api_host']) ?>"></div>
        </div>
      </details>
    </section>

    <button class="btn btn-gold w-full sm:w-auto !px-8"><i class="fa-regular fa-floppy-disk"></i> Save pixel settings</button>
  </form>

  <aside class="space-y-6">
    <section class="card p-5 space-y-3">
      <h2 class="font-serif text-lg text-wood-900">Check it works</h2>
      <ol class="text-sm space-y-2 list-decimal list-inside text-stone-600">
        <li>Open your website, then Events Manager → <strong>Test events</strong>: visits, add-to-cart and orders appear live.</li>
        <li>Or install the <strong>Meta Pixel Helper</strong> Chrome extension and open your site.</li>
        <li>Add a test event code above and press the button below to test the Conversions API.</li>
      </ol>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="test">
        <button class="btn btn-dark w-full" <?= $active && $s['capi_token'] !== '' ? '' : 'disabled title="Save a Pixel ID and access token first"' ?>><i class="fa-solid fa-vial"></i> Send server test event</button>
      </form>
      <p class="text-xs text-stone-400">Verify your domain in Business Settings → Brand safety → Domains (DNS TXT record) for the best ad results.</p>
    </section>

    <section class="card p-5">
      <h2 class="font-serif text-lg text-wood-900 mb-1">Events sent</h2>
      <p class="text-xs text-stone-500 mb-3">With the Conversions API on, every event goes from the browser <em>and</em> the server with the same ID — Meta keeps one copy, and nothing is lost to ad blockers.</p>
      <ul class="space-y-2 text-sm">
        <?php foreach ($events as [$name, $icon, $when]): ?>
          <li class="flex items-start gap-3">
            <i class="<?= $icon === 'fa-whatsapp' ? 'fa-brands' : 'fa-solid' ?> <?= $icon ?> w-4 text-center text-gold-600 mt-0.5"></i>
            <span><strong class="text-wood-900"><?= $name ?></strong><span class="block text-xs text-stone-500"><?= $when ?></span></span>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>

    <section class="card p-5">
      <h2 class="font-serif text-lg text-wood-900 mb-3">Latest server events</h2>
      <?php if (!$log): ?>
        <p class="text-sm text-stone-500">None yet. They appear here after orders once the Conversions API is on.</p>
      <?php else: ?>
        <ul class="space-y-2 text-xs">
          <?php foreach ($log as $entry): ?>
            <li class="flex items-start gap-2">
              <i class="fa-solid <?= $entry['ok'] ? 'fa-circle-check text-emerald-600' : 'fa-circle-xmark text-red-600' ?> mt-0.5"></i>
              <span><strong class="text-wood-900"><?= h($entry['event']) ?></strong> · <?= h(date('d M, g:i a', strtotime($entry['at']))) ?>
                <span class="block text-stone-500"><?= h($entry['message']) ?></span></span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
  </aside>
</div>
<?php admin_footer();
