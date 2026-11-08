<?php
/** WordPress database boundary. No private connection, driver or reconnect override. @package Nodevora */
if ( ! defined( 'ABSPATH' ) ) {
	exit; }
class Nodevora_Store {
	private static $insert_id = 0;
	private static $cache_users = array();
	private static $token      = null;
	private static $connection = 0;
    private static $isolation = null;
	public static function table( $name ) {
		global $wpdb;
		if ( ! in_array( $name, array( 'wallets', 'events', 'lots' ), true ) ) {
			throw new RuntimeException( esc_html__( 'Invalid Nodevora table.', 'nodevora-customer-rewards-for-woocommerce' ) ); }
		return $wpdb->prefix . 'nodevora_v2_' . $name;
	}
	public static function install() {
		global $wpdb;
		if ( is_multisite() ) {
			wp_die( esc_html__( 'Activate Nodevora on a single site installation.', 'nodevora-customer-rewards-for-woocommerce' ) ); }
		if (1!==(int)get_option('nodevora_v2_schema') && self::legacy_detected()) {wp_die(esc_html__('This release requires a fresh installation. Existing loyalty data was found and has not been changed. Restore the matching earlier plugin before updating this store.','nodevora-customer-rewards-for-woocommerce'));}
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$c       = $wpdb->get_charset_collate();
		$wallets = self::table( 'wallets' );
		$events  = self::table( 'events' );
		$lots    = self::table( 'lots' );
		dbDelta(
			"CREATE TABLE $wallets (
 user_id bigint(20) unsigned NOT NULL,
 balance bigint(20) NOT NULL DEFAULT 0,
 revision bigint(20) unsigned NOT NULL DEFAULT 0,
 PRIMARY KEY  (user_id)
) ENGINE=InnoDB $c;"
		);
		dbDelta(
			"CREATE TABLE $events (
 id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
 operation_key varchar(191) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 user_id bigint(20) unsigned NOT NULL,
 kind varchar(24) NOT NULL,
 points bigint(20) unsigned NOT NULL DEFAULT 0,
 delta bigint(20) NOT NULL DEFAULT 0,
 balance_after bigint(20) NOT NULL DEFAULT 0,
 order_id bigint(20) unsigned NOT NULL DEFAULT 0,
 source_id bigint(20) unsigned NOT NULL DEFAULT 0,
 payload longtext NOT NULL,
 created_at datetime NOT NULL,
 PRIMARY KEY  (id),
 UNIQUE KEY operation_key (operation_key),
 KEY user_history (user_id,id),
 KEY source_id (source_id),
 KEY order_id (order_id)
) ENGINE=InnoDB $c;"
		);
		dbDelta(
			"CREATE TABLE $lots (
 id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
 user_id bigint(20) unsigned NOT NULL,
 event_id bigint(20) unsigned NOT NULL,
 remaining bigint(20) unsigned NOT NULL,
 expires_at bigint(20) unsigned NOT NULL DEFAULT 0,
 PRIMARY KEY  (id),
 KEY user_expiry (user_id,expires_at,id),
 KEY event_id (event_id)
) ENGINE=InnoDB $c;"
		);
		foreach ( array( $wallets, $events, $lots ) as $table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Fresh schema inspection must detect legacy storage and verify InnoDB before activation.
			$engine = $wpdb->get_var( $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $table ) );
			if ( 'InnoDB' !== $engine ) {
				wp_die( esc_html__( 'Nodevora requires InnoDB tables.', 'nodevora-customer-rewards-for-woocommerce' ) ); }
		}
		add_option( 'nodevora_v2_settings', Nodevora_Settings::defaults(), '', false );
		update_option( 'nodevora_v2_schema', 1, false );
		add_rewrite_endpoint( 'loyalty-points', EP_ROOT | EP_PAGES );
		flush_rewrite_rules();
		if ( ! wp_next_scheduled( 'nodevora_v2_maintenance' ) ) {
			wp_schedule_event( time() + 60, 'hourly', 'nodevora_v2_maintenance' ); }
	}
	/** Reject caller-owned transactions before START: MySQL refuses SET TRANSACTION in an active transaction. */
	public static function begin( $snapshot = false ) {
		global $wpdb;
		if ( null !== self::$token ) {
			throw new RuntimeException( esc_html__( 'Nested Nodevora operation.', 'nodevora-customer-rewards-for-woocommerce' ) ); }
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Fresh session state and guarded SQL prevent partial writes after wpdb reconnect/replay.
		if ( '1' !== (string) $wpdb->get_var( 'SELECT @@session.autocommit' ) ) {
			throw new RuntimeException( esc_html__( 'Nodevora requires autocommit.', 'nodevora-customer-rewards-for-woocommerce' ) ); }
        self::$isolation=self::session_isolation();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Transaction control must execute on the owned connection; it is not cacheable.
		$isolation = $snapshot ? $wpdb->query( 'SET TRANSACTION ISOLATION LEVEL REPEATABLE READ' ) : $wpdb->query( 'SET TRANSACTION ISOLATION LEVEL READ COMMITTED' );
		if ( false === $isolation ) {
			throw new RuntimeException( esc_html__( 'Nodevora requires an idle autocommit connection.', 'nodevora-customer-rewards-for-woocommerce' ) );
		}
		$token = wp_generate_uuid4();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Fresh session state and guarded SQL prevent partial writes after wpdb reconnect/replay.
		if ( false === $wpdb->query( $wpdb->prepare( 'SET @nodevora_v2_token = %s', $token ) ) ) {
			throw new RuntimeException( esc_html__( 'Cannot establish database session.', 'nodevora-customer-rewards-for-woocommerce' ) ); }
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Fresh session state and guarded SQL prevent partial writes after wpdb reconnect/replay.
		self::$connection = (int) $wpdb->get_var( 'SELECT CONNECTION_ID()' );
		self::$token      = $token;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Prepared financial evidence or snapshot must be fresh for idempotency, expiration and atomic restore.
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			self::rollback();
			throw new RuntimeException( esc_html__( 'Cannot start Nodevora transaction.', 'nodevora-customer-rewards-for-woocommerce' ) ); }
		self::assert_session();
	}
	public static function guard() {
		global $wpdb;
		if ( null === self::$token ) {
			throw new RuntimeException( esc_html__( 'Missing Nodevora transaction.', 'nodevora-customer-rewards-for-woocommerce' ) ); }
		return $wpdb->prepare( '( @nodevora_v2_token = %s AND CONNECTION_ID() = %d )', self::$token, self::$connection );
	}
	private static function owns_session() {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Fresh session state and guarded SQL prevent partial writes after wpdb reconnect/replay.
        return '1' === (string) $wpdb->get_var( $wpdb->prepare( 'SELECT ( @nodevora_v2_token = %s AND CONNECTION_ID() = %d )', self::$token, self::$connection ) );
    }
	public static function assert_session() {
		global $wpdb;
		if ( ! self::owns_session() ) {
			throw new RuntimeException( esc_html__( 'Database session lost; operation not confirmed.', 'nodevora-customer-rewards-for-woocommerce' ) ); }
	}
	/** Only known prepared statements are accepted; every write includes the connection identity. */
	public static function write( $operation, $values ) {
		global $wpdb;
		if ( null === self::$token ) {
			throw new RuntimeException( esc_html__( 'Missing Nodevora transaction.', 'nodevora-customer-rewards-for-woocommerce' ) ); }
		$args = array_merge( $values, array( self::$token, self::$connection ) );
		switch ( $operation ) {
			case 'wallet_open':
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Fresh session state and guarded SQL prevent partial writes after wpdb reconnect/replay.
				$result = $wpdb->query( $wpdb->prepare( 'INSERT INTO %i (user_id) SELECT %d WHERE @nodevora_v2_token = %s AND CONNECTION_ID() = %d ON DUPLICATE KEY UPDATE user_id = user_id', $args[ 0 ], $args[ 1 ], $args[ 2 ], $args[ 3 ] ) );
				break;
			case 'wallet_update':
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Fresh session state and guarded SQL prevent partial writes after wpdb reconnect/replay.
				$result = $wpdb->query( $wpdb->prepare( 'UPDATE %i SET balance = %d, revision = revision + 1 WHERE user_id = %d AND @nodevora_v2_token = %s AND CONNECTION_ID() = %d', $args[ 0 ], $args[ 1 ], $args[ 2 ], $args[ 3 ], $args[ 4 ] ) );
				break;
			case 'lot_update':
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Fresh session state and guarded SQL prevent partial writes after wpdb reconnect/replay.
				$result = $wpdb->query( $wpdb->prepare( 'UPDATE %i SET remaining = %d WHERE id = %d AND @nodevora_v2_token = %s AND CONNECTION_ID() = %d', $args[ 0 ], $args[ 1 ], $args[ 2 ], $args[ 3 ], $args[ 4 ] ) );
				break;
			case 'lot_insert':
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Fresh session state and guarded SQL prevent partial writes after wpdb reconnect/replay.
				$result = $wpdb->query( $wpdb->prepare( 'INSERT INTO %i (user_id,event_id,remaining,expires_at) SELECT %d,%d,%d,%d WHERE @nodevora_v2_token = %s AND CONNECTION_ID() = %d', $args[ 0 ], $args[ 1 ], $args[ 2 ], $args[ 3 ], $args[ 4 ], $args[ 5 ], $args[ 6 ] ) );
				break;
			case 'event_insert':
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Fresh session state and guarded SQL prevent partial writes after wpdb reconnect/replay.
				$result = $wpdb->query( $wpdb->prepare( 'INSERT INTO %i (operation_key,user_id,kind,points,delta,balance_after,order_id,source_id,payload,created_at) SELECT %s,%d,%s,%d,%d,%d,%d,%d,%s,%s WHERE @nodevora_v2_token = %s AND CONNECTION_ID() = %d', $args[ 0 ], $args[ 1 ], $args[ 2 ], $args[ 3 ], $args[ 4 ], $args[ 5 ], $args[ 6 ], $args[ 7 ], $args[ 8 ], $args[ 9 ], $args[ 10 ], $args[ 11 ], $args[ 12 ] ) );
				break;
			case 'restore_wallet':
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Fresh session state and guarded SQL prevent partial writes after wpdb reconnect/replay.
				$result = $wpdb->query( $wpdb->prepare( 'INSERT INTO %i (user_id,balance,revision) SELECT %d,%d,%d WHERE @nodevora_v2_token = %s AND CONNECTION_ID() = %d', $args[ 0 ], $args[ 1 ], $args[ 2 ], $args[ 3 ], $args[ 4 ], $args[ 5 ] ) );
				break;
			case 'restore_event':
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Fresh session state and guarded SQL prevent partial writes after wpdb reconnect/replay.
				$result = $wpdb->query( $wpdb->prepare( 'INSERT INTO %i (id,operation_key,user_id,kind,points,delta,balance_after,order_id,source_id,payload,created_at) SELECT %d,%s,%d,%s,%d,%d,%d,%d,%d,%s,%s WHERE @nodevora_v2_token = %s AND CONNECTION_ID() = %d', $args[ 0 ], $args[ 1 ], $args[ 2 ], $args[ 3 ], $args[ 4 ], $args[ 5 ], $args[ 6 ], $args[ 7 ], $args[ 8 ], $args[ 9 ], $args[ 10 ], $args[ 11 ], $args[ 12 ], $args[ 13 ] ) );
				break;
			case 'restore_lot':
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Fresh session state and guarded SQL prevent partial writes after wpdb reconnect/replay.
				$result = $wpdb->query( $wpdb->prepare( 'INSERT INTO %i (id,user_id,event_id,remaining,expires_at) SELECT %d,%d,%d,%d,%d WHERE @nodevora_v2_token = %s AND CONNECTION_ID() = %d', $args[ 0 ], $args[ 1 ], $args[ 2 ], $args[ 3 ], $args[ 4 ], $args[ 5 ], $args[ 6 ], $args[ 7 ] ) );
				break;
			default:
				throw new InvalidArgumentException( esc_html__( 'Unknown Nodevora write.', 'nodevora-customer-rewards-for-woocommerce' ) );
		}
		if ( false === $result ) {
			throw new RuntimeException( esc_html__( 'Nodevora database write failed.', 'nodevora-customer-rewards-for-woocommerce' ) ); }
		self::$insert_id = (int) $wpdb->insert_id;
        if (in_array($operation,array('wallet_open','restore_wallet'),true)) {self::$cache_users[(int)$values[1]]=true;}
        if ('wallet_update'===$operation) {self::$cache_users[(int)$values[2]]=true;}
		self::assert_session();
		return $result;
	}
	public static function insert_id() { return self::$insert_id; }
	public static function rows( $operation, $args ) {
		global $wpdb;
		switch ( $operation ) {
			case 'event':
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Prepared financial evidence or snapshot must be fresh for idempotency, expiration and atomic restore.
				$result = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE operation_key = %s', $args[ 0 ], $args[ 1 ] ), ARRAY_A );
				break;
			case 'wallet':
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Prepared financial evidence or snapshot must be fresh for idempotency, expiration and atomic restore.
				$result = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE user_id = %d', $args[ 0 ], $args[ 1 ] ), ARRAY_A );
				break;
			case 'wallet_lock':
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Prepared locking read participates in the same financial transaction; cached rows cannot lock.
				$result = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE user_id = %d FOR UPDATE', $args[ 0 ], $args[ 1 ] ), ARRAY_A );
				break;
			case 'lots_lock':
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Prepared locking read participates in the same financial transaction; cached rows cannot lock.
				$result = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE user_id = %d AND remaining > 0 ORDER BY (expires_at = 0), expires_at, id FOR UPDATE', $args[ 0 ], $args[ 1 ] ), ARRAY_A );
				break;
			case 'source':
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Prepared financial evidence or snapshot must be fresh for idempotency, expiration and atomic restore.
				$result = $wpdb->get_results( $wpdb->prepare( 'SELECT id FROM %i WHERE source_id = %d AND kind = %s LIMIT 1', $args[ 0 ], $args[ 1 ], $args[ 2 ] ), ARRAY_A );
				break;
			case 'expired_source':
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Prepared financial evidence or snapshot must be fresh for idempotency, expiration and atomic restore.
				$result = $wpdb->get_results( $wpdb->prepare( 'SELECT COALESCE(SUM(points),0) AS points FROM %i WHERE source_id = %d AND kind = %s', $args[ 0 ], $args[ 1 ], $args[ 2 ] ), ARRAY_A );
				break;
			case 'expired_balance':
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Prepared financial evidence or snapshot must be fresh for idempotency, expiration and atomic restore.
				$result = $wpdb->get_results( $wpdb->prepare( 'SELECT COALESCE(SUM(remaining),0) AS expired FROM %i WHERE user_id = %d AND expires_at > 0 AND expires_at <= %d', $args[ 0 ], $args[ 1 ], $args[ 2 ] ), ARRAY_A );
				break;
			case 'due':
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Prepared financial evidence or snapshot must be fresh for idempotency, expiration and atomic restore.
				$result = $wpdb->get_results( $wpdb->prepare( 'SELECT DISTINCT user_id FROM %i WHERE remaining > 0 AND expires_at > 0 AND expires_at <= %d LIMIT 100', $args[ 0 ], $args[ 1 ] ), ARRAY_A );
				break;
			case 'snapshot':
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Prepared financial evidence or snapshot must be fresh for idempotency, expiration and atomic restore.
				$result = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i LIMIT %d', $args[ 0 ], $args[ 1 ] ), ARRAY_A );
				break;
			case 'empty_lock':
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Prepared locking read participates in the same financial transaction; cached rows cannot lock.
				$result = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i FOR UPDATE', $args[ 0 ] ), ARRAY_A );
				break;
			default:
				throw new InvalidArgumentException( esc_html__( 'Unknown Nodevora read.', 'nodevora-customer-rewards-for-woocommerce' ) );
		}
		if ( $wpdb->last_error || ! is_array( $result ) ) {
			throw new RuntimeException( esc_html__( 'Nodevora database read failed.', 'nodevora-customer-rewards-for-woocommerce' ) ); }
		return $result;
	}
	public static function commit() {
		global $wpdb;
		self::assert_session();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Transaction control must execute on the owned connection; it is not cacheable.
		$ok = $wpdb->query( 'COMMIT' );
        // COMMIT must close the owned transaction. Probe using the original session default,
        // so the check does not force the caller's next transaction to READ COMMITTED.
		if ( false === $ok || !self::owns_session() || !self::closed_transaction() ) {
			self::rollback();
			throw new RuntimeException( esc_html__( 'Database commit outcome unknown; retry the same operation.', 'nodevora-customer-rewards-for-woocommerce' ) );
		}
		self::$token      = null;
		self::$connection = 0;
        self::$isolation = null;
        foreach (self::$cache_users as $user=>$unused) {wp_cache_delete('history:'.$user,'nodevora');wp_cache_delete('totals:'.$user,'nodevora');}
        self::$cache_users=array();
	}
	public static function rollback() {
		global $wpdb;
		$failed = false;
		if ( null !== self::$token ) {
			$owned = self::owns_session();
			if ( $owned ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Transaction control must execute on the owned connection; it is not cacheable.
				$failed = false === $wpdb->query( 'ROLLBACK' ); }
		}
		self::$token      = null;
		self::$connection = 0;
        self::$isolation = null;
        self::$cache_users=array();
		if ( $failed ) {
			throw new RuntimeException( esc_html__( 'Rollback unconfirmed. Stop this request and retry the same operation.', 'nodevora-customer-rewards-for-woocommerce' ) ); }
	}

	public static function event( $key ) {
		global $wpdb;
		$r = self::rows( 'event', array( self::table( 'events' ), $key ) );
		return $r[0] ?? null;
	}
	public static function wallet( $user ) {
		global $wpdb;
		$r = self::rows( 'wallet', array( self::table( 'wallets' ), $user ) );
		return $r[0] ?? array(
			'user_id'  => $user,
			'balance'  => 0,
			'revision' => 0,
		);
	}
    /** Display-only history. Financial authorization never uses this cache. */
    public static function history($user,$before=0) {
        global $wpdb;
        $revision=self::wallet($user)['revision'];$key='history:'.$user;
        $enabled=Nodevora_Settings::get()['display_cache'] && !$before && null===self::$token;
        $cached=$enabled?wp_cache_get($key,'nodevora'):false;
        if(is_array($cached)&&$cached['revision']===$revision){return $cached['rows'];}
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Prepared custom-table display query has revision-aware caching and commit invalidation.
        $rows=$wpdb->get_results($wpdb->prepare('SELECT * FROM %i WHERE user_id = %d AND (%d = 0 OR id < %d) ORDER BY id DESC LIMIT 50',self::table('events'),$user,$before,$before),ARRAY_A);
        if($wpdb->last_error||!is_array($rows)){throw new RuntimeException(esc_html__('Nodevora database read failed.','nodevora-customer-rewards-for-woocommerce'));}
        if($enabled){wp_cache_set($key,array('revision'=>$revision,'rows'=>$rows),'nodevora',60);}return $rows;
    }
    /** Both lifetime display counters in one aggregate, instead of paginating the full journal twice. */
    public static function totals($user) {
        global $wpdb;
        $revision=self::wallet($user)['revision'];$key='totals:'.$user;$enabled=Nodevora_Settings::get()['display_cache'] && null===self::$token;
        $cached=$enabled?wp_cache_get($key,'nodevora'):false;
        if(is_array($cached)&&$cached['revision']===$revision){return $cached['totals'];}
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Prepared custom-table display query has revision-aware caching and commit invalidation.
        $rows=$wpdb->get_results($wpdb->prepare('SELECT kind, SUM(points) AS points FROM %i WHERE user_id = %d AND kind IN (%s,%s) GROUP BY kind',self::table('events'),$user,'credit','debit'),ARRAY_A);
        if($wpdb->last_error||!is_array($rows)){throw new RuntimeException(esc_html__('Nodevora database read failed.','nodevora-customer-rewards-for-woocommerce'));}
        $totals=array('credit'=>0,'debit'=>0);foreach($rows as$row){$totals[$row['kind']]=(int)$row['points'];}
        if($enabled){wp_cache_set($key,array('revision'=>$revision,'totals'=>$totals),'nodevora',60);}return $totals;
    }

    /** Legacy stores are preserved; no implicit conversion or empty-wallet cutover. */
    public static function legacy_detected() {
        global $wpdb;
        if(false!==get_option('rewardly_loyalty_settings',false)){return true;}
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Fresh schema inspection must detect legacy storage and verify InnoDB before activation.
        $found=$wpdb->get_var($wpdb->prepare('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN (%s,%s,%s) LIMIT 1',$wpdb->prefix.'rewardly_loyalty_log',$wpdb->prefix.'rewardly_loyalty_v2_wallet',$wpdb->prefix.'rewardly_loyalty_v2_event'));
        if($wpdb->last_error){throw new RuntimeException(esc_html__('Nodevora database read failed.','nodevora-customer-rewards-for-woocommerce'));}return null!==$found;
    }
    private static function session_isolation() {
        global $wpdb;
        // MySQL uses transaction_isolation; older MySQL and MariaDB expose tx_isolation.
        $before=$wpdb->suppress_errors(true);
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Fresh session state and guarded SQL prevent partial writes after wpdb reconnect/replay.
        $value=$wpdb->get_var('SELECT @@SESSION.transaction_isolation');
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Fresh session state and guarded SQL prevent partial writes after wpdb reconnect/replay.
        if(null===$value){$value=$wpdb->get_var('SELECT @@SESSION.tx_isolation');}
        $wpdb->suppress_errors($before);
        if(!in_array($value,array('READ-UNCOMMITTED','READ-COMMITTED','REPEATABLE-READ','SERIALIZABLE'),true)){throw new RuntimeException(esc_html__('Cannot verify the database isolation level.','nodevora-customer-rewards-for-woocommerce'));}
        return $value;
    }
    private static function closed_transaction() {
        global $wpdb;
        switch(self::$isolation) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Transaction control must execute on the owned connection; it is not cacheable.
            case 'READ-UNCOMMITTED':return false!==$wpdb->query('SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED');
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Transaction control must execute on the owned connection; it is not cacheable.
            case 'READ-COMMITTED':return false!==$wpdb->query('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Transaction control must execute on the owned connection; it is not cacheable.
            case 'REPEATABLE-READ':return false!==$wpdb->query('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Transaction control must execute on the owned connection; it is not cacheable.
            case 'SERIALIZABLE':return false!==$wpdb->query('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
        }
        throw new RuntimeException(esc_html__('Cannot verify the database isolation level.','nodevora-customer-rewards-for-woocommerce'));
    }

}
