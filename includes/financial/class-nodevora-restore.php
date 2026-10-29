<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/** Restauration sur cible vide uniquement ; une activité postérieure n’est jamais écrasée. */
class Nodevora_Restore {
	private static function permission() {
		if ( is_multisite() || ! current_user_can( 'manage_options' ) ) { throw new Nodevora_Financial_Error( 'Administrator restore permission required.' ); }
	}
	private static function maps( $db ) {
		global $wpdb;
		return Nodevora_Backup::table_map( $db ) + array( 'user_meta' => $wpdb->usermeta, 'post_meta' => $wpdb->postmeta, 'comment_meta' => $wpdb->commentmeta, 'order_meta' => $wpdb->prefix . 'wc_orders_meta' );
	}
	private static function empty_target( $db ) {
		foreach ( Nodevora_Backup::table_map( $db ) as $table ) {
			if ( $db->one( 'SELECT 1 AS present FROM `' . $table . '` LIMIT 1' ) ) { throw new Nodevora_Financial_Error( 'This store already contains loyalty activity. An older backup cannot replace it. Use an empty test copy or request reviewed reconciliation.' ); }
		}
		global $wpdb;
		foreach ( array( $wpdb->usermeta, $wpdb->postmeta, $wpdb->commentmeta, $wpdb->prefix . 'wc_orders_meta' ) as $table ) {
			if ( $db->one( 'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?', array( $table ) ) && $db->one( 'SELECT 1 AS present FROM `' . $table . '` WHERE meta_key REGEXP ? LIMIT 1', array( '^(rewardly_loyalty_|_rewardly|_nodevora)' ) ) ) { throw new Nodevora_Financial_Error( 'Existing loyalty metadata conflicts with this restore.' ); }
		}
	}
	private static function check_record( $db, $r, &$columns, &$references ) {
		if ( isset( $r['option'] ) ) {
			if ( ! in_array( $r['option'], array( 'rewardly_loyalty_settings', 'nodevora_financial_authority', Nodevora_Financial_Setup::OPTION, 'nodevora_access_policy' ), true ) ) { throw new Nodevora_Financial_Error( 'Unsupported backup option.' ); }
			if ( 'nodevora_financial_authority' === $r['option'] ) { if ( ! in_array( $r['value'], array( 'wallet', 'legacy' ), true ) ) { throw new Nodevora_Financial_Error( 'Invalid backup authority.' ); } return; }
			$value = @unserialize( $r['value'], array( 'allowed_classes' => false ) );
			if ( ! is_array( $value ) ) { throw new Nodevora_Financial_Error( 'Invalid backup configuration.' ); }
			$plain = static function( $v ) use ( &$plain ) { if ( is_array( $v ) ) { foreach ( $v as $item ) { $plain( $item ); } } elseif ( ! is_scalar( $v ) && null !== $v ) { throw new Nodevora_Financial_Error( 'Objects are not permitted in backup configuration.' ); } }; $plain( $value ); return;
		}
		$map = self::maps( $db ); $type = $r['table'];
		if ( ! isset( $map[$type] ) ) { throw new Nodevora_Financial_Error( 'Unknown backup table.' ); }
		if ( ! isset( $columns[$type] ) ) { $columns[$type] = array_column( $db->query( 'SHOW COLUMNS FROM `' . $map[$type] . '`' ), 'Field' ); }
		if ( array_diff( array_keys( $r['row'] ), $columns[$type] ) || array_diff( $columns[$type], array_keys( $r['row'] ) ) ) { throw new Nodevora_Financial_Error( 'Backup table columns are incompatible.' ); }
		foreach ( $r['row'] as $value ) { if ( ! is_int( $value ) && ! is_string( $value ) && null !== $value ) { throw new Nodevora_Financial_Error( 'Unsupported backup field.' ); } }
		if ( isset( $r['row']['meta_key'] ) && ! preg_match( '/^(rewardly_loyalty_|_rewardly|_nodevora)/D', $r['row']['meta_key'] ) ) { throw new Nodevora_Financial_Error( 'Unrelated WordPress metadata refused.' ); }
		if ( in_array( $type, array( 'user_meta', 'post_meta', 'comment_meta', 'order_meta' ), true ) ) { $pk = 'user_meta' === $type ? 'umeta_id' : ( 'order_meta' === $type ? 'id' : 'meta_id' ); if ( $db->one( 'SELECT 1 AS conflict FROM `' . $map[$type] . '` WHERE `' . $pk . '`=?', array( $r['row'][$pk] ) ) ) { throw new Nodevora_Financial_Error( 'A metadata identity conflicts with this backup.' ); } }
		$expected = Nodevora_Backup::references( $db, $type, $r['row'], $references );
		if ( ! isset( $r['references'] ) || $r['references'] !== $expected ) { throw new Nodevora_Financial_Error( 'Account or order identity does not match this store.' ); }
	}
	public static function preview( $path ) {
		self::permission(); $db = new Nodevora_Financial_DB();
		try {
			Nodevora_Financial_Schema::verify( $db ); self::empty_target( $db ); $columns = array(); $refs = array();
			return Nodevora_Backup::inspect( $path, static function( $r ) use ( $db, &$columns, &$refs ) { self::check_record( $db, $r, $columns, $refs ); } );
		} finally { $db->close(); }
	}
	private static function verify_rows( $db ) {
		$w = $db->table( 'wallet' ); $e = $db->table( 'event' ); $l = $db->table( 'lot' ); $a = $db->table( 'allocation' ); $s = $db->table( 'snapshot' );
		$bad = $db->one( 'SELECT w.user_id FROM ' . $w . ' w LEFT JOIN (SELECT user_id,SUM(delta_balance) b,SUM(delta_reserved) r,SUM(delta_debt) d FROM ' . $e . ' GROUP BY user_id) e ON e.user_id=w.user_id LEFT JOIN (SELECT user_id,SUM(remaining) b,SUM(reserved) r FROM ' . $l . ' GROUP BY user_id) l ON l.user_id=w.user_id WHERE w.state=? AND (w.balance<>COALESCE(e.b,0) OR w.reserved<>COALESCE(e.r,0) OR w.debt<>COALESCE(e.d,0) OR w.balance<>COALESCE(l.b,0) OR w.reserved<>COALESCE(l.r,0) OR w.debt<0 OR w.balance<w.reserved) LIMIT 1', array( 'ready' ) );
		if ( $bad ) { throw new Nodevora_Financial_Error( 'Backup wallet totals are inconsistent.' ); }
		$bad = $db->one( 'SELECT l.id FROM ' . $l . ' l LEFT JOIN ' . $e . ' e ON e.id=l.source_event_id LEFT JOIN (SELECT lot_id,SUM(delta_remaining) b,SUM(delta_reserved) r FROM ' . $a . ' GROUP BY lot_id) a ON a.lot_id=l.id WHERE e.id IS NULL OR e.user_id<>l.user_id OR l.remaining<>COALESCE(a.b,0) OR l.reserved<>COALESCE(a.r,0) OR l.remaining<l.reserved OR l.reserved<0 LIMIT 1' );
		if ( $bad ) { throw new Nodevora_Financial_Error( 'Backup allocations are inconsistent.' ); }
		foreach ( array( $e => 'payload', $s => 'payload' ) as $table => $column ) {
			$field = $table === $e ? 'payload_hash' : 'checksum';
			if ( $db->one( 'SELECT 1 AS bad FROM ' . $table . ' WHERE payload IS NOT NULL AND SHA2(payload,256)<>' . $field . ' LIMIT 1' ) ) { throw new Nodevora_Financial_Error( 'Backup journal or snapshot checksum mismatch.' ); }
		}
		if ( $db->one( 'SELECT a.event_id FROM ' . $a . ' a LEFT JOIN ' . $e . ' e ON e.id=a.event_id LEFT JOIN ' . $l . ' l ON l.id=a.lot_id WHERE e.id IS NULL OR l.id IS NULL OR e.user_id<>l.user_id LIMIT 1' ) ) { throw new Nodevora_Financial_Error( 'Backup contains an orphan allocation.' ); }
		if ( $db->one( 'SELECT o.id FROM ' . $db->table('outbox') . ' o LEFT JOIN ' . $e . ' e ON e.id=o.event_id WHERE e.id IS NULL OR e.user_id<>o.user_id LIMIT 1' ) ) { throw new Nodevora_Financial_Error( 'Backup contains an orphan effect.' ); }
		if ( $db->one( 'SELECT w.user_id FROM ' . $w . ' w LEFT JOIN ' . $s . ' s ON s.user_id=w.user_id AND s.version=1 WHERE s.user_id IS NULL LIMIT 1' ) ) { throw new Nodevora_Financial_Error( 'Backup account snapshot is missing.' ); }
	}
	public static function apply( $path, $expected_hash ) {
		self::permission(); $info = self::preview( $path );
		if ( ! is_string( $expected_hash ) || ! hash_equals( $info['sha256'], $expected_hash ) ) { throw new Nodevora_Financial_Error( 'Preview changed. Review the selected file again.' ); }
		$db = new Nodevora_Financial_DB(); $locked = false; $prior = Nodevora_Financial_Setup::state(); $marker = $prior;
		try {
			$key = 'nodevora:' . substr( hash( 'sha256', DB_NAME ), 0, 32 ) . ':setup'; $locked = 1 === (int) $db->one( 'SELECT GET_LOCK(?,0) AS acquired', array( $key ) )['acquired'];
			if ( ! $locked ) { throw new Nodevora_Financial_Error( 'Another data operation is running.' ); }
			$marker['phase'] = 'restoring'; update_option( Nodevora_Financial_Setup::OPTION, $marker, false );
			if ( get_option( Nodevora_Financial_Setup::OPTION ) !== $marker ) { throw new Nodevora_Financial_Error( 'Could not pause for restore.' ); }
			// La barrière partagée des wallets fait attendre les transactions en vol avant cette pause.
			$db->begin(); self::empty_target( $db ); $columns = array(); $refs = array(); $options = array(); $map = self::maps( $db );
			Nodevora_Backup::inspect( $path, static function( $r ) use ( $db, &$columns, &$refs, &$options, $map ) {
				self::check_record( $db, $r, $columns, $refs );
				if ( isset( $r['option'] ) ) { if ( isset( $options[$r['option']] ) ) { throw new Nodevora_Financial_Error( 'Duplicate backup option.' ); } $options[$r['option']] = $r['value']; return; }
				$names = array_keys( $r['row'] ); $quoted = array_map( static function( $n ) { return '`' . $n . '`'; }, $names );
				if ( 1 !== $db->query( 'INSERT INTO `' . $map[$r['table']] . '` (' . implode( ',', $quoted ) . ') VALUES (' . implode( ',', array_fill( 0, count( $names ), '?' ) ) . ')', array_values( $r['row'] ) ) ) { throw new Nodevora_Financial_Error( 'Backup record was not restored.' ); }
			} );
			self::verify_rows( $db );
			global $wpdb;
			$state = isset( $options[Nodevora_Financial_Setup::OPTION] ) ? unserialize( $options[Nodevora_Financial_Setup::OPTION], array( 'allowed_classes' => false ) ) : array();
			$state['phase'] = 'restore_review'; $state['recovery_approved'] = false; $state['restore_hash'] = $expected_hash; $state['restored_at'] = gmdate( 'c' );
			$options[Nodevora_Financial_Setup::OPTION] = serialize( $state );
			// L’autorité Wallet ne revient jamais à l’ancien moteur après restauration.
			$options['nodevora_financial_authority'] = 'wallet';
			foreach ( $options as $name => $value ) { $db->query( 'INSERT INTO `' . $wpdb->options . '` (option_name,option_value,autoload) VALUES (?,?,?) ON DUPLICATE KEY UPDATE option_value=VALUES(option_value)', array( $name, $value, 'off' ) ); }
			$db->commit(); wp_cache_delete( 'alloptions', 'options' ); wp_cache_delete( 'notoptions', 'options' ); foreach ( array_keys( $options ) as $name ) { wp_cache_delete( $name, 'options' ); } return $info;
		} catch ( Throwable $e ) {
			$db->rollback();
			// Une erreur conserve la pause : ne jamais rouvrir automatiquement après résultat incertain.
			throw $e;
		} finally { if ( $locked ) { $db->one( 'SELECT RELEASE_LOCK(?) AS released', array( $key ) ); } $db->close(); }
	}
}
