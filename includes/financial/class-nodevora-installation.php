<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/** Installation vierge seulement ; une preuve historique impose une synchronisation explicite. */
class Nodevora_Installation {
	private static $schema_scope = false;
	public static function permits_schema() { return self::$schema_scope; }
	private static function exists( $db, $table ) {
		return (bool) $db->one( 'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?', array( $table ) );
	}
	public static function detect( $db ) {
		global $wpdb;
		$state = Nodevora_Financial_Setup::state();
		if ( 'wallet' === get_option( 'nodevora_financial_authority' ) ) { return 'wallet'; }
		if ( $state && ! ( isset( $state['origin'], $state['phase'] ) && 'fresh' === $state['origin'] && 'failed' === $state['phase'] ) ) { return 'interrupted'; }
		foreach ( Nodevora_Financial_Schema::definitions() as $name => $unused ) {
			if ( self::exists( $db, $db->prefix . $name ) && $db->one( 'SELECT 1 AS found FROM ' . $db->table( $name ) . ' LIMIT 1' ) ) { return 'existing'; }
		}
		if ( $db->one( 'SELECT 1 AS found FROM `' . $wpdb->usermeta . '` WHERE meta_key REGEXP ? LIMIT 1', array( '^(rewardly_loyalty_|_rewardly_bonus_)' ) ) ) { return 'existing'; }
		foreach ( array( $wpdb->postmeta, $wpdb->prefix . 'wc_orders_meta' ) as $table ) {
			if ( self::exists( $db, $table ) && $db->one( 'SELECT 1 AS found FROM `' . $table . '` WHERE meta_key REGEXP ? LIMIT 1', array( '^(_rewardly|_nodevora_financial|_nodevora_refund|_nodevora_earning)' ) ) ) { return 'existing'; }
		}
		if ( $db->one( 'SELECT 1 AS found FROM `' . $wpdb->commentmeta . '` WHERE meta_key=? LIMIT 1', array( '_rewardly_bonus_review_awarded' ) ) ) { return 'existing'; }
		$legacy = $wpdb->prefix . 'rewardly_loyalty_log';
		if ( self::exists( $db, $legacy ) && $db->one( 'SELECT 1 AS found FROM `' . $legacy . '` LIMIT 1' ) ) { return 'existing'; }
		return 'empty';
	}
	public static function activate() {
		if ( is_multisite() || ! class_exists( 'WooCommerce' ) ) { return; }
		$db = null; $locked = false;
		try {
			$db = new Nodevora_Financial_DB();
			$key = 'nodevora:' . substr( hash( 'sha256', DB_NAME ), 0, 32 ) . ':setup';
			$locked = 1 === (int) $db->one( 'SELECT GET_LOCK(?,0) AS acquired', array( $key ) )['acquired'];
			if ( ! $locked ) { throw new Nodevora_Financial_Error( 'Another installation is running.' ); }
			$kind = self::detect( $db );
			if ( 'wallet' === $kind ) { Nodevora_Financial_Schema::verify( $db ); update_option( 'nodevora_installation_status', 'ready', false ); delete_option( 'nodevora_installation_error' ); return; }
			if ( 'empty' !== $kind ) { update_option( 'nodevora_installation_status', 'synchronization_required', false ); return; }
			// Ne jamais lever un arrêt imposé par l’opérateur, même sur un site vierge.
			if ( ( defined( 'NODEVORA_FINANCIAL_WRITES_PAUSED' ) && NODEVORA_FINANCIAL_WRITES_PAUSED ) || ( defined( 'NODEVORA_FINANCIAL_INTEGRATION_ALLOWED' ) && ! NODEVORA_FINANCIAL_INTEGRATION_ALLOWED ) ) { throw new Nodevora_Financial_Error( 'An operator safety override is active.' ); }
			self::$schema_scope = true;
			Nodevora_Financial_Schema::install( $db );
			// La création de tables ne suffit pas : revérifier les sources avant l’autorité unique.
			if ( 'empty' !== self::detect( $db ) ) { throw new Nodevora_Financial_Error( 'Existing loyalty evidence was detected.' ); }
			$state = array( 'phase' => 'active', 'origin' => 'fresh', 'activated_at' => gmdate( 'c' ), 'recovery_approved' => false );
			update_option( Nodevora_Financial_Setup::OPTION, $state, false );
			if ( get_option( Nodevora_Financial_Setup::OPTION ) !== $state ) { throw new Nodevora_Financial_Error( 'Installation state could not be saved.' ); }
			update_option( 'nodevora_financial_authority', 'wallet', false );
			if ( 'wallet' !== get_option( 'nodevora_financial_authority' ) ) { throw new Nodevora_Financial_Error( 'Wallet authority could not be saved.' ); }
			update_option( 'nodevora_installation_status', 'ready', false );
			delete_option( 'nodevora_installation_error' );
		} catch ( Throwable $error ) {
			update_option( 'nodevora_installation_status', 'blocked', false );
			// État fermé, sans écraser l’origine d’une ancienne migration ni une autorité durable.
			if ( ! Nodevora_Financial_Setup::state() ) { update_option( Nodevora_Financial_Setup::OPTION, array( 'phase' => 'failed', 'origin' => 'fresh' ), false ); }
			update_option( 'nodevora_installation_error', __( 'Nodevora could not prepare your points. Ask your site administrator to check database access and any active safety pause, then retry. Existing data is preserved.', 'nodevora-customer-rewards-for-woocommerce' ), false );
		} finally {
			self::$schema_scope = false;
			if ( $db ) { if ( $locked ) { $db->one( 'SELECT RELEASE_LOCK(?) AS released', array( $key ) ); } $db->close(); }
		}
	}
	/** Un compte vide est créé sous verrou ; aucune importation implicite d’un solde legacy. */
	public static function ensure_wallet( $user ) {
		$s = Nodevora_Financial_Setup::state();
		if ( empty( $s['origin'] ) || 'fresh' !== $s['origin'] || 'active' !== $s['phase'] || ! Nodevora_Financial_Setup::integrated() ) { return; }
		if ( $user < 1 || ! get_user_by( 'id', $user ) ) { throw new Nodevora_Financial_Error( 'Unknown wallet account.' ); }
		$db = new Nodevora_Financial_DB();
		try {
			$existing = $db->one( 'SELECT state FROM ' . $db->table( 'wallet' ) . ' WHERE user_id=?', array( $user ) );
			if ( $existing && 'ready' === $existing['state'] ) { return; }
			Nodevora_Financial_Schema::verify( $db ); $db->begin();
			$db->query( 'INSERT IGNORE INTO ' . $db->table( 'wallet' ) . ' (user_id,state) VALUES (?,?)', array( $user, 'pending' ) );
			$w = $db->one( 'SELECT * FROM ' . $db->table( 'wallet' ) . ' WHERE user_id=? FOR UPDATE', array( $user ) );
			if ( 'ready' === $w['state'] ) { $db->rollback(); return; }
			global $wpdb;
			if ( 'pending' !== $w['state'] || $db->one( 'SELECT 1 AS found FROM `' . $wpdb->usermeta . '` WHERE user_id=? AND meta_key REGEXP ? LIMIT 1', array( $user, '^(rewardly_loyalty_|_rewardly_bonus_)' ) ) ) { throw new Nodevora_Financial_Error( 'Historical account requires reviewed synchronization.' ); }
			$payload = json_encode( array( 'rows' => array(), 'new_account' => true ), JSON_THROW_ON_ERROR ); $hash = hash( 'sha256', $payload );
			$db->query( 'INSERT INTO ' . $db->table( 'snapshot' ) . ' (user_id,version,checksum,payload,created_at) VALUES (?,1,?,?,UTC_TIMESTAMP())', array( $user, $hash, $payload ) );
			$db->query( 'UPDATE ' . $db->table( 'wallet' ) . ' SET state=?,migration_version=1,checksum=? WHERE user_id=?', array( 'ready', $hash, $user ) ); $db->commit();
		} catch ( Throwable $e ) { $db->rollback(); throw $e; } finally { $db->close(); }
	}
}
