-- Migrasi tambahan terisolasi untuk fitur RAPBS/RKAS dan Rencana Belanja Bulanan.
-- Jalankan hanya pada database uji setelah backup. Tidak mengubah tabel absensi/siswa
-- maupun tabel spj_transaksi yang sudah ada. Tidak memasukkan data contoh/seed.
-- Engine: MySQL/MariaDB.

CREATE TABLE IF NOT EXISTS spj_rkas_import (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tahun_anggaran SMALLINT UNSIGNED NOT NULL,
  sumber_dana ENUM('BOSP','BOS_KINERJA') NOT NULL,
  nama_dokumen VARCHAR(255) NOT NULL,
  checksum_sha256 CHAR(64) NULL,
  status ENUM('draft','aktif','digantikan','dibatalkan') NOT NULL DEFAULT 'draft',
  total_pagu DECIMAL(15,2) NOT NULL DEFAULT 0,
  created_by BIGINT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  activated_at DATETIME NULL,
  INDEX idx_rkas_year_source_status (tahun_anggaran,sumber_dana,status),
  INDEX idx_rkas_checksum (checksum_sha256)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS spj_rkas_kegiatan (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  import_id BIGINT UNSIGNED NOT NULL,
  kode_kegiatan VARCHAR(80) NOT NULL DEFAULT '',
  kode_rekening VARCHAR(80) NOT NULL DEFAULT '',
  uraian VARCHAR(500) NOT NULL,
  jenis_belanja ENUM('OPERASI','MODAL','LAINNYA') NOT NULL DEFAULT 'LAINNYA',
  pagu DECIMAL(15,2) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_spj_rkas_kegiatan_import
    FOREIGN KEY (import_id) REFERENCES spj_rkas_import(id) ON DELETE RESTRICT,
  INDEX idx_rkas_kegiatan_import (import_id),
  INDEX idx_rkas_kode (kode_kegiatan,kode_rekening)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS spj_rencana_bulanan (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tahun_anggaran SMALLINT UNSIGNED NOT NULL,
  bulan TINYINT UNSIGNED NOT NULL,
  sumber_dana ENUM('BOSP','BOS_KINERJA') NOT NULL,
  kegiatan_id BIGINT UNSIGNED NOT NULL,
  nominal_rencana DECIMAL(15,2) NOT NULL DEFAULT 0,
  status ENUM('aktif','dibatalkan','perlu_ditinjau') NOT NULL DEFAULT 'aktif',
  catatan VARCHAR(500) NULL,
  created_by BIGINT NULL,
  updated_by BIGINT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_spj_rencana_kegiatan
    FOREIGN KEY (kegiatan_id) REFERENCES spj_rkas_kegiatan(id) ON DELETE RESTRICT,
  CONSTRAINT chk_spj_rencana_bulan CHECK (bulan BETWEEN 1 AND 12),
  CONSTRAINT chk_spj_rencana_nominal CHECK (nominal_rencana >= 0),
  UNIQUE KEY uq_spj_rencana_bulanan (tahun_anggaran,bulan,sumber_dana,kegiatan_id),
  INDEX idx_spj_rencana_tahun_sumber_status (tahun_anggaran,sumber_dana,status),
  INDEX idx_spj_rencana_kegiatan_status (kegiatan_id,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Relasi eksplisit transaksi -> kegiatan RKAS agar realisasi tidak ditebak dari teks uraian.
-- Satu transaksi hanya boleh dipetakan satu kali pada versi awal; perubahan harus ditinjau.
CREATE TABLE IF NOT EXISTS spj_transaksi_rkas (
  transaksi_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  kegiatan_id BIGINT UNSIGNED NOT NULL,
  catatan VARCHAR(500) NULL,
  mapped_by BIGINT NULL,
  mapped_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_spj_transaksi_rkas_kegiatan
    FOREIGN KEY (kegiatan_id) REFERENCES spj_rkas_kegiatan(id) ON DELETE RESTRICT,
  INDEX idx_spj_transaksi_rkas_kegiatan (kegiatan_id)
  -- FK transaksi sengaja tidak ditambahkan di migrasi ini karena tipe/constraint
  -- tabel spj_transaksi harus diverifikasi di database target sebelum aktivasi.
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
