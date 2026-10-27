<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/** Explicit opt-in read bridge; unsafe legacy mutation can never be its fallback. */
class Nodevora_Financial_Compatibility {
	public static function selected() { return defined('NODEVORA_FINANCIAL_MODE') && 'wallet' === NODEVORA_FINANCIAL_MODE; }
	public static function balance($user_id) { $wallet = new Nodevora_Financial_Wallet(); $state = $wallet->read((int)$user_id); return $state['available']; }
	public static function legacy_write_guard() {
		if (self::selected()) { throw new Nodevora_Financial_Error('Financial checkout/lifecycle integration is not ready; legacy write refused.'); }
	}
}
