<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
if (($_SESSION['role'] ?? '') !== 'admin') { http_response_code(403); exit('Akses impor RKAS hanya untuk admin.'); }
$pageTitle = 'Impor RKAS';
$errors = [];
$ok = '';
if (empty($_SESSION['spj_csrf'])) $_SESSION['spj_csrf'] = bin2hex(random_bytes(32));
if (!isset($_SESSION['spj_rkas_preview'])) $_SESSION['spj_rkas_preview'] = null;
function rkas_money($raw): ?float {
  $v = trim((string)$raw);
  if ($v === '') return null;
  $v = str_replace(['Rp','rp',' ',"\t"], '', $v);
  if (str_contains($v, ',') && str_contains($v, '.')) {
    if (strrpos($v, ',') > strrpos($v, '.')) $v = str_replace(',', '.', str_replace('.', '', $v));
    else $v = str_replace(',', '', $v);
  } elseif (str_contains($v, ',')) { if (preg_match('/^\d{1,3}(,\d{3})+$/', $v)) $v = str_replace(',', '', $v); else $v = str_replace(',', '.', $v); }
  elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $v)) $v = str_replace('.', '', $v);
  return is_numeric($v) && (float)$v >= 0 ? (float)$v : null;
}
function rkas_norm(string $s): string {
  $s = strtolower(trim(preg_replace('/\x{FEFF}/u', '', $s) ?? $s));
  return preg_replace('/[^a-z0-9]+/', '', $s) ?? $s;
}
$ready = false;
try { $pdo->query("SELECT 1 FROM spj_rkas_import LIMIT 1"); $pdo->query("SELECT 1 FROM spj_rkas_kegiatan LIMIT 1"); $ready = true; } catch (Throwable $e) {}
if (!$ready) $errors[] = 'Tabel anggaran belum tersedia. Jalankan migrasi database/spj_rencana_bulanan.sql di database uji setelah backup.';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $ready) {
  if (!hash_equals((string)$_SESSION['spj_csrf'], (string)($_POST['csrf'] ?? ''))) $errors[] = 'Sesi berakhir. Muat ulang halaman.';
  else {
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'clear_preview') { $_SESSION['spj_rkas_preview'] = null; $ok = 'Pratinjau dibersihkan.'; }
    elseif ($action === 'preview') {
      $year = filter_var($_POST['tahun'] ?? null, FILTER_VALIDATE_INT);
      $source = (string)($_POST['sumber'] ?? '');
      if (!$year || $year < 2000 || $year > 2100 || !in_array($source, ['BOSP','BOS_KINERJA'], true)) $errors[] = 'Tahun dan sumber dana tidak valid.';
      elseif (!isset($_FILES['file_rkas']) || $_FILES['file_rkas']['error'] !== UPLOAD_ERR_OK) $errors[] = 'Pilih file CSV yang valid.';
      elseif ($_FILES['file_rkas']['size'] < 1 || $_FILES['file_rkas']['size'] > 2*1024*1024) $errors[] = 'File harus berukuran maksimal 2 MB.';
      else {
        $tmp = $_FILES['file_rkas']['tmp_name'];
        $raw = file_get_contents($tmp);
        if ($raw === false || str_contains($raw, "\0")) $errors[] = 'File tidak terbaca sebagai CSV teks.';
        else {
          $checksum = hash('sha256', $raw);
          $fh = fopen($tmp, 'rb');
          $first = $fh ? fgets($fh) : false;
          $delimiter = ($first !== false && substr_count($first, ';') > substr_count($first, ',')) ? ';' : ',';
          if ($fh) rewind($fh);
          $header = $fh ? fgetcsv($fh, 0, $delimiter, '"', '\\') : false;
          $map = [];
          if (is_array($header)) foreach ($header as $i => $h) $map[rkas_norm((string)$h)] = $i;
          $aliases = [
            'kode_kegiatan'=>['kodekegiatan','kodeprogram','kodeaktivitas'],
            'kode_rekening'=>['koderekening','kodeakun','kodeaccount'],
            'uraian'=>['uraian','namakegiatan','kegiatan','uraianprogram'],
            'jenis_belanja'=>['jenisbelanja','jenis'],
            'pagu'=>['pagu','jumlahpagu','anggaran','jumlah']
          ];
          $col = [];
          foreach ($aliases as $key=>$names) { foreach ($names as $name) if (isset($map[$name])) { $col[$key]=$map[$name]; break; } }
          if (!$fh || !is_array($header) || !isset($col['uraian'],$col['pagu'])) $errors[] = 'Header CSV wajib memiliki kolom uraian dan pagu. Kolom lain: kode_kegiatan, kode_rekening, jenis_belanja.';
          else {
            $rows=[]; $line=1; $total=0; $bad=[];
            while (($data = fgetcsv($fh, 0, $delimiter, '"', '\\')) !== false) {
              $line++;
              if ($data === [null] || count(array_filter($data, fn($v)=>trim((string)$v)!==''))===0) continue;
              $desc=trim((string)($data[$col['uraian']]??''));
              $budget=rkas_money($data[$col['pagu']]??'');
              $kindRaw=strtolower(trim((string)($data[$col['jenis_belanja']]??'operasi')));
              $kind=(str_contains($kindRaw,'modal')?'MODAL':(str_contains($kindRaw,'operasi')?'OPERASI':(str_contains($kindRaw,'lain')?'LAINNYA':'LAINNYA')));
              if ($desc==='' || strlen($desc)>500 || $budget===null || $budget<=0) { $bad[]='Baris '.$line.': uraian/pagu kosong atau tidak valid.'; continue; }
              $rows[]=['kode_kegiatan'=>trim((string)($data[$col['kode_kegiatan']]??'')),'kode_rekening'=>trim((string)($data[$col['kode_rekening']]??'')),'uraian'=>$desc,'jenis_belanja'=>$kind,'pagu'=>$budget];
              $total += $budget;
              if (count($rows)>5000) { $bad[]='Maksimal 5.000 baris per file.'; break; }
            }
            if ($fh) fclose($fh);
            if ($bad) $errors = array_merge($errors, array_slice($bad,0,10));
            if (!$errors && !$rows) $errors[]='Tidak ada baris kegiatan valid dalam file.';
            if (!$errors) {
              $q=$pdo->prepare("SELECT id FROM spj_rkas_import WHERE tahun_anggaran=? AND sumber_dana=? AND checksum_sha256=? LIMIT 1");
              $q->execute([$year,$source,$checksum]);
              if ($q->fetchColumn()) $errors[]='File dengan isi yang sama sudah pernah diimpor. Impor dibatalkan agar tidak menggandakan pagu.';
              else $_SESSION['spj_rkas_preview']=['year'=>$year,'source'=>$source,'checksum'=>$checksum,'filename'=>basename((string)$_FILES['file_rkas']['name']),'rows'=>$rows,'total'=>$total];
            }
          }
        }
      }
    } elseif ($action === 'commit') {
      $preview = $_SESSION['spj_rkas_preview'];
      if (!is_array($preview) || empty($preview['rows'])) $errors[]='Tidak ada pratinjau yang siap disimpan. Unggah file dan pratinjau ulang.';
      elseif (($_POST['confirm'] ?? '') !== 'yes') $errors[]='Centang konfirmasi sebelum menyimpan.';
      else {
        try {
          $pdo->beginTransaction();
          $q=$pdo->prepare("SELECT id FROM spj_rkas_import WHERE tahun_anggaran=? AND sumber_dana=? AND status='aktif' FOR UPDATE");
          $q->execute([$preview['year'],$preview['source']]); $oldIds=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));
          if ($oldIds) {
            $marks=implode(',',array_fill(0,count($oldIds),'?'));
            $q=$pdo->prepare("SELECT COUNT(*) FROM spj_rkas_kegiatan k JOIN spj_rencana_bulanan r ON r.kegiatan_id=k.id WHERE k.import_id IN ($marks) AND r.status='aktif'");
            $q->execute($oldIds);
            if ((int)$q->fetchColumn()>0) throw new RuntimeException('Pagu aktif sudah dipakai rencana bulanan. Versi baru tidak boleh menggantinya sebelum rencana ditinjau.');
          }
          $q=$pdo->prepare("INSERT INTO spj_rkas_import (tahun_anggaran,sumber_dana,nama_dokumen,checksum_sha256,status,total_pagu,created_by,activated_at) VALUES (?,?,?,?,'draft',?, ?,NULL)");
          $q->execute([$preview['year'],$preview['source'],$preview['filename'],$preview['checksum'],$preview['total'],$_SESSION['akun_id']??null]);
          $importId=(int)$pdo->lastInsertId();
          $ins=$pdo->prepare("INSERT INTO spj_rkas_kegiatan (import_id,kode_kegiatan,kode_rekening,uraian,jenis_belanja,pagu) VALUES (?,?,?,?,?,?)");
          foreach($preview['rows'] as $r) $ins->execute([$importId,$r['kode_kegiatan'],$r['kode_rekening'],$r['uraian'],$r['jenis_belanja'],$r['pagu']]);
          if ($oldIds) {
            $marks=implode(',',array_fill(0,count($oldIds),'?'));
            $q=$pdo->prepare("UPDATE spj_rkas_import SET status='digantikan' WHERE id IN ($marks)"); $q->execute($oldIds);
          }
          $q=$pdo->prepare("UPDATE spj_rkas_import SET status='aktif',activated_at=NOW() WHERE id=?"); $q->execute([$importId]);
          $pdo->commit(); $_SESSION['spj_rkas_preview']=null; $ok='Impor RKAS tersimpan dan diaktifkan. Periksa total serta daftar kegiatan sebelum digunakan untuk rencana bulanan.';
        } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); $errors[]=$e instanceof RuntimeException ? $e->getMessage() : 'Impor gagal disimpan. Tidak ada perubahan yang dikonfirmasi.'; }
      }
    } else $errors[]='Aksi tidak dikenal.';
  }
}
$preview=$_SESSION['spj_rkas_preview'];
require __DIR__ . '/../../includes/header.php';
?>
<style>
.rkas-wrap{max-width:1180px;margin:auto}.rkas-panel{background:var(--card,#fff);border:1px solid var(--border,#e5e7eb);border-radius:14px;padding:18px;margin:0 0 16px}.rkas-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.rkas-field label{display:block;font-size:13px;margin-bottom:6px;color:var(--text-muted,#64748b)}.rkas-field input,.rkas-field select{width:100%;padding:10px;border:1px solid var(--border,#d1d5db);border-radius:8px;background:var(--card,#fff);color:inherit}.rkas-btn{display:inline-block;border:0;border-radius:8px;padding:10px 14px;background:var(--primary,#2563eb);color:white;cursor:pointer;text-decoration:none}.rkas-table-wrap{overflow:auto}.rkas-table{width:100%;border-collapse:collapse;min-width:760px}.rkas-table th,.rkas-table td{padding:9px;border-bottom:1px solid var(--border,#e5e7eb);font-size:13px;text-align:left}.rkas-table th{background:var(--surface,#f8fafc)}.rkas-alert,.rkas-ok{padding:12px;border-radius:9px;margin:10px 0}.rkas-alert{background:#fef2f2;color:#991b1b}.rkas-ok{background:#ecfdf5;color:#065f46}.rkas-muted{font-size:13px;color:var(--text-muted,#64748b)}@media(max-width:700px){.rkas-grid{grid-template-columns:1fr}.rkas-panel{padding:12px}}
</style>
<main class="main-content"><div class="rkas-wrap">
<div class="page-header"><div><h1>Impor Anggaran Tahunan</h1><p class="rkas-muted">CSV hasil ekspor/salinan Excel · pratinjau sebelum penyimpanan · tidak menimpa versi yang dipakai rencana aktif.</p></div></div>
<?php foreach($errors as $e): ?><div class="rkas-alert"><?= h($e) ?></div><?php endforeach; ?>
<?php if($ok): ?><div class="rkas-ok"><?= h($ok) ?></div><?php endif; ?>
<div class="rkas-panel"><h2 style="margin-top:0">1. Pilih file RKAS</h2>
<p class="rkas-muted">Buat CSV dengan header <code>kode_kegiatan,kode_rekening,uraian,jenis_belanja,pagu</code>. Kolom wajib: <code>uraian</code> dan <code>pagu</code>. Jenis belanja dapat berisi Operasi, Modal, atau Lainnya. File maksimal 2 MB dan 5.000 kegiatan.</p>
<p><a class="rkas-btn" style="background:#64748b" href="<?= h($basePath) ?>/templates/template_import_rkas.csv" download>Unduh template CSV kosong</a></p>
<form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?= h($_SESSION['spj_csrf']) ?>"><input type="hidden" name="action" value="preview">
<div class="rkas-grid"><div class="rkas-field"><label>Tahun anggaran</label><input type="number" name="tahun" min="2000" max="2100" value="<?= h($preview['year']??date('Y')) ?>" required></div>
<div class="rkas-field"><label>Sumber dana</label><select name="sumber"><option value="BOSP" <?= (($preview['source']??'BOSP')==='BOSP')?'selected':'' ?>>BOSP Reguler</option><option value="BOS_KINERJA" <?= (($preview['source']??'')==='BOS_KINERJA')?'selected':'' ?>>BOS Kinerja</option></select></div>
<div class="rkas-field"><label>File CSV</label><input type="file" name="file_rkas" accept=".csv,text/csv" required></div></div><p style="margin-bottom:0"><button class="rkas-btn" type="submit">Baca &amp; pratinjau</button></p></form></div>
<?php if(is_array($preview) && !empty($preview['rows'])): ?>
<div class="rkas-panel"><h2 style="margin-top:0">2. Pratinjau sebelum simpan</h2>
<p><strong><?= h($preview['filename']) ?></strong> · <?= count($preview['rows']) ?> kegiatan · Total pagu <strong>Rp <?= number_format((float)$preview['total'],0,',','.') ?></strong> · <?= h($preview['year']) ?> · <?= $preview['source']==='BOSP'?'BOSP Reguler':'BOS Kinerja' ?></p>
<div class="rkas-table-wrap"><table class="rkas-table"><thead><tr><th>Kode kegiatan</th><th>Kode rekening</th><th>Uraian</th><th>Jenis</th><th style="text-align:right">Pagu</th></tr></thead><tbody>
<?php foreach(array_slice($preview['rows'],0,100) as $r): ?><tr><td><?= h($r['kode_kegiatan']?:'-') ?></td><td><?= h($r['kode_rekening']?:'-') ?></td><td><?= h($r['uraian']) ?></td><td><?= h($r['jenis_belanja']) ?></td><td style="text-align:right;white-space:nowrap">Rp <?= number_format((float)$r['pagu'],0,',','.') ?></td></tr><?php endforeach; ?>
</tbody></table></div><?php if(count($preview['rows'])>100): ?><p class="rkas-muted">Menampilkan 100 dari <?= count($preview['rows']) ?> kegiatan. Semua baris validasi akan disimpan jika dikonfirmasi.</p><?php endif; ?>
<form method="post" style="margin-top:14px"><input type="hidden" name="csrf" value="<?= h($_SESSION['spj_csrf']) ?>"><input type="hidden" name="action" value="commit"><label style="display:block;margin-bottom:12px"><input type="checkbox" name="confirm" value="yes" required> Saya sudah memeriksa tahun, sumber dana, daftar kegiatan, dan total pagu.</label><button class="rkas-btn" type="submit">Konfirmasi &amp; simpan RKAS</button></form>
<form method="post" style="margin-top:8px"><input type="hidden" name="csrf" value="<?= h($_SESSION['spj_csrf']) ?>"><input type="hidden" name="action" value="clear_preview"><button class="rkas-btn" style="background:#64748b" type="submit">Batalkan pratinjau</button></form></div>
<?php endif; ?>
<div class="rkas-panel"><h2 style="margin-top:0">Batas tahap ini</h2><p class="rkas-muted">Halaman ini menerima CSV, bukan PDF atau XLSX secara langsung. Ekspor Excel sebagai CSV UTF-8 terlebih dahulu. Belum ada OCR PDF atau rekonsiliasi BKU; angka yang diimpor harus diperiksa terhadap dokumen RKAS asli sebelum menjadi acuan resmi.</p><a class="rkas-btn" href="<?= h($basePath) ?>/modules/spj_bosp/rencana_bulanan.php">Buka Rencana Bulanan</a></div>
</div></main>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
