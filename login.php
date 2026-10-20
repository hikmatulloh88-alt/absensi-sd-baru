<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
if (!$pdo) { http_response_code(503); exit('Database belum tersambung.'); }
try { $pdo->query("SELECT id FROM akun_pengguna LIMIT 1"); }
catch (Throwable $e) { header('Location: setup_admin.php'); exit; }
if (!empty($_SESSION['akun_id'])) { header('Location: index.php'); exit; }
if (empty($_SESSION['csrf_login'])) $_SESSION['csrf_login']=bin2hex(random_bytes(32));
$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
 if (!hash_equals((string)$_SESSION['csrf_login'],(string)($_POST['csrf']??''))) $error='Sesi login tidak valid. Muat ulang halaman.';
 else {
  $username=trim((string)($_POST['username']??'')); $password=(string)($_POST['password']??'');
  $q=$pdo->prepare("SELECT id,username,password_hash,nama,role,guru_id,siswa_id,aktif FROM akun_pengguna WHERE username=? LIMIT 1"); $q->execute([$username]); $u=$q->fetch();
  if ($u && (int)$u['aktif']===1 && password_verify($password,$u['password_hash'])) {
   session_regenerate_id(true); $_SESSION['akun_id']=(int)$u['id']; $_SESSION['username']=$u['username']; $_SESSION['nama']=$u['nama']; $_SESSION['role']=$u['role'];
   if ($u['siswa_id']!==null) $_SESSION['siswa_id']=(int)$u['siswa_id']; else unset($_SESSION['siswa_id']);
   if ($u['guru_id']!==null) $_SESSION['guru_id']=(int)$u['guru_id']; else unset($_SESSION['guru_id']);
   unset($_SESSION['csrf_login']); header('Location: index.php'); exit;
  } else $error='Username atau password salah, atau akun dinonaktifkan.';
 }
}
function esc($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
?><!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Login · Absensi+</title><link rel="stylesheet" href="assets/css/app.css"><style>
body{margin:0;background:#eef3fb;font-family:Arial,sans-serif}.wrap{min-height:100vh;display:grid;place-items:center;padding:20px;box-sizing:border-box}.card{background:white;padding:30px;border-radius:18px;width:min(100%,420px);box-shadow:0 18px 55px #233b6018;box-sizing:border-box}.card h1{margin-top:0;color:#1e3150}.card p{color:#65738a;line-height:1.5}.card label{display:block;font-weight:bold;font-size:13px;margin:14px 0 6px}.card input{box-sizing:border-box;width:100%;padding:12px;border:1px solid #d8e1ed;border-radius:9px}.btn{width:100%;margin-top:20px;padding:13px;background:#245fda;color:#fff;border:0;border-radius:9px;font-weight:bold;cursor:pointer}.err{background:#fff0f0;color:#9b1c1c;padding:12px;border-radius:8px}
</style></head><body><div class="wrap"><section class="card"><h1>ABSENSI+ Login</h1><p>SDN Ciburayut 01 · Masuk menggunakan akun Anda.</p><?php if($error): ?><div class="err"><?=esc($error)?></div><?php endif; ?><form method="post"><input type="hidden" name="csrf" value="<?=esc($_SESSION['csrf_login'])?>"><label>Username</label><input name="username" required autocomplete="username" autofocus><label>Password</label><input type="password" name="password" required autocomplete="current-password"><button class="btn" type="submit">Masuk</button></form></section></div></body></html>