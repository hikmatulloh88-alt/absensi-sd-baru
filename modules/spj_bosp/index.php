<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
if (($_SESSION['role'] ?? '') !== 'admin') { http_response_code(403); exit('Akses khusus admin.'); }
$pageTitle = 'SPJ BOSP';
require __DIR__ . '/../../includes/header.php';
?>
<section class="welcome"><div><span class="eyebrow">ADMINISTRASI KEUANGAN SEKOLAH</span><h2>SPJ BOSP</h2><p>Ruang pengelolaan dokumen dan pertanggungjawaban dana BOSP.</p></div><div class="date-chip">BOSP</div></section>
<section class="panel"><div class="panel-heading"><div><h3>SPJ BOSP</h3><p>Halaman awal untuk administrasi SPJ BOSP.</p></div></div>
<div class="tip-box"><strong>Ruang SPJ BOSP</strong><p>Menu sudah disiapkan. Tahap berikutnya dapat mencakup daftar kegiatan, periode/tahun anggaran, unggah dokumen pendukung, nominal, pajak, dan status kelengkapan berkas.</p></div>
<p style="margin-top:16px;color:#65738a;font-size:13px">Dokumen dan transaksi belum disimpan oleh halaman ini. Tidak ada data keuangan yang dibuat atau diubah.</p>
</section>
<?php require __DIR__ . '/../../includes/footer.php'; ?>