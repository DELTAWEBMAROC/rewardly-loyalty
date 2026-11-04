<?php
/** Dedicated primary connection: never retry an individual statement or reconnect in a transaction. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
class Nodevora_Financial_Error extends RuntimeException {}
class Nodevora_Financial_Unknown extends Nodevora_Financial_Error {}
/** Trusted, already-localized merchant instruction; never use for database diagnostics. */
class Nodevora_User_Error extends Nodevora_Financial_Error {}
class Nodevora_Financial_DB {
	protected $connection;
	public $prefix;
	public function __construct() {
		global $wpdb;
		if ( PHP_INT_SIZE < 8 || get_class( $wpdb ) !== 'wpdb' || ! preg_match( '/^[a-zA-Z0-9_]+$/D', $wpdb->prefix ) ) {
			throw new Nodevora_Financial_Error( 'Unsupported financial database configuration.' );
		}
		$host = $wpdb->parse_db_host( DB_HOST );
		if ( ! $host ) { throw new Nodevora_Financial_Error( 'Invalid primary database host.' ); }
		$this->prefix = $wpdb->prefix . 'rewardly_loyalty_v2_';
		$this->connection = mysqli_init();
		$flags = defined( 'MYSQL_CLIENT_FLAGS' ) ? MYSQL_CLIENT_FLAGS : 0;
		try {
			$ok = @$this->connection->real_connect( $host[0], DB_USER, DB_PASSWORD, DB_NAME, $host[1] ?: 3306, $host[2] ?: null, $flags );
			if ( ! $ok || ! $this->connection->set_charset( 'utf8mb4' ) ) { throw new Exception(); }
			if ( ! $this->connection->query( "SET SESSION sql_mode='STRICT_ALL_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'" ) ) { throw new Exception(); }
		} catch ( Throwable $e ) { throw new Nodevora_Financial_Error( 'Financial database unavailable.' ); }
	}
	public function table( $name ) {
		if ( ! in_array( $name, array( 'wallet', 'event', 'lot', 'allocation', 'order_state', 'outbox', 'snapshot' ), true ) ) { throw new Nodevora_Financial_Error( 'Unknown financial table.' ); }
		return '`' . $this->prefix . $name . '`';
	}
	public function query( $sql, $params = array() ) {
		try {
			$stmt = $this->connection->prepare( $sql );
			if ( ! $stmt ) { throw new Exception(); }
			if ( $params ) {
				$types = ''; $args = array();
				foreach ( $params as $value ) { $types .= is_int( $value ) ? 'i' : 's'; $args[] = $value; }
				$stmt->bind_param( $types, ...$args );
			}
			if ( ! $stmt->execute() ) { throw new Exception(); }
			if ( $stmt->field_count ) {
				$result = $stmt->get_result();
				if ( ! $result ) { throw new Exception(); }
				$rows = $result->fetch_all( MYSQLI_ASSOC ); $result->free(); $stmt->close(); return $rows;
			}
			$count = $stmt->affected_rows; $stmt->close(); return $count;
		} catch ( Throwable $e ) {
			// No SQL, parameter values or database credentials in exception messages.
			throw new Nodevora_Financial_Error( 'Financial SQL operation failed.', (int) $this->connection->errno );
		}
	}
	public function one( $sql, $params = array() ) { $rows = $this->query( $sql, $params ); return $rows ? $rows[0] : null; }
	public function id() { return (int) $this->connection->insert_id; }
	public function begin() { try { if ( ! $this->connection->begin_transaction() ) { throw new Exception(); } } catch ( Throwable $e ) { throw new Nodevora_Financial_Error( 'Cannot begin financial transaction.' ); } }
	/** Vue cohérente en lecture pour l’export, sur la connexion dédiée. */
	public function begin_snapshot() { try { if ( ! $this->connection->begin_transaction( MYSQLI_TRANS_START_WITH_CONSISTENT_SNAPSHOT ) ) { throw new Exception(); } } catch ( Throwable $e ) { throw new Nodevora_Financial_Error( 'Cannot start a consistent backup snapshot.' ); } }
	public function commit() {
		try { if ( ! $this->connection->commit() ) { throw new Exception(); } } catch ( Throwable $e ) { throw new Nodevora_Financial_Unknown( 'Commit outcome requires resolution.' ); }
	}
	public function rollback() { try { $this->connection->rollback(); } catch ( Throwable $ignored ) {} }
	public function close() { if ( $this->connection ) { $this->connection->close(); $this->connection = null; } }
	public function __destruct() { if ( $this->connection ) { $this->close(); } }
}
