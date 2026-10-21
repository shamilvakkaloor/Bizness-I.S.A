<?php
namespace App\Support;
use Illuminate\Validation\ValidationException;
final class Amount {
 public static function parse(mixed $value,int $places=2): int {
  $s=trim((string)$value);
  if (!preg_match('/^(-?)(\d{1,9})(?:\.(\d{1,'.$places.'}))?$/D',$s,$m)) throw ValidationException::withMessages(['amount'=>'Use a number with at most '.$places.' decimal places.']);
  return ($m[1]==='-'?-1:1)*((int)$m[2]*(10**$places)+(int)str_pad($m[3]??'',$places,'0'));
 }
 public static function divide(int $n,int $d): int {if($d<=0) throw new \InvalidArgumentException('Positive denominator required');return ($n<0?-1:1)*intdiv(abs($n)+intdiv($d,2),$d);}
 public static function display(int $paise): string {return number_format($paise/100,2,'.',',');}
}
