<?php
declare(strict_types=1);

$configFile = __DIR__ . '/config.php';

if (!is_file($configFile)) {
    http_response_code(500 );
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => 'Missing config.php. Copy config.example.php and configure the database.'
    ]);
    exit;
}

ob_start();
$config = require $configFile;
ob_end_clean();


function db(): PDO {
    static $pdo = null;
    global $config;
    if ($pdo instanceof PDO) return $pdo;
    $d = $config['db'];
    $dsn = "mysql:host={$d['host']};dbname={$d['name']};charset={$d['charset']}";
    $pdo = new PDO($dsn, $d['user'], $d['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

function json_response($payload, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function request_json(): array {
    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) json_response(['success' => false, 'error' => 'Request body must be valid JSON.'], 400);
    return $body;
}

function require_method(string $method): void {
    if ($_SERVER['REQUEST_METHOD'] !== $method) json_response(['success' => false, 'error' => 'Method not allowed.'], 405);
}

function id_or_new(?string $id = null): string { return $id ?: bin2hex(random_bytes(12)); }

function store_id(): string { global $config; return (string)$config['app']['store_id']; }

function clean_string($value, int $max = 10000): string {
    return mb_substr(trim((string)$value), 0, $max);
}

function set_cors(): void {
    global $config;
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
    $allowed = $config['app']['allowed_origins'] ?? ['*'];
    header('Access-Control-Allow-Origin: ' . (in_array('*', $allowed, true) ? '*' : (in_array($origin, $allowed, true) ? $origin : 'null')));
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-Admin-Token');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
}

function require_admin_token(): void {
    // Replace this with your authenticated admin session before exposing write endpoints publicly.
    global $config;
    $expected = $config['app']['admin_token'] ?? '';
    if ($expected === '') return; // setup mode; configure a token before production use
    $provided = $_SERVER['HTTP_X_ADMIN_TOKEN'] ?? '';
    if (!hash_equals($expected, $provided)) json_response(['success' => false, 'error' => 'Admin authorization required.'], 401);
}
