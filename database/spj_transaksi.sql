CREATE TABLE IF NOT EXISTS spj_transaksi (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sumber_dana ENUM('BOSP','BOS_KINERJA') NOT NULL,
  tanggal DATE NOT NULL,
  nomor_bukti VARCHAR(80) NOT NULL DEFAULT '',
  kegiatan VARCHAR(180) NOT NULL,
  uraian VARCHAR(255) NOT NULL,
  penyedia VARCHAR(180) NULL,
  nilai_bruto DECIMAL(15,2) NOT NULL DEFAULT 0,
  pajak DECIMAL(15,2) NOT NULL DEFAULT 0,
  bukti_file VARCHAR(255) NULL,
  created_by BIGINT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_spj_sumber_tanggal (sumber_dana,tanggal),
  INDEX idx_spj_nomor_bukti (nomor_bukti)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
