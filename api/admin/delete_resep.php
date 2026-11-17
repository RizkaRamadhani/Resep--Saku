<?php
require_once dirname(__DIR__) . '/_bootstrap.php';

require_method('POST');
require_auth('admin');

$data = request_json();

$id = filter_var($data['id_resep'] ?? null, FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    json_response([
        'success' => false,
        'message' => 'ID resep tidak valid.'
    ], 422);
}

try {
    $pdo = db();
    $pdo->beginTransaction();

    // Periksa apakah resep tersedia
    $check = $pdo->prepare(
        'SELECT id_resep FROM tb_resep WHERE id_resep = :id'
    );
    $check->execute(['id' => $id]);

    if (!$check->fetchColumn()) {
        $pdo->rollBack();

        json_response([
            'success' => false,
            'message' => 'Resep tidak ditemukan.'
        ], 404);
    }

    // Hapus data terkait resep
    $pdo->prepare(
        'DELETE FROM tb_bookmarks WHERE id_resep = :id'
    )->execute(['id' => $id]);

    $pdo->prepare(
        'DELETE FROM tb_bahan WHERE id_resep = :id'
    )->execute(['id' => $id]);

    $pdo->prepare(
        'DELETE FROM tb_instruksi WHERE id_resep = :id'
    )->execute(['id' => $id]);

    // Hapus resep utama
    $pdo->prepare(
        'DELETE FROM tb_resep WHERE id_resep = :id'
    )->execute(['id' => $id]);

    $pdo->commit();

    json_response([
        'success' => true,
        'message' => 'Resep berhasil dihapus.'
    ]);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log($e->getMessage());

    json_response([
        'success' => false,
        'message' => 'Gagal menghapus resep.'
    ], 500);
}