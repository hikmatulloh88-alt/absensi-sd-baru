<?php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','samesite'=>'Lax']);
  session_start();
}
$config = require __DIR__ . '/../config/database.php';
$dsn = "mysql:host={$config['host']};dbname={$config['database']};charset={$config['charset']}";
try {
  $pdo = new PDO($dsn, $config['username'], $config['password'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
  ]);
} catch (PDOException $e) { $pdo = null; }
function h($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }

/* Login diwajibkan untuk semua halaman aplikasi, kecuali login/setup/logout. */
$scriptName = str_replace('\\\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
$script = basename($scriptName);
$publicPages = ['login.php','setup_admin.php','logout.php'];
$modulePos = strpos($scriptName, '/modules/');
$basePath = $modulePos !== false ? substr($scriptName, 0, $modulePos) : rtrim(dirname($scriptName), '/');
if ($basePath === '/') $basePath = '';
if (!in_array($script, $publicPages, true)) {
  if (!$pdo) { http_response_code(503); exit('Database belum tersambung. Periksa config/database.php.'); }
  try { $pdo->query("SELECT id FROM akun_pengguna LIMIT 1"); }
  catch (Throwable $e) { header('Location: ' . $basePath . '/setup_admin.php'); exit; }
  if (empty($_SESSION['akun_id'])) { header('Location: ' . $basePath . '/login.php'); exit; }
  if (($_SESSION['role'] ?? '') === 'siswa' && !str_contains($scriptName, '/modules/surat_siswa/')) {
    http_response_code(403); exit('Akun siswa hanya dapat mengakses halaman pengajuan surat.');
  }
}
