<?php
// Shared helpers for every admin page: session login, CSRF protection and the page layout.
declare(strict_types=1);
require __DIR__ . '/../api/bootstrap.php';

session_name('ic_admin');
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);
session_start();

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">';
}

function require_post_csrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !hash_equals(csrf_token(), (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('This form expired. Please go back, refresh the page and try again.');
    }
}

function require_admin(): void
{
    if (empty($_SESSION['admin_id'])) {
        header('Location: login.php');
        exit;
    }
    // Keep the database current and apply the automatic order rules (courier sync at most every 15 min)
    $pdo = ic_db();
    ic_migrate($pdo);
    try {
        ic_run_automation($pdo);
    } catch (Throwable $e) {
        error_log('Automation failed: ' . $e->getMessage());
    }
}

function redirect(string $to): void
{
    header('Location: ' . $to);
    exit;
}

function flash(?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['flash'] = $message;
        return null;
    }
    $message = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $message;
}

function money($amount): string
{
    return $amount === null ? '—' : '৳' . number_format((float) $amount);
}

function status_badge(string $status): string
{
    $colors = [
        'new'           => 'bg-amber-100 text-amber-800',
        'confirmed'     => 'bg-sky-100 text-sky-800',
        'hold'          => 'bg-orange-100 text-orange-800',
        'fake'          => 'bg-red-100 text-red-800',
        'in_production' => 'bg-violet-100 text-violet-800',
        'shipping'      => 'bg-indigo-100 text-indigo-800',
        'waiting'       => 'bg-yellow-100 text-yellow-800',
        'delivered'     => 'bg-emerald-100 text-emerald-800',
        'partial'       => 'bg-teal-100 text-teal-800',
        'returned'      => 'bg-rose-100 text-rose-800',
        'cancelled'     => 'bg-stone-200 text-stone-600',
    ];
    $label = IC_ORDER_STATUSES[$status] ?? $status;
    return '<span class="inline-block whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-semibold ' . ($colors[$status] ?? 'bg-stone-100') . '">' . h($label) . '</span>';
}

// Delivery chance badge: green 70%+, amber 40–69%, red below 40%
function score_badge($score): string
{
    if ($score === null) {
        return '';
    }
    $score = (int) $score;
    $tone = $score >= 70 ? 'bg-emerald-50 text-emerald-700 ring-emerald-200' : ($score >= 40 ? 'bg-amber-50 text-amber-700 ring-amber-200' : 'bg-red-50 text-red-700 ring-red-200');
    return '<span class="inline-flex items-center gap-1 whitespace-nowrap rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ' . $tone . '" title="Chance the customer accepts the parcel">'
        . '<i class="fa-solid fa-shield-halved text-[10px]"></i>' . $score . '%</span>';
}

// Product images are stored relative to the site root; admin pages live one folder down
function asset_url(string $path): string
{
    return preg_match('#^(https?:|data:)#', $path) ? $path : '../' . ltrim($path, '/');
}

function whatsapp_link(string $phone, string $text = ''): string
{
    return 'https://wa.me/88' . $phone . ($text !== '' ? '?text=' . rawurlencode($text) : '');
}

