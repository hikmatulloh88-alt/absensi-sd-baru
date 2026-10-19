<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
$pageTitle = 'Laporan Absensi';
if (!$pdo) {
    require __DIR__ . '/../../includes/header.php';
    echo '<div class="notice warning">Database belum tersambung. Periksa config/database.php.</div>';
    require __DIR__ . '/../../includes/footer.php';
    exit;
}
$jenis = (($_GET['jenis'] ?? 'siswa') === 'guru') ? 'guru' : 'siswa';
$mode = (($_GET['mode'] ?? 'periode') === 'bulanan') ? 'bulanan' : 'periode';
$bulan = $_GET['bulan'] ?? date('Y-m');
if (!preg_match('/^\\d{4}-\\d{2}$/', $bulan)) $bulan = date('Y-m');
$start = $_GET['awal'] ?? date('Y-m-01');
$end = $_GET['akhir'] ?? date('Y-m-d');
if ($mode === 'bulanan') { $start = $bulan . '-01'; $end = date('Y-m-t', strtotime($start)); }
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) $start = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) $end = date('Y-m-d');
if ($start > $end) { [$start,$end] = [$end,$start]; }
$class = trim((string)($_GET['kelas'] ?? ''));
$statusFilter = strtolower(trim((string)($_GET['status'] ?? '')));
$allowedStatuses = ['hadir','izin','sakit','alpa','terlambat'];
if (!in_array($statusFilter, $allowedStatuses, true)) $statusFilter = '';
$search = trim((string)($_GET['cari'] ?? ''));
$personTable = $jenis === 'guru' ? 'guru' : 'siswa';
$classOptions = $jenis === 'siswa' ? $pdo->query("SELECT DISTINCT rombel FROM siswa WHERE status='aktif' AND COALESCE(rombel,'')<>'' ORDER BY rombel")->fetchAll(PDO::FETCH_COLUMN) : [];
$where = ["p.status='aktif'"];
$params = [];
if ($jenis === 'siswa' && $class !== '') { $where[] = 'p.rombel=?'; $params[] = $class; }
if ($search !== '') { $where[] = '(p.nama LIKE ? OR '.($jenis==='guru' ? 'p.nip' : 'p.nisn').' LIKE ? OR '.($jenis==='siswa' ? 'p.nis LIKE ?' : 'p.nip LIKE ?').')'; $params[]='%'.$search.'%'; $params[]='%'.$search.'%'; $params[]='%'.$search.'%'; }
$personWhere = implode(' AND ', $where);
$identity = $jenis === 'guru' ? "COALESCE(p.nip,'')" : "COALESCE(NULLIF(p.nisn,''),p.nis,'')";
$rombelExpr = $jenis === 'guru' ? "COALESCE(p.jabatan,'-')" : "COALESCE(p.rombel,'-')";
$sql = "SELECT p.id,p.nama,{$identity} AS identitas,{$rombelExpr} AS kelompok,
 SUM(CASE WHEN a.status='hadir' THEN 1 ELSE 0 END) AS hadir,
 SUM(CASE WHEN a.status='izin' THEN 1 ELSE 0 END) AS izin,
 SUM(CASE WHEN a.status='sakit' THEN 1 ELSE 0 END) AS sakit,
 SUM(CASE WHEN a.status='alpa' THEN 1 ELSE 0 END) AS alpa,
 SUM(CASE WHEN a.status='terlambat' THEN 1 ELSE 0 END) AS terlambat
 FROM {$personTable} p LEFT JOIN absensi a ON a.referensi_id=p.id AND a.jenis=? AND a.tanggal BETWEEN ? AND ?
 WHERE {$personWhere} GROUP BY p.id,p.nama,identitas,kelompok ORDER BY ".($jenis==='siswa' ? 'kelompok,' : '')."p.nama";
