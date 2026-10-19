<?php
if (!isset($pageTitle)) $pageTitle = 'Dashboard';
$uri = $_SERVER['REQUEST_URI'] ?? '';
function navActive(string $needle): string { global $uri; return str_contains($uri, $needle) ? ' active' : ''; }
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($pageTitle) ?> · Absensi SDN Ciburayut 01</title>
<link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
<div class="app-shell">
<aside class="sidebar" id="sidebar">
  <div class="brand"><div class="brand-mark">AC</div><div><strong>ABSENSI<span>+</span></strong><small>SDN CIBURAYUT 01</small></div><button class="close-menu" id="closeMenu" aria-label="Tutup menu">×</button></div>
  <div class="nav-caption">MENU UTAMA</div>
  <nav class="nav-list">
    <a class="nav-link<?= navActive('/index.php') ?>" href="index.php"><span>▦</span>Dashboard</a>
    <a class="nav-link<?= navActive('/modules/absensi/') ?>" href="modules/absensi/index.php"><span>◉</span>Absensi</a>
    <a class="nav-link<?= navActive('/modules/guru/') ?>" href="modules/guru/index.php"><span>♧</span>Data Guru</a>
    <a class="nav-link<?= navActive('/modules/siswa/') ?>" href="modules/siswa/index.php"><span>♙</span>Data Siswa</a>
    <a class="nav-link<?= navActive('/modules/laporan/') ?>" href="modules/laporan/index.php"><span>▤</span>Laporan</a>
  </nav>
  <div class="sidebar-bottom"><div class="school-avatar">SD</div><div><strong>SDN Ciburayut 01</strong><small>Mode pengembangan lokal</small></div></div>
</aside>
<div class="main-area">
<header class="topbar"><button class="menu-toggle" id="menuToggle" aria-label="Buka menu">☰</button><div class="crumb">Sekolah <span>/</span> <strong><?= h($pageTitle) ?></strong></div><div class="top-actions"><span class="local-badge"><i></i> LOCAL DEV</span><div class="user-avatar">A</div><div class="user-meta"><strong>Administrator</strong><small>Pengembang lokal</small></div></div></header>
<main class="page-content">
