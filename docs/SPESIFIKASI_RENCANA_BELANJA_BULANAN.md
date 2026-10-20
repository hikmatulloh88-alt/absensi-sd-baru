# Spesifikasi Fitur Rencana Belanja Bulanan — Modul SPJ BOSP

**Repositori:** `hikmatulloh88-alt/absensi-sd-baru`  
**Branch target:** `feat/spj-bosp-kinerja`  
**Status:** Spesifikasi; belum menjadi implementasi aplikasi  
**Batas perubahan:** Tambahan terisolasi untuk modul SPJ. Jangan mengubah modul absensi, data siswa, atau alur yang sudah berjalan.

## 1. Tujuan

Menambahkan perencanaan belanja per bulan yang menggunakan pagu dari RAPBS/RKAS tahunan dan terhubung dengan transaksi SPJ yang sudah ada. Pengguna memilih kegiatan yang akan direncanakan pada bulan tertentu, memasukkan nominal rencana, melihat pemeriksaan pagu, lalu meninjau pratinjau sebelum menyimpan.

Data contoh antarmuka yang diberikan pengguna hanya untuk spesifikasi/prototipe UI. Data contoh **tidak boleh dimasukkan sebagai data produksi atau seed database**.

## 2. Ruang lingkup

1. Pilih tahun anggaran, bulan tujuan, dan sumber dana: BOSP Reguler atau BOS Kinerja.
2. Tampilkan kegiatan dari RAPBS/RKAS yang sudah diimpor dan dipetakan.
3. Pilih satu atau lebih kegiatan untuk rencana bulan tersebut.
4. Masukkan atau ubah nominal rencana tiap kegiatan.
5. Tampilkan pagu tahunan, realisasi aktual, rencana aktif pada bulan lain, sisa yang tersedia untuk direncanakan, dan status validasi.
6. Tampilkan pratinjau lengkap sebelum penyimpanan; pengguna harus menekan konfirmasi simpan secara eksplisit.
7. Sediakan daftar rencana bulanan yang dapat dilihat, diperbarui, atau dibatalkan sesuai status dan hak akses.
8. Rencana yang sudah disimpan dapat menjadi referensi saat pengguna memasukkan transaksi SPJ dan menyusun SI. Rencana tidak otomatis membuat transaksi, memindahkan uang, atau menerbitkan SI.

## 3. Hubungan data dan aturan sumber kebenaran

- **RAPBS/RKAS** adalah sumber pagu tahunan per kegiatan, kode kegiatan, kode rekening, tahun anggaran, dan sumber dana.
- **Transaksi SPJ aktual** tetap berasal dari tabel `spj_transaksi` yang sudah ada. Jangan menyalin transaksi menjadi baris rencana.
- **Rencana bulanan** adalah catatan perencanaan terpisah, bukan transaksi realisasi.
- Pemetaan kegiatan harus menggunakan identitas kegiatan RAPBS/RKAS yang stabil (misalnya ID kegiatan yang diimpor); jangan hanya mengandalkan uraian teks yang bisa sama atau berubah.
- Pisahkan BOSP Reguler dan BOS Kinerja. Jangan menjumlahkan atau menggunakan pagu satu sumber untuk menutup sumber lain.
- Jangan mengubah struktur atau perilaku modul lain untuk membangun fitur ini.

## 4. Rumus dan validasi pagu

Semua nominal disimpan sebagai nilai numerik desimal yang aman untuk uang, bukan string berformat rupiah.

Untuk satu kegiatan dan sumber dana:

