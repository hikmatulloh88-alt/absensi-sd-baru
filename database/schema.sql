CREATE DATABASE IF NOT EXISTS absensi_sd_ciburayut_lokal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE absensi_sd_ciburayut_lokal;

CREATE TABLE IF NOT EXISTS guru (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nip VARCHAR(30) NULL,
  nama VARCHAR(150) NOT NULL,
  jabatan VARCHAR(100) NULL,
  status ENUM('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_guru_nama (nama)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS siswa (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nisn VARCHAR(20) NULL,
  nis VARCHAR(30) NULL,
  nama VARCHAR(150) NOT NULL,
  rombel VARCHAR(30) NULL,
  jenis_kelamin ENUM('L','P') NULL,
  status ENUM('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_siswa_nama (nama),
  INDEX idx_siswa_rombel (rombel)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS rombel (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(30) NOT NULL UNIQUE,
  tingkat TINYINT UNSIGNED NOT NULL,
  jenis ENUM('reguler','ekskul') NOT NULL DEFAULT 'reguler',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_rombel_jenis_tingkat (jenis, tingkat)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS absensi (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  jenis ENUM('guru','siswa') NOT NULL,
  referensi_id INT UNSIGNED NOT NULL,
  tanggal DATE NOT NULL,
  waktu TIME NULL,
  status ENUM('hadir','izin','sakit','alpa','terlambat') NOT NULL DEFAULT 'hadir',
  keterangan VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_absensi_orang_harian (jenis, referensi_id, tanggal),
  INDEX idx_absensi_tanggal_status (tanggal,status)
) ENGINE=InnoDB;

INSERT INTO guru (nip,nama,jabatan) VALUES
('','Administrator Demo','Administrator')
ON DUPLICATE KEY UPDATE nama=VALUES(nama);
