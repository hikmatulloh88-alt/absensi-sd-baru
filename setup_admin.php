<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
if (!$pdo) { http_response_code(503); exit('Database belum tersambung.'); }
$pdo->exec("CREATE TABLE IF NOT EXISTS akun_pengguna (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 username VARCHAR(80) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 nama VARCHAR(150) NOT NULL,
 role ENUM('admin','guru','siswa') NOT NULL DEFAULT 'admin',
 guru_id INT UNSIGNED NULL, siswa_id INT UNSIGNED NULL,
 aktif TINYINT(1) NOT NULL DEFAULT 1,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_akun_role (role), INDEX idx_akun_guru (guru_id), INDEX idx_akun_siswa (siswa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$done=(int)$pdo->query("SELECT COUNT(*) FROM akun_pengguna")->fetchColumn()>0;
$errors=[];
if($_SERVER['REQUEST_METHOD']==='POST' && !$done){
 if(!hash_equals((string)($_SESSION['csrf_setup']??''),(string)($_POST['csrf']??''))) $errors[]='Sesi tidak valid. Muat ulang halaman.';
 $nama=trim((string)($_POST['nama']??'')); $username=trim((string)($_POST['username']??'')); $pw=(string)($_POST['password']??''); $confirm=(string)($_POST['confirm']??'');
 if(mb_strlen($nama)<3) $errors[]='Nama minimal 3 karakter.';
 if(!preg_match('/^[A-Za-z0-9._-]{4,80}$/',$username)) $errors[]='Username 4–80 karakter, hanya huruf, angka, titik, _ atau -.';
 if(strlen($pw)<10) $errors[]='Password minimal 10 karakter.';
 if($pw!==$confirm) $errors[]='Konfirmasi password tidak sama.';
 if(!$errors){
  try { $q=$pdo->prepare("INSERT INTO akun_pengguna(username,password_hash,nama,role,aktif) VALUES(?,?,?,'admin',1)"); $q->execute([$username,password_hash($pw,PASSWORD_DEFAULT),$nama]); header('Location: login.php?setup=1'); exit; }
  catch(Throwable $e) { $errors[]='Akun gagal dibuat. Username mungkin sudah digunakan.'; }
 }
}
if(empty($_SESSION['csrf_setup'])) $_SESSION['csrf_setup']=bin2hex(random_bytes(32));
function esc($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
?><!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Setup Admin</title><link rel="stylesheet" href="assets/css/app.css"><style>
body{margin:0;background:#eef3fb;font-family:Arial,sans-serif}.wrap{min-height:100vh;display:grid;place-items:center;padding:20px;box-sizing:border-box}.card{background:white;padding:30px;border-radius:18px;width:min(100%,430px);box-shadow:0 18px 55px #233b6018;box-sizing:border-box}.card h1{margin-top:0;color:#1e3150}.card p{color:#65738a;line-height:1.5}.card label{display:block;font-weight:bold;font-size:13px;margin:14px 0 6px}.card input{box-sizing:border-box;width:100%;padding:12px;border:1px solid #d8e1ed;border-radius:9px}.btn{display:block;width:100%;margin-top:20px;padding:13px;background:#245fda;color:#fff;border:0;border-radius:9px;font-weight:bold;text-align:center;text-decoration:none;box-sizing:border-box}.err{background:#fff0f0;color:#9b1c1c;padding:12px;border-radius:8px}
</style></head><body><div class="wrap"><section class="card"><h1>Buat akun admin pertama</h1><p>Aplikasi belum memiliki akun. Buat administrator sekarang. Form ini terkunci otomatis setelah akun pertama dibuat.</p>
<?php if($done): ?><p>Akun admin sudah tersedia.</p><a class="btn" href="login.php">Ke halaman login</a><?php else: ?>
<?php if($errors): ?><div class="err"><?php foreach($errors as $e): ?><div><?=esc($e)?></div><?php endforeach; ?></div><?php endif; ?>
<form method="post"><input type="hidden" name="csrf" value="<?=esc($_SESSION['csrf_setup'])?>"><label>Nama admin</label><input name="nama" required maxlength="150" value="<?=esc($_POST['nama']??'Administrator')?>"><label>Username</label><input name="username" required minlength="4" maxlength="80" pattern="[A-Za-z0-9._-]+" autocomplete="username"><label>Password (minimal 10 karakter)</label><input type="password" name="password" required minlength="10" autocomplete="new-password"><label>Ulangi password</label><input type="password" name="confirm" required minlength="10" autocomplete="new-password"><button class="btn" type="submit">Buat akun admin</button></form>
<?php endif; ?></section></div></body></html>