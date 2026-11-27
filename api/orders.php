<?php
// Public: saves an order from the Custom Order Studio (type=custom) or the cart checkout (type=cart).
// Prices are always recalculated here from the database, never trusted from the browser.
require __DIR__ . '/bootstrap.php';

function fail(string $message, int $status = 422): void
{
    ic_json(['ok' => false, 'error' => $message], $status);
}

// Re-shapes $_FILES['files'] (multiple upload) into a simple list
function uploaded_files(): array
{
    if (empty($_FILES['files']) || !is_array($_FILES['files']['name'])) {
        return [];
    }
    $list = [];
    foreach ($_FILES['files']['name'] as $i => $name) {
        if ($_FILES['files']['error'][$i] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $list[] = [
            'name'     => (string) $name,
            'tmp_name' => $_FILES['files']['tmp_name'][$i],
            'size'     => (int) $_FILES['files']['size'][$i],
            'error'    => (int) $_FILES['files']['error'][$i],
        ];
    }
    return $list;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('Orders must be sent with POST.', 405);
}
// A request bigger than PHP's post_max_size arrives with empty $_POST
if (empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    fail('Your files are too large to upload. Please remove some and send them on WhatsApp instead.', 413);
}
if (!empty($_POST['_honey'])) {
    ic_json(['ok' => true, 'code' => 'IC-OK']); // quietly ignore spam bots
}

try {
    $cfg = ic_config();
    $pdo = ic_db();
    ic_migrate($pdo);

    $type = $_POST['type'] ?? '';
    $name = ic_clean($_POST['name'] ?? '', 120);
    $phone = ic_normalize_phone($_POST['whatsapp'] ?? '');
    if ($name === '') {
        fail('Please enter your name.');
    }
    if ($phone === '') {
        fail('Please enter a valid WhatsApp number, e.g. 01712345678.');
    }
    // Optional email: the customer gets an order confirmation there
    $email = strtolower(ic_clean((string) ($_POST['email'] ?? ''), 190));
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        fail('ইমেইল ঠিকানাটি সঠিক নয় — ঠিক করুন অথবা খালি রাখুন। (Please check the email address, or leave it empty.)');
    }

    // Spam limit — far above what a real customer needs: 5 orders in 10 minutes or 20 a day from one
    // internet connection, 4 an hour for one phone number
    $ip = ic_client_ip();
    if (ic_attempt_count($pdo, 'order_ip', $ip, 600) >= 5 || ic_attempt_count($pdo, 'order_ip', $ip, 86400) >= 20
        || ic_attempt_count($pdo, 'order_phone', $phone, 3600) >= 4) {
        error_log("Order limit reached for $ip / $phone");
        fail('অল্প সময়ে অনেকগুলো অর্ডার এসেছে। কিছুক্ষণ পর আবার চেষ্টা করুন অথবা WhatsApp-এ অর্ডার করুন। (Too many orders in a short time — please try again later or order on WhatsApp.)', 429);
    }

    $products = [];
    foreach ($pdo->query('SELECT * FROM products WHERE is_active = 1') as $row) {
        $products[$row['id']] = $row;
    }

    $items = [];
    $details = [];
    $address = ic_clean($_POST['address'] ?? '', 500);
    $region = null;
    $delivery = null;
    $paymentMethod = ic_clean($_POST['payment_method'] ?? '', 20);
    $trx = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) ($_POST['trx_id'] ?? '')));
    $trx = substr($trx, 0, 40);

    if ($type === 'custom') {
        $product = $products[$_POST['product_id'] ?? ''] ?? null;
        if (!$product) {
            fail('Please choose a product.');
        }
        if (empty($product['is_customizable'])) {
            fail('This product cannot be customized. Please add it to your cart instead.');
        }
        $qty = max(1, min(1000, (int) ($_POST['quantity'] ?? 1)));
        $unit = $product['price_bdt'] === null ? null : (int) $product['price_bdt'] + (int) $cfg['customization_fee'];
        $items[] = ['id' => $product['id'], 'title' => $product['title'], 'qty' => $qty, 'unit_bdt' => $unit];
        $subtotal = $unit === null ? null : $unit * $qty;
        $total = $subtotal;
        $advance = $total === null ? null : (int) ceil($total * (float) $cfg['advance_rate']);
        if ($advance !== null && strlen($trx) < 6) {
            fail('Please send the 30% advance and enter the Transaction ID.');
        }

        $marks = [];
        foreach ((array) json_decode((string) ($_POST['marks'] ?? '[]'), true) as $mark) {
            if (!is_array($mark) || count($marks) >= 9) {
                continue;
            }
            $marks[] = [
                'x'    => max(0, min(100, (float) ($mark['x'] ?? 0))),
                'y'    => max(0, min(100, (float) ($mark['y'] ?? 0))),
                'note' => ic_clean((string) ($mark['note'] ?? ''), 120),
            ];
        }
        $details = [
            'engraving_text' => ic_clean($_POST['engraving_text'] ?? '', 60),
            'font'           => ic_clean($_POST['font'] ?? '', 20),
            'occasion'       => ic_clean($_POST['occasion'] ?? '', 60),
            'notes'          => ic_clean($_POST['notes'] ?? '', 3000),
            'marks'          => $marks,
        ];
    } elseif ($type === 'cart') {
        $cartProducts = [];
        // Orders from a landing page get its timed offer price while the offer runs (checked here, not trusted
        // from the browser), with 10 minutes' grace for a customer who pressed Order just before it ended
        $landing = (string) ($_POST['landing'] ?? '');
        $landingOffer = null;
        if ($landing !== '' && isset($products[$landing]) && $products[$landing]['price_bdt'] !== null) {
            $landingPage = ic_landing_for($pdo, $products[$landing]);
            if ($landingPage['enabled']) {
                $landingOffer = ic_landing_offer($landingPage['data'], (int) $products[$landing]['price_bdt'], 600);
            }
        }
        foreach ((array) json_decode((string) ($_POST['items'] ?? '[]'), true) as $line) {
            $product = is_array($line) ? ($products[$line['id'] ?? ''] ?? null) : null;
            if (!$product || $product['price_bdt'] === null || count($items) >= 50) {
                continue;
            }
            $cartProducts[] = $product;
            $engraving = ic_clean((string) ($line['engraving'] ?? ''), 60);
            $onOffer = $landingOffer !== null && $product['id'] === $landing;
            $items[] = [
                'id'        => $product['id'],
                'title'     => $product['title'] . ($engraving !== '' ? " (engraved: {$engraving})" : '') . ($onOffer ? ' (landing page offer)' : ''),
                'qty'       => max(1, min(1000, (int) ($line['qty'] ?? 1))),
                'unit_bdt'  => ($onOffer ? $landingOffer['price'] : (int) $product['price_bdt']) + ($engraving !== '' ? (int) $cfg['customization_fee'] : 0),
            ];
        }
        if (!$items) {
            fail('Your cart is empty.');
        }
        if ($address === '') {
            fail('Please enter your delivery address.');
        }
        $region = ($_POST['region'] ?? '') === 'outside' ? 'outside' : 'dhaka';
        $subtotal = array_sum(array_map(function ($i) { return $i['unit_bdt'] * $i['qty']; }, $items));
        $delivery = ic_delivery_fee($cartProducts, $region, $subtotal, ic_shipping_defaults($pdo));
        $total = $subtotal + $delivery;
        $advance = null;
        // Paid first: landing pages also say which mobile banking (pay_channel); the shop's cart sends just "bkash"
        $prepaid = $paymentMethod === 'bkash';
        $paymentMethod = $prepaid ? (IC_PAY_CHANNELS[(string) ($_POST['pay_channel'] ?? '')] ?? 'bKash/Nagad') : 'Cash on Delivery';
        if ($prepaid && strlen($trx) < 6) {
            fail("Please enter the {$paymentMethod} Transaction ID.");
        }
        $details = ['notes' => ic_clean($_POST['notes'] ?? '', 3000)];
        // Orders placed on a product landing page remember which one (for the landing page stats)
        if ($landing !== '' && isset($products[$landing])) {
            $details['landing'] = $landing;
        }
    } else {
        fail('Unknown order type.');
    }

    // A "needed by" date from the customer makes the order important, with a reminder a few days earlier
    $neededBy = (string) ($_POST['needed_by'] ?? '');
    $neededBy = preg_match('/^\d{4}-\d{2}-\d{2}$/', $neededBy) && strtotime($neededBy) >= strtotime('today') ? $neededBy : null;
    $reminderDate = null;
    if ($neededBy !== null) {
        $daysBefore = (int) ($cfg['reminder_days_before'] ?? 2);
        $reminderDate = date('Y-m-d', max(strtotime('today'), strtotime("$neededBy -$daysBefore days")));
        $details['needed_by'] = $neededBy;
    }

    // Validate uploads before saving anything
    $files = $type === 'custom' ? uploaded_files() : [];
    $maxBytes = (int) $cfg['max_upload_mb'] * 1024 * 1024;
    if (count($files) > 15) {
        fail('Please upload at most 15 files.');
    }
    $totalBytes = 0;
    foreach ($files as $file) {
        if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
            fail("“{$file['name']}” is too large. Please send it on WhatsApp instead.");
        }
        if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            fail("“{$file['name']}” could not be uploaded. Please try again.");
        }
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $cfg['allowed_ext'], true)) {
            fail("“{$file['name']}” is not an allowed file type.");
        }
        $totalBytes += $file['size'];
    }
    if ($totalBytes > $maxBytes) {
        fail("Your files are over {$cfg['max_upload_mb']} MB in total. Remove some and send them on WhatsApp instead.");
    }

    $now = ic_now();
    $order = [
        'code'           => ic_new_order_code($pdo),
        'type'           => $type,
        'customer_name'  => $name,
        'whatsapp'       => $phone,
        'address'        => $address !== '' ? $address : null,
        'region'         => $region,
        'subtotal_bdt'   => $subtotal,
        'delivery_bdt'   => $delivery,
        'total_bdt'      => $total,
        'advance_bdt'    => $advance,
        'payment_method' => $paymentMethod !== '' ? $paymentMethod : null,
        'trx_id'         => $trx !== '' ? $trx : null,
        'customer_email' => $email !== '' ? $email : null,
    ];

    $pdo->beginTransaction();
    $pdo->prepare('INSERT INTO orders (code, type, status, customer_name, whatsapp, address, region, items_json, details_json,
            subtotal_bdt, delivery_bdt, total_bdt, advance_bdt, payment_method, trx_id, is_important, needed_by, reminder_date,
            reminder_note, created_at, updated_at, status_changed_at, client_ip, customer_email)
        VALUES (?, ?, \'new\', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([
            $order['code'], $type, $name, $phone, $order['address'], $region,
            json_encode($items, JSON_UNESCAPED_UNICODE), json_encode($details, JSON_UNESCAPED_UNICODE),
            $subtotal, $delivery, $total, $advance, $order['payment_method'], $order['trx_id'],
            $neededBy !== null ? 1 : 0, $neededBy, $reminderDate, $neededBy !== null ? "Customer needs it by $neededBy" : null,
            $now, $now, $now, $ip, $order['customer_email'],
        ]);
    $orderId = (int) $pdo->lastInsertId();

    $fileNames = [];
    $saveFile = $pdo->prepare('INSERT INTO order_files (order_id, original_name, stored_name, size_bytes, mime, created_at) VALUES (?, ?, ?, ?, ?, ?)');
    foreach ($files as $file) {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $stored = bin2hex(random_bytes(16)) . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], ic_uploads_dir() . '/' . $stored)) {
            throw new RuntimeException('Could not store upload');
        }
        $mime = function_exists('mime_content_type') ? (string) mime_content_type(ic_uploads_dir() . '/' . $stored) : '';
        $original = ic_clean(basename($file['name']), 255);
        $saveFile->execute([$orderId, $original, $stored, $file['size'], $mime, $now]);
        $fileNames[] = $original;
    }
    $pdo->commit();
    ic_attempt_add($pdo, 'order_ip', $ip);
    ic_attempt_add($pdo, 'order_phone', $phone);

    // Fake order check: unlikely-to-accept orders go straight to "Fake order" for the shop to review.
    // The customer always sees a normal confirmation.
    $isFake = false;
    try {
        $check = ic_score_order($pdo, $orderId);
        $isFake = $check['score'] !== null && $check['score'] < (int) ($cfg['fake_threshold'] ?? 40);
        if ($isFake) {
            ic_set_status($pdo, $orderId, 'fake');
        }
    } catch (Throwable $e) {
        error_log('Order score failed: ' . $e->getMessage());
    }

    ic_notify_new_order($order, ic_order_summary($order, $items, $details, $fileNames));
    // Order confirmation for customers who gave an email; the result is noted on the order for the admin
    if ($email !== '') {
        $sent = false;
        try {
            $sent = ic_send_order_confirmation($order, $items, $details);
        } catch (Throwable $e) {
            error_log('Order confirmation email failed: ' . $e->getMessage());
        }
        $details['email_sent'] = $sent ? ic_now() : 'failed';
        $pdo->prepare('UPDATE orders SET details_json = ? WHERE id = ?')->execute([json_encode($details, JSON_UNESCAPED_UNICODE), $orderId]);
    }
    // Facebook only learns from orders that look real; a fake-flagged order is sent later if the shop marks it "Not fake"
    if (!$isFake) {
        ic_meta_order_event($pdo, $order, $items); // server copy of the Pixel Purchase/Lead event
    }

    // "track" tells the website whether to fire its own Purchase/Lead pixel event for this order
    ic_json(['ok' => true, 'code' => $order['code'], 'total' => $total, 'advance' => $advance, 'delivery' => $delivery, 'track' => !$isFake]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Order failed: ' . $e->getMessage());
    ic_json(['ok' => false, 'error' => 'Sorry, we could not save your order. Please message us on WhatsApp.'], 500);
}