- **Pagu tahunan** = pagu kegiatan yang dipetakan dari RAPBS/RKAS aktif untuk tahun anggaran tersebut.
- **Realisasi aktual** = total transaksi SPJ yang memenuhi aturan penghitungan realisasi dan terhubung ke kegiatan tersebut, bukan total rencana.
- **Rencana aktif bulan lain** = total rencana yang masih aktif untuk kegiatan yang sama di bulan lain; jangan menghitung rencana yang dibatalkan.
- **Sisa tersedia untuk rencana bulan ini** = pagu tahunan − realisasi aktual − rencana aktif bulan lain.
- Saat mengedit rencana yang sudah ada, keluarkan nominal rencana itu sendiri dari penjumlahan rencana lain agar tidak mengurangi pagu dua kali.
- Validasi nominal rencana tidak boleh negatif dan total rencana baru tidak boleh melebihi sisa tersedia.
- Jika realisasi aktual bertambah setelah rencana dibuat, tandai rencana terkait sebagai perlu ditinjau jika total realisasi ditambah seluruh rencana aktif melebihi pagu. Jangan mengubah atau menghapus rencana diam-diam.
- Perhitungan harus dijalankan ulang di server ketika menyimpan, bukan hanya mengandalkan validasi JavaScript di browser.
- Jika belum ada pemetaan RAPBS/RKAS atau nilai pagu ambigu, jangan menganggap pagu nol atau menerima rencana tanpa peringatan; minta data diperbaiki terlebih dahulu.
- Perubahan pagu akibat revisi RAPBS/RKAS harus dapat dilacak. Rencana dan realisasi historis tidak boleh dihapus otomatis.

**Catatan realisasi:** transaksi pajak yang merupakan penerimaan/setoran pajak atau perpindahan saldo tidak boleh ikut dihitung sebagai belanja dua kali. Gunakan aturan klasifikasi BKU yang tervalidasi ketika fitur impor BKU dibangun. Sampai klasifikasi itu tersedia, jangan mengklaim rekonsiliasi BKU sudah otomatis.

## 5. Perlindungan dari duplikasi

- Setiap rencana memiliki ID unik.
- Untuk satu kombinasi tahun anggaran, sumber dana, bulan, dan kegiatan, gunakan satu rencana aktif yang dapat diedit; jika pengguna mengimpor atau mengirim formulir yang sama lagi, tampilkan rencana yang sudah ada alih-alih membuat duplikat.
- Terapkan transaksi database dan kendala unik yang sesuai dengan model data final; cek kembali sebelum implementasi karena skema impor RAPBS/RKAS belum tersedia.
- Rencana bukan transaksi. Menautkan transaksi SPJ ke rencana tidak boleh membuat transaksi kedua.
- Transaksi SPJ yang sama tidak boleh dihitung dua kali karena muncul pada laporan atau rencana berbeda.
- Pembatalan rencana mempertahankan jejak audit/status; jangan menghapus transaksi SPJ aktual.

## 6. Pratinjau sebelum simpan

Pratinjau harus menunjukkan:

- tahun anggaran, bulan, dan sumber dana;
- kegiatan, kode kegiatan, kode rekening, dan uraian RAPBS/RKAS;
- pagu tahunan;
- realisasi aktual hingga saat ini;
- rencana aktif bulan lain;
- nominal rencana untuk bulan yang dipilih;
- sisa pagu setelah realisasi dan seluruh rencana aktif;
- status validasi dan alasan jika tidak lolos;
- total nominal rencana yang dipilih.

Tombol **Simpan Rencana** aktif hanya jika validasi berhasil. Tampilkan ringkasan setelah penyimpanan berhasil; jika gagal, pertahankan input pengguna dan tampilkan pesan yang jelas.

## 7. Contoh data UI (bukan data produksi)

Bulan contoh: **Februari 2026**. Semua angka berikut hanya data contoh untuk memvalidasi perilaku antarmuka; tidak boleh otomatis dimasukkan ke database.

| Kegiatan contoh | Pagu contoh | Rencana contoh | Dipilih |
|---|---:|---:|---|
| Kegiatan Pramuka | Rp6.500.000 | Rp1.000.000 | Ya |
| ATK dan administrasi | Rp5.076.000 | Rp500.000 | Ya |
| Pembayaran internet | Rp7.800.000 | Rp650.000 | Tidak |
| Asesmen sekolah | Rp11.040.000 | Rp0 | Tidak |

