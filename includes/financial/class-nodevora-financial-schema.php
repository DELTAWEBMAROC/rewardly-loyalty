<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
class Nodevora_Financial_Schema {
	const VERSION = 1;
	public static function definitions() {
		return array(
			'wallet' => 'user_id BIGINT UNSIGNED PRIMARY KEY,balance BIGINT NOT NULL DEFAULT 0,reserved BIGINT NOT NULL DEFAULT 0,debt BIGINT NOT NULL DEFAULT 0,revision BIGINT NOT NULL DEFAULT 0,state VARCHAR(24) NOT NULL,migration_version INT NOT NULL DEFAULT 0,checksum CHAR(64) NULL',
			'event' => 'id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id BIGINT UNSIGNED NOT NULL,operation_key VARBINARY(191) NOT NULL,kind VARCHAR(24) NOT NULL,payload_hash CHAR(64) NOT NULL,original_event_id BIGINT UNSIGNED NULL,gross_points BIGINT NOT NULL,delta_balance BIGINT NOT NULL DEFAULT 0,delta_reserved BIGINT NOT NULL DEFAULT 0,delta_debt BIGINT NOT NULL DEFAULT 0,result LONGTEXT NOT NULL,created_at DATETIME NOT NULL,UNIQUE KEY operation_key(operation_key),KEY user_history(user_id,id),KEY original_event(original_event_id,kind)',
			'lot' => 'id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id BIGINT UNSIGNED NOT NULL,source_event_id BIGINT UNSIGNED NOT NULL,remaining BIGINT NOT NULL,reserved BIGINT NOT NULL DEFAULT 0,earned_at DATETIME NULL,expires_at DATETIME NULL,provenance LONGTEXT NOT NULL,KEY eligible(user_id,expires_at,id)',
			'allocation' => 'event_id BIGINT UNSIGNED NOT NULL,lot_id BIGINT UNSIGNED NOT NULL,leg INT NOT NULL,reservation_key VARBINARY(191) NULL,original_event_id BIGINT UNSIGNED NULL,original_lot_id BIGINT UNSIGNED NULL,reason VARCHAR(24) NOT NULL,delta_remaining BIGINT NOT NULL,delta_reserved BIGINT NOT NULL,PRIMARY KEY(event_id,lot_id,leg),KEY reservation(reservation_key,lot_id),KEY restoration(original_event_id,original_lot_id,reason)',
			'order_state' => 'order_id BIGINT UNSIGNED PRIMARY KEY,user_id BIGINT UNSIGNED NOT NULL,intent VARBINARY(96) NOT NULL,state VARCHAR(32) NOT NULL,fingerprint CHAR(64) NOT NULL,points BIGINT NOT NULL DEFAULT 0,discount_minor BIGINT NOT NULL DEFAULT 0,currency CHAR(3) NOT NULL,debit_event_id BIGINT UNSIGNED NULL,generation BIGINT NOT NULL DEFAULT 0,revision BIGINT NOT NULL DEFAULT 0,data LONGTEXT NOT NULL,updated_at DATETIME NOT NULL,KEY recovery(state,updated_at)',
			'outbox' => 'id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,event_id BIGINT UNSIGNED NOT NULL,user_id BIGINT UNSIGNED NOT NULL,effect VARCHAR(24) NOT NULL,state VARCHAR(24) NOT NULL DEFAULT \'pending\',attempts INT NOT NULL DEFAULT 0,lease_until DATETIME NULL,payload LONGTEXT NOT NULL,UNIQUE KEY delivery(event_id,effect),KEY due(state,lease_until)',
			'snapshot' => 'user_id BIGINT UNSIGNED NOT NULL,version INT NOT NULL,checksum CHAR(64) NOT NULL,payload LONGBLOB NOT NULL,created_at DATETIME NOT NULL,PRIMARY KEY(user_id,version)',
		);
	}
	/** Explicit installer only: no activation/production migration side effects. */
	public static function install( Nodevora_Financial_DB $db ) {
		foreach ( self::definitions() as $name => $definition ) { $db->query( 'CREATE TABLE IF NOT EXISTS ' . $db->table( $name ) . ' (' . $definition . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4' ); }
		self::verify( $db );
	}
	public static function verify( Nodevora_Financial_DB $db ) {
		foreach ( self::definitions() as $name => $definition ) {
			$info = $db->one( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?', array( $db->prefix . $name ) );
			if ( ! $info || 'InnoDB' !== $info['ENGINE'] ) { throw new Nodevora_Financial_Error( 'Financial schema is not ready.' ); }
			// Compare required columns and exact index shape, not just table presence.
			$columns = $db->query( 'SHOW COLUMNS FROM ' . $db->table( $name ) );
			$actual = array_column( $columns, 'Field' );
			preg_match_all( '/(?:^|,)([a-z_]+) (?:BIGINT|INT|CHAR|VARBINARY|VARCHAR|LONGTEXT|LONGBLOB|DATETIME)/', $definition, $matches );
			if ( array_diff( $matches[1], $actual ) ) { throw new Nodevora_Financial_Error( 'Financial columns are incomplete.' ); }
			preg_match_all( '/(?:^|,)([a-z_]+) (BIGINT(?: UNSIGNED)?|INT|CHAR\(\d+\)|VARCHAR\(\d+\)|VARBINARY\(\d+\)|LONGTEXT|LONGBLOB|DATETIME)/', $definition, $types, PREG_SET_ORDER );
			$actual_types = array_column( $columns, 'Type', 'Field' );
			foreach ( $types as $type ) {
				$found = preg_replace( '/^(bigint|int)\(\d+\)/', '$1', strtolower( $actual_types[$type[1]] ) );
				if ( strtolower( $type[2] ) !== $found ) { throw new Nodevora_Financial_Error( 'Financial column type mismatch.' ); }
			}
			$indexes = $db->query( 'SHOW INDEX FROM ' . $db->table( $name ) );
			$unique = array(); foreach ( $indexes as $index ) { if ( '0' === (string) $index['Non_unique'] ) { $unique[$index['Key_name']][] = $index['Column_name']; } }
			$required = array( 'wallet'=>array('user_id'), 'event'=>array('operation_key'), 'lot'=>array('id'), 'allocation'=>array('event_id','lot_id','leg'), 'order_state'=>array('order_id'), 'outbox'=>array('event_id','effect'), 'snapshot'=>array('user_id','version') );
			if ( ! in_array( $required[$name], $unique, true ) ) { throw new Nodevora_Financial_Error( 'Financial uniqueness is missing.' ); }
		}
	}
}
