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

CREATE TABLE IF NOT EXISTS matpel_pilihan (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(100) NOT NULL UNIQUE,
  tingkat TINYINT UNSIGNED NULL,
  keterangan VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS siswa_matpel_pilihan (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  siswa_id INT UNSIGNED NOT NULL,
  matpel_id INT UNSIGNED NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_siswa_matpel_pilihan (siswa_id,matpel_id),
  INDEX idx_siswa_matpel (matpel_id),
  CONSTRAINT fk_smp_siswa FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE,
  CONSTRAINT fk_smp_matpel FOREIGN KEY (matpel_id) REFERENCES matpel_pilihan(id) ON DELETE CASCADE
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


CREATE TABLE IF NOT EXISTS rapor_nilai (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, siswa_id INT UNSIGNED NOT NULL,
 semester TINYINT UNSIGNED NOT NULL, tahun_pelajaran VARCHAR(20) NOT NULL,
 mapel VARCHAR(100) NOT NULL, kkm DECIMAL(5,2) NOT NULL DEFAULT 75, nilai DECIMAL(5,2) NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_rapor_siswa_semester_mapel (siswa_id,semester,tahun_pelajaran,mapel),
 INDEX idx_rapor_semester (semester,tahun_pelajaran),
 CONSTRAINT fk_rapor_nilai_siswa FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rapor_catatan (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, siswa_id INT UNSIGNED NOT NULL,
 semester TINYINT UNSIGNED NOT NULL, tahun_pelajaran VARCHAR(20) NOT NULL,
 hadir INT UNSIGNED NOT NULL DEFAULT 0, sakit INT UNSIGNED NOT NULL DEFAULT 0,
 izin INT UNSIGNED NOT NULL DEFAULT 0, alpa INT UNSIGNED NOT NULL DEFAULT 0,
 sikap VARCHAR(255) NULL, kerajinan VARCHAR(255) NULL, kebersihan VARCHAR(255) NULL,
 ekskul VARCHAR(255) NULL, catatan_wali TEXT NULL, wali_kelas VARCHAR(150) NULL,
 nip_wali VARCHAR(40) NULL, kepala_sekolah VARCHAR(150) NULL, nip_kepala VARCHAR(40) NULL,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_rapor_catatan (siswa_id,semester,tahun_pelajaran),
 CONSTRAINT fk_rapor_catatan_siswa FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
