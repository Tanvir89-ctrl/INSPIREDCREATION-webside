<?php
// Courier connectors: Steadfast, Pathao and RedX behind one interface.
// Credentials are saved from the admin "Courier" page (settings table); Steadfast can also come from config.php.
// Steadfast follows its official API docs. Pathao and RedX follow their merchant APIs as used by community SDKs,
// so replies are read defensively and "Test connection" shows the raw answer if anything differs.
declare(strict_types=1);

const IC_COURIERS = [
    'steadfast' => [
        'label'    => 'Steadfast',
        'fields'   => ['api_key' => 'API Key', 'secret_key' => 'Secret Key'],
        'secrets'  => ['api_key', 'secret_key'],
        'base_url' => 'https://portal.packzy.com/api/v1',
        'help'     => 'Steadfast merchant panel → API → copy the API Key and Secret Key.',
    ],
    'pathao' => [
        'label'    => 'Pathao',
        'fields'   => ['client_id' => 'Client ID', 'client_secret' => 'Client Secret', 'username' => 'Merchant email', 'password' => 'Merchant password', 'store_id' => 'Store ID'],
        'secrets'  => ['client_secret', 'password'],
        'base_url' => 'https://api-hermes.pathao.com',
        'help'     => 'Pathao merchant panel → Developers API → Merchant API credentials. Store ID is under Stores.',
    ],
    'redx' => [
        'label'    => 'RedX',
        'fields'   => ['token' => 'API access token', 'pickup_store_id' => 'Pickup store ID (optional)'],
        'secrets'  => ['token'],
        'base_url' => 'https://openapi.redx.com.bd/v1.0.0-beta',
        'help'     => 'RedX merchant dashboard → Developer API → generate an access token.',
    ],
];

// Our pipeline status for each courier status (anything unknown keeps the order where it is)
function ic_courier_map_status(string $courier, string $raw): ?string
{
    $s = strtolower(str_replace([' ', '-'], '_', trim($raw)));
    if ($courier === 'steadfast') {
        return IC_STEADFAST_STATUS_MAP[$s] ?? null;
    }
    if ($s === '') {
        return null;
    }
    if (strpos($s, 'partial') !== false) {
        return 'partial';
    }
    if (strpos($s, 'return') !== false || strpos($s, 'cancel') !== false) {
        return 'returned';
    }
    if (in_array($s, ['delivered', 'paid'], true)) {
        return 'delivered';
    }
    if (in_array($s, ['pending', 'pickup_requested', 'assigned_for_pickup', 'pickup_pending'], true)) {
        return 'shipping';
    }
    return 'waiting'; // picked, at hub, in transit, out for delivery, on hold…
}

function ic_courier_settings(PDO $pdo, string $courier): array
{
    $saved = json_decode((string) ic_setting($pdo, "courier_$courier", '{}'), true) ?: [];
    $settings = $saved + ['enabled' => 1, 'base_url' => IC_COURIERS[$courier]['base_url']];
    if ($courier === 'steadfast' && empty($saved['api_key'])) {
        $legacy = ic_config()['courier'] ?? [];
        if (!empty($legacy['api_key'])) {
            $settings = ['api_key' => $legacy['api_key'], 'secret_key' => $legacy['secret_key'] ?? '',
                'base_url' => $legacy['base_url'] ?? $settings['base_url']] + $settings;
        }
    }
    return $settings;
}

function ic_courier_save_settings(PDO $pdo, string $courier, array $settings): void
{
    ic_set_setting($pdo, "courier_$courier", json_encode($settings, JSON_UNESCAPED_UNICODE));
}

function ic_courier_connected(PDO $pdo, string $courier): bool
{
    $s = ic_courier_settings($pdo, $courier);
    if (empty($s['enabled'])) {
        return false;
    }
    foreach (IC_COURIERS[$courier]['fields'] as $field => $label) {
        if (empty($s[$field]) && strpos($label, 'optional') === false) {
            return false;
        }
    }
    return true;
}

