ABSENSI SDN CIBURAYUT 01 — STARTER LOKAL
========================================

PENTING
Ini adalah starter pengembangan lokal dengan dashboard dan skema database dasar.
Ini BELUM merupakan salinan lengkap aplikasi produksi Anda dan belum menggantikan
modul lama (absensi wajah, QR, login, impor/ekspor, dan integrasi lainnya).
Jangan unggah ke hosting atau gunakan untuk absensi produksi sebelum dikembangkan
dan diuji.

CARA MEMASANG
1. Ekstrak folder absensi_sd_ciburayut_lokal ke:
   C:\xampp\htdocs\absensi-sd-baru
2. Jalankan Apache dan MySQL dari XAMPP Control Panel.
3. Buka http://localhost/phpmyadmin
4. Pilih menu Import dan impor file database/schema.sql.
   File ini membuat database absensi_sd_ciburayut_lokal dan tabel awal.
5. Jika nama pengguna/password MySQL lokal berbeda, edit:
   config/database.php
   Konfigurasi standar XAMPP biasanya user root dan password kosong.
6. Buka:
   http://localhost/absensi-sd-baru/

ISI PAKET
- index.php                 Dashboard awal
- config/database.php       Pengaturan koneksi PDO
- database/schema.sql       Struktur database awal
- includes/                 Bootstrap, header, footer
- assets/css/app.css        UI responsif
- assets/js/app.js          Menu mobile

LANGKAH BERIKUTNYA
Agar bisa mengimprove aplikasi asli tanpa merusak fitur, siapkan ZIP source code
aplikasi Absensi SDN Ciburayut 01 yang sekarang, beserta struktur database
hasil export (tanpa password/API key/token). Setelah itu, modul lama bisa diaudit
dan dikembangkan bertahap dengan backup dan pengujian lokal.
