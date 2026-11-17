RESEP SAKU - PHP REST API + SPA

KEBUTUHAN
- Laragon (Apache/Nginx dan MySQL/MariaDB)
- Browser modern
- Koneksi internet untuk memuat gambar contoh dari Unsplash

INSTALASI DI LARAGON
1. Pastikan proyek berada di C:\\laragon\\www\\Resep--Saku
2. Jalankan Laragon lalu klik Start All.
3. Buka http://localhost/phpmyadmin (atau menu Database di Laragon).
4. Untuk database baru, import Backup/database_baru.sql. Untuk database yang
   sudah memiliki tabel tb_resep, tb_bahan, dan tb_instruksi, gunakan
   Backup/migration_existing_database.sql.
   Jangan gunakan database.sql untuk instalasi ini; file tersebut membuat skema
   lama yang tidak dipakai endpoint frontend.
5. Pastikan config/config.php memakai DB_NAME resep--saku dan kredensial MySQL
   Laragon yang benar. Atur environment variable RESEP_SAKU_JWT_SECRET dengan
   string acak minimal 32 karakter sebelum mengaktifkan login/bookmark. Di
   Windows, jalankan `setx RESEP_SAKU_JWT_SECRET "<secret-acak>"`, lalu tutup
   dan buka kembali Laragon agar Apache membaca variabel baru. Alternatifnya,
   ganti nilai fallback JWT_SECRET di config/config.php untuk instalasi lokal.
   Jangan gunakan nilai contoh atau membagikan secret.
6. Buka http://localhost:8080/Resep--Saku/ melalui Apache, bukan file://.

ENDPOINT API
- GET http://localhost:8080/Resep--Saku/api/search.php
- GET http://localhost:8080/Resep--Saku/api/search.php?q=ayam
- GET http://localhost:8080/Resep--Saku/api/search.php?kategori=Makan
- GET http://localhost:8080/Resep--Saku/api/detail.php?id=1
- POST /api/auth/register.php dan /api/auth/login.php (JSON: username, password)
- POST /api/auth/logout.php (Bearer token)
- GET /api/bookmark/list.php (Bearer token)
- POST /api/bookmark/toggle.php (Bearer token, JSON: id_resep)

CATATAN
- Pengaturan koneksi ada di config/config.php. Default Laragon biasanya user root
  dengan password kosong. Sesuaikan jika MySQL Anda memakai password.
- Saat mengambil project dari GitHub, salin config/config.example.php menjadi
  config/config.php dan isi pengaturan lokal. config/config.php, file uji,
  unggahan pribadi, dan backup database pribadi tidak disimpan di Git.
- Endpoint mengirim JSON. Frontend tidak memerlukan framework.
- Beranda, pencarian/filter, dan detail resep terhubung ke endpoint resep;
  login/daftar, logout, dan bookmark menggunakan endpoint autentikasi.
- Panel admin tersedia di http://localhost:8080/Resep--Saku/admin.html dan
  hanya dapat digunakan oleh akun ber-role admin. Buat akun melalui halaman
  daftar, lalu ubah rolenya secara manual di phpMyAdmin:
  `UPDATE tb_users SET role = 'admin' WHERE username = 'username_kamu';`
  Jangan memberikan role admin kepada akun yang tidak dipercaya.
- Jalankan halaman melalui Apache/Laragon, bukan dengan membuka file HTML langsung.
- Daftar akun memakai username (bukan email) dan password minimal 8 karakter.
  Login menyimpan token sesi di browser; bookmark memerlukan login.
- Jangan mengimpor database.sql ke database yang berisi data penting; file itu
  merupakan skema lama dan berisi DROP TABLE.
