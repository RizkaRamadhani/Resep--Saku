<?php
require_once dirname(__DIR__) . '/_bootstrap.php';
require_method('POST');
$data = request_json();

$username = clean_string($data['username'] ?? null, 50);
$password = $data['password'] ?? null;
if (!$username || !is_string($password)) json_response(['success' => false, 'message' => 'Username dan password wajib diisi.'], 422);

try {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT id_user, username, password, role FROM tb_users WHERE username = :username LIMIT 1');
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch();
    if (!$user || !password_verify($password, $user['password'])) {
        json_response(['success' => false, 'message' => 'Username atau password salah.'], 401);
    }
    if (!jwt_secret_is_configured()) {
        json_response(['success' => false, 'message' => 'Secret autentikasi belum dikonfigurasi oleh administrator.'], 503);
    }

    $now = time();
    $expiresAt = $now + JWT_TTL_SECONDS;
    $token = create_jwt(['sub' => (int)$user['id_user'], 'iat' => $now, 'exp' => $expiresAt]);
    $save = $pdo->prepare(
        'INSERT INTO tb_auth_tokens (id_user, token_hash, expires_at)
         VALUES (:id_user, :token_hash, FROM_UNIXTIME(:expires_at))'
    );
    $save->execute([
        'id_user' => (int)$user['id_user'],
        'token_hash' => hash('sha256', $token),
        'expires_at' => $expiresAt,
    ]);

    json_response([
        'success' => true,
        'message' => 'Login berhasil.',
        'token' => $token,
        'token_type' => 'Bearer',
        'expires_in' => JWT_TTL_SECONDS,
        'user' => ['id_user' => (int)$user['id_user'], 'username' => $user['username'], 'role' => $user['role']]
    ]);
} catch (Throwable $e) {
    error_log($e->getMessage());
    json_response(['success' => false, 'message' => 'Login gagal diproses.'], 500);
}
