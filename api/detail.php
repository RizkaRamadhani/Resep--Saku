<?php
require_once __DIR__ . '/_bootstrap.php';
require_method('GET');

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$id) json_response(['success' => false, 'message' => 'Parameter id resep tidak valid.'], 422);

try {
    $pdo = db();
    $recipeStmt = $pdo->prepare(
        'SELECT id_resep, judul_resep, gambar_url, waktu_estimasi, kategori, tingkat_kesulitan, deskripsi
         FROM tb_resep WHERE id_resep = :id LIMIT 1'
    );
    $recipeStmt->execute(['id' => $id]);
    $recipe = $recipeStmt->fetch();
    if (!$recipe) json_response(['success' => false, 'message' => 'Resep tidak ditemukan.'], 404);

    $ingredientStmt = $pdo->prepare(
        'SELECT id_bahan, nama_bahan, takaran FROM tb_bahan
         WHERE id_resep = :id ORDER BY id_bahan ASC'
    );
    $ingredientStmt->execute(['id' => $id]);
    $ingredients = $ingredientStmt->fetchAll();

    $instructionStmt = $pdo->prepare(
        'SELECT id_instruksi, langkah_ke, deskripsi_langkah FROM tb_instruksi
         WHERE id_resep = :id ORDER BY langkah_ke ASC'
    );
    $instructionStmt->execute(['id' => $id]);
    $instructions = $instructionStmt->fetchAll();

    json_response(['success' => true, 'data' => [
        'resep' => $recipe,
        'bahan' => $ingredients,
        'instruksi' => $instructions
    ]]);
} catch (Throwable $e) {
    error_log($e->getMessage());
    json_response(['success' => false, 'message' => 'Detail resep gagal dimuat.'], 500);
}
