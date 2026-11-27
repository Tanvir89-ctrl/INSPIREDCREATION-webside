<?php
// Meta (Facebook) Pixel + Conversions API.
// The browser pixel tracks shop activity; the server sends Purchase/Lead too (same event_id) so orders still count
// when the browser pixel is blocked. Meta merges the two copies (deduplication).
declare(strict_types=1);

function ic_meta_settings(PDO $pdo): array
{
    $saved = json_decode((string) ic_setting($pdo, 'meta_pixel', '{}'), true) ?: [];
    return $saved + [
        'pixel_id'        => '',
        'enabled'         => 0,
        'capi_token'      => '',
        'test_event_code' => '',
        'version'         => 'v25.0',
        'api_host'        => 'https://graph.facebook.com',
    ];
}

function ic_meta_active(array $s): bool
{
    return !empty($s['enabled']) && preg_match('/^\d{6,20}$/', (string) $s['pixel_id']) === 1;
}

function ic_meta_hash(string $value): string
{
    return hash('sha256', strtolower(trim($value)));
}

// Keeps the last few server replies so the admin page can show what Meta answered
function ic_meta_log(PDO $pdo, string $event, bool $ok, string $message): void
{
    $log = json_decode((string) ic_setting($pdo, 'meta_capi_log', '[]'), true) ?: [];
    array_unshift($log, ['at' => ic_now(), 'event' => $event, 'ok' => $ok, 'message' => ic_clean($message, 300)]);
    ic_set_setting($pdo, 'meta_capi_log', json_encode(array_slice($log, 0, 12), JSON_UNESCAPED_UNICODE));
}

// Sends events to the Conversions API; returns Meta's reply
function ic_meta_send(PDO $pdo, array $events, bool $useTestCode = true): array
{
    $s = ic_meta_settings($pdo);
    if (!ic_meta_active($s) || $s['capi_token'] === '') {
        throw new RuntimeException('Pixel ID and Conversions API token are needed.');
    }
    $body = ['data' => $events];
    if ($useTestCode && $s['test_event_code'] !== '') {
        $body['test_event_code'] = $s['test_event_code'];
    }
    $url = rtrim($s['api_host'], '/') . '/' . rawurlencode($s['version']) . '/' . $s['pixel_id'] . '/events?access_token=' . rawurlencode($s['capi_token']);
    [$code, $data] = ic_http_json('POST', $url, [], $body);
    if ($code >= 400 || !isset($data['events_received'])) {
        throw new RuntimeException((string) ($data['error']['message'] ?? "Meta replied with HTTP $code"));
    }
    return $data;
}

// Browser events that also get a server copy (Purchase/Lead are sent by api/orders.php instead)
const IC_META_BROWSER_EVENTS = ['PageView', 'ViewContent', 'Search', 'AddToWishlist', 'AddToCart', 'InitiateCheckout', 'CustomizeProduct', 'Contact'];

// Server copy of one website event, with the same event_id as the browser pixel so Meta keeps only one.
// Input comes from the visitor's browser, so every field is checked and trimmed.
function ic_meta_browser_event(PDO $pdo, array $in): void
{
    $s = ic_meta_settings($pdo);
    $event = (string) ($in['event'] ?? '');
    $eventId = (string) ($in['event_id'] ?? '');
    if (!ic_meta_active($s) || $s['capi_token'] === '' || !in_array($event, IC_META_BROWSER_EVENTS, true)
        || !preg_match('/^[A-Za-z0-9_-]{8,64}$/', $eventId)) {
        return;
    }
    $p = is_array($in['params'] ?? null) ? $in['params'] : [];
    $custom = [];
    if (isset($p['content_ids']) && is_array($p['content_ids'])) {
        $custom['content_ids'] = array_values(array_map(function ($id) { return ic_clean((string) $id, 60); }, array_slice($p['content_ids'], 0, 20)));
        $custom['content_type'] = 'product';
    }
    if (isset($p['content_name'])) {
        $custom['content_name'] = ic_clean((string) $p['content_name'], 150);
    }
    if (isset($p['value']) && is_numeric($p['value'])) {
        $custom['value'] = min(10000000, max(0, (float) $p['value']));
        $custom['currency'] = 'BDT';
    }
    if (isset($p['num_items']) && is_numeric($p['num_items'])) {
        $custom['num_items'] = min(1000, max(0, (int) $p['num_items']));
    }
    if (isset($p['search_string'])) {
        $custom['search_string'] = ic_clean((string) $p['search_string'], 100);
    }
    $userData = [
        'client_ip_address' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
        'client_user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 400),
    ];
    foreach (['fbp', 'fbc'] as $cookie) {
        $value = ic_clean((string) ($in[$cookie] ?? ''), 200);
        if (preg_match('/^fb\.\d\.\d+\./', $value)) {
            $userData[$cookie] = $value;
        }
    }
    try {
        ic_meta_send($pdo, [array_filter([
            'event_name'       => $event,
            'event_time'       => time(),
            'event_id'         => $eventId,
            'action_source'    => 'website',
            'event_source_url' => filter_var((string) ($in['url'] ?? ''), FILTER_VALIDATE_URL) ?: null,
            'user_data'        => $userData,
            'custom_data'      => $custom ?: null,
        ], function ($v) { return $v !== null; })]);
    } catch (Throwable $e) {
        ic_meta_log($pdo, $event, false, $e->getMessage()); // only failures are logged — page views would flood the log
    }
}

