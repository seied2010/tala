<?php
declare(strict_types=1);
namespace GoldCommerce;

/** Exact nonnegative arithmetic. Intermediate products never enter a PHP float. */
final class Decimal {
    public static function norm(string $n): string { return ltrim($n, '0') ?: '0'; }
    public static function cmp(string $a, string $b): int {
        $a=self::norm($a); $b=self::norm($b);
        return strlen($a)<=>strlen($b) ?: strcmp($a,$b);
    }
    public static function add(string $a, string $b): string {
        $r=''; $carry=0;
        for ($i=strlen($a)-1,$j=strlen($b)-1;$i>=0||$j>=0||$carry;$i--,$j--) {
            $v=($i>=0?(int)$a[$i]:0)+($j>=0?(int)$b[$j]:0)+$carry;
            $r=($v%10).$r; $carry=intdiv($v,10);
        }
        return self::norm($r);
    }
    public static function sub(string $a,string $b): string {
        if(self::cmp($a,$b)<0) throw new \DomainException('negative_component');
        $r='';$borrow=0;
        for($i=strlen($a)-1,$j=strlen($b)-1;$i>=0;$i--,$j--){
            $v=(int)$a[$i]-($j>=0?(int)$b[$j]:0)-$borrow;
            $borrow=$v<0?1:0;if($borrow)$v+=10;$r=$v.$r;
        } return self::norm($r);
    }
    public static function mul(string $a,string $b): string {
        $r='0';$zeros='';
        for($j=strlen($b)-1;$j>=0;$j--){
            $part='';$carry=0;
            for($i=strlen($a)-1;$i>=0;$i--){$v=(int)$a[$i]*(int)$b[$j]+$carry;$part=($v%10).$part;$carry=intdiv($v,10);}
            if($carry)$part=$carry.$part;$r=self::add($r,$part.$zeros);$zeros.='0';
        }return self::norm($r);
    }
    public static function div(string $a,string $b,string $round='half_up'): string {
        if(!in_array($round,['half_up','down','up'],true)||self::norm($b)==='0')throw new \DomainException('invalid_division');
        $q='';$rem='0';
        foreach(str_split($a) as $digit){$rem=self::norm($rem.$digit);$n=0;while(self::cmp($rem,$b)>=0){$rem=self::sub($rem,$b);$n++;}$q.=$n;}
        if(($round==='half_up'&&self::cmp(self::mul($rem,'2'),$b)>=0)||($round==='up'&&$rem!=='0'))$q=self::add($q,'1');
        return self::norm($q);
    }
    public static function parse(mixed $value,int $scale=4): string {
        if(!is_string($value)&&!is_int($value))throw new \DomainException('decimal_string_required');
        $s=strtr((string)$value,array_combine(preg_split('//u','۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩',-1,PREG_SPLIT_NO_EMPTY),str_split('01234567890123456789')));
        if(!preg_match('/^(\d{1,12})(?:\.(\d{1,'.$scale.'}))?$/D',$s,$m))throw new \DomainException('invalid_decimal');
        return self::norm($m[1].str_pad($m[2]??'',$scale,'0'));
    }
    public static function integer(string $n): int {
        if(!preg_match('/^\d+$/D',$n)||self::cmp($n,'9000000000000000')>0)throw new \OverflowException('money_overflow');
        return (int)$n;
    }
    public static function money(mixed $value): string {return self::div(self::parse($value),'10000');}
    public static function quantity(mixed $value): string { return self::parse($value,4); }
    public static function display(string $irr,string $unit='IRR'): string {
        if($unit==='IRR')return $irr;
        if($unit!=='IRT')throw new \DomainException('unknown_currency');
        $s=str_pad($irr,2,'0',STR_PAD_LEFT);return substr($s,0,-1).'.'.substr($s,-1);
    }
}
