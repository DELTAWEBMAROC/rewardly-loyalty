<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/** Decimal strings at the Woo boundary, bounded integer arithmetic in financial decisions. */
class Nodevora_Financial_Money {
	public static function scale($decimals) { if (!is_int($decimals)||$decimals<0||$decimals>6) { throw new Nodevora_Financial_Error('Unsupported currency precision.'); } return (int)pow(10,$decimals); }
	public static function minor($decimal, $decimals) {
		if (!is_string($decimal)||!preg_match('/^(-?)([0-9]+)(?:\.([0-9]+))?$/D',$decimal,$m)) { throw new Nodevora_Financial_Error('Invalid decimal monetary amount.'); }
		$scale=self::scale($decimals); $whole=ltrim($m[2],'0'); $whole=''===$whole?'0':$whole;
		if (strlen($whole)>12) { throw new Nodevora_Financial_Error('Monetary amount exceeds supported bounds.'); }
		$fraction=isset($m[3])?$m[3]:'';
		if (strlen($fraction)>$decimals && trim(substr($fraction,$decimals),'0')!=='') { throw new Nodevora_Financial_Error('Monetary amount requires explicit rounding.'); }
		$n=self::multiply((int)$whole,$scale)+(int)str_pad(substr($fraction,0,$decimals),$decimals,'0'); Nodevora_Financial_Wallet::points($n);
		return '-'===$m[1]?-$n:$n;
	}
	public static function multiply($a,$b) { Nodevora_Financial_Wallet::points($a);Nodevora_Financial_Wallet::points($b);if($b&&$a>intdiv(intdiv(PHP_INT_MAX,4),$b)){throw new Nodevora_Financial_Error('Financial multiplication exceeds supported bounds.');}return $a*$b; }
	public static function decimal($minor,$decimals) { $scale=self::scale($decimals);$sign=$minor<0?'-':'';$minor=abs($minor);Nodevora_Financial_Wallet::points($minor);return $sign.intdiv($minor,$scale).($decimals?'.'.str_pad((string)($minor%$scale),$decimals,'0',STR_PAD_LEFT):''); }
	public static function discount($points,$ratio,$scale) { if($ratio<1){throw new Nodevora_Financial_Error('Invalid conversion rate.');}return intdiv(self::multiply($points,$scale),$ratio); }
	public static function charge($minor,$ratio,$scale) { if($scale<1){throw new Nodevora_Financial_Error('Invalid monetary scale.');}return intdiv(self::multiply($minor,$ratio),$scale); }
}