$stmt = $pdo->prepare($sql);
$stmt->execute(array_merge([$jenis,$start,$end],$params));
$people = $stmt->fetchAll();
if ($statusFilter !== '') {
    $people = array_values(array_filter($people, static fn($r) => (int)$r[$statusFilter] > 0));
}
$summary = ['hadir'=>0,'izin'=>0,'sakit'=>0,'alpa'=>0,'terlambat'=>0];
foreach ($people as $person) foreach ($summary as $key=>$unused) $summary[$key] += (int)$person[$key];
$totalPeople = count($people);
$recorded = array_sum($summary);
$presentRate = $recorded ? round(($summary['hadir']+$summary['terlambat'])/$recorded*100,1) : 0;
$classSummary = [];
foreach ($people as $person) {
    $key = $person['kelompok'] ?: '-';
    if (!isset($classSummary[$key])) $classSummary[$key] = ['hadir'=>0,'izin'=>0,'sakit'=>0,'alpa'=>0,'terlambat'=>0,'orang'=>0];
    $classSummary[$key]['orang']++;
    foreach ($summary as $status=>$unused) $classSummary[$key][$status] += (int)$person[$status];
}
$daily = [];
try {
    $dailyQ = $pdo->prepare("SELECT a.tanggal, SUM(a.status='hadir') hadir, SUM(a.status='izin') izin, SUM(a.status='sakit') sakit, SUM(a.status='alpa') alpa, SUM(a.status='terlambat') terlambat FROM absensi a JOIN {$personTable} p ON p.id=a.referensi_id AND p.status='aktif' WHERE a.jenis=? AND a.tanggal BETWEEN ? AND ?".($jenis==='siswa' && $class!=='' ? " AND p.rombel=?" : "")." GROUP BY a.tanggal ORDER BY a.tanggal");
    $dailyQ->execute($jenis==='siswa' && $class!=='' ? [$jenis,$start,$end,$class] : [$jenis,$start,$end]);
    $daily = $dailyQ->fetchAll();
} catch (Throwable $e) { $daily = []; }
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="laporan-absensi-'.$jenis.'-'.$start.'-'.$end.'.csv"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output','w');
    fputcsv($out, ['No','Nama',$jenis==='guru'?'NIP':'NISN/NIS',$jenis==='guru'?'Jabatan':'Kelas','Hadir','Izin','Sakit','Alpa','Terlambat','Persentase Hadir']);
    foreach ($people as $i=>$p) {
        $n = array_sum(array_map(static fn($k)=>(int)$p[$k], ['hadir','izin','sakit','alpa','terlambat']));
        fputcsv($out, [$i+1,$p['nama'],$p['identitas'],$p['kelompok'],$p['hadir'],$p['izin'],$p['sakit'],$p['alpa'],$p['terlambat'],$n ? round(($p['hadir']+$p['terlambat'])/$n*100,1).'%' : '0%']);
    }
    fclose($out); exit;
}
function reportDate(string $d): string { return date('d/m/Y', strtotime($d)); }
$queryBase = ['jenis'=>$jenis,'mode'=>$mode,'bulan'=>$bulan,'awal'=>$start,'akhir'=>$end,'kelas'=>$class,'status'=>$statusFilter,'cari'=>$search];
$csvUrl = '?' . http_build_query($queryBase + ['export'=>'csv']);
$maxDaily = 1;
foreach ($daily as $d) $maxDaily = max($maxDaily, (int)$d['hadir']+(int)$d['izin']+(int)$d['sakit']+(int)$d['alpa']+(int)$d['terlambat']);
$colors = ['hadir'=>'#20a66a','izin'=>'#4388f5','sakit'=>'#f5b82e','alpa'=>'#f05260','terlambat'=>'#8b70db'];
require __DIR__ . '/../../includes/header.php';
?>
<style>
.report-head{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;margin-bottom:22px}.report-title{display:flex;align-items:center;gap:14px}.report-symbol{width:54px;height:54px;border-radius:15px;background:#e5efff;color:#1764db;display:grid;place-items:center;font-size:27px}.report-head h2{font:800 25px Manrope;margin:0 0 6px;color:#142448}.report-head p{margin:0;color:#8090a8}.report-actions{display:flex;gap:8px;flex-wrap:wrap}.report-btn{border:0;border-radius:9px;padding:11px 14px;color:white;font-weight:700;font-size:12px;cursor:pointer;display:inline-flex;align-items:center;gap:7px}.report-btn.blue{background:#1764df}.report-btn.green{background:#16a267}.report-btn.red{background:#e94855}.report-btn.gray{background:#64748b}.report-stats{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px;margin-bottom:16px}.report-stat{padding:17px;border:1px solid #e7edf5;border-radius:12px;display:flex;gap:12px;align-items:center;background:#fff;min-width:0}.report-stat .stat-icon{margin:0;flex:0 0 48px;width:48px;height:48px;border-radius:12px;display:grid;place-items:center;font-size:23px}.report-stat strong{display:block;font:800 25px Manrope;color:#152644;line-height:1.1}.report-stat small{display:block;color:#708099;font-size:11px;margin-top:6px}.report-panel{background:#fff;border:1px solid #e6edf5;border-radius:13px;padding:17px;margin-bottom:15px;box-shadow:0 5px 18px #1f365808}.report-panel h3{font:800 15px Manrope;margin:0 0 15px;color:#17294b}.report-filters{display:grid;grid-template-columns:1fr 1fr 1fr 1fr 1.4fr auto;gap:12px;align-items:end}.report-month-note{display:inline-flex;align-items:center;gap:7px;padding:8px 11px;background:#eaf2ff;color:#245bb4;border-radius:8px;font-size:12px;font-weight:700;margin-bottom:14px}.report-filters label{display:block;font-size:11px;color:#40516d;font-weight:700;margin-bottom:7px}.report-filters input,.report-filters select{width:100%;height:40px;border:1px solid #dbe3ee;border-radius:8px;padding:0 10px;color:#283953;background:white;font:inherit;min-width:0}.report-layout{display:grid;grid-template-columns:1fr 1.25fr .9fr;gap:13px;margin-bottom:15px}.report-chart{background:#fff;border:1px solid #e6edf5;border-radius:13px;padding:17px;min-width:0}.report-chart h3{font:800 14px Manrope;margin:0 0 15px}.donut-row{display:flex;align-items:center;gap:17px}.donut{width:145px;height:145px;border-radius:50%;background:conic-gradient(#20a66a 0 70%,#4388f5 70% 82%,#f5b82e 82% 89%,#f05260 89% 95%,#8b70db 95% 100%);position:relative;flex:0 0 auto}.donut:after{content:'';position:absolute;inset:28px;border-radius:50%;background:#fff}.donut-center{position:absolute;inset:0;z-index:1;display:flex;flex-direction:column;justify-content:center;align-items:center;font:800 24px Manrope;color:#142448}.donut-center small{font:500 11px 'DM Sans';color:#75839a}.legend{display:grid;gap:9px;font-size:12px}.legend div{display:flex;align-items:center;gap:7px;white-space:nowrap}.legend i{width:10px;height:10px;border-radius:3px;display:inline-block}.daily-chart{display:flex;align-items:stretch;gap:5px;height:150px;padding-top:10px;border-bottom:1px solid #e2e9f2;overflow:hidden}.daily-col{display:flex;flex:1;min-width:4px;flex-direction:column;justify-content:flex-end;gap:1px;align-items:stretch}.daily-col i{display:block;min-height:1px;border-radius:2px 2px 0 0}.daily-labels{display:flex;justify-content:space-between;color:#8290a5;font-size:9px;margin-top:7px}.class-bars{display:grid;gap:13px}.class-row{display:grid;grid-template-columns:55px 1fr 28px;align-items:center;gap:8px;font-size:11px}.class-bar{height:13px;background:#edf1f7;border-radius:3px;overflow:hidden;display:flex}.class-bar i{height:100%;display:block}.class-total{text-align:right;font-weight:700}.report-table-wrap{overflow:auto}.report-table{border-collapse:separate;border-spacing:0;width:100%;font-size:12px;white-space:nowrap}.report-table th{background:#f3f6fa;color:#33445e;font-size:11px;text-align:left;padding:12px 10px;border-bottom:1px solid #e2e8f0}.report-table td{padding:11px 10px;border-bottom:1px solid #edf1f6}.report-table tbody tr:hover{background:#f8fbff}.num-center{text-align:center}.status-hadir{color:#14975f;font-weight:800}.status-izin{color:#2672dd;font-weight:800}.status-sakit{color:#cb8b05;font-weight:800}.status-alpa{color:#e44453;font-weight:800}.status-terlambat{color:#7657d5;font-weight:800}.rate-pill{background:#e9f8f0;color:#168451;border-radius:20px;padding:4px 8px;font-weight:800}.detail-link{color:#1764df;font-weight:700}.report-note{font-size:11px;color:#7c8aa0;margin-top:10px}.report-empty{padding:25px;text-align:center;color:#7d8ba0}.report-pagination{font-size:12px;color:#7d8ba0;padding-top:12px}
@media(max-width:1250px){.report-stats{grid-template-columns:repeat(3,minmax(0,1fr))}.report-layout{grid-template-columns:1fr 1fr}.report-chart:last-child{grid-column:1/-1}.report-filters{grid-template-columns:repeat(3,minmax(0,1fr))}}
@media(max-width:760px){.report-head{display:block}.report-actions{margin-top:16px}.report-stats{grid-template-columns:repeat(2,minmax(0,1fr))}.report-layout{grid-template-columns:1fr}.report-chart:last-child{grid-column:auto}.report-filters{grid-template-columns:1fr 1fr}.report-stat{padding:12px}.report-stat strong{font-size:21px}.donut-row{flex-wrap:wrap}.donut{width:125px;height:125px}}
@media print{@page{size:A4 landscape;margin:10mm}.sidebar,.topbar,.no-print,.report-filters,.report-actions{display:none!important}.main-area{margin:0!important;width:100%!important}.page-content{padding:0!important}.report-panel,.report-chart,.report-stat{box-shadow:none;break-inside:avoid}.report-stats{grid-template-columns:repeat(5,1fr)}.report-layout{grid-template-columns:1fr 1.2fr .9fr}.report-table{font-size:9px}.report-table th,.report-table td{padding:6px}}
</style>
<section class="report-head">
 <div class="report-title"><div class="report-symbol">▤</div><div><h2>Laporan Absensi <?= $jenis==='guru'?'Guru':'Siswa' ?></h2><p>Rekap kehadiran berdasarkan periode, kelas, dan status.</p></div></div>
 <div class="report-actions no-print"><button type="button" class="report-btn green" onclick="window.print()">▣ Cetak / PDF</button><a class="report-btn blue" href="<?=h($csvUrl)?>">▤ Export Excel (CSV)</a></div>
</section>
<div class="report-stats">
 <?php $statItems=[['hadir','Hadir','✓','#e2f7eb','#12945a'],['izin','Izin','▤','#e4f1ff','#2779dc'],['sakit','Sakit','✚','#fff3cf','#bb8409'],['alpa','Alfa','×','#ffe6e8','#df3f4e'],['total','Total '.($jenis==='guru'?'Guru':'Siswa'),$totalPeople,'#e7edff','#3168df']]; foreach($statItems as $item): $val=$item[0]==='total'?$totalPeople:$summary[$item[0]]; ?>
 <article class="report-stat"><div class="stat-icon" style="background:<?=h($item[3])?>;color:<?=h($item[4])?>"><?=h($item[2])?></div><div><small><?=h($item[1])?></small><strong><?=number_format((int)$val)?></strong><small><?= $item[0]==='total'?'Data aktif dalam filter':($recorded?number_format($val/$recorded*100,1,',','.').'% dari catatan':'Belum ada catatan') ?></small></div></article>
 <?php endforeach;?>
</div>
<section class="report-panel no-print">
 <h3>▦ Filter Laporan</h3>
 <form method="get" class="report-filters">
  <div><label for="jenis">Jenis Laporan</label><select name="jenis" id="jenis"><option value="siswa" <?=$jenis==='siswa'?'selected':''?>>Siswa</option><option value="guru" <?=$jenis==='guru'?'selected':''?>>Guru</option></select></div>
  <div><label for="mode">Periode Laporan</label><select name="mode" id="mode" onchange="document.getElementById('monthField').style.display=this.value==='bulanan'?'block':'none';document.getElementById('dateFields').style.display=this.value==='bulanan'?'none':'contents'"><option value="periode" <?=$mode==='periode'?'selected':''?>>Rentang tanggal</option><option value="bulanan" <?=$mode==='bulanan'?'selected':''?>>Rekap bulanan</option></select></div>
  <div id="monthField" style="<?=$mode==='bulanan'?'':'display:none'?>"><label for="bulan">Pilih Bulan</label><input type="month" id="bulan" name="bulan" value="<?=h($bulan)?>"></div>
  <div id="dateFields" style="display:<?=$mode==='bulanan'?'none':'contents'?>"><div><label for="awal">Tanggal Awal</label><input type="date" id="awal" name="awal" value="<?=h($start)?>"></div><div><label for="akhir">Tanggal Akhir</label><input type="date" id="akhir" name="akhir" value="<?=h($end)?>"></div></div>
  <div><label for="kelas">Kelas / Rombel</label><select name="kelas" id="kelas" <?=$jenis==='guru'?'disabled':''?>><option value="">Semua Kelas</option><?php foreach($classOptions as $option):?><option value="<?=h($option)?>" <?=$class===$option?'selected':''?>><?=h($option)?></option><?php endforeach;?></select></div>
  <div><label for="status">Status Kehadiran</label><select name="status" id="status"><option value="">Semua Status</option><?php foreach($allowedStatuses as $s):?><option value="<?=h($s)?>" <?=$statusFilter===$s?'selected':''?>><?=h(ucfirst($s))?></option><?php endforeach;?></select></div>
  <div><label for="cari">Cari Nama / NISN</label><input type="search" id="cari" name="cari" value="<?=h($search)?>" placeholder="Nama atau nomor identitas"></div>
  <div><button class="report-btn blue" type="submit">⌕ Tampilkan</button></div>
 </form>
 <div class="report-note">Periode <?=h(reportDate($start))?> – <?=h(reportDate($end))?>. Persentase dihitung dari catatan kehadiran yang tersimpan, bukan dari hari sekolah.</div>
</section>
<?php if ($mode === 'bulanan'): ?><div class="report-month-note">▦ Rekap bulanan: <?=h(date('F Y', strtotime($start)))?> · data diringkas untuk satu bulan penuh.</div><?php endif; ?>
<div class="report-layout">
 <section class="report-chart"><h3>▦ Rekap Kehadiran</h3><div class="donut-row"><div class="donut" style="background:conic-gradient(<?=$colors['hadir']?> 0 <?= $recorded?(($summary['hadir']+$summary['terlambat'])/$recorded*100):0 ?>%,<?=$colors['izin']?> <?= $recorded?(($summary['hadir']+$summary['terlambat'])/$recorded*100):0 ?>% <?= $recorded?(($summary['hadir']+$summary['terlambat']+$summary['izin'])/$recorded*100):0 ?>%,<?=$colors['sakit']?> <?= $recorded?(($summary['hadir']+$summary['terlambat']+$summary['izin'])/$recorded*100):0 ?>% <?= $recorded?(($summary['hadir']+$summary['terlambat']+$summary['izin']+$summary['sakit'])/$recorded*100):0 ?>%,<?=$colors['alpa']?> <?= $recorded?(($summary['hadir']+$summary['terlambat']+$summary['izin']+$summary['sakit'])/$recorded*100):0 ?>% 100%)"><div class="donut-center"><?=number_format($recorded)?><small>Catatan</small></div></div><div class="legend"><?php foreach(['hadir'=>'Hadir','izin'=>'Izin','sakit'=>'Sakit','alpa'=>'Alpa','terlambat'=>'Terlambat'] as $k=>$label):?><div><i style="background:<?=$colors[$k]?>"></i><?=h($label)?> <strong><?=number_format($summary[$k])?></strong></div><?php endforeach;?></div></div></section>
 <section class="report-chart"><h3>▦ Grafik Kehadiran Harian</h3><div class="daily-chart"><?php if(!$daily):?><div class="report-empty">Belum ada catatan pada periode ini.</div><?php else: foreach($daily as $d): $vals=[];foreach($colors as $k=>$color)$vals[$k]=(int)$d[$k];$sum=array_sum($vals);?><div class="daily-col" title="<?=h(reportDate($d['tanggal']))?> · <?=$sum?> catatan"><?php foreach($colors as $k=>$color):?><i style="height:<?=max(0,round($vals[$k]/$maxDaily*125))?>px;background:<?=$color?>"></i><?php endforeach;?></div><?php endforeach;endif;?></div><div class="daily-labels"><span><?=h($daily?reportDate($daily[0]['tanggal']):'')?></span><span><?=h($daily?reportDate($daily[count($daily)-1]['tanggal']):'')?></span></div></section>
 <section class="report-chart"><h3>▦ Rekap per <?= $jenis==='guru'?'Jabatan':'Kelas' ?></h3><div class="class-bars"><?php if(!$classSummary):?><div class="report-empty">Belum ada data.</div><?php else: $maxClass=1;foreach($classSummary as $cs)$maxClass=max($maxClass,$cs['hadir']+$cs['izin']+$cs['sakit']+$cs['alpa']+$cs['terlambat']);foreach($classSummary as $name=>$cs):?><div class="class-row"><span><?=h($name)?></span><div class="class-bar"><?php foreach($colors as $k=>$color):$v=$cs[$k];if($v):?><i style="width:<?=($v/$maxClass*100)?>%;background:<?=$color?>"></i><?php endif;endforeach;?></div><span class="class-total"><?=$cs['orang']?></span></div><?php endforeach;endif;?></div></section>
</div>
<div class="report-actions no-print" style="margin:0 0 15px"><button type="button" class="report-btn green" onclick="window.print()">▣ Cetak PDF</button><a class="report-btn blue" href="<?=h($csvUrl)?>">▤ Export CSV</a></div>
<section class="report-panel"><h3>▦ Data Kehadiran <?= $jenis==='guru'?'Guru':'Siswa' ?></h3><div class="report-table-wrap"><table class="report-table"><thead><tr><th>No</th><th>Nama</th><th><?= $jenis==='guru'?'NIP':'NISN / NIS' ?></th><th><?= $jenis==='guru'?'Jabatan':'Kelas' ?></th><th>Hadir</th><th>Izin</th><th>Sakit</th><th>Alpa</th><th>Terlambat</th><th>% Hadir</th></tr></thead><tbody>
<?php if(!$people):?><tr><td colspan="10" class="report-empty">Tidak ada data sesuai filter.</td></tr><?php else:foreach($people as $i=>$p):$n=array_sum(array_map(static fn($k)=>(int)$p[$k],['hadir','izin','sakit','alpa','terlambat']));$rate=$n?round(((int)$p['hadir']+(int)$p['terlambat'])/$n*100,1):0;?><tr><td><?= $i+1 ?></td><td><strong><?=h($p['nama'])?></strong></td><td><?=h($p['identitas']?:'-')?></td><td><?=h($p['kelompok'])?></td><td class="num-center status-hadir"><?=$p['hadir']?></td><td class="num-center status-izin"><?=$p['izin']?></td><td class="num-center status-sakit"><?=$p['sakit']?></td><td class="num-center status-alpa"><?=$p['alpa']?></td><td class="num-center status-terlambat"><?=$p['terlambat']?></td><td><span class="rate-pill"><?=number_format($rate,1,',','.')?>%</span></td></tr><?php endforeach;endif;?>
</tbody></table></div><div class="report-pagination">Menampilkan <?=count($people)?> data · Periode <?=h(reportDate($start))?> sampai <?=h(reportDate($end))?></div></section>
