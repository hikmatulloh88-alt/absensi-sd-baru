<?php
declare(strict_types=1);

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
