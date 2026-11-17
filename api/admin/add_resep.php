<?php
require_once dirname(__DIR__) . '/_bootstrap.php';
require_method('POST');
require_auth('admin');

$uploadedFile = null;
$gambarUrl = null;

if (!empty($_FILES['gambar']) && isset($_POST['payload'])) {
    $payload = json_decode((string)$_POST['payload'], true);
    if (!is_array($payload)) json_response(['success' => false, 'message' => 'Field payload harus berisi JSON valid.'], 400);

    $file = $_FILES['gambar'];
    if ($file['error'] !== UPLOAD_ERR_OK) json_response(['success' => false, 'message' => 'Upload gambar gagal.'], 422);
    if ($file['size'] > 5 * 1024 * 1024) json_response(['success' => false, 'message' => 'Ukuran gambar maksimal 5 MB.'], 422);

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($extensions[$mime])) json_response(['success' => false, 'message' => 'Format gambar harus JPG, PNG, atau WEBP.'], 422);
    if (!is_dir(UPLOAD_DIR) && !mkdir(UPLOAD_DIR, 0755, true)) {
        json_response(['success' => false, 'message' => 'Folder upload tidak dapat dibuat.'], 500);
    }
    $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    $uploadedFile = rtrim(UPLOAD_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;
    if (!move_uploaded_file($file['tmp_name'], $uploadedFile)) {
        json_response(['success' => false, 'message' => 'Gambar tidak dapat disimpan.'], 500);
    }
    $gambarUrl = rtrim(UPLOAD_URL_PREFIX, '/') . '/' . $filename;
} else {
    $payload = request_json();
    $gambarUrl = clean_string($payload['gambar_url'] ?? null, 500);
}

$judul = clean_string($payload['judul_resep'] ?? null, 150);
$waktu = filter_var($payload['waktu_estimasi'] ?? 0, FILTER_VALIDATE_INT);
$kategori = $payload['kategori'] ?? 'Makan';
$tingkat = $payload['tingkat_kesulitan'] ?? 'Mudah';
$deskripsi = isset($payload['deskripsi']) ? trim((string)$payload['deskripsi']) : null;
$bahan = $payload['bahan'] ?? null;
$instruksi = $payload['instruksi'] ?? null;

if (!$judul || $waktu === false || $waktu < 0 || $waktu > 65535) {
    if ($uploadedFile && is_file($uploadedFile)) unlink($uploadedFile);
    json_response(['success' => false, 'message' => 'Judul atau waktu estimasi tidak valid.'], 422);
}
if (!in_array($kategori, ['Sarapan', 'Makan', 'Camilan'], true) ||
    !in_array($tingkat, ['Mudah', 'Sedang', 'Sulit'], true)) {
    if ($uploadedFile && is_file($uploadedFile)) unlink($uploadedFile);
    json_response(['success' => false, 'message' => 'Kategori atau tingkat kesulitan tidak valid.'], 422);
}
if (!is_array($bahan) || count($bahan) < 1 || !is_array($instruksi) || count($instruksi) < 1) {
    if ($uploadedFile && is_file($uploadedFile)) unlink($uploadedFile);
    json_response(['success' => false, 'message' => 'Array bahan dan instruksi wajib diisi minimal satu item.'], 422);
}
if (count($bahan) > 100 || count($instruksi) > 100) {
    if ($uploadedFile && is_file($uploadedFile)) unlink($uploadedFile);
    json_response(['success' => false, 'message' => 'Maksimal 100 bahan dan 100 langkah instruksi.'], 422);
}

try {
    $pdo = db();
    $pdo->beginTransaction();

    $insertRecipe = $pdo->prepare(
        'INSERT INTO tb_resep (judul_resep, gambar_url, waktu_estimasi, kategori, tingkat_kesulitan, deskripsi)
         VALUES (:judul, :gambar, :waktu, :kategori, :tingkat, :deskripsi)'
    );
    $insertRecipe->execute([
        'judul' => $judul, 'gambar' => $gambarUrl, 'waktu' => $waktu,
        'kategori' => $kategori, 'tingkat' => $tingkat, 'deskripsi' => $deskripsi
    ]);
    $idResep = (int)$pdo->lastInsertId();

    $insertIngredient = $pdo->prepare(
        'INSERT INTO tb_bahan (id_resep, nama_bahan, takaran) VALUES (:id_resep, :nama, :takaran)'
    );
    foreach ($bahan as $item) {
        $nama = is_array($item) ? clean_string($item['nama_bahan'] ?? null, 150) : null;
        $takaran = is_array($item) ? clean_string($item['takaran'] ?? null, 100) : null;
        if (!$nama || !$takaran) throw new InvalidArgumentException('Data bahan tidak lengkap atau terlalu panjang.');
        $insertIngredient->execute(['id_resep' => $idResep, 'nama' => $nama, 'takaran' => $takaran]);
    }

    $insertInstruction = $pdo->prepare(
        'INSERT INTO tb_instruksi (id_resep, langkah_ke, deskripsi_langkah)
         VALUES (:id_resep, :langkah, :deskripsi)'
    );
    $seenSteps = [];
    foreach ($instruksi as $item) {
        $step = is_array($item) ? filter_var($item['langkah_ke'] ?? null, FILTER_VALIDATE_INT) : false;
        $description = is_array($item) ? clean_string($item['deskripsi_langkah'] ?? null, 10000) : null;
        if ($step === false || $step < 1 || $step > 65535 || !$description || isset($seenSteps[$step])) {
            throw new InvalidArgumentException('Data instruksi tidak valid atau nomor langkah ganda.');
        }
        $seenSteps[$step] = true;
        $insertInstruction->execute(['id_resep' => $idResep, 'langkah' => $step, 'deskripsi' => $description]);
    }

    $pdo->commit();
    json_response(['success' => true, 'message' => 'Resep berhasil ditambahkan.', 'data' => [
        'id_resep' => $idResep, 'judul_resep' => $judul, 'gambar_url' => $gambarUrl
    ]], 201);
} catch (InvalidArgumentException $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    if ($uploadedFile && is_file($uploadedFile)) unlink($uploadedFile);
    json_response(['success' => false, 'message' => $e->getMessage()], 422);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    if ($uploadedFile && is_file($uploadedFile)) unlink($uploadedFile);
    error_log($e->getMessage());
    json_response(['success' => false, 'message' => 'Gagal menambahkan resep. Semua perubahan database dibatalkan.'], 500);
}
