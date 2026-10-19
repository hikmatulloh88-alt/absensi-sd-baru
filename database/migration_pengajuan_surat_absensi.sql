-- Jalankan sekali pada database absensi_sd_ciburayut_lokal.
-- Modul ini terpisah; tidak mengubah tabel siswa atau absensi yang sudah ada.
CREATE TABLE IF NOT EXISTS pengajuan_surat_absensi (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  siswa_id INT UNSIGNED NOT NULL,
  jenis ENUM('izin','sakit') NOT NULL,
  tanggal_mulai DATE NOT NULL,
  tanggal_selesai DATE NOT NULL,
  keterangan VARCHAR(500) NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  file_asli VARCHAR(255) NOT NULL,
  status ENUM('menunggu','disetujui','ditolak') NOT NULL DEFAULT 'menunggu',
  catatan_verifikasi VARCHAR(500) NULL,
  diverifikasi_oleh VARCHAR(150) NULL,
  diverifikasi_pada DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_surat_siswa_tanggal (siswa_id,tanggal_mulai,tanggal_selesai),
  INDEX idx_surat_status_tanggal (status,created_at),
  CONSTRAINT fk_surat_siswa FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