function ic_connected_couriers(PDO $pdo): array
{
    return array_values(array_filter(array_keys(IC_COURIERS), function ($c) use ($pdo) {
        return ic_courier_connected($pdo, $c);
    }));
}

function ic_default_courier(PDO $pdo): ?string
{
    $connected = ic_connected_couriers($pdo);
    $default = (string) ic_setting($pdo, 'courier_default', '');
    return in_array($default, $connected, true) ? $default : ($connected[0] ?? null);
}

function ic_courier_key_from_label(?string $label): ?string
{
    foreach (IC_COURIERS as $key => $info) {
        if (strcasecmp((string) $label, $info['label']) === 0) {
            return $key;
        }
    }
    return null;
}

// JSON over HTTPS; returns [httpCode, decodedBody]
function ic_http_json(string $method, string $url, array $headers, ?array $body = null): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 25,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => array_merge(['Accept: application/json', 'Content-Type: application/json'], $headers),
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
    }
    $raw = curl_exec($ch);
    $error = curl_error($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($raw === false) {
        throw new RuntimeException('Could not reach the courier: ' . $error);
    }
    $data = json_decode((string) $raw, true);
    if (!is_array($data)) {
        throw new RuntimeException("The courier replied with HTTP $code: " . substr(strip_tags((string) $raw), 0, 160));
    }
    return [$code, $data];
}

// Finds the first value for any of $keys anywhere in a nested reply (couriers wrap data differently)
function ic_find_value(array $data, array $keys)
{
    foreach ($keys as $key) {
        if (isset($data[$key]) && !is_array($data[$key])) {
            return $data[$key];
        }
    }
    foreach ($data as $value) {
        if (is_array($value)) {
            $found = ic_find_value($value, $keys);
            if ($found !== null) {
                return $found;
            }
        }
    }
    return null;
}

function ic_courier_error(array $data, string $fallback): string
{
    $message = ic_find_value($data, ['message', 'error', 'msg']);
    $errors = $data['errors'] ?? null;
    if (is_array($errors)) {
        $flat = [];
        array_walk_recursive($errors, function ($v) use (&$flat) { $flat[] = $v; });
        $message = trim(($message ? "$message: " : '') . implode(' ', $flat));
    }
    return $message ? (string) $message : $fallback;
}

/* ---------------- Steadfast ---------------- */

function ic_steadfast_call(array $s, string $method, string $path, ?array $body = null): array
{
    return ic_http_json($method, rtrim($s['base_url'], '/') . $path, ['Api-Key: ' . $s['api_key'], 'Secret-Key: ' . $s['secret_key']], $body)[1];
}

/* ---------------- Pathao ---------------- */

function ic_pathao_token(PDO $pdo, array $s, bool $fresh = false): string
{
    $cached = json_decode((string) ic_setting($pdo, 'pathao_token', '{}'), true) ?: [];
    if (!$fresh && !empty($cached['token']) && ($cached['expires_at'] ?? 0) > time() + 60 && ($cached['client_id'] ?? '') === $s['client_id']) {
        return $cached['token'];
    }
    [$code, $data] = ic_http_json('POST', rtrim($s['base_url'], '/') . '/aladdin/api/v1/issue-token', [], [
        'client_id'     => $s['client_id'],
        'client_secret' => $s['client_secret'],
        'username'      => $s['username'],
        'password'      => $s['password'],
        'grant_type'    => 'password',
    ]);
    $token = ic_find_value($data, ['access_token', 'token']);
    if (!$token) {
        // Some Pathao accounts use the newer client-credentials login
        [$code, $data] = ic_http_json('POST', rtrim($s['base_url'], '/') . '/aladdin/api/v1/external/login', [], [
            'client_id' => $s['client_id'], 'client_secret' => $s['client_secret'],
        ]);
        $token = ic_find_value($data, ['access_token', 'token']);
    }
    if (!$token) {
        throw new RuntimeException('Pathao login failed: ' . ic_courier_error($data, "HTTP $code"));
    }
    $expires = (int) (ic_find_value($data, ['expires_in']) ?? 3600);
    $expiresAt = $expires > 1000000000 ? $expires : time() + $expires; // seconds or an epoch time
    ic_set_setting($pdo, 'pathao_token', json_encode(['token' => $token, 'expires_at' => $expiresAt, 'client_id' => $s['client_id']]));
    return (string) $token;
}