function admin_header(string $title, string $active = ''): void
{
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title><?= h($title) ?> — INSPIRED CREATIONS Admin</title>
  <link rel="icon" type="image/png" href="../assets/logo-mark.png">
  <script src="https://cdn.tailwindcss.com/3.4.17"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@700&family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600&display=swap" rel="stylesheet">
  <script>
    tailwind.config = { theme: { extend: {
      colors: {
        wood: { 50: '#FDF8F5', 100: '#F7EDE6', 200: '#EBD4C3', 300: '#DEB99F', 500: '#A86D43', 700: '#733F20', 800: '#5C3118', 900: '#3D1F0E', 950: '#24130A' },
        cream: { 50: '#FCFAF6', 100: '#F7F1E8', 200: '#EFE5D6', 300: '#E3D4BF' },
        gold: { 200: '#E9D1AC', 300: '#DDB982', 500: '#B8874D', 600: '#9C6D38', 700: '#7E562B' }
      },
      fontFamily: { sans: ['"Plus Jakarta Sans"', 'sans-serif'], serif: ['"Playfair Display"', 'serif'], display: ['Cinzel', 'serif'] }
    } } };
  </script>
  <style type="text/tailwindcss">
    @layer components {
      .card { @apply rounded-2xl bg-white border border-cream-300 shadow-sm; }
      .input { @apply w-full rounded-xl border border-cream-300 bg-white px-3.5 py-2.5 text-sm focus:outline-none focus:border-wood-700; }
      .label { @apply block text-xs font-semibold uppercase tracking-wider text-stone-500 mb-1.5; }
      .btn { @apply inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold transition whitespace-nowrap; }
      .btn-dark { @apply bg-wood-800 text-white hover:bg-wood-900; }
      .btn-gold { @apply bg-gold-500 text-white hover:bg-gold-600; }
      .btn-light { @apply border border-cream-300 bg-white text-wood-800 hover:bg-cream-100; }
      .btn-danger { @apply border border-red-200 bg-white text-red-700 hover:bg-red-50; }
      .th { @apply px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-stone-500; }
      .td { @apply px-4 py-3 text-sm align-top; }
    }
  </style>
</head>
<body class="bg-cream-100 text-stone-700 font-sans antialiased min-h-screen">
  <?php if (!empty($_SESSION['admin_id'])): ?>
  <?php
  // Order counts for the menu badges
  $menuCounts = [];
  try {
      foreach (ic_db()->query('SELECT status, COUNT(*) AS n FROM orders GROUP BY status') as $row) {
          $menuCounts[$row['status']] = (int) $row['n'];
      }
  } catch (Throwable $e) {
  }
  $statusIcons = [
      'new' => 'fa-inbox', 'confirmed' => 'fa-circle-check', 'hold' => 'fa-hand', 'fake' => 'fa-user-secret',
      'in_production' => 'fa-hammer', 'shipping' => 'fa-truck-ramp-box', 'waiting' => 'fa-warehouse',
      'delivered' => 'fa-house-circle-check', 'partial' => 'fa-scale-unbalanced', 'returned' => 'fa-rotate-left', 'cancelled' => 'fa-ban',
  ];
  $currentStatus = $active === 'orders.php' ? (string) ($_GET['status'] ?? '') : '';
  $menuLink = function (string $href, string $icon, string $label, bool $current, ?int $count = null) {
      echo '<a href="' . h($href) . '" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm '
          . ($current ? 'bg-wood-800 text-white' : 'text-stone-700 hover:bg-cream-200') . '">'
          . '<i class="fa-solid ' . $icon . ' w-4 text-center ' . ($current ? 'text-gold-300' : 'text-gold-600') . '"></i>'
          . '<span class="flex-1">' . h($label) . '</span>'
          . ($count ? '<span class="text-[11px] font-semibold rounded-full px-2 py-0.5 ' . ($current ? 'bg-white/15' : 'bg-cream-200 text-wood-800') . '">' . $count . '</span>' : '')
          . '</a>';
  };
  ?>
  <header class="bg-wood-950 text-cream-100 relative z-40">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 flex items-center gap-3 py-3">
      <a href="index.php" class="flex items-center gap-3">
        <img src="../assets/logo-mark.png" alt="" class="w-9 h-9 rounded-full object-contain p-1 bg-[#140E0C] ring-1 ring-gold-300/60">
        <span>
          <span class="block font-display text-sm tracking-[0.12em]">INSPIRED CREATIONS</span>
          <span class="block text-[10px] uppercase tracking-[0.25em] text-gold-300">Admin panel</span>
        </span>
      </a>

      <!-- Main menu: everything lives behind the 3-dot button -->
      <div class="relative" id="adminMenu">
        <button type="button" id="adminMenuBtn" aria-haspopup="true" aria-expanded="false" aria-controls="adminMenuPanel" title="Menu"
          class="w-10 h-10 rounded-full flex items-center justify-center text-gold-200 hover:bg-white/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-gold-300">
          <i class="fa-solid fa-ellipsis-vertical text-lg"></i>
          <span class="sr-only">Open menu</span>
        </button>
        <div id="adminMenuPanel" class="hidden fixed left-3 right-3 top-16 sm:absolute sm:left-0 sm:right-auto sm:top-12 sm:w-72 max-h-[80vh] overflow-y-auto rounded-2xl bg-cream-50 text-stone-700 shadow-2xl ring-1 ring-black/10 p-2">
          <?php
          $script = basename($_SERVER['SCRIPT_NAME']);
          $shipTab = $script === 'shipping.php' ? (string) ($_GET['tab'] ?? 'all') : '';
          $group = function (string $title, string $icon, bool $open, callable $links) {
              echo '<details class="group/menu" ' . ($open ? 'open' : '') . '>'
                  . '<summary class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-semibold text-wood-900 cursor-pointer list-none hover:bg-cream-200 [&::-webkit-details-marker]:hidden">'
                  . '<i class="fa-solid ' . $icon . ' w-4 text-center text-gold-600"></i><span class="flex-1">' . h($title) . '</span>'
                  . '<i class="fa-solid fa-chevron-down text-[10px] text-stone-400 transition group-open/menu:rotate-180"></i></summary>'
                  . '<div class="ml-4 pl-2 border-l border-cream-300 mt-1 mb-2 space-y-0.5">';
              $links();
              echo '</div></details>';
          };
          $menuLink('index.php', 'fa-chart-line', 'Dashboard', $script === 'index.php');

          $group('Orders', 'fa-receipt', in_array($script, ['orders.php', 'order.php'], true), function () use ($menuLink, $currentStatus, $menuCounts, $statusIcons, $script) {
              $onList = $script === 'orders.php';
              $menuLink('orders.php', 'fa-list', 'All orders', $onList && $currentStatus === '' && empty($_GET['period']) && empty($_GET['important']), array_sum($menuCounts));
              $menuLink('orders.php?period=today', 'fa-sun', "Today's orders", $onList && ($_GET['period'] ?? '') === 'today');
              $menuLink('orders.php?important=1', 'fa-star', 'Important', $onList && !empty($_GET['important']));
              foreach (IC_ORDER_STATUSES as $key => $label) {
                  $menuLink("orders.php?status=$key", $statusIcons[$key] ?? 'fa-circle', $label, $currentStatus === $key, $menuCounts[$key] ?? 0);
              }
          });

          $group('Product', 'fa-box-open', in_array($script, ['products.php', 'product.php', 'visibility.php', 'customers.php'], true), function () use ($menuLink, $script) {
              $menuLink('products.php', 'fa-boxes-stacked', 'All products', $script === 'products.php' || ($script === 'product.php' && !empty($_GET['id'])));
              $menuLink('product.php', 'fa-square-plus', 'Add product', $script === 'product.php' && empty($_GET['id']));
              $menuLink('visibility.php', 'fa-toggle-on', 'Store Visibility', $script === 'visibility.php');
              $menuLink('customers.php', 'fa-users', 'Customers by product', $script === 'customers.php');
          });

          $group('Courier', 'fa-truck-ramp-box', in_array($script, ['couriers.php'], true), function () use ($menuLink, $script, $currentStatus, $menuCounts) {
              $menuLink('couriers.php', 'fa-plug', 'Courier connections', $script === 'couriers.php');
              $menuLink('orders.php?status=confirmed', 'fa-paper-plane', 'Ready to send', false, $menuCounts['confirmed'] ?? 0);
              $menuLink('orders.php?status=shipping', 'fa-truck-ramp-box', 'Booked (Shipping)', false, $menuCounts['shipping'] ?? 0);
              $menuLink('orders.php?status=waiting', 'fa-warehouse', 'At courier (Waiting)', false, $menuCounts['waiting'] ?? 0);
          });

          $group('Customer Insights', 'fa-chart-pie', in_array($script, ['map.php', 'audience.php'], true), function () use ($menuLink, $script) {
              $menuLink('map.php', 'fa-map-location-dot', 'Order map', $script === 'map.php');
              $menuLink('audience.php', 'fa-users-viewfinder', 'Ad audience (Facebook)', $script === 'audience.php');
          });

          $group('Landing Pages', 'fa-wand-magic-sparkles', in_array($script, ['landing.php', 'landing-edit.php'], true), function () use ($menuLink, $script) {
              $menuLink('landing.php', 'fa-table-cells-large', 'All landing pages', $script === 'landing-edit.php' || ($script === 'landing.php' && ($_GET['tab'] ?? '') !== 'templates'));
              $menuLink('landing.php?tab=templates', 'fa-swatchbook', 'Templates (' . count(IC_LANDING_TEMPLATES) . ')', $script === 'landing.php' && ($_GET['tab'] ?? '') === 'templates');
          });

          $group('Marketing', 'fa-bullhorn', $script === 'pixel.php', function () use ($menuLink, $script) {
              $menuLink('pixel.php', 'fa-bullseye', 'Facebook Pixel', $script === 'pixel.php');
          });

          $group('Payment', 'fa-wallet', $script === 'payments.php', function () use ($menuLink, $script) {
              $menuLink('payments.php', 'fa-qrcode', 'Bangla QR', $script === 'payments.php');
          });

          $group('Shipping', 'fa-truck-fast', $shipTab !== '', function () use ($menuLink, $shipTab) {
              $menuLink('shipping.php?tab=add', 'fa-plus', 'Add shipping', $shipTab === 'add');
              $menuLink('shipping.php?tab=all', 'fa-table-list', 'All shipping', $shipTab === 'all');
              $menuLink('shipping.php?tab=free', 'fa-gift', 'Free shipping', $shipTab === 'free');
          });

          $group('Settings', 'fa-gear', in_array($script, ['account.php', 'backup.php', 'security.php'], true), function () use ($menuLink, $script) {
              $menuLink('security.php', 'fa-shield-halved', 'Security check', $script === 'security.php');
              $menuLink('backup.php', 'fa-cloud-arrow-down', 'Backup', $script === 'backup.php');
              $menuLink('account.php', 'fa-user-lock', 'Account & admins', $script === 'account.php');
          });
          ?>
          <p class="px-3 pt-3 pb-1 text-[10px] font-semibold uppercase tracking-[0.2em] text-stone-400">More</p>
          <a href="../" target="_blank" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-stone-700 hover:bg-cream-200">
            <i class="fa-solid fa-arrow-up-right-from-square w-4 text-center text-gold-600"></i><span class="flex-1">View website</span>
          </a>
          <form method="post" action="logout.php">
            <?= csrf_field() ?>
            <button class="w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-red-700 hover:bg-red-50">
              <i class="fa-solid fa-right-from-bracket w-4 text-center"></i><span class="flex-1 text-left">Log out (<?= h($_SESSION['admin_name'] ?? '') ?>)</span>
            </button>
          </form>
        </div>
      </div>
    </div>
  </header>
  <script>
    (() => {
      const menu = document.getElementById('adminMenu');
      const btn = document.getElementById('adminMenuBtn');
      const panel = document.getElementById('adminMenuPanel');
      const setOpen = (open) => {
        panel.classList.toggle('hidden', !open);
        btn.setAttribute('aria-expanded', String(open));
      };
      btn.addEventListener('click', () => setOpen(panel.classList.contains('hidden')));
      document.addEventListener('click', (e) => { if (!menu.contains(e.target)) setOpen(false); });
      document.addEventListener('keydown', (e) => { if (e.key === 'Escape') { setOpen(false); btn.focus(); } });
    })();
  </script>
  <?php endif; ?>
  <main class="max-w-7xl mx-auto px-4 sm:px-6 py-8">
    <?php if ($message = flash()): ?>
      <div class="mb-6 rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-800"><?= h($message) ?></div>
    <?php endif; ?>
<?php
}

function admin_footer(): void
{
    echo "  </main>\n</body>\n</html>\n";
}
