<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
if (($_SESSION['role'] ?? '') !== 'admin') { http_response_code(403); exit('Akses anggaran hanya untuk admin.'); }
$pageTitle = 'Anggaran & Rencana Bulanan';
$errors = [];
$ok = '';
$year = (int)($_GET['tahun'] ?? date('Y'));
$month = (int)($_GET['bulan'] ?? date('n'));
$source = (string)($_GET['sumber'] ?? 'BOSP');
if ($year < 2000 || $year > 2100) $year = (int)date('Y');
if ($month < 1 || $month > 12) $month = (int)date('n');
if (!in_array($source, ['BOSP','BOS_KINERJA'], true)) $source = 'BOSP';
if (empty($_SESSION['spj_csrf'])) $_SESSION['spj_csrf'] = bin2hex(random_bytes(32));
$ready = false;
try {
  $pdo->query("SELECT 1 FROM spj_rkas_import LIMIT 1");
  $pdo->query("SELECT 1 FROM spj_rkas_kegiatan LIMIT 1");
  $pdo->query("SELECT 1 FROM spj_rencana_bulanan LIMIT 1");
  $ready = true;
} catch (Throwable $e) {}
if (!$ready) $errors[] = 'Tabel anggaran belum tersedia. Backup database dahulu, lalu jalankan database/spj_rencana_bulanan.sql pada database uji.';
if ($ready && $_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!hash_equals((string)$_SESSION['spj_csrf'], (string)($_POST['csrf'] ?? ''))) {
    $errors[] = 'Sesi formulir berakhir. Muat ulang halaman.';
  } else {
    $action = (string)($_POST['action'] ?? '');
    $year = (int)($_POST['tahun'] ?? $year);
    $month = (int)($_POST['bulan'] ?? $month);
    $source = (string)($_POST['sumber'] ?? $source);
    if ($year < 2000 || $year > 2100 || $month < 1 || $month > 12 || !in_array($source, ['BOSP','BOS_KINERJA'], true)) $errors[] = 'Tahun, bulan, atau sumber dana tidak valid.';
    elseif ($action === 'add_activity') {
      $desc = trim((string)($_POST['uraian'] ?? ''));
      $code = trim((string)($_POST['kode_kegiatan'] ?? ''));
      $account = trim((string)($_POST['kode_rekening'] ?? ''));
      $kind = (string)($_POST['jenis_belanja'] ?? 'OPERASI');
      $budget = filter_var($_POST['pagu'] ?? null, FILTER_VALIDATE_FLOAT);
      if ($desc === '' || strlen($desc) > 500 || !in_array($kind, ['OPERASI','MODAL','LAINNYA'], true) || $budget === false || $budget <= 0) {
        $errors[] = 'Isi uraian kegiatan dan pagu lebih dari nol dengan benar.';
      } else {
        try {
          $pdo->beginTransaction();
          $q = $pdo->prepare("SELECT id FROM spj_rkas_import WHERE tahun_anggaran=? AND sumber_dana=? AND status='aktif' ORDER BY id DESC LIMIT 1 FOR UPDATE");
          $q->execute([$year,$source]); $importId = $q->fetchColumn();
          if (!$importId) {
            $q = $pdo->prepare("INSERT INTO spj_rkas_import (tahun_anggaran,sumber_dana,nama_dokumen,status) VALUES (?,?,?,'aktif')");
            $q->execute([$year,$source,'Input manual awal']); $importId = (int)$pdo->lastInsertId();
            $pdo->prepare("UPDATE spj_rkas_import SET activated_at=NOW() WHERE id=?")->execute([$importId]);
          }
          $q = $pdo->prepare("SELECT COALESCE(SUM(pagu),0) FROM spj_rkas_kegiatan WHERE import_id=?");
          $q->execute([$importId]); $total = (float)$q->fetchColumn();
          $q = $pdo->prepare("INSERT INTO spj_rkas_kegiatan (import_id,kode_kegiatan,kode_rekening,uraian,jenis_belanja,pagu) VALUES (?,?,?,?,?,?)");
          $q->execute([$importId,$code,$account,$desc,$kind,$budget]);
          $pdo->prepare("UPDATE spj_rkas_import SET total_pagu=? WHERE id=?")->execute([$total+(float)$budget,$importId]);
          $pdo->commit(); $ok = 'Kegiatan anggaran tersimpan.';
        } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); $errors[] = 'Gagal menyimpan kegiatan. Periksa struktur database dan data yang dimasukkan.'; }
      }
    } elseif ($action === 'save_plan') {
      $ids = $_POST['kegiatan_id'] ?? []; $amounts = $_POST['nominal'] ?? [];
      if (!is_array($ids) || !is_array($amounts) || count($ids) > 500) $errors[] = 'Data rencana tidak valid.';
      else {
        try {
          $pdo->beginTransaction();
          $find = $pdo->prepare("SELECT k.id,k.pagu,i.tahun_anggaran,i.sumber_dana FROM spj_rkas_kegiatan k JOIN spj_rkas_import i ON i.id=k.import_id WHERE k.id=? AND i.tahun_anggaran=? AND i.sumber_dana=? AND i.status='aktif' FOR UPDATE");
          $planned = $pdo->prepare("SELECT COALESCE(SUM(nominal_rencana),0) FROM spj_rencana_bulanan WHERE kegiatan_id=? AND status='aktif' AND NOT (tahun_anggaran=? AND bulan=? AND sumber_dana=?)");
          $upsert = $pdo->prepare("INSERT INTO spj_rencana_bulanan (tahun_anggaran,bulan,sumber_dana,kegiatan_id,nominal_rencana,status,created_by,updated_by) VALUES (?,?,?,?,?,'aktif',?,?) ON DUPLICATE KEY UPDATE nominal_rencana=VALUES(nominal_rencana),status='aktif',updated_by=VALUES(updated_by)");
          $actor = isset($_SESSION['akun_id']) ? (int)$_SESSION['akun_id'] : null;
          foreach ($ids as $i => $rawId) {
            $id = filter_var($rawId,FILTER_VALIDATE_INT); $amount = filter_var($amounts[$i] ?? null,FILTER_VALIDATE_FLOAT);
            if (!$id || $amount === false || $amount < 0) { $errors[] = 'Ada baris kegiatan atau nominal yang tidak valid.'; break; }
            $find->execute([$id,$year,$source]); $activity = $find->fetch();
            if (!$activity) { $errors[] = 'Kegiatan tidak ditemukan pada pagu aktif tahun dan sumber dana terpilih.'; break; }
            $planned->execute([$id,$year,$month,$source]); $other = (float)$planned->fetchColumn();
            if ($other + (float)$amount > (float)$activity['pagu']) { $errors[] = 'Rencana melebihi pagu tahunan untuk kegiatan: '.$id.'. Kurangi nominal sebelum menyimpan.'; break; }
            $upsert->execute([$year,$month,$source,$id,$amount,$actor,$actor]);
          }
          if ($errors) $pdo->rollBack(); else { $pdo->commit(); $ok = 'Rencana bulan ini tersimpan. Ini belum mengurangi pagu resmi; realisasi SPJ belum otomatis dipetakan pada tahap ini.'; }
        } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); $errors[] = 'Rencana gagal disimpan. Periksa struktur database dan coba kembali.'; }
      }
    } else $errors[] = 'Aksi tidak dikenal.';
  }
}
$activities = []; $plannedById = []; $yearPlans = [];
if ($ready) {
  try {
    $q = $pdo->prepare("SELECT k.id,k.kode_kegiatan,k.kode_rekening,k.uraian,k.jenis_belanja,k.pagu FROM spj_rkas_kegiatan k JOIN spj_rkas_import i ON i.id=k.import_id WHERE i.tahun_anggaran=? AND i.sumber_dana=? AND i.status='aktif' ORDER BY k.id");
    $q->execute([$year,$source]); $activities = $q->fetchAll();
    $q = $pdo->prepare("SELECT kegiatan_id,nominal_rencana FROM spj_rencana_bulanan WHERE tahun_anggaran=? AND bulan=? AND sumber_dana=? AND status='aktif'");
    $q->execute([$year,$month,$source]); foreach ($q->fetchAll() as $p) $plannedById[(int)$p['kegiatan_id']] = (float)$p['nominal_rencana'];
    $q = $pdo->prepare("SELECT kegiatan_id,COALESCE(SUM(nominal_rencana),0) total FROM spj_rencana_bulanan WHERE tahun_anggaran=? AND sumber_dana=? AND status='aktif' GROUP BY kegiatan_id");
    $q->execute([$year,$source]); foreach ($q->fetchAll() as $p) $yearPlans[(int)$p['kegiatan_id']] = (float)$p['total'];
  } catch (Throwable $e) { $errors[] = 'Data anggaran gagal dibaca. Pastikan migrasi database telah dijalankan.'; }
}
require __DIR__ . '/../../includes/header.php';
?>
<style>
.spj-wrap{max-width:1200px;margin:0 auto}.spj-panel{background:var(--card,#fff);border:1px solid var(--border,#e5e7eb);border-radius:14px;padding:18px;margin:0 0 18px}.spj-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.spj-field label{display:block;font-size:13px;margin-bottom:6px;color:var(--text-muted,#64748b)}.spj-field input,.spj-field select{width:100%;min-width:0;padding:10px;border:1px solid var(--border,#d1d5db);border-radius:8px;background:var(--card,#fff);color:inherit}.spj-actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-top:14px}.spj-btn{display:inline-block;border:0;border-radius:8px;padding:10px 14px;background:var(--primary,#2563eb);color:#fff;text-decoration:none;cursor:pointer}.spj-table-wrap{overflow:auto}.spj-table{width:100%;border-collapse:collapse;min-width:720px}.spj-table th,.spj-table td{padding:10px;border-bottom:1px solid var(--border,#e5e7eb);text-align:left;font-size:13px}.spj-table th{background:var(--surface,#f8fafc)}.spj-num{text-align:right!important;white-space:nowrap}.spj-alert{padding:12px;border-radius:9px;margin:10px 0;background:#fef2f2;color:#991b1b}.spj-ok{padding:12px;border-radius:9px;margin:10px 0;background:#ecfdf5;color:#065f46}.spj-muted{font-size:13px;color:var(--text-muted,#64748b)}@media(max-width:760px){.spj-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.spj-panel{padding:12px}}@media(max-width:440px){.spj-grid{grid-template-columns:1fr}}
</style>
<main class="main-content"><div class="spj-wrap">
<div class="page-header"><div><h1>Anggaran &amp; Rencana Bulanan</h1><p class="spj-muted">Tahap awal · input kegiatan dan pagu, lalu susun rencana per bulan.</p></div></div>
<?php foreach ($errors as $error): ?><div class="spj-alert"><?= h($error) ?></div><?php endforeach; ?>
<?php if ($ok): ?><div class="spj-ok"><?= h($ok) ?></div><?php endif; ?>
<div class="spj-panel"><form method="get"><div class="spj-grid">
<div class="spj-field"><label>Tahun anggaran</label><input type="number" name="tahun" min="2000" max="2100" value="<?= h($year) ?>" required></div>
<div class="spj-field"><label>Sumber dana</label><select name="sumber"><option value="BOSP" <?= $source==='BOSP'?'selected':'' ?>>BOSP Reguler</option><option value="BOS_KINERJA" <?= $source==='BOS_KINERJA'?'selected':'' ?>>BOS Kinerja</option></select></div>
<div class="spj-field"><label>Bulan rencana</label><select name="bulan"><?php for($m=1;$m<=12;$m++): ?><option value="<?= $m ?>" <?= $month===$m?'selected':'' ?>><?= h(date('F',mktime(0,0,0,$m,1))) ?></option><?php endfor; ?></select></div>
<div class="spj-field" style="display:flex;align-items:end"><button class="spj-btn" type="submit">Tampilkan</button></div>
</div></form></div>
<?php if ($ready): ?>
<div class="spj-panel"><h2 style="margin-top:0">Tambah kegiatan pagu tahunan</h2><p class="spj-muted">Input manual sementara. Impor otomatis dari file RKAS/ARKAS akan dibuat pada tahap berikutnya.</p>
<form method="post"><input type="hidden" name="csrf" value="<?= h($_SESSION['spj_csrf']) ?>"><input type="hidden" name="action" value="add_activity"><input type="hidden" name="tahun" value="<?= h($year) ?>"><input type="hidden" name="bulan" value="<?= h($month) ?>"><input type="hidden" name="sumber" value="<?= h($source) ?>">
<div class="spj-grid">
<div class="spj-field"><label>Kode kegiatan</label><input name="kode_kegiatan" maxlength="80" placeholder="Opsional"></div>
<div class="spj-field"><label>Kode rekening</label><input name="kode_rekening" maxlength="80" placeholder="Opsional"></div>
<div class="spj-field"><label>Jenis belanja</label><select name="jenis_belanja"><option value="OPERASI">Operasi</option><option value="MODAL">Modal</option><option value="LAINNYA">Lainnya</option></select></div>
<div class="spj-field"><label>Pagu tahunan (Rp)</label><input name="pagu" type="number" min="1" step="0.01" required></div>
<div class="spj-field" style="grid-column:1/-1"><label>Uraian kegiatan</label><input name="uraian" maxlength="500" required placeholder="Nama kegiatan sesuai RKAS"></div>
</div><div class="spj-actions"><button class="spj-btn" type="submit">Simpan kegiatan</button></div></form></div>
<div class="spj-panel"><h2 style="margin-top:0">Rencana belanja · <?= h(date('F',mktime(0,0,0,$month,1))) ?> <?= h($year) ?></h2>
<p class="spj-muted">Masukkan nominal rencana untuk setiap kegiatan. Validasi membatasi total rencana aktif sepanjang tahun agar tidak melebihi pagu kegiatan. Realisasi transaksi belum terhubung otomatis, jadi angka ini belum merupakan sisa pagu setelah belanja.</p>
<?php if (!$activities): ?><p>Belum ada kegiatan pagu untuk pilihan ini. Tambahkan kegiatan di atas terlebih dahulu.</p><?php else: ?>
<form method="post"><input type="hidden" name="csrf" value="<?= h($_SESSION['spj_csrf']) ?>"><input type="hidden" name="action" value="save_plan"><input type="hidden" name="tahun" value="<?= h($year) ?>"><input type="hidden" name="bulan" value="<?= h($month) ?>"><input type="hidden" name="sumber" value="<?= h($source) ?>">
<div class="spj-table-wrap"><table class="spj-table"><thead><tr><th>Kode</th><th>Kegiatan / Rekening</th><th class="spj-num">Pagu tahunan</th><th class="spj-num">Rencana aktif setahun</th><th class="spj-num">Rencana bulan ini (Rp)</th></tr></thead><tbody>
<?php foreach ($activities as $a): $id=(int)$a['id']; $current=$plannedById[$id]??0; ?>
<tr><td><?= h($a['kode_kegiatan'] ?: '-') ?></td><td><strong><?= h($a['uraian']) ?></strong><br><span class="spj-muted"><?= h($a['kode_rekening'] ?: 'Tanpa kode rekening') ?> · <?= h($a['jenis_belanja']) ?></span></td><td class="spj-num">Rp <?= number_format((float)$a['pagu'],0,',','.') ?></td><td class="spj-num">Rp <?= number_format($yearPlans[$id]??0,0,',','.') ?></td><td><input aria-label="Rencana <?= h($a['uraian']) ?>" style="width:155px;max-width:100%;padding:8px;border:1px solid var(--border,#d1d5db);border-radius:7px" type="number" min="0" step="0.01" name="nominal[]" value="<?= h(number_format($current,2,'.','')) ?>"><input type="hidden" name="kegiatan_id[]" value="<?= $id ?>"></td></tr>
<?php endforeach; ?>
</tbody></table></div><div class="spj-actions"><button class="spj-btn" type="submit">Simpan rencana bulan ini</button></div></form><?php endif; ?></div>
<?php endif; ?>
<div class="spj-panel"><strong>Catatan penting</strong><p class="spj-muted">Ini tahap awal untuk uji coba. Belum mengimpor PDF/Excel, belum menghitung realisasi dari BKU, belum menghubungkan transaksi lama ke kode RKAS, dan belum membuat SI. Jangan gunakan untuk laporan resmi sebelum tahap impor dan rekonsiliasi selesai.</p></div>
</div></main>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
