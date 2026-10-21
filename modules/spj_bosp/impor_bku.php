<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
if (($_SESSION['role'] ?? '') !== 'admin') { http_response_code(403); exit('Akses impor BKU hanya untuk admin.'); }
$pageTitle = 'Impor BKU';
$errors=[]; $ok='';
if (empty($_SESSION['spj_csrf'])) $_SESSION['spj_csrf']=bin2hex(random_bytes(32));
if (!isset($_SESSION['spj_bku_preview'])) $_SESSION['spj_bku_preview']=null;
require_once __DIR__ . '/lib_spj_bku.php';
$ready=false;
try{$pdo->query("SELECT id FROM spj_transaksi LIMIT 1");$ready=true;}catch(Throwable $e){}
if(!$ready)$errors[]='Tabel spj_transaksi belum tersedia. Jalankan database/spj_transaksi.sql di database uji.';
if($_SERVER['REQUEST_METHOD']==='POST'&&$ready){
 if(!hash_equals((string)$_SESSION['spj_csrf'],(string)($_POST['csrf']??'')))$errors[]='Sesi formulir berakhir. Muat ulang halaman.';
 else {
  $action=(string)($_POST['action']??'');
  if($action==='clear'){$_SESSION['spj_bku_preview']=null;$ok='Pratinjau dibersihkan.';}
  elseif($action==='preview'){
   $year=filter_var($_POST['tahun']??null,FILTER_VALIDATE_INT);$source=(string)($_POST['sumber']??'');
   if(!$year||$year<2000||$year>2100||!in_array($source,['BOSP','BOS_KINERJA'],true))$errors[]='Tahun dan sumber dana tidak valid.';
   elseif(!isset($_FILES['file_bku'])||$_FILES['file_bku']['error']!==UPLOAD_ERR_OK)$errors[]='Pilih file CSV BKU yang valid.';
   elseif($_FILES['file_bku']['size']<1||$_FILES['file_bku']['size']>2*1024*1024)$errors[]='File maksimal 2 MB.';
   else{
    $tmp=$_FILES['file_bku']['tmp_name'];$raw=file_get_contents($tmp);
    if($raw===false||str_contains($raw,"\0"))$errors[]='File tidak terbaca sebagai CSV teks.';
    else{
     $checksum=hash('sha256',$raw);$fh=fopen($tmp,'rb');$first=$fh?fgets($fh):false;$delimiter=($first!==false&&substr_count($first,';')>substr_count($first,','))?';':',';
     if($fh)rewind($fh);$header=$fh?fgetcsv($fh,0,$delimiter,'"','\\'):false;$map=[];
     if(is_array($header))foreach($header as $i=>$h)$map[bku_norm((string)$h)]=$i;
     $aliases=[
      'tanggal'=>['tanggal','tgl','date'],
      'nomor'=>['nomorbukti','nobukti','nomor','nocek','noref'],
      'uraian'=>['uraian','keterangan','deskripsi','transaksi'],
      'kegiatan'=>['kegiatan','program','namakegiatan'],
      'masuk'=>['penerimaan','masuk','debet','debit','penerimaanrp'],
      'keluar'=>['pengeluaran','keluar','kredit','credit','pengeluaranrp'],
      'saldo'=>['saldo','saldoakhir']
     ];$col=[];
     foreach($aliases as $key=>$names)foreach($names as $n)if(isset($map[$n])){$col[$key]=$map[$n];break;}
     if(!$fh||!is_array($header)||!isset($col['tanggal'],$col['uraian'],$col['keluar']))$errors[]='CSV wajib memiliki kolom tanggal, uraian/keterangan, dan pengeluaran. Kolom opsional: nomor_bukti, kegiatan, penerimaan, saldo.';
     else{
      $rows=[];$bad=[];$line=1;$totSpend=0;$totTaxReceipt=0;$totTaxDeposit=0;$totOther=0;
      while(($data=fgetcsv($fh,0,$delimiter,'"','\\'))!==false){
       $line++;if($data===[null]||count(array_filter($data,fn($v)=>trim((string)$v)!==''))===0)continue;
       $dateRaw=trim((string)($data[$col['tanggal']]??''));$date='';
       if(preg_match('/^(\d{1,2})[\/-](\d{1,2})[\/-](\d{4})$/',$dateRaw,$m))$date=sprintf('%04d-%02d-%02d',(int)$m[3],(int)$m[2],(int)$m[1]);
       elseif(preg_match('/^\d{4}-\d{2}-\d{2}$/',$dateRaw))$date=$dateRaw;
       $desc=trim((string)($data[$col['uraian']]??''));$out=bku_money($data[$col['keluar']]??'');$in=isset($col['masuk'])?bku_money($data[$col['masuk']]??''):0.0;
       $balanceRaw=isset($col['saldo'])?trim((string)($data[$col['saldo']]??'')):'';$balance=($balanceRaw!=='')?bku_money($balanceRaw):null;
       if(!$date||!checkdate((int)substr($date,5,2),(int)substr($date,8,2),(int)substr($date,0,4))||(int)substr($date,0,4)!==$year||$desc===''||$out===null||$in===null||(isset($col['saldo'])&&$balance===null&&trim((string)($data[$col['saldo']]??''))!=='')){$bad[]='Baris '.$line.': tahun tanggal harus sama dengan tahun anggaran terpilih, dan tanggal/uraian/nominal harus valid.';continue;}
       $voucher=trim((string)($data[$col['nomor']]??''));$activity=trim((string)($data[$col['kegiatan']]??''));
       $kind=bku_classify($desc,$activity,(float)$out,(float)$in);
       $rows[]=['tanggal'=>$date,'nomor'=>$voucher,'uraian'=>mb_substr($desc,0,255),'kegiatan'=>mb_substr($activity!==''?$activity:'Belum dipetakan',0,180),'masuk'=>$in,'keluar'=>$out,'saldo'=>$balance,'selisih_saldo'=>null,'jenis'=>$kind];
       if($kind==='PENGELUARAN'&&$out>0)$totSpend+=$out;elseif($kind==='PENERIMAAN_PAJAK')$totTaxReceipt+=$in;elseif($kind==='SETOR_PAJAK')$totTaxDeposit+=$out;else$totOther+=$out;
       if(count($rows)>5000){$bad[]='Maksimal 5.000 baris per file.';break;}
      }
      if($fh)fclose($fh);
      if($bad)$errors=array_merge($errors,array_slice($bad,0,10));
      if(!$errors&&!$rows)$errors[]='Tidak ada baris BKU yang valid.';
      if(!$errors){
       // Reconcile between reported balances, accumulating movements across rows
       // whose saldo cell is blank instead of comparing against a stale balance.
       [$rows,$balanceChecks,$balanceWarnings]=bku_reconcile_balances($rows);
       $q=$pdo->prepare("SELECT id FROM spj_transaksi WHERE sumber_dana=? AND tanggal=? AND nomor_bukti=? AND nilai_bruto=? AND uraian=? LIMIT 1");
       $seenInFile=[];
       foreach($rows as &$row){
        $key=bku_row_key($source,$row);
        $duplicateInFile=isset($seenInFile[$key]);
        $seenInFile[$key]=true;
        $existsInDb=false;
        // The transaction table stores expenses only; tax/balance movements are checked within this CSV only.
        if($row['jenis']==='PENGELUARAN'&&(float)$row['keluar']>0){
         $q->execute([$source,$row['tanggal'],$row['nomor'],$row['keluar'],$row['uraian']]);
         $existsInDb=(bool)$q->fetchColumn();
        }
        $row['duplikat']=$duplicateInFile||$existsInDb;
       }unset($row);
       // Recompute every preview bucket after duplicate flags are known.
       $totSpend=0.0;$totTaxReceipt=0.0;$totTaxDeposit=0.0;$totOther=0.0;$duplicateCount=0;
       foreach($rows as $checkedRow){
        if(!empty($checkedRow['duplikat'])){$duplicateCount++;continue;}
        if($checkedRow['jenis']==='PENGELUARAN'&&(float)$checkedRow['keluar']>0)$totSpend+=(float)$checkedRow['keluar'];
        elseif($checkedRow['jenis']==='PENERIMAAN_PAJAK')$totTaxReceipt+=(float)$checkedRow['masuk'];
        elseif($checkedRow['jenis']==='SETOR_PAJAK')$totTaxDeposit+=(float)$checkedRow['keluar'];
        else $totOther+=(float)$checkedRow['keluar'];
       }
       $_SESSION['spj_bku_preview']=['year'=>$year,'source'=>$source,'checksum'=>$checksum,'filename'=>basename((string)$_FILES['file_bku']['name']),'rows'=>$rows,'total_spend'=>$totSpend,'duplicate_count'=>$duplicateCount,'tax_receipt'=>$totTaxReceipt,'tax_deposit'=>$totTaxDeposit,'other_out'=>$totOther,'balance_warnings'=>$balanceWarnings,'balance_checks'=>$balanceChecks,'balance_available'=>isset($col['saldo'])];
      }
     }
    }
   }
  }elseif($action==='commit'){
   $p=$_SESSION['spj_bku_preview'];
   if(!is_array($p)||empty($p['rows']))$errors[]='Pratinjau BKU tidak tersedia. Unggah dan pratinjau ulang.';
   elseif(($_POST['confirm']??'')!=='yes')$errors[]='Centang konfirmasi sebelum menyimpan.';
   else{
    try{
     $pdo->beginTransaction();$exists=$pdo->prepare("SELECT id FROM spj_transaksi WHERE sumber_dana=? AND tanggal=? AND nomor_bukti=? AND nilai_bruto=? AND uraian=? LIMIT 1 FOR UPDATE");
     $ins=$pdo->prepare("INSERT INTO spj_transaksi(sumber_dana,tanggal,nomor_bukti,kegiatan,uraian,penyedia,nilai_bruto,pajak,bukti_file,created_by) VALUES(?,?,?,?,?,NULL,?,0,NULL,?)");
     $added=0;$skipped=0;$ignored=0;$actor=isset($_SESSION['akun_id'])?(int)$_SESSION['akun_id']:null;
     foreach($p['rows'] as $row){
      if($row['jenis']!=='PENGELUARAN'||(float)$row['keluar']<=0){$ignored++;continue;}
      if($row['duplikat']){$skipped++;continue;}
      $exists->execute([$p['source'],$row['tanggal'],$row['nomor'],$row['keluar'],$row['uraian']]);
      if($exists->fetchColumn()){$skipped++;continue;}
      $ins->execute([$p['source'],$row['tanggal'],$row['nomor'],$row['kegiatan'],$row['uraian'],(float)$row['keluar'],$actor]);$added++;
     }
     $pdo->commit();$_SESSION['spj_bku_preview']=null;$ok='Impor BKU selesai: '.$added.' pengeluaran masuk, '.$skipped.' duplikat dilewati, '.$ignored.' baris penerimaan/pajak/saldo atau non-pengeluaran tidak dimasukkan.';
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$errors[]='Impor gagal. Transaksi tidak disimpan sebagian.';}
   }
  }else$errors[]='Aksi tidak dikenal.';
 }
}
$preview=$_SESSION['spj_bku_preview'];
require __DIR__.'/../../includes/header.php';
?>
<style>
.bku-wrap{max-width:1180px;margin:auto}.bku-panel{background:var(--card,#fff);border:1px solid var(--border,#e5e7eb);border-radius:14px;padding:18px;margin:0 0 16px}.bku-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.bku-field label{display:block;font-size:13px;margin-bottom:6px;color:var(--text-muted,#64748b)}.bku-field input,.bku-field select{width:100%;padding:10px;border:1px solid var(--border,#d1d5db);border-radius:8px;background:var(--card,#fff);color:inherit}.bku-btn{display:inline-block;border:0;border-radius:8px;padding:10px 14px;background:var(--primary,#2563eb);color:#fff;cursor:pointer}.bku-table-wrap{overflow:auto}.bku-table{width:100%;border-collapse:collapse;min-width:900px}.bku-table th,.bku-table td{padding:9px;border-bottom:1px solid var(--border,#e5e7eb);font-size:12px;text-align:left}.bku-table th{background:var(--surface,#f8fafc)}.bku-alert,.bku-ok{padding:12px;border-radius:9px;margin:10px 0}.bku-alert{background:#fef2f2;color:#991b1b}.bku-ok{background:#ecfdf5;color:#065f46}.bku-muted{font-size:13px;color:var(--text-muted,#64748b)}.bku-stats{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:10px}.bku-stat{padding:12px;border:1px solid var(--border,#e5e7eb);border-radius:10px}.bku-stat strong{display:block;margin-top:6px}@media(max-width:700px){.bku-grid,.bku-stats{grid-template-columns:1fr}.bku-panel{padding:12px}}
</style>
<div class="main-content"><div class="bku-wrap">
<div class="page-header"><div><h1>Impor BKU</h1><p class="bku-muted">Pratinjau CSV, deteksi duplikat, dan pisahkan penerimaan/setoran pajak dari belanja.</p></div></div>
<?php foreach($errors as $e): ?><div class="bku-alert"><?= h($e) ?></div><?php endforeach; ?><?php if($ok): ?><div class="bku-ok"><?= h($ok) ?></div><?php endif; ?>
<div class="bku-panel"><h2 style="margin-top:0">1. Unggah BKU CSV</h2><p class="bku-muted">Ekspor/simpan BKU sebagai CSV UTF-8. Kolom wajib: tanggal, uraian/keterangan, pengeluaran. Opsional: nomor_bukti, kegiatan, penerimaan, saldo. Maksimal 2 MB dan 5.000 baris. PDF belum didukung langsung.</p>
<form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?= h($_SESSION['spj_csrf']) ?>"><input type="hidden" name="action" value="preview"><div class="bku-grid"><div class="bku-field"><label>Tahun anggaran</label><input type="number" name="tahun" min="2000" max="2100" value="<?= h($preview['year']??date('Y')) ?>" required></div><div class="bku-field"><label>Sumber dana</label><select name="sumber"><option value="BOSP" <?= (($preview['source']??'BOSP')==='BOSP')?'selected':'' ?>>BOSP Reguler</option><option value="BOS_KINERJA" <?= (($preview['source']??'')==='BOS_KINERJA')?'selected':'' ?>>BOS Kinerja</option></select></div><div class="bku-field"><label>File CSV BKU</label><input type="file" name="file_bku" accept=".csv,text/csv" required></div></div><p><button class="bku-btn" type="submit">Baca &amp; pratinjau BKU</button></p></form></div>
<?php if(is_array($preview)&&!empty($preview['rows'])): ?>
<div class="bku-panel"><h2 style="margin-top:0">2. Periksa sebelum simpan</h2><p><strong><?= h($preview['filename']) ?></strong> · <?= count($preview['rows']) ?> baris · <?= h($preview['source']==='BOSP'?'BOSP Reguler':'BOS Kinerja') ?> · Tahun <?= h($preview['year']) ?></p>
<div class="bku-stats"><div class="bku-stat">Pengeluaran akan diimpor<strong>Rp <?= number_format((float)$preview['total_spend'],0,',','.') ?></strong></div><div class="bku-stat">Baris duplikat dilewati<strong><?= (int)($preview['duplicate_count']??0) ?></strong></div><div class="bku-stat">Penerimaan pajak (kolom masuk)<strong>Rp <?= number_format((float)$preview['tax_receipt'],0,',','.') ?></strong></div><div class="bku-stat">Setoran pajak (kolom keluar)<strong>Rp <?= number_format((float)$preview['tax_deposit'],0,',','.') ?></strong></div><div class="bku-stat">Pengeluaran lain diabaikan<strong>Rp <?= number_format((float)$preview['other_out'],0,',','.') ?></strong></div></div>
<?php if(empty($preview['balance_available'])): ?><div class="bku-alert" style="margin-top:12px">Rekonsiliasi saldo belum dapat dilakukan karena CSV tidak memiliki kolom saldo.</div><?php elseif((int)($preview['balance_checks']??0)===0): ?><div class="bku-alert" style="margin-top:12px">Kolom saldo tersedia, tetapi tidak ada dua saldo berurutan yang dapat dibandingkan. Rekonsiliasi belum dapat disimpulkan.</div><?php elseif(!empty($preview['balance_warnings'])): ?><div class="bku-alert" style="margin-top:12px">Rekonsiliasi saldo menemukan <?= (int)$preview['balance_warnings'] ?> baris dengan selisih saldo lebih dari Rp 0,01 dari <?= (int)$preview['balance_checks'] ?> perbandingan. Periksa saldo BKU asli sebelum menyimpan.</div><?php else: ?><p class="bku-muted">Tidak ditemukan selisih saldo lebih dari Rp 0,01 dari <?= (int)$preview['balance_checks'] ?> perbandingan. Tetap cocokkan dengan BKU asli.</p><?php endif; ?>
<div class="bku-table-wrap" style="margin-top:12px"><table class="bku-table"><thead><tr><th>Tanggal</th><th>No. bukti</th><th>Uraian</th><th>Kategori otomatis</th><th style="text-align:right">Masuk</th><th style="text-align:right">Keluar</th><th style="text-align:right">Saldo</th><th style="text-align:right">Selisih saldo</th><th>Duplikat</th></tr></thead><tbody>
<?php foreach(array_slice($preview['rows'],0,150) as $r): ?><tr><td><?= h($r['tanggal']) ?></td><td><?= h($r['nomor']?:'-') ?></td><td><?= h($r['uraian']) ?></td><td><?= h(str_replace('_',' ',$r['jenis'])) ?></td><td style="text-align:right;white-space:nowrap">Rp <?= number_format((float)$r['masuk'],0,',','.') ?></td><td style="text-align:right;white-space:nowrap">Rp <?= number_format((float)$r['keluar'],0,',','.') ?></td><td style="text-align:right;white-space:nowrap"><?= $r['saldo']===null?'—':'Rp '.number_format((float)$r['saldo'],0,',','.') ?></td><td style="text-align:right;white-space:nowrap"><?= $r['selisih_saldo']===null?'—':(($r['selisih_saldo']>0?'+':''). 'Rp '.number_format((float)$r['selisih_saldo'],0,',','.')) ?></td><td><?= !empty($r['duplikat'])?'Sudah ada':'-' ?></td></tr><?php endforeach; ?>
</tbody></table></div><?php if(count($preview['rows'])>150): ?><p class="bku-muted">Menampilkan 150 baris pertama. Semua baris sudah diproses dalam pratinjau.</p><?php endif; ?>
<p class="bku-muted">Hanya baris yang diklasifikasikan sebagai pengeluaran akan masuk. Penerimaan pajak dihitung dari kolom masuk; setoran pajak dihitung dari kolom keluar. Keduanya ditampilkan pada pratinjau tetapi tidak dimasukkan sebagai belanja. Saldo, penerimaan biasa, dan baris duplikat juga dilewati. Nilai pajak transaksi diimpor sebagai 0 karena pemisahan bruto/pajak perlu rekonsiliasi terpisah. Periksa klasifikasi otomatis sebelum konfirmasi.</p>
<form method="post"><input type="hidden" name="csrf" value="<?= h($_SESSION['spj_csrf']) ?>"><input type="hidden" name="action" value="commit"><label style="display:block;margin:12px 0"><input type="checkbox" name="confirm" value="yes" required> Saya sudah memeriksa tanggal, uraian, nominal, klasifikasi, dan duplikat.</label><button class="bku-btn" type="submit">Konfirmasi &amp; impor pengeluaran</button></form>
<form method="post" style="margin-top:8px"><input type="hidden" name="csrf" value="<?= h($_SESSION['spj_csrf']) ?>"><input type="hidden" name="action" value="clear"><button class="bku-btn" type="submit" style="background:#64748b">Batalkan pratinjau</button></form></div><?php endif; ?>
<div class="bku-panel"><p class="bku-muted">Penting: ini tahap awal CSV, bukan pembaca PDF langsung. Pencocokan otomatis ke kode rekening RKAS dan rekonsiliasi pajak belum dilakukan. Pastikan hasil impor cocok dengan BKU asli sebelum dipakai untuk laporan resmi.</p></div>
</div></div><?php require __DIR__.'/../../includes/footer.php'; ?>
