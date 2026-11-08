<?php
/** Single points engine for every entry point. @package Nodevora */
if ( ! defined( 'ABSPATH' ) ) {
	exit; }
class Nodevora_Engine {
 private static $notifications=array();
	const LIMIT = 1000000000;
	public static function apply( $user, $key, $kind, $points, $details = array() ) {
		global $wpdb;
		$user = (int) $user;
		if ( $user < 1 || ! get_userdata( $user ) || ! is_int( $points ) || $points < 0 || $points > self::LIMIT || ! preg_match( '/^[a-zA-Z0-9:._-]{1,191}$/D', $key ) || ! in_array( $kind, array( 'credit', 'debit', 'reverse', 'restore', 'control', 'expire' ), true ) ) {
			throw new InvalidArgumentException( esc_html__( 'Invalid points operation.', 'nodevora-customer-rewards-for-woocommerce' ) );
		}
		$request    = hash( 'sha256', wp_json_encode( array( $user, $kind, $points, $details ) ) );
		self::$notifications=array();
		$committing = false;
		try {
			Nodevora_Store::begin();
			Nodevora_Store::write( 'wallet_open', array( Nodevora_Store::table( 'wallets' ), $user ) );
			$wallet = Nodevora_Store::rows( 'wallet_lock', array( Nodevora_Store::table( 'wallets' ), $user ) );
			Nodevora_Store::assert_session();
			if ( ! $wallet ) {
				throw new RuntimeException( esc_html__( 'Wallet lock unavailable.', 'nodevora-customer-rewards-for-woocommerce' ) ); }
			$old = Nodevora_Store::event( $key );
			if ( $old ) {
				self::match_request( $old, $request );
				Nodevora_Store::rollback();
				return $old; }
			if ( ! empty( $details['order_id'] ) && in_array( $kind, array( 'credit', 'debit' ), true ) && Nodevora_Store::event( 'order:' . $details['order_id'] . ':close' ) ) {
				throw new RuntimeException( esc_html__( 'Closed order cannot acquire or earn points.', 'nodevora-customer-rewards-for-woocommerce' ) ); }
			$balance = (int) $wallet[0]['balance'];
			$lots    = Nodevora_Store::rows( 'lots_lock', array( Nodevora_Store::table( 'lots' ), $user ) );
			Nodevora_Store::assert_session();
			foreach ( $lots as &$lot ) {
				if ( (int) $lot['expires_at'] > 0 && (int) $lot['expires_at'] <= time() ) {
					$expired  = (int) $lot['remaining'];
					$balance -= $expired;
					self::record( $user, 'expire:lot:' . $lot['id'], 'expire', $expired, -$expired, $balance, array( 'source_id' => (int) $lot['event_id'] ), '' );
					self::remaining( $lot, 0 );
				}
			}
			unset( $lot );
			$delta       = 0;
			$new_lots    = array();
			$allocations = array();
			if ( 'credit' === $kind ) {
				$delta      = $points;
				$days       = Nodevora_Settings::get()['expiration_days'];
				$new_lots[] = array(
					'remaining'  => min( $points, max( 0, $balance + $points ) ),
					'expires_at' => $days ? time() + $days * DAY_IN_SECONDS : 0,
					'event_id'   => 0,
				);
			} elseif ( 'debit' === $kind ) {
				if ( $points > max( 0, $balance ) ) {
					throw new RuntimeException( esc_html__( 'Insufficient points.', 'nodevora-customer-rewards-for-woocommerce' ) ); }
				$delta       = -$points;
				$allocations = self::consume( $lots, $points );
			} elseif ( in_array( $kind, array( 'reverse', 'restore' ), true ) ) {
				$source   = Nodevora_Store::event( $details['source_key'] ?? '' );
				$expected = 'reverse' === $kind ? 'credit' : 'debit';
				if ( ! $source || (int) $source['user_id'] !== $user || $source['kind'] !== $expected ) {
					throw new RuntimeException( esc_html__( 'Original operation does not match.', 'nodevora-customer-rewards-for-woocommerce' ) ); }
				$details['source_id'] = (int) $source['id'];
				$prior                = Nodevora_Store::rows( 'source', array( Nodevora_Store::table( 'events' ), $source['id'], $kind ) );
				if ( $prior ) {
					throw new RuntimeException( esc_html__( 'Original operation already reversed.', 'nodevora-customer-rewards-for-woocommerce' ) ); }
				if ( 'reverse' === $kind ) {
					$expired = Nodevora_Store::rows( 'expired_source', array( Nodevora_Store::table( 'events' ), $source['id'], 'expire' ) );
					$points  = max( 0, (int) $source['points'] - (int) $expired[0]['points'] );
					// Remove this entitlement's unspent lots first. Consumed rewards become an explicit debt.
					usort(
						$lots,
						static function ( $a, $b ) use ( $source ) {
							return ( (int) $b['event_id'] === (int) $source['id'] ) <=> ( (int) $a['event_id'] === (int) $source['id'] );
						}
					);
					self::consume( $lots, $points );
					$delta = -$points;
				} else {
					$p    = json_decode( $source['payload'], true );
					$debt = max( 0, -$balance );
					foreach ( $p['allocations'] ?? array() as $allocation ) {
						$n          = $allocation['points'];
						$delta     += $n;
						$settled    = min( $n, $debt );
						$debt      -= $settled;
						$new_lots[] = array(
							'remaining'  => $n - $settled,
							'expires_at' => $allocation['expires_at'],
							'event_id'   => $allocation['event_id'],
						);
					}
					$points = (int) $source['points'];
				}
			}
			$balance += $delta;
			if ( abs( $balance ) > self::LIMIT ) {
				throw new RuntimeException( esc_html__( 'Wallet limit exceeded.', 'nodevora-customer-rewards-for-woocommerce' ) ); }
			$event_id = self::record( $user, $key, $kind, $points, $delta, $balance, $details, $request, $allocations );
			foreach ( $new_lots as $lot ) {
				if ( ! $lot['remaining'] ) {
					continue; }
				Nodevora_Store::write( 'lot_insert', array( Nodevora_Store::table( 'lots' ), $user, $lot['event_id'] ?: $event_id, $lot['remaining'], $lot['expires_at'] ) );
				$lot['id'] = Nodevora_Store::insert_id();
				if ( $lot['expires_at'] && $lot['expires_at'] <= time() ) {
					$expired = (int) $lot['remaining']; $balance -= $expired;
					self::record( $user, 'expire:lot:' . $lot['id'], 'expire', $expired, -$expired, $balance, array( 'source_id' => (int) ( $lot['event_id'] ?: $event_id ) ), '' );
					self::remaining( $lot, 0 );
				}
			}
			Nodevora_Store::write( 'wallet_update', array( Nodevora_Store::table( 'wallets' ), $balance, $user ) );
			$committing = true;
			Nodevora_Store::commit();
		} catch ( Throwable $error ) {
			Nodevora_Store::rollback();
			// An unacknowledged COMMIT is resolved only by fresh evidence of this exact immutable request.
			if ( $committing ) {
				$confirmed = Nodevora_Store::event( $key );
				if ( $confirmed ) {
					self::match_request( $confirmed, $request );
					wp_cache_delete( 'history:' . $user, 'nodevora' );
                    wp_cache_delete('totals:'.$user,'nodevora');
                    self::notify_committed();
					return $confirmed; }
			}
			throw $error;
		}
		wp_cache_delete( 'history:' . $user, 'nodevora' );
		$confirmed = Nodevora_Store::event( $key );
		if ( ! $confirmed ) {
			throw new RuntimeException( esc_html__( 'Operation not confirmed. Retry with the same key.', 'nodevora-customer-rewards-for-woocommerce' ) ); }
		self::match_request( $confirmed, $request );
		self::notify_committed();
		return $confirmed;
	}
    private static function notify_committed() {
        foreach(self::$notifications as $operation){$notice=Nodevora_Store::event($operation);if($notice){do_action('nodevora_v2_committed',$notice);}}self::$notifications=array();
    }
	private static function match_request( $event, $request ) {
		$p = json_decode( $event['payload'], true );
		if ( ! hash_equals( $p['request'] ?? '', $request ) ) {
			throw new RuntimeException( esc_html__( 'Idempotency key conflict.', 'nodevora-customer-rewards-for-woocommerce' ) ); }
	}
	private static function record( $user, $key, $kind, $points, $delta, $balance, $details, $request, $allocations = array() ) {
		global $wpdb;
		$payload = wp_json_encode(
			array(
				'request'     => $request,
				'details'     => $details,
				'allocations' => $allocations,
			)
		);
		Nodevora_Store::write( 'event_insert', array( Nodevora_Store::table( 'events' ), $key, $user, $kind, $points, $delta, $balance, $details['order_id'] ?? 0, $details['source_id'] ?? 0, $payload, gmdate( 'Y-m-d H:i:s' ) ) );
		self::$notifications[]=$key;
		return Nodevora_Store::insert_id();
	}
	private static function remaining( &$lot, $value ) {
		global $wpdb;
		Nodevora_Store::write( 'lot_update', array( Nodevora_Store::table( 'lots' ), $value, $lot['id'] ) );
		$lot['remaining'] = $value;
	}
	private static function consume( &$lots, $points ) {
		$allocations = array();
		foreach ( $lots as &$lot ) {
			$n = min( $points, (int) $lot['remaining'] );
			if ( ! $n ) {
				continue; }
			self::remaining( $lot, (int) $lot['remaining'] - $n );
			$points       -= $n;
			$allocations[] = array(
				'points'     => $n,
				'expires_at' => (int) $lot['expires_at'],
				'event_id'   => (int) $lot['event_id'],
			);
		}
		unset( $lot );
		return $allocations;
	}
	public static function balance( $user ) {
		global $wpdb;
		$wallet = Nodevora_Store::wallet( $user );
		$r      = Nodevora_Store::rows( 'expired_balance', array( Nodevora_Store::table( 'lots' ), $user, time() ) );
		return max( 0, (int) $wallet['balance'] - (int) $r[0]['expired'] );
	}
	public static function history($user,$before=0) { return Nodevora_Store::history($user,$before); }
}
