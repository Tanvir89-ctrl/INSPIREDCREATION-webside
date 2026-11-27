<?php
// Public: server copy of a website pixel event (sent by the browser with navigator.sendBeacon).
// Answers at once, then forwards the event to Meta, so visitors never wait for Facebook.
require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 4096 || empty($_SERVER['HTTP_USER_AGENT'])) {
    http_response_code(400);
    exit;
}
$input = json_decode((string) file_get_contents('php://input'), true);

ignore_user_abort(true); // keep forwarding to Meta after the browser has gone
ic_discard_output();
http_response_code(204);
header('Content-Length: 0');
header('Connection: close');
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} else {
    flush();
}

if (is_array($input)) {
    try {
        ic_meta_browser_event(ic_db(), $input);
    } catch (Throwable $e) {
        error_log('Pixel server event failed: ' . $e->getMessage());
    }
}