// The customer's browser details for an order: from the order's saved meta_json when it has one (orders sent later,
// e.g. after "Not fake"), otherwise from the order request that is happening right now
function ic_meta_order_context(array $order): array
{
    $saved = !empty($order['meta_json']) ? (json_decode((string) $order['meta_json'], true) ?: []) : null;
    if ($saved === null) {
        $saved = ic_meta_request_context();
    }
    return array_merge(['fbp' => '', 'fbc' => '', 'url' => '', 'ua' => '', 'ip' => ''], array_map('strval', $saved));
}

// What Meta needs to match an order to the visitor, read from the website's order request
function ic_meta_request_context(): array
{
    $url = filter_var((string) ($_POST['page_url'] ?? ''), FILTER_VALIDATE_URL) ?: (filter_var((string) ($_SERVER['HTTP_REFERER'] ?? ''), FILTER_VALIDATE_URL) ?: '');
    return [
        'fbp' => ic_clean((string) ($_POST['fbp'] ?? ''), 200),
        'fbc' => ic_clean((string) ($_POST['fbc'] ?? ''), 200),
        'url' => substr($url, 0, 500),
        'ua'  => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 400),
        'ip'  => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
    ];
}

// Sends a saved order (e.g. one the shop just marked "Not fake") with its original time; Meta only accepts the last 7 days
function ic_meta_order_event_from_row(PDO $pdo, array $row): void
{
    $placed = strtotime((string) $row['created_at']);
    if ($placed === false || time() - $placed > 6 * 86400) {
        return;
    }
    ic_meta_order_event($pdo, $row, (array) json_decode((string) $row['items_json'], true), $placed);
}

// Server copy of the website's Purchase (priced orders) or Lead (price-on-request) event for an order.
// $eventTime: when the order was placed (Meta accepts events up to 7 days old); defaults to now.
function ic_meta_order_event(PDO $pdo, array $order, array $items, ?int $eventTime = null): void
{
    $s = ic_meta_settings($pdo);
    if (!ic_meta_active($s) || $s['capi_token'] === '') {
        return;
    }
    $event = $order['total_bdt'] === null ? 'Lead' : 'Purchase';
    $phone = '88' . $order['whatsapp']; // 8801XXXXXXXXX, digits only
    $names = preg_split('/\s+/', trim($order['customer_name'])) ?: [];
    // The customer's own browser details, saved when the order was placed (never the admin's, when an order is
    // sent later after "Not fake")
    $ctx = ic_meta_order_context($order);
    $userData = [
        'ph'                => [ic_meta_hash($phone)],
        'external_id'       => [ic_meta_hash($phone)],
        'country'           => [ic_meta_hash('bd')],
        'client_ip_address' => $ctx['ip'],
        'client_user_agent' => $ctx['ua'],
    ];
    // City from the delivery address (district), lower-case with no spaces as Meta asks
    $place = !empty($order['address']) ? ic_geo_locate((string) $order['address'], $order['region'] ?? null) : null;
    if ($place && empty($place['guessed'])) {
        $userData['ct'] = [ic_meta_hash(preg_replace('/[^a-z]/', '', strtolower(IC_BD_DISTRICTS[$place['district']][0])))];
    }
    if (!empty($order['customer_email'])) {
        $userData['em'] = [ic_meta_hash(strtolower(trim($order['customer_email'])))]; // email gives Meta the best match
    }
    if (!empty($names[0])) {
        $userData['fn'] = [ic_meta_hash($names[0])];
    }
    if (count($names) > 1) {
        $userData['ln'] = [ic_meta_hash((string) end($names))];
    }
    foreach (['fbp', 'fbc'] as $cookie) {
        if ($ctx[$cookie] !== '') {
            $userData[$cookie] = $ctx[$cookie];
        }
    }
    $customData = [
        'currency'     => 'BDT',
        'content_type' => 'product',
        'content_ids'  => array_values(array_map(function ($i) { return (string) $i['id']; }, $items)),
        'contents'     => array_values(array_map(function ($i) {
            return array_filter(['id' => (string) $i['id'], 'quantity' => (int) $i['qty'], 'item_price' => $i['unit_bdt']], function ($v) { return $v !== null; });
        }, $items)),
        'num_items'    => array_sum(array_map(function ($i) { return (int) $i['qty']; }, $items)),
        'order_id'     => $order['code'],
    ];
    if ($order['total_bdt'] !== null) {
        $customData['value'] = (float) $order['total_bdt'];
    }
    $sourceUrl = $ctx['url'] !== '' ? $ctx['url'] : null;
    try {
        $reply = ic_meta_send($pdo, [array_filter([
            'event_name'       => $event,
            'event_time'       => $eventTime ?? time(),
            'event_id'         => $order['code'], // the website sends the same eventID, so Meta counts it once
            'action_source'    => 'website',
            'event_source_url' => $sourceUrl,
            'user_data'        => $userData,
            'custom_data'      => $customData,
        ], function ($v) { return $v !== null; })]);
        ic_meta_log($pdo, "$event {$order['code']}", true, 'Received: ' . ($reply['events_received'] ?? 0));
    } catch (Throwable $e) {
        ic_meta_log($pdo, "$event {$order['code']}", false, $e->getMessage());
    }
}
