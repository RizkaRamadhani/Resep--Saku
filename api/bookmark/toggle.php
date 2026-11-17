<?php
require_once dirname(__DIR__) . '/_bootstrap.php';
require_method('POST');
$user = require_auth();

$data = request_json();
$idResep = filter_var($data['id_resep'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$idResep) json_response(['success' => false, 'message' => 'ID resep tidak valid.'], 422);

$pdo = db();
try {
    $checkRecipe = $pdo->prepare('SELECT id_resep FROM tb_resep WHERE id_resep = :id LIMIT 1');
    $checkRecipe->execute(['id' => $idResep]);
    if (!$checkRecipe->fetchColumn()) json_response(['success' => false, 'message' => 'Resep tidak ditemukan.'], 404);

    $pdo->beginTransaction();
    $check = $pdo->prepare('SELECT id_bookmark FROM tb_bookmarks WHERE id_user = :user_id AND id_resep = :recipe_id FOR UPDATE');
    $check->execute(['user_id' => $user['id_user'], 'recipe_id' => $idResep]);
    $bookmarkId = $check->fetchColumn();

    if ($bookmarkId) {
        $delete = $pdo->prepare('DELETE FROM tb_bookmarks WHERE id_bookmark = :id');
        $delete->execute(['id' => $bookmarkId]);
        $pdo->commit();
        json_response(['success' => true, 'bookmarked' => false, 'message' => 'Resep dihapus dari bookmark.']);
    }

    $insert = $pdo->prepare('INSERT INTO tb_bookmarks (id_user, id_resep) VALUES (:user_id, :recipe_id)');
    $insert->execute(['user_id' => $user['id_user'], 'recipe_id' => $idResep]);
    $pdo->commit();
    json_response(['success' => true, 'bookmarked' => true, 'message' => 'Resep ditambahkan ke bookmark.'], 201);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log($e->getMessage());
    json_response(['success' => false, 'message' => 'Bookmark gagal diproses.'], 500);
}
