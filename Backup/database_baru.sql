-- HANYA untuk instalasi database baru/kosong.
-- Jangan impor file ini ke database lama yang datanya ingin dipertahankan.
CREATE DATABASE IF NOT EXISTS `resep--saku` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `resep--saku`;

CREATE TABLE tb_users (
  id_user BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('admin','user') NOT NULL DEFAULT 'user',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE tb_resep (
  id_resep INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  judul_resep VARCHAR(150) NOT NULL,
  gambar_url VARCHAR(500) DEFAULT NULL,
  waktu_estimasi SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  kategori ENUM('Sarapan','Makan','Camilan') NOT NULL DEFAULT 'Makan',
  tingkat_kesulitan ENUM('Mudah','Sedang','Sulit') NOT NULL DEFAULT 'Mudah',
  deskripsi TEXT DEFAULT NULL,
  FULLTEXT KEY ft_judul_resep (judul_resep),
  KEY idx_kategori (kategori)
) ENGINE=InnoDB;

CREATE TABLE tb_bahan (
  id_bahan INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_resep INT UNSIGNED NOT NULL,
  nama_bahan VARCHAR(150) NOT NULL,
  takaran VARCHAR(100) NOT NULL,
  KEY idx_bahan_resep (id_resep),
  CONSTRAINT fk_bahan_resep FOREIGN KEY (id_resep) REFERENCES tb_resep(id_resep)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE tb_instruksi (
  id_instruksi INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_resep INT UNSIGNED NOT NULL,
  langkah_ke SMALLINT UNSIGNED NOT NULL,
  deskripsi_langkah TEXT NOT NULL,
  UNIQUE KEY uq_resep_langkah (id_resep, langkah_ke),
  CONSTRAINT fk_instruksi_resep FOREIGN KEY (id_resep) REFERENCES tb_resep(id_resep)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE tb_bookmarks (
  id_bookmark BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_user BIGINT UNSIGNED NOT NULL,
  id_resep INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_bookmark_user FOREIGN KEY (id_user) REFERENCES tb_users(id_user) ON DELETE CASCADE,
  CONSTRAINT fk_bookmark_resep FOREIGN KEY (id_resep) REFERENCES tb_resep(id_resep) ON DELETE CASCADE,
  UNIQUE KEY uq_user_resep (id_user, id_resep)
) ENGINE=InnoDB;

CREATE TABLE tb_auth_tokens (
  id_token BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_user BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  revoked_at DATETIME DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_token_user FOREIGN KEY (id_user) REFERENCES tb_users(id_user) ON DELETE CASCADE,
  INDEX idx_token_expiry (expires_at)
) ENGINE=InnoDB;
