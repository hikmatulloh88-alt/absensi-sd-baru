<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
if (!in_array($_SESSION['role'] ?? '', ['guru','admin'], true)) { http_response_code(403); exit('Halaman ini khusus guru dan admin.'); }
$pageTitle = 'Profil Guru';
$guru = null;
if (!empty($_SESSION['guru_id'])) {
  $q = $pdo->prepare('SELECT id,nip,nama,jabatan,status FROM guru WHERE id=? LIMIT 1');
  $q->execute([(int)$_SESSION['guru_id']]); $guru = $q->fetch();
}
require __DIR__ . '/../../includes/header.php';
?>
<style>
.profile-card{max-width:760px;background:#fff;border:1px solid #e7ebf2;border-radius:18px;padding:24px;box-shadow:0 5px 18px #15223b08}.profile-head{display:flex;align-items:center;gap:16px;margin-bottom:22px}.profile-avatar{width:68px;height:68px;border-radius:18px;background:#eaf0ff;color:#2459d3;display:grid;place-items:center;font-size:27px;font-weight:800}.profile-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.profile-item{background:#f8fafc;border-radius:12px;padding:14px}.profile-item small{display:block;color:#667085;margin-bottom:6px}.profile-item strong{overflow-wrap:anywhere}.profile-note{font-size:13px;color:#667085;margin-top:18px}@media(max-width:600px){.profile-grid{grid-template-columns:1fr}}
</style>
<section class="profile-card">
<?php if ($guru): ?>
<div class="profile-head"><div class="profile-avatar"><?= h(mb_strtoupper(mb_substr((string)$guru['nama'],0,1))) ?></div><div><h2 style="margin:0 0 5px"><?= h($guru['nama']) ?></h2><span style="color:#667085"><?= h($guru['jabatan'] ?: 'Guru') ?></span></div></div>
<div class="profile-grid">
<div class="profile-item"><small>Nama Lengkap</small><strong><?= h($guru['nama']) ?></strong></div>
<div class="profile-item"><small>NIP</small><strong><?= h($guru['nip'] ?: 'Belum diisi') ?></strong></div>
<div class="profile-item"><small>Status</small><strong><?= h(ucfirst($guru['status'])) ?></strong></div>
<div class="profile-item"><small>Username Login</small><strong><?= h($_SESSION['username'] ?? '-') ?></strong></div>
</div>
<p class="profile-note">Data identitas bersumber dari data master guru. Jika ada data yang salah, hubungi admin sekolah untuk memperbaruinya.</p>
<?php elseif (($_SESSION['role'] ?? '') === 'admin'): ?>
<h2>Profil Guru</h2><p>Halaman ini menampilkan profil guru berdasarkan akun guru yang sedang login. Untuk mengelola seluruh data guru, buka menu <a href="<?= h($basePath) ?>/modules/guru/index.php">Data Guru</a>.</p>
<?php else: ?>
<h2>Profil Guru belum tertaut</h2><p>Akun ini belum terhubung ke data master guru. Hubungi admin sekolah untuk menautkan akun ke data guru yang benar.</p>
<?php endif; ?>
</section>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