function ic_pathao_call(PDO $pdo, array $s, string $method, string $path, ?array $body = null): array
{
    $url = rtrim($s['base_url'], '/') . '/aladdin/api/v1/' . ltrim($path, '/');
    [$code, $data] = ic_http_json($method, $url, ['Authorization: Bearer ' . ic_pathao_token($pdo, $s)], $body);
    if ($code === 401) { // token expired early: log in again once
        [$code, $data] = ic_http_json($method, $url, ['Authorization: Bearer ' . ic_pathao_token($pdo, $s, true)], $body);
    }
    return [$code, $data];
}

// City / zone / area lists for the order page dropdowns: [[id, name], ...]
function ic_pathao_locations(PDO $pdo, string $type, int $parentId = 0): array
{
    $s = ic_courier_settings($pdo, 'pathao');
    $paths = ['cities' => 'countries/1/city-list', 'zones' => "cities/$parentId/zone-list", 'areas' => "zones/$parentId/area-list"];
    $keys = ['cities' => ['city_id', 'city_name'], 'zones' => ['zone_id', 'zone_name'], 'areas' => ['area_id', 'area_name']][$type];
    [, $data] = ic_pathao_call($pdo, $s, 'GET', $paths[$type]);
    $list = $data['data']['data'] ?? $data['data'] ?? [];
    $out = [];
    foreach ((array) $list as $row) {
        if (is_array($row) && isset($row[$keys[0]])) {
            $out[] = [(int) $row[$keys[0]], (string) ($row[$keys[1]] ?? $row[$keys[0]])];
        }
    }
    return $out;
}

/* ---------------- RedX ---------------- */

function ic_redx_call(array $s, string $method, string $path, ?array $body = null): array
{
    return ic_http_json($method, rtrim($s['base_url'], '/') . $path, ['API-ACCESS-TOKEN: Bearer ' . $s['token']], $body);
}

// Delivery areas of a district (cached for a day): [[id, name], ...]
function ic_redx_areas(PDO $pdo, string $district): array
{
    $cacheKey = 'redx_areas_' . strtolower(preg_replace('/[^a-z]/i', '', $district));
    $cached = json_decode((string) ic_setting($pdo, $cacheKey, '{}'), true) ?: [];
    if (($cached['at'] ?? 0) > time() - 86400 && !empty($cached['areas'])) {
        return $cached['areas'];
    }
    [, $data] = ic_redx_call(ic_courier_settings($pdo, 'redx'), 'GET', '/areas?district_name=' . rawurlencode($district));
    $areas = [];
    foreach ((array) ($data['areas'] ?? (array_is_list_compat($data) ? $data : [])) as $row) {
        if (is_array($row) && isset($row['id'])) {
            $areas[] = [(int) $row['id'], (string) ($row['name'] ?? $row['id'])];
        }
    }
    ic_set_setting($pdo, $cacheKey, json_encode(['at' => time(), 'areas' => $areas], JSON_UNESCAPED_UNICODE));
    return $areas;
}

function array_is_list_compat(array $a): bool
{
    return $a === [] || array_keys($a) === range(0, count($a) - 1);
}

// Picks the RedX area whose name appears in the address (longest match wins)
function ic_redx_guess_area(PDO $pdo, string $address, string $region): ?array
{
    $districts = ['Dhaka', 'Gazipur', 'Narayanganj', 'Chattogram', 'Sylhet', 'Rajshahi', 'Khulna', 'Barishal', 'Rangpur', 'Mymensingh',
        'Cumilla', 'Bogura', 'Jashore', 'Cox\'s Bazar', 'Feni', 'Noakhali', 'Tangail', 'Savar', 'Narsingdi', 'Dinajpur'];
    $district = $region === 'dhaka' ? 'Dhaka' : null;
    foreach ($districts as $d) {
        if (stripos($address, $d) !== false) {
            $district = $d;
            break;
        }
    }
    $best = null;
    foreach (ic_redx_areas($pdo, $district ?? 'Dhaka') as [$id, $name]) {
        if ($name !== '' && stripos($address, $name) !== false && (!$best || strlen($name) > strlen($best[1]))) {
            $best = [$id, $name];
        }
    }
    return $best;
}

