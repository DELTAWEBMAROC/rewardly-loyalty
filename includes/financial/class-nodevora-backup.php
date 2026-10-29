<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/** Sauvegarde Nodevora NDJSON bornée ; aucun SQL ni fichier public dans le format. */
class Nodevora_Backup {
	const FORMAT = 1;
	const MAX_BYTES = 33554432;
	const MAX_ROWS = 100000;
	public static function table_map( $db ) {
		global $wpdb;
		$map = array();
		foreach ( Nodevora_Financial_Schema::definitions() as $key => $unused ) { $map[$key] = $db->prefix . $key; }
		$map['history'] = $wpdb->prefix . 'rewardly_loyalty_log';
		return $map;
	}
	private static function append( $stream, $line, &$count ) {
		$bytes = strlen( $line );
		if ( $bytes > 1048576 || $count + $bytes > self::MAX_BYTES || fwrite( $stream, $line ) !== $bytes ) { throw new Nodevora_Financial_Error( 'Backup exceeds the supported size or could not be written.' ); }
		$count += $bytes;
	}
	public static function create() {
		if ( ! current_user_can( 'manage_options' ) || is_multisite() ) { throw new Nodevora_Financial_Error( 'Administrator backup permission required.' ); }
		$db = new Nodevora_Financial_DB(); $stream = tmpfile(); $bytes = 0; $rows = 0;
		if ( ! $stream ) { $db->close(); throw new Nodevora_Financial_Error( 'Private backup storage unavailable.' ); }
		try {
			$tables = self::table_map( $db );
			foreach ( $tables as $name => $table ) { if ( ! $db->one( 'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?', array( $table ) ) ) { unset( $tables[$name] ); } }
			$verified = count( $tables ) >= count( Nodevora_Financial_Schema::definitions() );
			if ( $verified ) { Nodevora_Financial_Schema::verify( $db ); }
			foreach ( $tables as $table ) {
				$info = $db->one( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?', array( $table ) );
				if ( ! $info || 'InnoDB' !== $info['ENGINE'] ) { throw new Nodevora_Financial_Error( 'A consistent Nodevora backup requires transactional tables.' ); }
			}
			$db->query( 'SET TRANSACTION ISOLATION LEVEL REPEATABLE READ' );
			$db->begin_snapshot();
			$hash = hash_init( 'sha256' ); $reference_cache = array();
			$write = static function( $record ) use ( $stream, &$bytes, $hash ) { $line = json_encode( $record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE ) . "\n"; self::append( $stream, $line, $bytes ); hash_update( $hash, $line ); };
			$write( array( 'format' => 'nodevora', 'version' => self::FORMAT, 'schema' => Nodevora_Financial_Schema::VERSION, 'plugin' => NODEVORA_CUSTOMER_REWARDS_VERSION, 'financial_schema_verified' => $verified, 'created_at' => gmdate( 'c' ) ) );
			foreach ( $tables as $key => $table ) {
				// Pagination stable dans la même vue transactionnelle ; plafond explicite, jamais de truncature silencieuse.
				for ( $offset = 0; $offset <= self::MAX_ROWS; $offset += 250 ) {
					$batch = $db->query( 'SELECT * FROM `' . $table . '` ORDER BY ' . ( 'wallet' === $key ? 'user_id' : ( 'snapshot' === $key ? 'user_id,version' : ( 'allocation' === $key ? 'event_id,lot_id,leg' : ( 'order_state' === $key ? 'order_id' : 'id' ) ) ) ) . ' LIMIT 250 OFFSET ' . $offset );
					foreach ( $batch as $row ) { if ( ++$rows > self::MAX_ROWS ) { throw new Nodevora_Financial_Error( 'Backup row limit exceeded; request an operator-assisted export.' ); } $write( array( 'table' => $key, 'row' => $row, 'references' => self::references( $db, $key, $row, $reference_cache ) ) ); }
					if ( count( $batch ) < 250 ) { break; }
				}
			}
			// Inclure les seules métadonnées de fidélité, jamais les données générales WordPress.
			global $wpdb;
			$meta = array( 'user_meta' => array( $wpdb->usermeta, 'umeta_id', '^(rewardly_loyalty_|_rewardly_bonus_)' ), 'post_meta' => array( $wpdb->postmeta, 'meta_id', '^(_rewardly|_nodevora)' ), 'comment_meta' => array( $wpdb->commentmeta, 'meta_id', '^_rewardly_bonus_' ), 'order_meta' => array( $wpdb->prefix . 'wc_orders_meta', 'id', '^(_rewardly|_nodevora)' ) );
			foreach ( $meta as $type => $spec ) {
				$exists = $db->one( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?', array( $spec[0] ) );
				if ( ! $exists ) { continue; } if ( 'InnoDB' !== $exists['ENGINE'] ) { throw new Nodevora_Financial_Error( 'Loyalty metadata is not transactional.' ); }
				for ( $cursor = 0, $n = 0; $n <= self::MAX_ROWS; $n += 250 ) {
					$batch = $db->query( 'SELECT * FROM `' . $spec[0] . '` WHERE `' . $spec[1] . '`>? AND meta_key REGEXP ? ORDER BY `' . $spec[1] . '` LIMIT 250', array( $cursor, $spec[2] ) );
					foreach ( $batch as $row ) { $cursor = (int) $row[$spec[1]]; if ( ++$rows > self::MAX_ROWS ) { throw new Nodevora_Financial_Error( 'Backup row limit exceeded.' ); } $write( array( 'table' => $type, 'row' => $row, 'references' => self::references( $db, $type, $row, $reference_cache ) ) ); }
					if ( count( $batch ) < 250 ) { break; }
				}
			}
			$options = array( 'rewardly_loyalty_settings', 'nodevora_financial_authority', Nodevora_Financial_Setup::OPTION, 'nodevora_access_policy' );
			foreach ( $options as $option ) {
				$row = $db->one( 'SELECT option_value FROM `' . $wpdb->options . '` WHERE option_name=?', array( $option ) );
				if ( $row ) { $write( array( 'option' => $option, 'value' => $row['option_value'] ) ); }
			}
			$db->rollback();
			$trailer = json_encode( array( 'complete' => true, 'rows' => $rows, 'sha256' => hash_final( $hash ) ), JSON_THROW_ON_ERROR ) . "\n";
			self::append( $stream, $trailer, $bytes ); rewind( $stream ); return $stream;
		} catch ( Throwable $e ) { $db->rollback(); fclose( $stream ); throw $e; } finally { $db->close(); }
	}
	/** Les identités référencées sont vérifiées, sans exporter de comptes WordPress. */
	public static function references( $db, $type, $row, &$cache ) {
		global $wpdb;
		$ids = array();
		if ( ! empty( $row['user_id'] ) ) { $ids['user'] = (int) $row['user_id']; }
		if ( ! empty( $row['order_id'] ) ) { $ids['order'] = (int) $row['order_id']; }
		if ( 'post_meta' === $type ) { $ids['post'] = (int) $row['post_id']; }
		if ( 'comment_meta' === $type ) { $ids['comment'] = (int) $row['comment_id']; }
		$refs = array();
		foreach ( $ids as $kind => $id ) {
			$key = $kind . ':' . $id;
			if ( ! isset( $cache[$key] ) ) {
				if ( 'user' === $kind ) { $identity = $db->one( 'SELECT ID,user_login,user_email FROM `' . $wpdb->users . '` WHERE ID=?', array( $id ) ); }
				elseif ( 'comment' === $kind ) { $identity = $db->one( 'SELECT comment_ID,comment_post_ID,user_id FROM `' . $wpdb->comments . '` WHERE comment_ID=?', array( $id ) ); }
				elseif ( 'order' === $kind ) {
					$exists = $db->one( 'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?', array( $wpdb->prefix . 'wc_orders' ) );
					$identity = $exists ? $db->one( 'SELECT id,type,parent_order_id,customer_id,total_amount,currency FROM `' . $wpdb->prefix . 'wc_orders` WHERE id=?', array( $id ) ) : null;
					if ( ! $identity ) { $identity = $db->one( 'SELECT ID,post_type,post_name,post_parent FROM `' . $wpdb->posts . '` WHERE ID=?', array( $id ) ); }
				} else { $identity = $db->one( 'SELECT ID,post_type,post_name,post_parent FROM `' . $wpdb->posts . '` WHERE ID=?', array( $id ) ); }
				if ( ! $identity ) { throw new Nodevora_Financial_Error( 'A referenced account or order is missing.' ); }
				$cache[$key] = hash( 'sha256', json_encode( $identity, JSON_THROW_ON_ERROR ) );
			}
			$refs[$key] = $cache[$key];
		}
		return $refs;
	}

	/** Premier passage : format, intégrité et lignes bornées, sans aucune écriture. */
	public static function inspect( $path, $visitor = null ) {
		if ( ! is_file( $path ) || filesize( $path ) > self::MAX_BYTES || filesize( $path ) < 10 ) { throw new Nodevora_Financial_Error( 'Unsupported backup size.' ); }
		$f = fopen( $path, 'rb' ); $hash = hash_init( 'sha256' ); $rows = 0; $header = null; $trailer = null; $counts = array();
		try {
			for ( $line_number = 0; $line_number < self::MAX_ROWS + 10; $line_number++ ) {
				$line = fgets( $f, 1048577 ); if ( false === $line ) { break; }
				if ( strlen( $line ) > 1048576 || substr( $line, -1 ) !== "\n" ) { throw new Nodevora_Financial_Error( 'Invalid backup record.' ); }
				$r = json_decode( $line, true, 32, JSON_THROW_ON_ERROR );
				if ( ! is_array( $r ) ) { throw new Nodevora_Financial_Error( 'Invalid backup record.' ); }
				if ( null === $header ) {
					if ( ( $r['format'] ?? '' ) !== 'nodevora' || ( $r['version'] ?? null ) !== self::FORMAT || ( $r['schema'] ?? null ) !== Nodevora_Financial_Schema::VERSION || ( $r['plugin'] ?? '' ) !== NODEVORA_CUSTOMER_REWARDS_VERSION ) { throw new Nodevora_Financial_Error( 'Incompatible backup format, schema or plugin version.' ); } $header = $r;
				} elseif ( isset( $r['complete'] ) ) {
					if ( true !== $r['complete'] || $r['rows'] !== $rows || ! is_string( $r['sha256'] ) || ! hash_equals( hash_final( $hash ), $r['sha256'] ) || false !== fgets( $f ) ) { throw new Nodevora_Financial_Error( 'Backup integrity verification failed.' ); } $trailer = $r; break;
				} elseif ( isset( $r['table'], $r['row'] ) && is_array( $r['row'] ) ) { if ( array_diff( array_keys($r), array('table','row','references') ) ) { throw new Nodevora_Financial_Error('Ambiguous backup record.'); } if ( ++$rows > self::MAX_ROWS ) { throw new Nodevora_Financial_Error( 'Backup row limit exceeded.' ); } $counts[$r['table']] = 1 + ( $counts[$r['table']] ?? 0 );
				} elseif ( ! isset( $r['option'], $r['value'] ) || ! is_string( $r['value'] ) || array_diff( array_keys($r), array('option','value') ) ) { throw new Nodevora_Financial_Error( 'Unknown backup record.' ); }
				if ( $visitor && $line_number > 0 ) { $visitor( $r ); }
				hash_update( $hash, $line );
			}
			if ( ! $header || ! $trailer ) { throw new Nodevora_Financial_Error( 'Incomplete backup refused.' ); }
			return array( 'header' => $header, 'tables' => $counts, 'rows' => $rows, 'bytes' => filesize( $path ), 'sha256' => hash_file( 'sha256', $path ) );
		} finally { fclose( $f ); }
	}
}
