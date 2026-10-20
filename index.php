<?php
require_once __DIR__ . '/includes/bootstrap.php';
$pageTitle = 'Dashboard';
$today = date('Y-m-d');
$stats = ['guru'=>0,'siswa'=>0,'hadir'=>0,'izin'=>0,'terlambat'=>0];
try {
  $stats['guru'] = (int)$pdo->query("SELECT COUNT(*) FROM guru")->fetchColumn();
  $stats['siswa'] = (int)$pdo->query("SELECT COUNT(*) FROM siswa WHERE status='aktif'")->fetchColumn();
  $q = $pdo->prepare("SELECT status, COUNT(*) total FROM absensi WHERE tanggal=? GROUP BY status");
  $q->execute([$today]);
  foreach ($q->fetchAll() as $r) {
    if ($r['status']==='hadir') $stats['hadir']=(int)$r['total'];
    if ($r['status']==='izin' || $r['status']==='sakit') $stats['izin']+=(int)$r['total'];
    if ($r['status']==='terlambat') $stats['terlambat']=(int)$r['total'];
  }
} catch (Throwable $e) { $setupWarning = true; }
require __DIR__ . '/includes/header.php';
?>
<?php if (($_SESSION['role'] ?? '') === 'siswa'): ?>
<section class="welcome">
  <div><span class="eyebrow">DASHBOARD SISWA</span><h2>Halo, <?= h($_SESSION['nama'] ?? 'Siswa') ?> 👋</h2><p>Selamat datang di portal siswa SDN Ciburayut 01.</p></div>
  <div class="date-chip">📅 <?= h(date('d F Y')) ?></div>
</section>
<section class="panel activity-panel">
  <div class="panel-heading"><div><h3>Selamat datang!</h3><p>Ini adalah halaman dashboard akun siswa.</p></div><span class="status-pill">SISWA</span></div>
  <div class="tip-box"><strong>Informasi</strong><p>Gunakan menu di sebelah kiri untuk mengajukan izin atau sakit dan memperbarui nomor telepon pada profil. Dashboard ini tidak menampilkan menu cepat.</p></div>
</section>
<?php else: ?>
<section class="welcome">
  <div><span class="eyebrow">PUSAT KENDALI SEKOLAH</span><h2>Selamat datang, Admin 👋</h2><p>Ringkasan aktivitas ABSENSI SDN CIBURAYUT 01 hari ini.</p></div>
  <div class="date-chip">📅 <?= h(date('d F Y')) ?></div>
</section>
<?php if (!empty($setupWarning)): ?><div class="notice warning">Database belum siap. Impor <code>database/schema.sql</code> melalui phpMyAdmin, lalu sesuaikan <code>config/database.php</code>.</div><?php endif; ?>
<div class="stats-grid">
  <article class="stat-card"><div class="stat-icon blue">👨‍🏫</div><div class="stat-label">Total Guru</div><div class="stat-number"><?= number_format($stats['guru']) ?></div><div class="stat-foot">Data tenaga pendidik</div></article>
  <article class="stat-card"><div class="stat-icon violet">🎒</div><div class="stat-label">Siswa Aktif</div><div class="stat-number"><?= number_format($stats['siswa']) ?></div><div class="stat-foot">Terdaftar di sekolah</div></article>
  <article class="stat-card"><div class="stat-icon green">✓</div><div class="stat-label">Hadir Hari Ini</div><div class="stat-number"><?= number_format($stats['hadir']) ?></div><div class="stat-foot">Catatan kehadiran</div></article>
  <article class="stat-card"><div class="stat-icon amber">◷</div><div class="stat-label">Izin / Sakit / Terlambat</div><div class="stat-number"><?= number_format($stats['izin']+$stats['terlambat']) ?></div><div class="stat-foot">Perlu dipantau</div></article>
</div>
<section class="panel side-panel dashboard-system"><div class="panel-heading"><div><h3>Status sistem</h3><p>Lingkungan pengembangan</p></div><span class="status-pill">LOCAL</span></div>
    <div class="system-row"><span class="system-symbol">⌘</span><div><strong>Mode pengembangan</strong><small>Berjalan di komputer lokal</small></div><span class="check">✓</span></div>
    <div class="system-row"><span class="system-symbol">▤</span><div><strong>Database</strong><small><?= isset($setupWarning) ? 'Perlu konfigurasi' : 'Koneksi aktif' ?></small></div><span class="<?= isset($setupWarning)?'warn-mark':'check' ?>"><?= isset($setupWarning)?'!':'✓' ?></span></div>
    <div class="tip-box"><strong>💡 Catatan pengembangan</strong><p>Uji fitur di lokal terlebih dahulu. Jangan menimpa aplikasi hosting sebelum fitur dan database diuji.</p></div>
  </section>
<section class="panel activity-panel"><div class="panel-heading"><div><h3>Rencana pengembangan</h3><p>Urutan kerja yang aman untuk aplikasi sekolah.</p></div><span class="tag">VERSI LOKAL 1.0</span></div>
  <div class="roadmap"><div class="roadmap-step active"><span>01</span><div><strong>Fondasi dan dashboard</strong><small>Struktur proyek, konfigurasi, dan tampilan utama</small></div><em>Starter</em></div><div class="roadmap-step"><span>02</span><div><strong>Data guru dan siswa</strong><small>CRUD, pencarian, rombel, dan validasi</small></div><em>Berikutnya</em></div><div class="roadmap-step"><span>03</span><div><strong>Absensi dan laporan</strong><small>Input kehadiran, filter tanggal, ekspor/cetak</small></div><em>Berikutnya</em></div></div>
</section>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
