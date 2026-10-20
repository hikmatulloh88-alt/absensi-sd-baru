<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
if (($_SESSION['role'] ?? '') !== 'admin') { http_response_code(403); exit('Akses khusus admin.'); }
$pageTitle = 'SPJ BOS Kinerja';
require __DIR__ . '/../../includes/header.php';
?>
<section class="welcome"><div><span class="eyebrow">ADMINISTRASI KEUANGAN SEKOLAH</span><h2>SPJ BOS Kinerja</h2><p>Ruang pengelolaan dokumen dan pertanggungjawaban dana BOS Kinerja.</p></div><div class="date-chip">BOS KINERJA</div></section>
<section class="panel"><div class="panel-heading"><div><h3>SPJ BOS Kinerja</h3><p>Halaman awal untuk administrasi SPJ BOS Kinerja.</p></div></div>
<div class="tip-box"><strong>Ruang SPJ BOS Kinerja</strong><p>Menu sudah disiapkan. Tahap berikutnya dapat mencakup daftar kegiatan, periode/tahun anggaran, unggah dokumen pendukung, nominal, pajak, dan status kelengkapan berkas.</p></div>
<p style="margin-top:16px;color:#65738a;font-size:13px">Dokumen dan transaksi belum disimpan oleh halaman ini. Tidak ada data keuangan yang dibuat atau diubah.</p>
</section>
<?php require __DIR__ . '/../../includes/footer.php'; ?>