# Magic Edit Lokal (tanpa API Key)

Magic Edit Lokal adalah editor foto ringan untuk ABSEN BARU. Semua pemrosesan dilakukan di browser pengguna; modul tidak memanggil layanan AI, tidak membutuhkan API Key, dan tidak mengirim foto ke endpoint server.

## Fitur lokal

- Unggah foto JPG, PNG, atau WebP.
- Foto otomatis diperkecil dengan sisi terpanjang maksimal 1.000 piksel agar lebih ramah untuk laptop dengan RAM terbatas.
- Brush dan penghapus untuk membuat seleksi area.
- Penghapusan area terpilih menggunakan propagasi/inpainting piksel tetangga sederhana.
- Pipet warna dan penghapusan background yang tersambung dengan warna yang dipilih, dengan toleransi yang dapat diatur.
- Penyesuaian kecerahan, kontras, dan saturasi.
- Undo/redo terbatas dan pemulihan foto asli.
- Perbandingan sebelum/sesudah dan unduh PNG.

## Cara membuka

Login sebagai admin atau guru, lalu buka menu **Magic Edit AI** pada sidebar, atau akses:

\`/modules/magic_edit/index.php\`

Siswa tidak diberi akses.

## Cara menggunakan

### Hapus objek atau noda

1. Unggah foto.
2. Pilih Brush dan tandai objek/noda yang ingin dihilangkan.
3. Gunakan Penghapus untuk mengoreksi seleksi.
4. Klik **Hapus area terpilih**.
5. Periksa hasil dan gunakan Undo jika hasilnya kurang baik.

Pengisian area dilakukan dengan propagasi warna dari piksel di sekitar seleksi. Ini paling cocok untuk noda kecil, kabel tipis, atau objek kecil pada latar yang relatif rata. Hasilnya dapat tampak buram atau tidak alami untuk objek besar, tekstur kompleks, atau wajah.

### Hapus background sederhana

1. Pilih alat **Pipet BG** pada toolbar.
2. Klik warna background yang ingin dihapus.
3. Atur toleransi warna.
4. Klik **Hapus background terpilih**.
5. Unduh sebagai PNG agar transparansi tetap terjaga.

Metode ini menggunakan flood-fill berbasis kemiripan warna dan bekerja paling baik pada background polos atau relatif seragam. Rambut, tepi transparan, bayangan, dan background kompleks mungkin memerlukan koreksi tambahan.

### Atur warna

Geser kecerahan, kontras, atau saturasi, lalu klik **Terapkan penyesuaian**. Gunakan Undo untuk membatalkan hasil.

## Batasan yang perlu diketahui

Mode tanpa API ini **bukan AI generatif seperti Canva Magic Edit**. Prompt teks tidak dapat menciptakan objek, mengganti pakaian/seragam, mengubah pose, atau membuat latar baru. Untuk hal tersebut dibutuhkan model generatif lokal yang besar atau layanan AI eksternal. Versi lokal ini sengaja dibuat tanpa dependensi cloud agar lebih ringan dan menjaga foto tetap di perangkat.

## Privasi dan perangkat

- Foto diproses di memori browser dan tidak diunggah ke server oleh modul ini.
- Modul tidak menyimpan foto ke database sekolah atau folder server.
- Batas unggah 10 MB dan ukuran kerja maksimal 1.000 piksel pada sisi terpanjang.
- Pengeditan gambar menggunakan CPU/browser dan dapat terasa lambat pada laptop kelas dasar. Tutup tab berat lain jika browser kehabisan memori.
- Unduh hasil sebelum menutup halaman; hasil tidak disimpan permanen.

## Pemeriksaan

Workflow GitHub Actions memeriksa sintaks PHP dan JavaScript. Pengujian visual dan kualitas inpainting tetap perlu dilakukan di XAMPP pada perangkat target.
