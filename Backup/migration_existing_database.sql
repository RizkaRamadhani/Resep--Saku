-- MIGRASI AMAN untuk database lama resep_saku.
-- Tidak menghapus tabel/data tb_resep, tb_bahan, atau tb_instruksi.
-- PENTING: id_resep mengikuti tipe tabel lama: INT UNSIGNED.
USE `resep--saku`;

CREATE TABLE IF NOT EXISTS tb_users (
  id_user BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('admin','user') NOT NULL DEFAULT 'user',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tb_bookmarks (
  id_bookmark BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_user BIGINT UNSIGNED NOT NULL,
  id_resep INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_bookmark_user FOREIGN KEY (id_user) REFERENCES tb_users(id_user) ON DELETE CASCADE,
  CONSTRAINT fk_bookmark_resep FOREIGN KEY (id_resep) REFERENCES tb_resep(id_resep) ON DELETE CASCADE,
  UNIQUE KEY uq_user_resep (id_user, id_resep),
  KEY idx_bookmark_resep (id_resep)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tb_auth_tokens (
  id_token BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_user BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  revoked_at DATETIME DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_token_user FOREIGN KEY (id_user) REFERENCES tb_users(id_user) ON DELETE CASCADE,
  INDEX idx_token_expiry (expires_at)
) ENGINE=InnoDB;

-- Tambahkan FULLTEXT hanya jika belum tersedia.
SET @has_fulltext = (
  SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'tb_resep'
    AND index_name = 'ft_judul_resep'
);
SET @fulltext_sql = IF(
  @has_fulltext = 0,
  'ALTER TABLE tb_resep ADD FULLTEXT INDEX ft_judul_resep (judul_resep)',
  'SELECT ''FULLTEXT index ft_judul_resep sudah tersedia'' AS info'
);
PREPARE stmt_fulltext FROM @fulltext_sql;
EXECUTE stmt_fulltext;
DEALLOCATE PREPARE stmt_fulltext;
