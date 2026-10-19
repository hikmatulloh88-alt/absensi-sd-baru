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
<link rel="stylesheet" href="<?= h($basePath) ?>/assets/css/app.css?v=20261019rapor1">
</head>
<body>
<div class="app-shell">
<aside class="sidebar" id="sidebar">
  <div class="brand"><div class="brand-mark">AC</div><div><strong>ABSENSI<span>+</span></strong><small>SDN CIBURAYUT 01</small></div><button class="close-menu" id="closeMenu" aria-label="Tutup menu">×</button></div>
  <div class="nav-caption">MENU UTAMA</div>
  <nav class="nav-list">
    <a class="nav-link<?= (str_ends_with($uri, '/index.php') && !str_contains($uri, '/modules/')) ? ' active' : '' ?>" href="<?= h($basePath) ?>/index.php"><span>▦</span>Dashboard</a>
    <div class="nav-group<?= navActive('/modules/absensi/') ?>">
      <button class="nav-group-toggle<?= navActive('/modules/absensi/') ?>" type="button" aria-expanded="<?= str_contains($uri, '/modules/absensi/') ? 'true' : 'false' ?>" aria-controls="nav-absensi">
        <span class="nav-icon">◉</span><span class="nav-label">Absensi</span><span class="nav-chevron">⌄</span>
      </button>
      <div class="nav-submenu<?= str_contains($uri, '/modules/absensi/') ? ' expanded' : '' ?>" id="nav-absensi">
        <a class="nav-sublink<?= str_contains($uri, '/modules/absensi/') && (($_GET['jenis'] ?? 'siswa') !== 'guru') ? ' active' : '' ?>" href="<?= h($basePath) ?>/modules/absensi/index.php?jenis=siswa"><span>☻</span>Absensi Siswa</a>
        <a class="nav-sublink<?= str_contains($uri, '/modules/absensi/') && (($_GET['jenis'] ?? '') === 'guru') ? ' active' : '' ?>" href="<?= h($basePath) ?>/modules/absensi/index.php?jenis=guru"><span>🎓</span>Absensi Guru</a>
      </div>
    </div>

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
      <div class="nav-submenu<?= (str_contains($uri, '/modules/rombel/') || str_contains($uri, '/modules/ekskul/') || str_contains($uri, '/modules/matpel_pilihan/')) ? ' expanded' : '' ?>" id="nav-rombel">
        <a class="nav-sublink<?= navActive('/modules/rombel/') ?>" href="<?= h($basePath) ?>/modules/rombel/index.php"><span>♟</span>Reguler</a>
        <a class="nav-sublink<?= navActive('/modules/ekskul/') ?>" href="<?= h($basePath) ?>/modules/ekskul/index.php"><span>⚽</span>Ekskul</a>
        <a class="nav-sublink<?= navActive('/modules/matpel_pilihan/') ?>" href="<?= h($basePath) ?>/modules/matpel_pilihan/index.php"><span>⚑</span>Matpel Pilihan</a>
      </div>
    </div>

    <div class="nav-group">
      <button class="nav-group-toggle" type="button" aria-expanded="false" aria-controls="nav-nilai">
        <span class="nav-icon">▤</span><span class="nav-label">Nilai</span><span class="nav-chevron">⌄</span>
      </button>
      <div class="nav-submenu<?= str_contains($uri, '/modules/nilai/') ? ' expanded' : '' ?>" id="nav-nilai">
        <a class="nav-sublink<?= navActive('/modules/nilai/') ?>" href="<?= h($basePath) ?>/modules/nilai/index.php"><span>▤</span>Rapor</a>
      </div>
    </div>

    <a class="nav-link<?= navActive('/modules/laporan/') ?>" href="<?= h($basePath) ?>/modules/laporan/index.php"><span>▤</span>Laporan</a>
  </nav>
  <div class="sidebar-bottom"><div class="school-avatar">SD</div><div><strong>SDN Ciburayut 01</strong><small>Mode pengembangan lokal</small></div></div>
</aside>
<div class="main-area">
<header class="topbar"><button class="menu-toggle" id="menuToggle" aria-label="Buka menu">☰</button><div class="crumb">Sekolah <span>/</span> <strong><?= h($pageTitle) ?></strong></div><div class="top-actions"><span class="local-badge"><i></i> LOCAL DEV</span><div class="user-avatar">A</div><div class="user-meta"><strong>Administrator</strong><small>Pengembang lokal</small></div></div></header>
<main class="page-content">
