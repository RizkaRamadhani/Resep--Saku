SKEMA DATABASE RESEP SAKU
========================

Folder ini hanya menyimpan skrip SQL untuk menyiapkan database.
Kode frontend dan backend aktif berada di folder utama proyek:
C:\laragon\www\Resep--Saku

PILIH SKRIP SESUAI KONDISI DATABASE
- database_baru.sql: hanya untuk database baru/kosong.
- migration_existing_database.sql: untuk database lama yang sudah memiliki
  tabel tb_resep, tb_bahan, dan tb_instruksi. Ekspor/backup database terlebih
  dahulu. Migrasi ini menambahkan tabel autentikasi dan bookmark tanpa menghapus
  data resep yang ada.

Jangan impor database_baru.sql ke database lama yang datanya ingin dipertahankan.
Skrip database_baru.sql membuat skema baru dan data contoh.

KONFIGURASI APLIKASI
- Buka aplikasi melalui Laragon di http://localhost:8080/Resep--Saku/.
- Koneksi database diatur di config/config.php.
- Sebelum memakai autentikasi, ganti JWT_SECRET dengan string acak rahasia
  minimal 32 karakter.
- Jangan menyalin folder backend dari arsip ini ke folder utama; gunakan kode
  frontend dan backend yang sudah ada di folder utama proyek.
