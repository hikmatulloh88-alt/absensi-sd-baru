<?php
declare(strict_types=1);

if (!function_exists('si_parse_amount')) {
function si_parse_amount(string $raw): ?string {
 $raw=trim($raw);
 if($raw==='')return '';
 $raw=preg_replace('/^Rp\\s*/i','',$raw);
 if($raw===''||preg_match('/^[0-9]{1,15}$/',$raw)===1)return $raw;
 if(preg_match('/^[0-9]{1,3}(?:\\.[0-9]{3})+$/',$raw)===1||preg_match('/^[0-9]{1,3}(?:,[0-9]{3})+$/',$raw)===1){
  $digits=str_replace(['.',','],'',$raw);
  return strlen($digits)<=15?$digits:null;
 }
 return null;
}
}


if (!function_exists('si_total_supported')) {
/**
 * SI menyebut total dalam kata-kata. Batasi ke 15 digit agar total
 * tidak jatuh ke fallback angka mentah pada si_terbilang().
 */
function si_total_supported(array $recipients): bool {
 $total = 0;
 foreach ($recipients as $recipient) {
  $amount = (string)($recipient['amount'] ?? '');
  if (preg_match('/^[0-9]{1,15}$/', $amount) !== 1) return false;
  $total += (int)$amount;
  if ($total > 999999999999999) return false;
 }
 return true;
}
}

if (!function_exists('si_terbilang')) {
function si_terbilang(int $n): string {
 if($n===0)return 'nol';
 if($n<0)return 'minus '.si_terbilang(abs($n));
 $w=['','satu','dua','tiga','empat','lima','enam','tujuh','delapan','sembilan','sepuluh','sebelas'];
 if($n<12)return $w[$n];if($n<20)return si_terbilang($n-10).' belas';if($n<100)return si_terbilang(intdiv($n,10)).' puluh'.($n%10?' '.si_terbilang($n%10):'');
 if($n<200)return 'seratus'.($n>100?' '.si_terbilang($n-100):'');if($n<1000)return si_terbilang(intdiv($n,100)).' ratus'.($n%100?' '.si_terbilang($n%100):'');
 if($n<2000)return 'seribu'.($n>1000?' '.si_terbilang($n-1000):'');if($n<1000000)return si_terbilang(intdiv($n,1000)).' ribu'.($n%1000?' '.si_terbilang($n%1000):'');
 if($n<1000000000)return si_terbilang(intdiv($n,1000000)).' juta'.($n%1000000?' '.si_terbilang($n%1000000):'');
 if($n<1000000000000)return si_terbilang(intdiv($n,1000000000)).' miliar'.($n%1000000000?' '.si_terbilang($n%1000000000):'');
 if($n<1000000000000000)return si_terbilang(intdiv($n,1000000000000)).' triliun'.($n%1000000000000?' '.si_terbilang($n%1000000000000):'');
 return (string)$n;
}
}