Total rencana dari kegiatan yang dicentang pada contoh UI adalah **Rp1.500.000**. Pagu dan nominal di tabel ini bukan nilai yang harus diimpor ke sistem.

## 8. Rancangan data (konseptual; jangan jalankan SQL ini sebagai migrasi)

Tabel terpisah, misalnya `spj_rencana_bulanan`, dengan sekurang-kurangnya:

- ID rencana;
- tahun anggaran dan bulan;
- sumber dana;
- referensi ID kegiatan RAPBS/RKAS;
- nominal rencana;
- status (aktif/dibatalkan/perlu ditinjau);
- catatan;
- pembuat dan waktu dibuat/diubah.

Skema final hanya boleh ditetapkan setelah struktur hasil impor RAPBS/RKAS dan hubungan transaksi SPJ diperiksa. Jangan menambah kolom asumsi ke `spj_transaksi` atau mengubah tabel yang sudah dipakai tanpa migrasi kompatibel dan rencana pemulihan.

## 9. Keamanan dan hak akses

- Ikuti pola autentikasi, pemeriksaan role admin, dan CSRF yang digunakan modul SPJ saat ini.
- Validasi semua ID, bulan, sumber dana, dan nominal di server.
- Gunakan prepared statements.
- Jangan menerima pagu tahunan dari nilai tersembunyi/form browser sebagai sumber tepercaya; ambil dari database.
- Perubahan status dan pembatalan harus tercatat dan tidak boleh menghapus transaksi aktual.

## 10. Kriteria penerimaan dan pengujian

1. Rencana dapat dibuat untuk bulan dan sumber dana yang dipilih.
2. Kegiatan berasal dari data RAPBS/RKAS yang diimpor, bukan daftar hard-coded.
3. Pratinjau tampil sebelum penyimpanan dan tidak ada simpan otomatis.
4. Rencana yang melebihi sisa pagu ditolak di server dengan alasan yang jelas.
5. Saat mengedit rencana, nominal rencana itu sendiri tidak dihitung sebagai rencana bulan lain.
6. Rencana dibatalkan tidak lagi mengurangi ketersediaan; riwayatnya tetap ada.
7. Perubahan realisasi aktual memicu perhitungan ulang dan menandai kelebihan pagu.
8. Sumber dana BOSP Reguler dan BOS Kinerja tetap terpisah.
9. Mengirim formulir dua kali tidak membuat dua rencana aktif yang sama.
10. Data contoh UI tidak muncul sebagai data produksi.
11. Tidak ada perubahan pada modul absensi atau data siswa.
12. Jalankan PHP lint, uji alur admin/CSRF, dan uji perhitungan pada database uji sebelum deployment.

## 11. Urutan implementasi yang disarankan

1. Periksa dan tetapkan pemetaan struktur impor RAPBS/RKAS ke identitas kegiatan dan pagu.
2. Rancang migrasi baru yang terpisah serta mekanisme backup/rollback.
3. Buat halaman rencana bulanan terisolasi di modul SPJ.
4. Implementasikan kalkulasi dan validasi server-side serta pencegahan duplikasi.
5. Tambahkan pratinjau dan konfirmasi simpan.
6. Hubungkan referensi rencana dengan input transaksi SPJ tanpa menggandakan data.
7. Uji dengan salinan database dan file contoh; rekonsiliasi angka sebelum memakai data produksi.
8. Perbarui dokumentasi dan tunggu pengujian sebelum perubahan digabungkan ke `main`.

**Larangan:** jangan gunakan contoh kegiatan/nominal sebagai data awal produksi, jangan menghapus data, jangan mengubah modul absensi/siswa, dan jangan menggabungkan ke `main` sebelum pengujian disetujui.
