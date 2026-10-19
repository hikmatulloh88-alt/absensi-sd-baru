<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
if (!in_array($_SESSION['role'] ?? '', ['guru','admin'], true)) { http_response_code(403); exit('Halaman ini khusus guru dan admin.'); }
$pageTitle = 'Ruang Kerja Guru';
$namaGuru = trim((string)($_SESSION['nama'] ?? 'Bapak/Ibu Guru'));
$hari = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
$bulan = [1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
$tanggalHariIni = $hari[(int)date('w')] . ', ' . date('j') . ' ' . $bulan[(int)date('n')] . ' ' . date('Y');
$absensiHariIni = null;
if (!empty($_SESSION['guru_id'])) {
  $q = $pdo->prepare("SELECT status,waktu FROM absensi WHERE jenis='guru' AND referensi_id=? AND tanggal=? LIMIT 1");
  $q->execute([(int)$_SESSION['guru_id'],date('Y-m-d')]); $absensiHariIni = $q->fetch() ?: null;
}
require __DIR__ . '/../../includes/header.php';
?>
<style>
.teacher-welcome{background:linear-gradient(115deg,#173b70,#2463a8);border-radius:20px;padding:28px 30px;color:#fff;display:flex;justify-content:space-between;gap:20px;align-items:center;overflow:hidden;position:relative}.teacher-welcome:after{content:"";position:absolute;width:230px;height:230px;border:36px solid #ffffff12;border-radius:50%;right:8%;top:-120px}.teacher-welcome>*{position:relative;z-index:1}.teacher-eyebrow{font-size:12px;letter-spacing:.12em;text-transform:uppercase;color:#d6e7ff;font-weight:800}.teacher-welcome h1{font-size:clamp(23px,3vw,32px);line-height:1.2;margin:10px 0}.teacher-welcome p{margin:0;color:#e2edff;font-size:14px}.teacher-date{padding:13px 16px;border:1px solid #ffffff45;background:#ffffff12;border-radius:13px;white-space:nowrap;font-size:13px}.teacher-section-title{display:flex;justify-content:space-between;align-items:end;gap:12px;margin:26px 0 13px}.teacher-section-title h2{font-size:18px;margin:0}.teacher-section-title p{font-size:12px;color:#667085;margin:5px 0 0}.teacher-shortcuts{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:15px}.teacher-shortcut{display:flex;align-items:center;gap:14px;background:#fff;border:1px solid #e5eaf2;border-radius:15px;padding:18px;text-decoration:none;color:inherit;transition:transform .15s,border-color .15s}.teacher-shortcut:hover{transform:translateY(-2px);border-color:#9db9eb}.teacher-shortcut-icon{flex:0 0 46px;width:46px;height:46px;border-radius:13px;background:#edf4ff;display:grid;place-items:center;font-size:22px}.teacher-shortcut h3{font-size:14px;margin:0 0 5px}.teacher-shortcut p{font-size:12px;line-height:1.5;color:#667085;margin:0}.teacher-lower{display:grid;grid-template-columns:minmax(0,1.3fr) minmax(260px,.7fr);gap:16px;margin-top:18px}.teacher-panel{background:#fff;border:1px solid #e5eaf2;border-radius:15px;padding:20px}.teacher-panel h2{font-size:16px;margin:0 0 15px}.teacher-doc-list{display:grid;gap:10px}.teacher-doc{display:flex;align-items:center;gap:12px;padding:12px;background:#f8fafc;border-radius:11px}.teacher-doc-icon{font-size:20px}.teacher-doc strong{display:block;font-size:13px}.teacher-doc small{display:block;color:#667085;font-size:11px;margin-top:4px}.teacher-doc-status{margin-left:auto;color:#667085;background:#edf0f5;border-radius:20px;padding:5px 8px;font-size:10px;white-space:nowrap}.teacher-attendance{display:flex;align-items:center;gap:12px;padding:14px;background:#f8fafc;border-radius:12px}.teacher-attendance-mark{width:42px;height:42px;display:grid;place-items:center;border-radius:12px;background:#e9f8ef;color:#14804a;font-size:20px}.teacher-attendance strong{display:block;font-size:14px}.teacher-attendance small{display:block;color:#667085;margin-top:4px}.teacher-panel-link{display:inline-block;margin-top:14px;color:#2459d3;font-weight:800;font-size:12px;text-decoration:none}.teacher-tip{margin-top:14px;color:#667085;font-size:12px;line-height:1.6}.teacher-admin-note{font-size:12px;color:#667085;margin:14px 0 0}@media(max-width:900px){.teacher-shortcuts{grid-template-columns:repeat(2,minmax(0,1fr))}.teacher-lower{grid-template-columns:1fr}.teacher-welcome{align-items:flex-start;flex-direction:column}}@media(max-width:560px){.teacher-shortcuts{grid-template-columns:1fr}.teacher-welcome{padding:22px}.teacher-date{white-space:normal}}
</style>
<section class="teacher-welcome">
  <div><div class="teacher-eyebrow">Ruang Kerja Guru · SDN Ciburayut 01</div><h1>Wilujeng sumping, <?= h($namaGuru) ?> 👋</h1><p>Semoga kegiatan belajar hari ini berjalan lancar. Semua kebutuhan mengajar dimulai dari sini.</p></div>
  <div class="teacher-date"><?= h($tanggalHariIni) ?></div>
</section>
<div class="teacher-section-title"><div><h2>Akses Cepat</h2><p>Menu yang sering digunakan dalam kegiatan sehari-hari.</p></div></div>
<div class="teacher-shortcuts">
<a class="teacher-shortcut" href="<?= h($basePath) ?>/modules/profil_guru/index.php"><div class="teacher-shortcut-icon">👤</div><div><h3>Profil Saya</h3><p>Lihat data dan identitas guru.</p></div></a>
<a class="teacher-shortcut" href="<?= h($basePath) ?>/modules/absensi/index.php?jenis=guru"><div class="teacher-shortcut-icon">📅</div><div><h3>Absensi Guru</h3><p>Buka pencatatan dan riwayat kehadiran.</p></div></a>
<a class="teacher-shortcut" href="<?= h($basePath) ?>/modules/akun_saya/password.php"><div class="teacher-shortcut-icon">🔐</div><div><h3>Keamanan Akun</h3><p>Ganti password secara mandiri.</p></div></a>
</div>
<div class="teacher-section-title"><div><h2>Administrasi Pembelajaran</h2><p>Ruang perangkat ajar pribadi, disiapkan bertahap.</p></div></div>
<div class="teacher-lower">
<section class="teacher-panel"><h2>Perangkat Ajar Saya</h2><div class="teacher-doc-list">
<div class="teacher-doc"><div class="teacher-doc-icon">📘</div><div><strong>ATP &amp; Tujuan Pembelajaran</strong><small>Alur dan tujuan pembelajaran</small></div><span class="teacher-doc-status">Tahap berikutnya</span></div>
<div class="teacher-doc"><div class="teacher-doc-icon">📝</div><div><strong>Modul Ajar</strong><small>Rencana kegiatan belajar di kelas</small></div><span class="teacher-doc-status">Tahap berikutnya</span></div>
<div class="teacher-doc"><div class="teacher-doc-icon">📒</div><div><strong>Jurnal Mengajar</strong><small>Catatan pelaksanaan pembelajaran</small></div><span class="teacher-doc-status">Tahap berikutnya</span></div>
</div><p class="teacher-tip">Fitur dokumen akan dibuat sebagai ruang kerja guru, bukan menu administrasi sekolah. Dokumen yang sudah ada tidak akan ditimpa.</p></section>
<section class="teacher-panel"><h2>Kehadiran Hari Ini</h2>
<?php if ($absensiHariIni): ?>
<div class="teacher-attendance"><div class="teacher-attendance-mark">✓</div><div><strong><?= h(ucfirst((string)$absensiHariIni['status'])) ?></strong><small><?= $absensiHariIni['waktu'] ? 'Waktu tercatat ' . h(substr((string)$absensiHariIni['waktu'],0,5)) : 'Status kehadiran tercatat' ?></small></div></div>
<?php else: ?>
<div class="teacher-attendance"><div class="teacher-attendance-mark" style="background:#fff4df;color:#9a6700">◷</div><div><strong>Belum ada catatan</strong><small>Data absensi hari ini belum ditemukan.</small></div></div>
<?php endif; ?>
<a class="teacher-panel-link" href="<?= h($basePath) ?>/modules/absensi/index.php?jenis=guru">Buka modul absensi →</a>
<?php if (($_SESSION['role'] ?? '') === 'admin'): ?><p class="teacher-admin-note">Anda sedang melihat halaman ini sebagai admin.</p><?php endif; ?>
</section></div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
