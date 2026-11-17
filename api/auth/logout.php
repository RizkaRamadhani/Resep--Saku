<?php
require_once dirname(__DIR__) . '/_bootstrap.php';
require_method('POST');
$token = bearer_token();
if (!$token) json_response(['success' => false, 'message' => 'Token tidak ditemukan.'], 401);
$user = require_auth();

try {
    $stmt = db()->prepare('UPDATE tb_auth_tokens SET revoked_at = NOW() WHERE token_hash = :token_hash AND revoked_at IS NULL');
    $stmt->execute(['token_hash' => hash('sha256', $token)]);
    json_response(['success' => true, 'message' => 'Logout berhasil.']);
} catch (Throwable $e) {
    error_log($e->getMessage());
    json_response(['success' => false, 'message' => 'Logout gagal diproses.'], 500);
}
