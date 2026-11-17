<?php
require_once dirname(__DIR__) . '/_bootstrap.php';
require_method('POST');
$data = request_json();

$username = clean_string($data['username'] ?? null, 50);
$password = $data['password'] ?? null;
if (!$username || !is_string($password) || strlen($password) < 8 || strlen($password) > 72) {
    json_response(['success' => false, 'message' => 'Username wajib diisi; password minimal 8 dan maksimal 72 karakter.'], 422);
}

try {
    $stmt = db()->prepare("INSERT INTO tb_users (username, password, role) VALUES (:username, :password, 'user')");
    $stmt->execute(['username' => $username, 'password' => password_hash($password, PASSWORD_DEFAULT)]);
    json_response(['success' => true, 'message' => 'Registrasi berhasil. Silakan login.'], 201);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        json_response(['success' => false, 'message' => 'Username sudah digunakan.'], 409);
    }

    error_log($e->getMessage());
    json_response(['success' => false, 'message' => 'Registrasi gagal diproses.'], 500);
}
