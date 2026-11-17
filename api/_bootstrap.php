<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Vary: Origin');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function json_response(array $data, int $status = 200): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function require_method(string $method): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== $method) {
        header('Allow: ' . $method . ', OPTIONS');
        json_response(['success' => false, 'message' => 'Method tidak diizinkan.'], 405);
    }
}

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

function request_json(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '', true);
    if (!is_array($data)) json_response(['success' => false, 'message' => 'Body harus berupa JSON yang valid.'], 400);
    return $data;
}

function clean_string(mixed $value, int $maxLength): ?string {
    if (!is_string($value)) return null;
    $value = trim($value);
    if ($value === '' || mb_strlen($value) > $maxLength) return null;
    return $value;
}

function base64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function create_jwt(array $payload): string {
    $header = base64url_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $body = base64url_encode(json_encode($payload));
    $signature = base64url_encode(hash_hmac('sha256', $header . '.' . $body, JWT_SECRET, true));
    return $header . '.' . $body . '.' . $signature;
}

function jwt_secret_is_configured(): bool {
    return strlen(JWT_SECRET) >= 32 &&
        JWT_SECRET !== 'GANTI_DENGAN_SECRET_ACAK_MINIMAL_32_KARAKTER';
}

function bearer_token(): ?string {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!$header && function_exists('getallheaders')) {
        $headers = getallheaders();
        $header = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    }
    return preg_match('/^Bearer\s+(.+)$/i', trim($header), $m) ? trim($m[1]) : null;
}

function require_auth(?string $requiredRole = null): array {
    $token = bearer_token();
    if (!$token) json_response(['success' => false, 'message' => 'Token tidak ditemukan. Silakan login.'], 401);
    if (!jwt_secret_is_configured()) {
        json_response(['success' => false, 'message' => 'Secret autentikasi belum dikonfigurasi oleh administrator.'], 503);
    }

    $parts = explode('.', $token);
    if (count($parts) !== 3) json_response(['success' => false, 'message' => 'Token tidak valid.'], 401);

    [$head, $body, $signature] = $parts;
    $expected = base64url_encode(hash_hmac('sha256', $head . '.' . $body, JWT_SECRET, true));
    if (!hash_equals($expected, $signature)) json_response(['success' => false, 'message' => 'Token tidak valid.'], 401);

    $payloadJson = base64_decode(strtr($body, '-_', '+/') . str_repeat('=', (4 - strlen($body) % 4) % 4));
    $payload = json_decode($payloadJson ?: '', true);
    if (!is_array($payload) || empty($payload['sub']) || empty($payload['exp']) || (int)$payload['exp'] < time()) {
        json_response(['success' => false, 'message' => 'Token sudah kedaluwarsa atau tidak valid.'], 401);
    }

    $stmt = db()->prepare(
        'SELECT u.id_user, u.username, u.role
         FROM tb_auth_tokens t
         INNER JOIN tb_users u ON u.id_user = t.id_user
         WHERE t.token_hash = :token_hash AND t.revoked_at IS NULL
           AND t.expires_at > NOW() AND u.id_user = :id_user
         LIMIT 1'
    );
    $stmt->execute(['token_hash' => hash('sha256', $token), 'id_user' => (int)$payload['sub']]);
    $user = $stmt->fetch();
    if (!$user) json_response(['success' => false, 'message' => 'Sesi tidak aktif. Silakan login kembali.'], 401);
    if ($requiredRole !== null && $user['role'] !== $requiredRole) {
        json_response(['success' => false, 'message' => 'Akses ditolak. Role tidak memiliki izin.'], 403);
    }
    return $user;
}
