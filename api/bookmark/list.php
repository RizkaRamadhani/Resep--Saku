<?php
require_once dirname(__DIR__) . '/_bootstrap.php';
require_method('GET');
$user = require_auth();

try {
    $stmt = db()->prepare(
        'SELECT r.id_resep, r.judul_resep, r.gambar_url, r.waktu_estimasi,
                r.kategori, r.tingkat_kesulitan, b.created_at AS bookmarked_at
         FROM tb_bookmarks b
         INNER JOIN tb_resep r ON r.id_resep = b.id_resep
         WHERE b.id_user = :user_id ORDER BY b.created_at DESC'
    );
    $stmt->execute(['user_id' => $user['id_user']]);
    json_response(['success' => true, 'data' => $stmt->fetchAll()]);
} catch (Throwable $e) {
    error_log($e->getMessage());
    json_response(['success' => false, 'message' => 'Daftar bookmark gagal dimuat.'], 500);
}
