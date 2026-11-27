<?php
// Real customer reviews. Only customers whose order was delivered get a private review link (signed with a secret,
// so nobody can guess one). Reviews show on the website only after the shop approves them in admin → Reviews.

// Secret used to sign review links (made once, kept in the database)
function ic_review_secret(PDO $pdo): string
{
    $secret = (string) ic_setting($pdo, 'review_secret', '');
    if ($secret === '') {
        $secret = bin2hex(random_bytes(24));
        ic_set_setting($pdo, 'review_secret', $secret);
    }
    return $secret;
}

function ic_review_token(PDO $pdo, string $orderCode): string
{
    return substr(hash_hmac('sha256', 'review|' . $orderCode, ic_review_secret($pdo)), 0, 20);
}

// The website's address for links in emails/WhatsApp: config 'site_url', else the address this page was opened on
function ic_site_url(): ?string
{
    $cfg = ic_config();
    if (!empty($cfg['site_url'])) {
        return rtrim((string) $cfg['site_url'], '/');
    }
    if (empty($_SERVER['HTTP_HOST'])) {
        return null;
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    $host = preg_replace('/[^a-z0-9.\-:\[\]]/i', '', (string) $_SERVER['HTTP_HOST']);
    // The site's folder: pages in api/ or admin/ live one level below it
    $dir = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php')));
    if (preg_match('#/(api|admin)$#', $dir)) {
        $dir = str_replace('\\', '/', dirname($dir)); // Windows gives "\" for the top folder
    }
    $dir = rtrim($dir, '/.');
    return ($https ? 'https://' : 'http://') . $host . $dir;
}

function ic_review_url(PDO $pdo, array $order): ?string
{
    $base = ic_site_url();
    return $base === null ? null : $base . '/review.php?o=' . rawurlencode($order['code']) . '&t=' . ic_review_token($pdo, $order['code']);
}

// Parcel status page (the QR code on the courier label opens it). Signed like the review link, so order pages
// cannot be guessed; it shows only a masked name/phone, never the full address.
function ic_track_token(PDO $pdo, string $orderCode): string
{
    return substr(hash_hmac('sha256', 'track|' . $orderCode, ic_review_secret($pdo)), 0, 12);
}

function ic_track_url(PDO $pdo, array $order): ?string
{
    $base = ic_site_url();
    return $base === null ? null : $base . '/track.php?o=' . rawurlencode($order['code']) . '&k=' . ic_track_token($pdo, $order['code']);
}

// WhatsApp message (Bangla) the shop can send with the review link
function ic_review_whatsapp_text(PDO $pdo, array $order): string
{
    $first = trim((string) strtok(trim($order['customer_name']), ' '));
    return "আসসালামু আলাইকুম {$first}, INSPIRED CREATIONS থেকে বলছি। আপনার অর্ডার ({$order['code']}) কেমন লাগলো? "
        . "১ মিনিটে একটি রিভিউ দিলে আমরা খুব খুশি হবো — চাইলে পণ্যের একটি ছবিও দিতে পারেন:\n" . ic_review_url($pdo, $order);
}

// When an order becomes Delivered: email the review link once (only if the customer gave an email)
function ic_review_after_delivery(PDO $pdo, int $orderId): void
{
    try {
        $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        if (!$order || empty($order['customer_email']) || !function_exists('mail')) {
            return;
        }
        $details = json_decode((string) $order['details_json'], true) ?: [];
        $url = ic_review_url($pdo, $order);
        if (!empty($details['review_asked']) || $url === null) {
            return;
        }
        $e = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
        $first = trim((string) strtok(trim($order['customer_name']), ' '));
        $html = '<!DOCTYPE html><html lang="bn"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>'
            . '<body style="margin:0;background:#f7f1e8;font-family:Arial,sans-serif;color:#222"><div style="max-width:520px;margin:0 auto;padding:24px 16px">'
            . '<div style="background:#24130a;color:#f7f1e8;border-radius:16px 16px 0 0;padding:18px;text-align:center;font-weight:700;letter-spacing:3px">INSPIRED CREATIONS</div>'
            . '<div style="background:#fff;border-radius:0 0 16px 16px;padding:24px;text-align:center">'
            . '<p style="font-size:18px;margin:0 0 10px">প্রিয় ' . $e($first) . ',</p>'
            . '<p style="line-height:1.7;margin:0 0 18px">আশা করি আপনার অর্ডার (' . $e($order['code']) . ') পেয়ে ভালো লেগেছে। ১ মিনিটে একটি রিভিউ দিলে আমরা খুব খুশি হবো — চাইলে পণ্যের একটি ছবিও দিতে পারেন।</p>'
            . '<div style="font-size:28px;color:#f59e0b;margin-bottom:16px">★★★★★</div>'
            . '<a href="' . $e($url) . '" style="display:inline-block;background:#733f20;color:#fff;text-decoration:none;padding:12px 28px;border-radius:99px;font-weight:700">রিভিউ দিন</a>'
            . '</div></div></body></html>';
        $host = preg_replace('/[^a-z0-9.-]/i', '', (string) parse_url($url, PHP_URL_HOST));
        $headers = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n"
            . 'From: =?UTF-8?B?' . base64_encode('INSPIRED CREATIONS') . "?= <no-reply@{$host}>";
        if (!empty(ic_config()['order_email'])) {
            $headers .= "\r\nReply-To: " . ic_config()['order_email'];
        }
        $sent = @mail($order['customer_email'], '=?UTF-8?B?' . base64_encode('আপনার অর্ডার কেমন লাগলো? — INSPIRED CREATIONS') . '?=', chunk_split(base64_encode($html)), $headers);
        $details['review_asked'] = $sent ? ic_now() : 'failed';
        $pdo->prepare('UPDATE orders SET details_json = ? WHERE id = ?')->execute([json_encode($details, JSON_UNESCAPED_UNICODE), $orderId]);
    } catch (Throwable $e) {
        error_log('Review request failed: ' . $e->getMessage());
    }
}

// Star average and count per product from approved reviews: [product_id => ['rating' => 4.8, 'count' => 12]]
function ic_review_stats(PDO $pdo): array
{
    $stats = [];
    foreach ($pdo->query("SELECT product_id, AVG(rating) AS r, COUNT(*) AS n FROM reviews WHERE status = 'approved' GROUP BY product_id") as $row) {
        $stats[$row['product_id']] = ['rating' => round((float) $row['r'], 1), 'count' => (int) $row['n']];
    }
    return $stats;
}

// Approved reviews for the website (newest first), optionally for one product
function ic_reviews_public(PDO $pdo, ?string $productId = null, int $limit = 12): array
{
    $sql = "SELECT r.id, r.product_id, r.name, r.district, r.rating, r.body, r.photo, r.approved_at, r.created_at, p.title AS product
        FROM reviews r LEFT JOIN products p ON p.id = r.product_id WHERE r.status = 'approved'";
    $args = [];
    if ($productId !== null) {
        $sql .= ' AND r.product_id = ?';
        $args[] = $productId;
    }
    $stmt = $pdo->prepare($sql . ' ORDER BY r.approved_at DESC, r.id DESC LIMIT ' . max(1, min(50, $limit)));
    $stmt->execute($args);
    return $stmt->fetchAll();
}
