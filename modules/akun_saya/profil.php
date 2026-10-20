<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
if (($_SESSION['role'] ?? '') !== 'siswa' || empty($_SESSION['siswa_id'])) { http_response_code(403); exit('Halaman ini khusus akun siswa.'); }
$pageTitle = 'Edit Profil Saya';
if (empty($_SESSION['csrf_profil'])) $_SESSION['csrf_profil'] = bin2hex(random_bytes(32));
$errors=[]; $success='';
$column=$pdo->query("SHOW COLUMNS FROM siswa LIKE 'no_telepon'")->fetch();
if (!$column) { http_response_code(503); exit('Kolom nomor telepon belum tersedia. Jalankan database/migration_siswa_no_telepon.sql terlebih dahulu.'); }
$siswaId=(int)$_SESSION['siswa_id'];
if ($_SERVER['REQUEST_METHOD']==='POST') {
  if (!hash_equals((string)$_SESSION['csrf_profil'],(string)($_POST['csrf']??''))) $errors[]='Sesi keamanan tidak valid. Muat ulang halaman.';
  else {
    $telepon=trim((string)($_POST['no_telepon']??''));
    if (mb_strlen($telepon)>30 || ($telepon!=='' && !preg_match('/^[0-9+() .-]{6,30}$/',$telepon))) $errors[]='Nomor telepon tidak valid. Gunakan angka dan tanda +, -, atau spasi.';
    if (!$errors) {
      $q=$pdo->prepare("UPDATE siswa SET no_telepon=? WHERE id=? AND status='aktif'");
      $q->execute([$telepon!==''?$telepon:null,$siswaId]);
      $success='Nomor telepon berhasil diperbarui.';
      $_SESSION['csrf_profil']=bin2hex(random_bytes(32));
    }
  }
}
$q=$pdo->prepare("SELECT nama,nisn,nis,rombel,no_telepon FROM siswa WHERE id=? AND status='aktif' LIMIT 1");
$q->execute([$siswaId]); $siswa=$q->fetch();
if (!$siswa) { http_response_code(403); exit('Data siswa tidak valid atau tidak aktif.'); }
require __DIR__ . '/../../includes/header.php';
?>
<style>
.profil-wrap{max-width:760px;margin:0 auto}.profil-hero{background:linear-gradient(120deg,#173b70,#2463a8);color:white;padding:25px;border-radius:18px;margin-bottom:18px}.profil-hero h1{margin:0 0 7px;font-size:24px}.profil-hero p{margin:0;color:#dbeafe;font-size:13px}.profil-card{background:#fff;border:1px solid #e5eaf2;border-radius:16px;padding:22px;margin-bottom:16px}.profil-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.profil-info{padding:13px;background:#f8fafc;border-radius:10px}.profil-info small{display:block;color:#667085;font-size:11px;margin-bottom:6px}.profil-info strong{overflow-wrap:anywhere;font-size:14px}.profil-field{display:grid;gap:7px;margin-top:18px}.profil-field label{font-weight:700;font-size:13px}.profil-field input{width:100%;box-sizing:border-box;border:1px solid #d0d5dd;border-radius:10px;padding:12px;font:inherit}.profil-save{margin-top:16px;background:#2459d3;color:#fff;border:0;border-radius:10px;padding:12px 16px;font-weight:800;cursor:pointer}.profil-alert{padding:12px;border-radius:10px;margin-bottom:14px}.profil-ok{background:#ecfdf3;color:#027a48}.profil-error{background:#fff1f0;color:#b42318}.profil-note{font-size:12px;line-height:1.6;color:#667085;margin-top:14px}@media(max-width:600px){.profil-grid{grid-template-columns:1fr}.profil-card{padding:16px}}
</style>
<div class="profil-wrap">
<section class="profil-hero"><h1>Edit Profil Saya</h1><p>Halo, <?= h($siswa['nama']) ?>. Periksa identitasmu dan perbarui nomor telepon yang bisa dihubungi.</p></section>
<?php if($success): ?><div class="profil-alert profil-ok"><?= h($success) ?></div><?php endif; ?>
<?php if($errors): ?><div class="profil-alert profil-error"><?php foreach($errors as $e): ?><div><?= h($e) ?></div><?php endforeach; ?></div><?php endif; ?>
<section class="profil-card"><h2 style="margin-top:0;font-size:17px">Data Siswa</h2><div class="profil-grid">
<div class="profil-info"><small>Nama Lengkap</small><strong><?= h($siswa['nama']) ?></strong></div>
<div class="profil-info"><small>NISN</small><strong><?= h($siswa['nisn'] ?: '-') ?></strong></div>
<div class="profil-info"><small>NIS</small><strong><?= h($siswa['nis'] ?: '-') ?></strong></div>
<div class="profil-info"><small>Rombel</small><strong><?= h($siswa['rombel'] ?: '-') ?></strong></div>
</div><p class="profil-note">Nama, NISN, NIS, dan rombel berasal dari data master sekolah dan tidak dapat diubah dari akun siswa.</p>
<form method="post"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf_profil']) ?>"><div class="profil-field"><label for="no_telepon">Nomor Telepon / WhatsApp</label><input id="no_telepon" name="no_telepon" type="tel" maxlength="30" inputmode="tel" value="<?= h($siswa['no_telepon'] ?? '') ?>" placeholder="Contoh: 081234567890"><small style="color:#667085">Gunakan nomor yang aktif dan bisa dihubungi. Boleh dikosongkan jika belum punya.</small></div><button class="profil-save" type="submit">Simpan Profil</button></form></section></div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