/* ---------------- One interface for all couriers ---------------- */

// Books the parcel; the order moves to Shipping. $opts: pathao city/zone/area ids, redx area id/name, weight (kg)
function ic_courier_send(PDO $pdo, array $order, string $courier, int $cod, array $opts = []): void
{
    if (!ic_courier_connected($pdo, $courier)) {
        throw new RuntimeException(IC_COURIERS[$courier]['label'] . ' is not connected yet.');
    }
    if (!$order['address']) {
        throw new RuntimeException('Add the delivery address first.');
    }
    $s = ic_courier_settings($pdo, $courier);
    $items = (array) json_decode($order['items_json'], true);
    $description = ic_clean(implode(', ', array_map(function ($i) { return "{$i['title']} x{$i['qty']}"; }, $items)), 250);
    $quantity = max(1, array_sum(array_map(function ($i) { return (int) $i['qty']; }, $items)));
    $note = $order['needed_by'] ? "Needed by {$order['needed_by']}" : '';
    $cod = max(0, $cod);

    if ($courier === 'steadfast') {
        $data = ic_steadfast_call($s, 'POST', '/create_order', [
            'invoice' => $order['code'], 'recipient_name' => ic_clean($order['customer_name'], 100), 'recipient_phone' => $order['whatsapp'],
            'recipient_address' => ic_clean((string) $order['address'], 250), 'cod_amount' => $cod, 'item_description' => $description, 'note' => $note,
        ]);
        $c = $data['consignment'] ?? [];
        if ((int) ($data['status'] ?? 0) !== 200 || empty($c['consignment_id'])) {
            throw new RuntimeException(ic_courier_error($data, 'Steadfast did not accept the parcel.'));
        }
        $consignment = (string) $c['consignment_id'];
        $tracking = (string) ($c['tracking_code'] ?? '');
        $raw = (string) ($c['status'] ?? 'in_review');
    } elseif ($courier === 'pathao') {
        $body = [
            'store_id' => (int) $s['store_id'], 'merchant_order_id' => $order['code'],
            'recipient_name' => ic_clean($order['customer_name'], 100), 'recipient_phone' => $order['whatsapp'],
            'recipient_address' => ic_clean((string) $order['address'], 220),
            'delivery_type' => 48, 'item_type' => 2, 'item_quantity' => $quantity, 'item_weight' => (float) ($opts['weight'] ?? 0.5),
            'amount_to_collect' => $cod, 'item_description' => $description, 'special_instruction' => $note,
        ];
        foreach (['recipient_city' => 'city', 'recipient_zone' => 'zone', 'recipient_area' => 'area'] as $field => $opt) {
            if (!empty($opts[$opt])) {
                $body[$field] = (int) $opts[$opt];
            }
        }
        [$code, $data] = ic_pathao_call($pdo, $s, 'POST', 'orders', $body);
        $consignment = (string) (ic_find_value($data, ['consignment_id']) ?? '');
        if ($consignment === '') {
            throw new RuntimeException('Pathao: ' . ic_courier_error($data, "HTTP $code — choose city, zone and area and try again."));
        }
        $tracking = $consignment;
        $raw = (string) (ic_find_value($data, ['order_status']) ?? 'Pending');
    } else {
        $area = !empty($opts['redx_area_id']) ? [(int) $opts['redx_area_id'], (string) ($opts['redx_area_name'] ?? '')]
            : ic_redx_guess_area($pdo, (string) $order['address'], (string) $order['region']);
        if (!$area) {
            throw new RuntimeException('RedX needs the delivery area — choose it on the order page.');
        }
        $body = [
            'customer_name' => ic_clean($order['customer_name'], 100), 'customer_phone' => $order['whatsapp'],
            'delivery_area' => $area[1], 'delivery_area_id' => $area[0], 'customer_address' => ic_clean((string) $order['address'], 250),
            'merchant_invoice_id' => $order['code'], 'cash_collection_amount' => (string) $cod,
            'parcel_weight' => (int) round(((float) ($opts['weight'] ?? 0.5)) * 1000), 'instruction' => $note, 'value' => (int) $order['total_bdt'],
        ];
        if (!empty($s['pickup_store_id'])) {
            $body['pickup_store_id'] = (int) $s['pickup_store_id'];
        }
        [$code, $data] = ic_redx_call($s, 'POST', '/parcel', $body);
        $tracking = (string) (ic_find_value($data, ['tracking_id', 'trackingId']) ?? '');
        if ($tracking === '') {
            throw new RuntimeException('RedX: ' . ic_courier_error($data, "HTTP $code"));
        }
        $consignment = $tracking;
        $raw = 'pickup-pending';
    }

    $now = ic_now();
    $pdo->prepare('UPDATE orders SET courier = ?, consignment_id = ?, tracking_code = ?, courier_status = ?, cod_bdt = ?,
            shipped_at = ?, status = ?, status_changed_at = ?, updated_at = ? WHERE id = ?')
        ->execute([IC_COURIERS[$courier]['label'], $consignment, $tracking, $raw, $cod, $now,
            ic_courier_map_status($courier, $raw) ?? 'shipping', $now, $now, $order['id']]);
}

// Latest status text from the courier for one order
function ic_courier_fetch_status(PDO $pdo, string $courier, array $order): string
{
    $s = ic_courier_settings($pdo, $courier);
    if ($courier === 'steadfast') {
        $data = ic_steadfast_call($s, 'GET', '/status_by_cid/' . rawurlencode($order['consignment_id']));
        return (string) ($data['delivery_status'] ?? '');
    }
    if ($courier === 'pathao') {
        [, $data] = ic_pathao_call($pdo, $s, 'GET', 'orders/' . rawurlencode($order['consignment_id']) . '/info');
        return (string) (ic_find_value($data, ['order_status', 'status']) ?? '');
    }
    [, $data] = ic_redx_call($s, 'GET', '/parcel/info/' . rawurlencode($order['tracking_code'] ?: $order['consignment_id']));
    return (string) (ic_find_value($data, ['status']) ?? '');
}

// Checks credentials with a harmless read call; returns a short human message
function ic_courier_test(PDO $pdo, string $courier): string
{
    $s = ic_courier_settings($pdo, $courier);
    if ($courier === 'steadfast') {
        $data = ic_steadfast_call($s, 'GET', '/get_balance');
        if (!isset($data['current_balance'])) {
            throw new RuntimeException(ic_courier_error($data, 'Unexpected reply: ' . json_encode($data)));
        }
        return 'Connected. Current balance ৳' . number_format((float) $data['current_balance']);
    }
    if ($courier === 'pathao') {
        [$code, $data] = ic_pathao_call($pdo, $s, 'GET', 'stores');
        $stores = $data['data']['data'] ?? $data['data'] ?? null;
        if ($code >= 400 || !is_array($stores)) {
            throw new RuntimeException(ic_courier_error($data, "HTTP $code: " . json_encode($data)));
        }
        return 'Connected. ' . count($stores) . ' store' . (count($stores) === 1 ? '' : 's') . ' found on your Pathao account.';
    }
    [$code, $data] = ic_redx_call($s, 'GET', '/pickup/stores');
    if ($code >= 400 || !isset($data['pickup_stores'])) {
        throw new RuntimeException(ic_courier_error($data, "HTTP $code: " . json_encode($data)));
    }
    return 'Connected. ' . count((array) $data['pickup_stores']) . ' pickup store(s) found on your RedX account.';
}
