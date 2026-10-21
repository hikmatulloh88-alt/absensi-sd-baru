<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
if (($_SESSION['role'] ?? '') !== 'admin') { http_response_code(403); exit('Akses bukti hanya untuk admin.'); }
$id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT);
if(!$id || $id<1){http_response_code(400);exit('ID bukti tidak valid.');}
$source=basename(__DIR__);
$allowedSources=['spj_bosp'=>'BOSP','spj_bos_kinerja'=>'BOS_KINERJA'];
if(!isset($allowedSources[$source])){http_response_code(404);exit('Bukti tidak ditemukan.');}
try {
 $q=$pdo->prepare('SELECT bukti_file FROM spj_transaksi WHERE id=? AND sumber_dana=? LIMIT 1');
 $q->execute([$id,$allowedSources[$source]]);
 $stored=(string)($q->fetchColumn() ?: '');
} catch(Throwable $e) {$stored='';}
$name=basename($stored);
if($stored==='' || ($stored!==$name && $stored!=='bukti/'.$name)){http_response_code(404);exit('Bukti tidak ditemukan.');}
$path=__DIR__.'/bukti/'.$name;
if(!is_file($path)){http_response_code(404);exit('File bukti tidak ditemukan.');}
$fi=new finfo(FILEINFO_MIME_TYPE);$mime=$fi->file($path) ?: 'application/octet-stream';
$types=['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png'];
if(!isset($types[$mime]) || strtolower(pathinfo($name,PATHINFO_EXTENSION))!==$types[$mime]){http_response_code(415);exit('Format bukti tidak diizinkan.');}
header('X-Content-Type-Options: nosniff');
header('Content-Type: '.$mime);
header('Content-Disposition: attachment; filename="bukti-'.$id.'.'.$types[$mime].'"');
header('Content-Length: '.filesize($path));
header('Cache-Control: private, no-store, max-age=0');
readfile($path);
exit;
