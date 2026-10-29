<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/** Restauration sur cible vide uniquement ; une activité postérieure n’est jamais écrasée. */
class Nodevora_Restore {
	private static function permission() {
		if ( is_multisite() || ! current_user_can( 'manage_options' ) ) { throw new Nodevora_Financial_Error( 'Administrator restore permission required.' ); }
	}
	private static function maps( $db ) {
		global $wpdb;
		return Nodevora_Backup::table_map( $db ) + array( 'user_meta' => $wpdb->usermeta, 'post_meta' => $wpdb->postmeta, 'comment_meta' => $wpdb->commentmeta, 'order_meta' => $wpdb->prefix . 'wc_orders_meta', 'item_meta' => $wpdb->prefix . 'woocommerce_order_itemmeta' );
	}
	private static function empty_target( $db ) {
		foreach ( Nodevora_Backup::table_map( $db ) as $table ) {
			if ( $db->one( 'SELECT 1 AS present FROM `' . $table . '` LIMIT 1' ) ) { throw new Nodevora_Financial_Error( 'This store already contains loyalty activity. An older backup cannot replace it. Use an empty test copy or request reviewed reconciliation.' ); }
		}
		global $wpdb;
		foreach ( array( $wpdb->usermeta, $wpdb->postmeta, $wpdb->commentmeta, $wpdb->prefix . 'wc_orders_meta', $wpdb->prefix . 'woocommerce_order_itemmeta' ) as $table ) {
			if ( $db->one( 'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?', array( $table ) ) && $db->one( 'SELECT 1 AS present FROM `' . $table . '` WHERE meta_key REGEXP ? LIMIT 1', array( '^(rewardly_loyalty_|_rewardly|_nodevora)' ) ) ) { throw new Nodevora_Financial_Error( 'Existing loyalty metadata conflicts with this restore.' ); }
		}
	}
	private static function check_record( $db, $r, &$columns, &$references ) {
		if ( isset( $r['option'] ) ) {
			if ( ! in_array( $r['option'], array( 'rewardly_loyalty_settings', 'nodevora_financial_authority', Nodevora_Financial_Setup::OPTION, 'nodevora_access_policy' ), true ) ) { throw new Nodevora_Financial_Error( 'Unsupported backup option.' ); }
			if ( 'nodevora_financial_authority' === $r['option'] ) { if ( ! in_array( $r['value'], array( 'wallet', 'legacy' ), true ) ) { throw new Nodevora_Financial_Error( 'Invalid backup authority.' ); } return; }
			$value = @unserialize( $r['value'], array( 'allowed_classes' => false ) );
			if ( ! is_array( $value ) ) { throw new Nodevora_Financial_Error( 'Invalid backup configuration.' ); }
			json_encode($value,JSON_THROW_ON_ERROR,32); // Refuser les cycles et profondeurs avant le parcours des valeurs.
			$plain = static function( $v ) use ( &$plain ) { if ( is_array( $v ) ) { foreach ( $v as $item ) { $plain( $item ); } } elseif ( ! is_scalar( $v ) && null !== $v ) { throw new Nodevora_Financial_Error( 'Objects are not permitted in backup configuration.' ); } }; $plain( $value ); return;
		}
		$map = self::maps( $db ); $type = $r['table'];
		if ( ! isset( $map[$type] ) ) { throw new Nodevora_Financial_Error( 'Unknown backup table.' ); }
		if ( ! isset( $columns[$type] ) ) { $columns[$type] = array_column( $db->query( 'SHOW COLUMNS FROM `' . $map[$type] . '`' ), 'Field' ); }
		if ( array_diff( array_keys( $r['row'] ), $columns[$type] ) || array_diff( $columns[$type], array_keys( $r['row'] ) ) ) { throw new Nodevora_Financial_Error( 'Backup table columns are incompatible.' ); }
		foreach ( $r['row'] as $value ) { if ( ! is_int( $value ) && ! is_string( $value ) && null !== $value ) { throw new Nodevora_Financial_Error( 'Unsupported backup field.' ); } }
		if ( isset( $r['row']['meta_key'] ) && ! preg_match( '/^(rewardly_loyalty_|_rewardly|_nodevora)/D', $r['row']['meta_key'] ) ) { throw new Nodevora_Financial_Error( 'Unrelated WordPress metadata refused.' ); }
		if ( in_array( $type, array( 'user_meta', 'post_meta', 'comment_meta', 'order_meta', 'item_meta' ), true ) ) { $pk = 'user_meta' === $type ? 'umeta_id' : ( 'order_meta' === $type ? 'id' : 'meta_id' ); if ( $db->one( 'SELECT 1 AS conflict FROM `' . $map[$type] . '` WHERE `' . $pk . '`=?', array( $r['row'][$pk] ) ) ) { throw new Nodevora_Financial_Error( 'A metadata identity conflicts with this backup.' ); } }
		if(isset($r['row']['meta_value'])&&is_string($r['row']['meta_value'])&&preg_match('/^(?:a|O|C|s|b|i|d|r|R):.*[;}]$/s',$r['row']['meta_value'])){$decoded=@unserialize($r['row']['meta_value'],array('allowed_classes'=>false,'max_depth'=>32));if(false===$decoded&&'b:0;'!==$r['row']['meta_value']){throw new Nodevora_Financial_Error('Invalid serialized loyalty metadata.');}json_encode($decoded,JSON_THROW_ON_ERROR,32);$plain=static function($v)use(&$plain){if(is_array($v)){foreach($v as$item){$plain($item);}}elseif(is_object($v)){throw new Nodevora_Financial_Error('Objects are not permitted in loyalty metadata.');}};$plain($decoded);}
		$expected = Nodevora_Backup::references( $db, $type, $r['row'], $references );
		if ( ! isset( $r['references'] ) || $r['references'] !== $expected ) { throw new Nodevora_Financial_Error( 'Account or order identity does not match this store.' ); }
	}
	public static function preview( $path ) {
		self::permission(); $db = new Nodevora_Financial_DB();
		try {
			Nodevora_Financial_Schema::verify( $db ); self::empty_target( $db ); $columns = array(); $refs = array(); $seen = array();
			return Nodevora_Backup::inspect( $path, static function( $r ) use ( $db, &$columns, &$refs, &$seen ) {
				self::check_record( $db, $r, $columns, $refs );
				if(isset($r['option'])){$keys=array('option:'.$r['option']);}else{
					$type=$r['table'];$row=$r['row'];$pk='wallet'===$type?'user_id':('snapshot'===$type?'user_id':('order_state'===$type?'order_id':('user_meta'===$type?'umeta_id':(in_array($type,array('post_meta','comment_meta','item_meta'),true)?'meta_id':'id'))));
					$identity='allocation'===$type?array($row['event_id'],$row['lot_id'],$row['leg']):array($row[$pk]);if('snapshot'===$type){$identity[]=$row['version'];}
					$keys=array($type.':pk:'.json_encode($identity));
					if('event'===$type){$keys[]=$type.':operation:'.$row['operation_key'];}if('outbox'===$type){$keys[]=$type.':delivery:'.json_encode(array($row['event_id'],$row['effect']));}
				}
				foreach($keys as$key){$digest=hash('sha256',$key);if(isset($seen[$digest])){throw new Nodevora_Financial_Error('Duplicate backup record.');}$seen[$digest]=true;}
			} );
		} finally { $db->close(); }
	}
	private static function verify_rows( $db ) {
		$w = $db->table( 'wallet' ); $e = $db->table( 'event' ); $l = $db->table( 'lot' ); $a = $db->table( 'allocation' ); $s = $db->table( 'snapshot' );
		$max=intdiv(PHP_INT_MAX,4);if($db->one('SELECT 1 AS bad FROM '.$w.' WHERE state NOT IN (?,?) OR balance<0 OR reserved<0 OR debt<0 OR balance>? OR reserved>? OR debt>? LIMIT 1',array('ready','needs_review',$max,$max,$max))){throw new Nodevora_Financial_Error('Backup wallet bounds are invalid.');}
		$bad = $db->one( 'SELECT w.user_id FROM ' . $w . ' w LEFT JOIN (SELECT user_id,SUM(delta_balance) b,SUM(delta_reserved) r,SUM(delta_debt) d FROM ' . $e . ' GROUP BY user_id) e ON e.user_id=w.user_id LEFT JOIN (SELECT user_id,SUM(remaining) b,SUM(reserved) r FROM ' . $l . ' GROUP BY user_id) l ON l.user_id=w.user_id WHERE w.state=? AND (w.balance<>COALESCE(e.b,0) OR w.reserved<>COALESCE(e.r,0) OR w.debt<>COALESCE(e.d,0) OR w.balance<>COALESCE(l.b,0) OR w.reserved<>COALESCE(l.r,0) OR w.debt<0 OR w.balance<w.reserved) LIMIT 1', array( 'ready' ) );
		if ( $bad ) { throw new Nodevora_Financial_Error( 'Backup wallet totals are inconsistent.' ); }
		$bad = $db->one( 'SELECT l.id FROM ' . $l . ' l LEFT JOIN ' . $e . ' e ON e.id=l.source_event_id LEFT JOIN (SELECT lot_id,SUM(delta_remaining) b,SUM(delta_reserved) r FROM ' . $a . ' GROUP BY lot_id) a ON a.lot_id=l.id WHERE e.id IS NULL OR e.user_id<>l.user_id OR l.remaining<>COALESCE(a.b,0) OR l.reserved<>COALESCE(a.r,0) OR l.remaining<l.reserved OR l.reserved<0 LIMIT 1' );
		if ( $bad ) { throw new Nodevora_Financial_Error( 'Backup allocations are inconsistent.' ); }
		if($db->one('SELECT 1 AS bad FROM '.$e.' WHERE NOT JSON_VALID(result) OR JSON_TYPE(result)<>? OR (payload IS NOT NULL AND (NOT JSON_VALID(payload) OR JSON_TYPE(payload)<>?)) OR gross_points<0 OR gross_points>? LIMIT 1',array('OBJECT','OBJECT',$max))){throw new Nodevora_Financial_Error('Backup journal record is invalid.');}
		foreach ( array( $e => 'payload', $s => 'payload' ) as $table => $column ) {
			$field = $table === $e ? 'payload_hash' : 'checksum';
			if ( $db->one( 'SELECT 1 AS bad FROM ' . $table . ' WHERE payload IS NOT NULL AND BINARY SHA2(payload,256)<>BINARY ' . $field . ' LIMIT 1' ) ) { throw new Nodevora_Financial_Error( 'Backup journal or snapshot checksum mismatch.' ); }
		}
		if ( $db->one( 'SELECT a.event_id FROM ' . $a . ' a LEFT JOIN ' . $e . ' e ON e.id=a.event_id LEFT JOIN ' . $l . ' l ON l.id=a.lot_id WHERE e.id IS NULL OR l.id IS NULL OR e.user_id<>l.user_id LIMIT 1' ) ) { throw new Nodevora_Financial_Error( 'Backup contains an orphan allocation.' ); }
		if ( $db->one( 'SELECT o.id FROM ' . $db->table('outbox') . ' o LEFT JOIN ' . $e . ' e ON e.id=o.event_id WHERE e.id IS NULL OR e.user_id<>o.user_id LIMIT 1' ) ) { throw new Nodevora_Financial_Error( 'Backup contains an orphan effect.' ); }
		if($db->one('SELECT 1 AS bad FROM '.$e.' e LEFT JOIN '.$w.' w ON w.user_id=e.user_id WHERE w.user_id IS NULL LIMIT 1')||$db->one('SELECT 1 AS bad FROM '.$s.' s LEFT JOIN '.$w.' w ON w.user_id=s.user_id WHERE w.user_id IS NULL LIMIT 1')){throw new Nodevora_Financial_Error('Backup contains an orphan account record.');}
		if($db->one('SELECT 1 AS bad FROM '.$w.' w JOIN '.$s.' s ON s.user_id=w.user_id AND s.version=1 WHERE BINARY w.checksum<>BINARY s.checksum OR w.checksum IS NULL LIMIT 1')){throw new Nodevora_Financial_Error('Backup account checksum is inconsistent.');}
		if($db->one('SELECT 1 AS bad FROM '.$db->table('order_state').' o LEFT JOIN '.$w.' w ON w.user_id=o.user_id LEFT JOIN '.$e.' e ON e.id=o.debit_event_id WHERE w.user_id IS NULL OR (o.debit_event_id IS NOT NULL AND (e.id IS NULL OR e.user_id<>o.user_id)) LIMIT 1')){throw new Nodevora_Financial_Error('Backup order funding reference is inconsistent.');}
		if ( $db->one( 'SELECT w.user_id FROM ' . $w . ' w LEFT JOIN ' . $s . ' s ON s.user_id=w.user_id AND s.version=1 WHERE s.user_id IS NULL LIMIT 1' ) ) { throw new Nodevora_Financial_Error( 'Backup account snapshot is missing.' ); }
	}
	public static function resume_reviewed( $recovery_approved = false ) {
		self::permission(); $state = Nodevora_Financial_Setup::state();
		if ( ( $state['phase'] ?? '' ) !== 'restore_review' || ! in_array( $state['restore_previous_phase'] ?? '', array('active','paused'), true ) ) { throw new Nodevora_Financial_Error('Complete synchronization before enabling restored accounts.'); }
		if ( (defined('NODEVORA_FINANCIAL_WRITES_PAUSED')&&NODEVORA_FINANCIAL_WRITES_PAUSED) || (defined('NODEVORA_FINANCIAL_INTEGRATION_ALLOWED')&&!NODEVORA_FINANCIAL_INTEGRATION_ALLOWED) ) { throw new Nodevora_Financial_Error('An operator safety override prevents activation.'); }
		$db=new Nodevora_Financial_DB(); try { Nodevora_Financial_Schema::verify($db); self::verify_rows($db); } finally {$db->close();}
		$state['phase']='active';$state['recovery_approved']=(bool)$recovery_approved;update_option(Nodevora_Financial_Setup::OPTION,$state,false);
		if(get_option(Nodevora_Financial_Setup::OPTION)!==$state){throw new Nodevora_Financial_Error('Restore approval was not saved.');}
		update_option('nodevora_installation_status','ready',false);
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
			$db->begin(); self::empty_target( $db ); $columns = array(); $refs = array(); $options = array(); $meta_cache = array(); $map = self::maps( $db );
			$restored_info=Nodevora_Backup::inspect( $path, static function( $r ) use ( $db, &$columns, &$refs, &$options, &$meta_cache, $map ) {
				self::check_record( $db, $r, $columns, $refs );
				if ( isset( $r['option'] ) ) { if ( isset( $options[$r['option']] ) ) { throw new Nodevora_Financial_Error( 'Duplicate backup option.' ); } $options[$r['option']] = $r['value']; return; }
				if(isset($r['row']['meta_key'])){$types=array('user_meta'=>array('user','user_id'),'post_meta'=>array('post','post_id'),'comment_meta'=>array('comment','comment_id'),'order_meta'=>array('orders','order_id'),'item_meta'=>array('order_item','order_item_id'));$spec=$types[$r['table']];$meta_cache[$spec[0].'_meta'][(int)$r['row'][$spec[1]]]=true;}
				$names = array_keys( $r['row'] ); $quoted = array_map( static function( $n ) { return '`' . $n . '`'; }, $names );
				if ( 1 !== $db->query( 'INSERT INTO `' . $map[$r['table']] . '` (' . implode( ',', $quoted ) . ') VALUES (' . implode( ',', array_fill( 0, count( $names ), '?' ) ) . ')', array_values( $r['row'] ) ) ) { throw new Nodevora_Financial_Error( 'Backup record was not restored.' ); }
			} );
			if(!hash_equals($expected_hash,$restored_info['sha256'])){throw new Nodevora_Financial_Error('Backup changed during restore.');}
			self::verify_rows( $db );
			global $wpdb;
			$state = isset( $options[Nodevora_Financial_Setup::OPTION] ) ? unserialize( $options[Nodevora_Financial_Setup::OPTION], array( 'allowed_classes' => false ) ) : array();
			$state['restore_previous_phase'] = $state['phase'] ?? ''; $state['phase'] = 'restore_review'; $state['recovery_approved'] = false; $state['restore_hash'] = $expected_hash; $state['restored_at'] = gmdate( 'c' );
			$options[Nodevora_Financial_Setup::OPTION] = serialize( $state );
			// L’autorité Wallet ne revient jamais à l’ancien moteur après restauration.
			$options['nodevora_financial_authority'] = 'wallet';
			foreach ( $options as $name => $value ) { $db->query( 'INSERT INTO `' . $wpdb->options . '` (option_name,option_value,autoload) VALUES (?,?,?) ON DUPLICATE KEY UPDATE option_value=VALUES(option_value)', array( $name, $value, 'off' ) ); }
			$db->commit(); foreach($meta_cache as$group=>$ids){foreach($ids as$id=>$unused){wp_cache_delete($id,$group);}} wp_cache_delete( 'alloptions', 'options' ); wp_cache_delete( 'notoptions', 'options' ); foreach ( array_keys( $options ) as $name ) { wp_cache_delete( $name, 'options' ); } return $info;
		} catch ( Throwable $e ) {
			$db->rollback();wp_cache_delete('alloptions','options');wp_cache_delete('notoptions','options');foreach(array('rewardly_loyalty_settings','nodevora_financial_authority',Nodevora_Financial_Setup::OPTION,'nodevora_access_policy')as$name){wp_cache_delete($name,'options');}
			// Une erreur conserve la pause : ne jamais rouvrir automatiquement après résultat incertain.
			throw $e;
		} finally { if ( $locked ) { $db->one( 'SELECT RELEASE_LOCK(?) AS released', array( $key ) ); } $db->close(); }
	}
}
