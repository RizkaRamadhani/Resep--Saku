<?php
require_once __DIR__ . '/_bootstrap.php';
require_method('GET');

$q = trim((string)($_GET['q'] ?? ''));
$kategori = trim((string)($_GET['kategori'] ?? ''));
if (mb_strlen($q) > 100) json_response(['success' => false, 'message' => 'Kata kunci terlalu panjang.'], 422);

try {
    $params = [];
    $where = [];
    if ($q !== '') {
        $where[] = '(MATCH(r.judul_resep) AGAINST (:q_full IN NATURAL LANGUAGE MODE)
                     OR r.judul_resep LIKE :q_like
                     OR EXISTS (SELECT 1 FROM tb_bahan b
                                WHERE b.id_resep = r.id_resep AND b.nama_bahan LIKE :q_bahan))';
        $params['q_full'] = $q;
        $params['q_like'] = '%' . $q . '%';
        $params['q_bahan'] = '%' . $q . '%';
    }
    if ($kategori !== '') {
        $allowed = ['Sarapan', 'Makan', 'Camilan'];
        if (!in_array($kategori, $allowed, true)) json_response(['success' => false, 'message' => 'Kategori tidak valid.'], 422);
        $where[] = 'r.kategori = :kategori';
        $params['kategori'] = $kategori;
    }

    $sql = 'SELECT r.id_resep, r.judul_resep, r.gambar_url, r.waktu_estimasi,
                   r.kategori, r.tingkat_kesulitan,
                   (SELECT COUNT(*) FROM tb_bahan b2 WHERE b2.id_resep = r.id_resep) AS jumlah_bahan
            FROM tb_resep r';
    if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
    $sql .= ' ORDER BY r.id_resep DESC LIMIT 100';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    json_response(['success' => true, 'data' => $rows, 'total' => count($rows)]);
} catch (Throwable $e) {
    error_log($e->getMessage());
    json_response(['success' => false, 'message' => 'Pencarian resep gagal diproses.'], 500);
}
