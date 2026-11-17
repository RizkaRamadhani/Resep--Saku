
<?php
require_once dirname(__DIR__) . '/_bootstrap.php';
require_method('POST');
require_auth('admin');

$uploadedFile = null;
$uploadedImage = null;
if (isset($_POST['payload'])) {
    $data = json_decode((string)$_POST['payload'], true);
    if (!is_array($data)) {
        json_response(['success' => false, 'message' => 'Field payload harus berisi JSON valid.'], 400);
    }
} else {
    $data = request_json();
}

$file = $_FILES['gambar'] ?? null;
if ($file !== null) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        json_response(['success' => false, 'message' => 'Upload gambar gagal.'], 422);
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        json_response(['success' => false, 'message' => 'Ukuran gambar maksimal 5 MB.'], 422);
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($extensions[$mime])) {
        json_response(['success' => false, 'message' => 'Format gambar harus JPG, PNG, atau WEBP.'], 422);
    }
    $uploadedImage = ['file' => $file, 'extension' => $extensions[$mime]];
}

$id = filter_var($data['id_resep'] ?? null, FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    json_response(['success' => false, 'message' => 'ID resep tidak valid.'], 422);
}

$judul = clean_string($data['judul_resep'] ?? null, 150);
$waktu = filter_var($data['waktu_estimasi'] ?? null, FILTER_VALIDATE_INT);
$kategori = $data['kategori'] ?? null;
$tingkat = $data['tingkat_kesulitan'] ?? null;
$deskripsi = isset($data['deskripsi']) ? trim((string)$data['deskripsi']) : null;
$bahan = $data['bahan'] ?? null;
$instruksi = $data['instruksi'] ?? null;
$gambar = clean_string($data['gambar_url'] ?? null, 500);

if (!$judul || $waktu === false || $waktu < 0 || $waktu > 65535 ||
    !in_array($kategori, ['Sarapan', 'Makan', 'Camilan'], true) ||
    !in_array($tingkat, ['Mudah', 'Sedang', 'Sulit'], true) ||
    !is_array($bahan) || count($bahan) < 1 || count($bahan) > 100 ||
    !is_array($instruksi) || count($instruksi) < 1 || count($instruksi) > 100) {
    json_response(['success' => false, 'message' => 'Data resep tidak valid.'], 422);
}

try {
    $pdo = db();
    $pdo->beginTransaction();

    $check = $pdo->prepare('SELECT id_resep FROM tb_resep WHERE id_resep = :id');
    $check->execute(['id' => $id]);

    if (!$check->fetchColumn()) {
        $pdo->rollBack();
        json_response(['success' => false, 'message' => 'Resep tidak ditemukan.'], 404);
    }

    if ($uploadedImage !== null) {
        if (!is_dir(UPLOAD_DIR) && !mkdir(UPLOAD_DIR, 0755, true)) {
            throw new RuntimeException('Folder upload tidak dapat dibuat.');
        }

        $filename = bin2hex(random_bytes(16)) . '.' . $uploadedImage['extension'];
        $uploadedFile = rtrim(UPLOAD_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;
        if (!move_uploaded_file($uploadedImage['file']['tmp_name'], $uploadedFile)) {
            throw new RuntimeException('Gambar tidak dapat disimpan.');
        }
        $gambar = rtrim(UPLOAD_URL_PREFIX, '/') . '/' . $filename;
    }

    $sql = 'UPDATE tb_resep SET judul_resep=:judul,
            waktu_estimasi=:waktu, kategori=:kategori,
            tingkat_kesulitan=:tingkat, deskripsi=:deskripsi';

    $params = [
        'judul' => $judul,
        'waktu' => $waktu,
        'kategori' => $kategori,
        'tingkat' => $tingkat,
        'deskripsi' => $deskripsi,
        'id' => $id
    ];

    if ($gambar !== null) {
        $sql .= ', gambar_url=:gambar';
        $params['gambar'] = $gambar;
    }

    $sql .= ' WHERE id_resep=:id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $pdo->prepare('DELETE FROM tb_bahan WHERE id_resep=:id')
        ->execute(['id' => $id]);

    $pdo->prepare('DELETE FROM tb_instruksi WHERE id_resep=:id')
        ->execute(['id' => $id]);

    $insertBahan = $pdo->prepare(
        'INSERT INTO tb_bahan (id_resep,nama_bahan,takaran)
         VALUES (:id,:nama,:takaran)'
    );

    foreach ($bahan as $item) {
        $nama = is_array($item) ? clean_string($item['nama_bahan'] ?? null, 150) : null;
        $takaran = is_array($item) ? clean_string($item['takaran'] ?? null, 100) : null;

        if (!$nama || !$takaran) {
            throw new InvalidArgumentException('Data bahan tidak valid.');
        }

        $insertBahan->execute([
            'id' => $id,
            'nama' => $nama,
            'takaran' => $takaran
        ]);
    }

    $insertInstruksi = $pdo->prepare(
        'INSERT INTO tb_instruksi (id_resep,langkah_ke,deskripsi_langkah)
         VALUES (:id,:langkah,:deskripsi)'
    );

    $seen = [];

    foreach ($instruksi as $item) {
        $step = is_array($item) ? filter_var($item['langkah_ke'] ?? null, FILTER_VALIDATE_INT) : false;
        $desc = is_array($item) ? clean_string($item['deskripsi_langkah'] ?? null, 10000) : null;

        if ($step === false || $step < 1 || $step > 65535 || !$desc || isset($seen[$step])) {
            throw new InvalidArgumentException('Instruksi tidak valid.');
        }

        $seen[$step] = true;
        $insertInstruksi->execute([
            'id' => $id,
            'langkah' => $step,
            'deskripsi' => $desc
        ]);
    }

    $pdo->commit();

    json_response([
        'success' => true,
        'message' => 'Resep berhasil diperbarui.'
    ]);

} catch (InvalidArgumentException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($uploadedFile && is_file($uploadedFile)) {
        unlink($uploadedFile);
    }

    json_response([
        'success' => false,
        'message' => $e->getMessage()
    ], 422);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($uploadedFile && is_file($uploadedFile)) {
        unlink($uploadedFile);
    }

    error_log($e->getMessage());

    json_response([
        'success' => false,
        'message' => 'Gagal memperbarui resep.'
    ], 500);
}
