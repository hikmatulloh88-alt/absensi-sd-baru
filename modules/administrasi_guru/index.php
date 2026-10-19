<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
if (!in_array($_SESSION['role'] ?? '', ['guru','admin'], true)) { http_response_code(403); exit('Halaman ini khusus guru dan admin.'); }
$pageTitle = 'Administrasi Guru';
require __DIR__ . '/../../includes/header.php';
?>
<style>
.adm-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px}.adm-card{background:#fff;border:1px solid #e7ebf2;border-radius:16px;padding:20px;text-decoration:none;color:inherit;display:block;box-shadow:0 5px 18px #15223b08}.adm-icon{width:44px;height:44px;border-radius:12px;background:#eef2ff;color:#2459d3;display:grid;place-items:center;font-size:22px;margin-bottom:13px}.adm-card h3{margin:0 0 7px;font-size:16px}.adm-card p{font-size:13px;color:#667085;line-height:1.6;margin:0}.adm-note{margin-top:18px;padding:14px;background:#f8fafc;border-radius:12px;color:#667085;font-size:13px}
</style>
<p style="color:#667085;margin-top:0">Pusat akses administrasi pembelajaran guru. Menu ini disiapkan bertahap agar fitur yang sudah berjalan tetap aman.</p>
<div class="adm-grid">
<a class="adm-card" href="<?= h($basePath) ?>/modules/akun_saya/password.php"><div class="adm-icon">🔐</div><h3>Keamanan Akun</h3><p>Ganti password akun masing-masing secara mandiri.</p></a>
<a class="adm-card" href="<?= h($basePath) ?>/modules/absensi/index.php?jenis=guru"><div class="adm-icon">📅</div><h3>Absensi Guru</h3><p>Buka modul absensi guru yang sudah tersedia.</p></a>
<a class="adm-card" href="<?= h($basePath) ?>/modules/laporan/index.php"><div class="adm-icon">📊</div><h3>Laporan</h3><p>Akses laporan yang sudah ada sesuai kewenangan akun.</p></a>
</div>
<div class="adm-note">Modul perangkat pembelajaran seperti ATP, modul ajar, dan jurnal mengajar akan ditambahkan setelah struktur dokumen sekolah ditentukan. Saat ini tidak ada data lama yang diubah.</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
