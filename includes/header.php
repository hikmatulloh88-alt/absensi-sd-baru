<?php
if (!isset($pageTitle)) $pageTitle = 'Dashboard';
$uri = $_SERVER['REQUEST_URI'] ?? '';
$scriptName = str_replace('\\\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
$modulePos = strpos($scriptName, '/modules/');
$basePath = $modulePos !== false ? substr($scriptName, 0, $modulePos) : rtrim(dirname($scriptName), '/');
if ($basePath === '/') $basePath = '';
function navActive(string $needle): string { global $uri; return str_contains($uri, $needle) ? ' active' : ''; }
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($pageTitle) ?> · Absensi SDN Ciburayut 01</title>
<link rel="stylesheet" href="<?= h($basePath) ?>/assets/css/app.css?v=20261019menusfull">
</head>
<body>
<div class="app-shell">
<aside class="sidebar" id="sidebar">
  <div class="brand"><div class="brand-mark">AC</div><div><strong>ABSENSI<span>+</span></strong><small>SDN CIBURAYUT 01</small></div><button class="close-menu" id="closeMenu" aria-label="Tutup menu">×</button></div>
  <div class="nav-caption">MENU UTAMA</div>
  <nav class="nav-list">
    <a class="nav-link<?= (str_ends_with($uri, '/index.php') && !str_contains($uri, '/modules/')) ? ' active' : '' ?>" href="<?= h($basePath) ?>/index.php"><span>▦</span>Dashboard</a>
    <a class="nav-link<?= navActive('/modules/absensi/') ?>" href="<?= h($basePath) ?>/modules/absensi/index.php"><span>◉</span>Absensi</a>

    <div class="nav-group<?= navActive('/modules/guru/') ?>" >
      <button class="nav-group-toggle<?= navActive('/modules/guru/') ?>" type="button" aria-expanded="<?= str_contains($uri, '/modules/guru/') ? 'true' : 'false' ?>" aria-controls="nav-gtk">
        <span class="nav-icon">🎓</span><span class="nav-label">GTK</span><span class="nav-chevron">⌄</span>
      </button>
      <div class="nav-submenu<?= str_contains($uri, '/modules/guru/') ? ' expanded' : '' ?>" id="nav-gtk">
        <a class="nav-sublink<?= navActive('/modules/guru/') ?>" href="<?= h($basePath) ?>/modules/guru/index.php"><span>🎓</span>Guru</a>
        <span class="nav-sublink nav-unavailable"><span>🎓</span>Tendik <small>Belum tersedia</small></span>
        <span class="nav-sublink nav-unavailable"><span>🎓</span><em>GTK Non-Aktif</em> <small>Belum tersedia</small></span>
      </div>
    </div>

    <div class="nav-group<?= navActive('/modules/siswa/') ?>">
      <button class="nav-group-toggle<?= navActive('/modules/siswa/') ?>" type="button" aria-expanded="<?= str_contains($uri, '/modules/siswa/') ? 'true' : 'false' ?>" aria-controls="nav-siswa">
        <span class="nav-icon">☻</span><span class="nav-label">Peserta Didik</span><span class="nav-chevron">⌄</span>
      </button>
      <div class="nav-submenu<?= str_contains($uri, '/modules/siswa/') ? ' expanded' : '' ?>" id="nav-siswa">
        <a class="nav-sublink<?= navActive('/modules/siswa/') ?>" href="<?= h($basePath) ?>/modules/siswa/index.php"><span>☻</span>Peserta Didik</a>
        <span class="nav-sublink nav-unavailable"><span>☻</span><em>PD Keluar</em> <small>Belum tersedia</small></span>
      </div>
    </div>

    <div class="nav-group">
      <button class="nav-group-toggle" type="button" aria-expanded="false" aria-controls="nav-rombel">
        <span class="nav-icon">♟</span><span class="nav-label">Rombongan Belajar</span><span class="nav-chevron">⌄</span>
      </button>
      <div class="nav-submenu" id="nav-rombel">
        <span class="nav-sublink nav-unavailable"><span>♟</span>Reguler <small>Belum tersedia</small></span>
        <span class="nav-sublink nav-unavailable"><span>⚽</span>Ekskul <small>Belum tersedia</small></span>
        <span class="nav-sublink nav-unavailable"><span>⚑</span>Matpel Pilihan <small>Belum tersedia</small></span>
      </div>
    </div>

    <div class="nav-group">
      <button class="nav-group-toggle" type="button" aria-expanded="false" aria-controls="nav-nilai">
        <span class="nav-icon">▤</span><span class="nav-label">Nilai</span><span class="nav-chevron">⌄</span>
      </button>
      <div class="nav-submenu" id="nav-nilai">
        <span class="nav-sublink nav-unavailable"><span>▤</span>Rapor <small>Belum tersedia</small></span>
      </div>
    </div>

    <a class="nav-link<?= navActive('/modules/laporan/') ?>" href="<?= h($basePath) ?>/modules/laporan/index.php"><span>▤</span>Laporan</a>
  </nav>
  <div class="sidebar-bottom"><div class="school-avatar">SD</div><div><strong>SDN Ciburayut 01</strong><small>Mode pengembangan lokal</small></div></div>
</aside>
<div class="main-area">
<header class="topbar"><button class="menu-toggle" id="menuToggle" aria-label="Buka menu">☰</button><div class="crumb">Sekolah <span>/</span> <strong><?= h($pageTitle) ?></strong></div><div class="top-actions"><span class="local-badge"><i></i> LOCAL DEV</span><div class="user-avatar">A</div><div class="user-meta"><strong>Administrator</strong><small>Pengembang lokal</small></div></div></header>
<main class="page-content">
