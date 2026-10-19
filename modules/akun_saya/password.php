<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
if (empty($_SESSION['akun_id'])) { http_response_code(401); exit('Silakan login terlebih dahulu.'); }
$pageTitle = 'Ganti Password';
if (empty($_SESSION['csrf_password'])) $_SESSION['csrf_password'] = bin2hex(random_bytes(32));
$errors = []; $notice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!hash_equals((string)$_SESSION['csrf_password'], (string)($_POST['csrf'] ?? ''))) $errors[] = 'Sesi keamanan tidak valid. Muat ulang halaman.';
  else {
    $lama = (string)($_POST['password_lama'] ?? '');
    $baru = (string)($_POST['password_baru'] ?? '');
    $konfirmasi = (string)($_POST['konfirmasi'] ?? '');
    $q = $pdo->prepare('SELECT password_hash FROM akun_pengguna WHERE id=? AND aktif=1 LIMIT 1');
    $q->execute([(int)$_SESSION['akun_id']]); $akun = $q->fetch();
    if (!$akun || !password_verify($lama, (string)$akun['password_hash'])) $errors[] = 'Password lama tidak sesuai.';
    if (strlen($baru) < 10) $errors[] = 'Password baru minimal 10 karakter.';
    if ($baru !== $konfirmasi) $errors[] = 'Konfirmasi password baru tidak sama.';
    if ($lama !== '' && $baru === $lama) $errors[] = 'Password baru harus berbeda dari password lama.';
    if (!$errors) {
      $q = $pdo->prepare('UPDATE akun_pengguna SET password_hash=? WHERE id=?');
      $q->execute([password_hash($baru, PASSWORD_DEFAULT),(int)$_SESSION['akun_id']]);
      $notice = 'Password berhasil diganti.';
      $_SESSION['csrf_password'] = bin2hex(random_bytes(32));
    }
  }
}
require __DIR__ . '/../../includes/header.php';
?>
<style>
.password-wrap{max-width:560px;margin:0 auto}.password-card{background:#fff;border:1px solid #e7ebf2;border-radius:16px;padding:24px;box-shadow:0 5px 18px #15223b08}.password-card h2{margin:0 0 7px;font-size:20px}.password-muted{font-size:13px;color:#667085;margin:0 0 20px}.password-field{display:grid;gap:7px;margin:16px 0}.password-field label{font-size:13px;font-weight:700;color:#344054}.password-field input{width:100%;box-sizing:border-box;border:1px solid #d0d5dd;border-radius:10px;padding:12px}.password-submit{background:#2459d3;color:#fff;border:0;border-radius:10px;padding:12px 16px;font-weight:800;cursor:pointer}.password-alert{padding:12px;border-radius:10px;margin-bottom:14px;background:#ecfdf3;color:#027a48}.password-alert.error{background:#fff1f0;color:#b42318}
</style>
<div class="password-wrap">
<?php if ($notice): ?><div class="password-alert"><?= h($notice) ?></div><?php endif; ?>
<?php if ($errors): ?><div class="password-alert error"><?php foreach ($errors as $error): ?><div><?= h($error) ?></div><?php endforeach; ?></div><?php endif; ?>
<section class="password-card"><h2>🔐 Ganti Password</h2><p class="password-muted">Akun: <?= h($_SESSION['username'] ?? '') ?>. Gunakan password yang sulit ditebak dan jangan dibagikan kepada orang lain.</p>
<form method="post" autocomplete="off">
<input type="hidden" name="csrf" value="<?= h($_SESSION['csrf_password']) ?>">
<div class="password-field"><label>Password lama</label><input type="password" name="password_lama" required autocomplete="current-password"></div>
<div class="password-field"><label>Password baru (minimal 10 karakter)</label><input type="password" name="password_baru" required minlength="10" autocomplete="new-password"></div>
<div class="password-field"><label>Ulangi password baru</label><input type="password" name="konfirmasi" required minlength="10" autocomplete="new-password"></div>
<button class="password-submit" type="submit">Simpan Password Baru</button>
</form></section></div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
