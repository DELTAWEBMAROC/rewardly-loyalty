<?php
/** Bounded, site-bound V2 backup. Restore only into an empty V2 wallet store. @package Nodevora */
if ( ! defined( 'ABSPATH' ) ) {
	exit; }
class Nodevora_Backup {
	const MAX_BYTES = 8388608;
	const MAX_ROWS  = 20000;
	public static function init() {
		add_action( 'admin_post_nodevora_v2_export', array( __CLASS__, 'download' ) );
		add_action( 'admin_post_nodevora_v2_import', array( __CLASS__, 'upload' ) );
	}
	public static function export() {
		global $wpdb;
		try {
			Nodevora_Store::begin( true );
			$data = array(
				'format'     => 'nodevora-v2-2',
				'orders'     => array(),
				'site'       => home_url(),
				'created'    => gmdate( 'c' ),
				'settings'   => Nodevora_Settings::get(),
                'preferences' => Rewardly_Loyalty_Helpers::get_settings(),
				'identities' => array(),
				'tables'     => array(),
			);
			foreach ( array( 'wallets', 'events', 'lots' ) as $name ) {
				$data['tables'][ $name ] = Nodevora_Store::rows( 'snapshot', array( Nodevora_Store::table( $name ), self::MAX_ROWS + 1 ) );
				if ( count( $data['tables'][ $name ] ) > self::MAX_ROWS ) {
					throw new RuntimeException( esc_html__( 'Backup row limit exceeded.', 'nodevora-customer-rewards-for-woocommerce' ) ); }
			}
			foreach ( $data['tables']['wallets'] as $wallet ) {
				$u = get_userdata( $wallet['user_id'] );
				if ( ! $u ) {
					throw new RuntimeException( esc_html__( 'A wallet owner no longer exists. Review before exporting.', 'nodevora-customer-rewards-for-woocommerce' ) );
				} $data['identities'][ $u->ID ] = $u->user_email; }
			foreach ( $data['tables']['events'] as $event ) {
				$order_id = (int) $event['order_id'];
				if ( $order_id && ! isset( $data['orders'][ $order_id ] ) ) {
					$data['orders'][ $order_id ] = self::order_fingerprint( $order_id );
				}
			}
			Nodevora_Store::commit();
			$payload = wp_json_encode( $data );
			if ( strlen( $payload ) > self::MAX_BYTES - 256 ) {
				throw new RuntimeException( esc_html__( 'Backup size limit exceeded.', 'nodevora-customer-rewards-for-woocommerce' ) ); }
			$archive = wp_json_encode(
				array(
					'sha256'  => hash( 'sha256', $payload ),
					'payload' => $payload,
				)
			);
			if ( ! is_string( $archive ) || strlen( $archive ) > self::MAX_BYTES ) {
				throw new RuntimeException( esc_html__( 'Backup size limit exceeded.', 'nodevora-customer-rewards-for-woocommerce' ) );
			}
			return $archive;
		} catch ( Throwable $e ) {
			Nodevora_Store::rollback();
			throw $e; }
	}
	public static function validate( $json ) {
		if ( strlen( $json ) > self::MAX_BYTES ) {
			throw new RuntimeException( esc_html__( 'Backup exceeds 8 MiB.', 'nodevora-customer-rewards-for-woocommerce' ) ); }
		$envelope = json_decode( $json, true, 32, JSON_THROW_ON_ERROR );
		if ( ! is_array( $envelope ) || ! is_string( $envelope['payload'] ?? null ) || ! is_string( $envelope['sha256'] ?? null ) || ! hash_equals( hash( 'sha256', $envelope['payload'] ), $envelope['sha256'] ) ) {
			throw new RuntimeException( esc_html__( 'Backup checksum mismatch.', 'nodevora-customer-rewards-for-woocommerce' ) ); }
		$d = json_decode( $envelope['payload'], true, 32, JSON_THROW_ON_ERROR );
		if ( 'nodevora-v2-2' !== ( $d['format'] ?? '' ) || home_url() !== ( $d['site'] ?? '' ) || ! isset( $d['tables'], $d['identities'], $d['orders'] ) || ! is_array( $d['orders'] ) ) {
			throw new RuntimeException( esc_html__( 'Only same-site V2 backups are supported.', 'nodevora-customer-rewards-for-woocommerce' ) ); }
		if ( ! is_array( $d['settings'] ?? null ) || array_diff( array_keys( $d['settings'] ), array_keys( Nodevora_Settings::defaults() ) ) ) {
			throw new RuntimeException( esc_html__( 'Invalid backup settings.', 'nodevora-customer-rewards-for-woocommerce' ) ); }
		foreach ( $d['orders'] as $order_id => $fingerprint ) {
			if ( ! is_string( $fingerprint ) || ! hash_equals( self::order_fingerprint( (int) $order_id ), $fingerprint ) ) {
				throw new RuntimeException( esc_html__( 'Order state differs from the backup. Restore matching WooCommerce data before restoring points.', 'nodevora-customer-rewards-for-woocommerce' ) );
			}
		}
		if (!is_array($d['preferences']??null) || array_diff(array_keys($d['preferences']),array_keys(Rewardly_Loyalty_Helpers::get_default_settings()))) { throw new RuntimeException(esc_html__('Invalid backup settings.','nodevora-customer-rewards-for-woocommerce')); }
		$balances   = array();
		$journal = array();
		$ids        = array();
		$keys       = array();
		$lot_totals = array();
		foreach ( array( 'wallets', 'events', 'lots' ) as $name ) {
			if ( ! isset( $d['tables'][ $name ] ) || ! is_array( $d['tables'][ $name ] ) || count( $d['tables'][ $name ] ) > self::MAX_ROWS ) {
				throw new RuntimeException( esc_html__( 'Invalid backup tables.', 'nodevora-customer-rewards-for-woocommerce' ) ); }
		}
		foreach ( $d['tables']['wallets'] as $w ) {
			self::fields( $w, array( 'user_id', 'balance', 'revision' ) );
			$uid = (int) $w['user_id'];
			$u   = get_userdata( $uid );
			if ( ! $u || ( $d['identities'][ $uid ] ?? '' ) !== $u->user_email || isset( $balances[ $uid ] ) || abs( (int) $w['balance'] ) > Nodevora_Engine::LIMIT ) {
				throw new RuntimeException( esc_html__( 'Wallet owner or amount mismatch.', 'nodevora-customer-rewards-for-woocommerce' ) ); }
			$balances[ $uid ]   = 0;
			$lot_totals[ $uid ] = 0;
		}
		usort(
			$d['tables']['events'],
			static function ( $a, $b ) {
				return (int) $a['id'] <=> (int) $b['id'];
			}
		);
		foreach ( $d['tables']['events'] as $e ) {
			self::fields( $e, array( 'id', 'operation_key', 'user_id', 'kind', 'points', 'delta', 'balance_after', 'order_id', 'source_id', 'payload', 'created_at' ) );
			$uid = (int) $e['user_id'];
			$id  = (int) $e['id'];
			if ( ! isset( $balances[ $uid ] ) || $id < 1 || isset( $ids[ $id ] ) || isset( $keys[ $e['operation_key'] ] ) || ! preg_match( '/^[a-zA-Z0-9:._-]{1,191}$/D', $e['operation_key'] ) || ! in_array( $e['kind'], array( 'credit', 'debit', 'reverse', 'restore', 'control', 'expire' ), true ) || (int) $e['points'] < 0 || (int) $e['points'] > Nodevora_Engine::LIMIT ) {
				throw new RuntimeException( esc_html__( 'Invalid journal entry.', 'nodevora-customer-rewards-for-woocommerce' ) ); }
			$p = json_decode( $e['payload'], true, 32, JSON_THROW_ON_ERROR );
			if ( ! is_array( $p ) || ! isset( $p['request'], $p['details'], $p['allocations'] ) || ! preg_match( '/^([a-f0-9]{64})?$/D', $p['request'] ) ) {
				throw new RuntimeException( esc_html__( 'Invalid journal payload.', 'nodevora-customer-rewards-for-woocommerce' ) ); }
			if ( (int) $e['source_id'] && ( ! isset( $ids[ (int) $e['source_id'] ] ) || $ids[ (int) $e['source_id'] ] !== $uid ) ) {
				throw new RuntimeException( esc_html__( 'Journal source mismatch.', 'nodevora-customer-rewards-for-woocommerce' ) ); }
			$points = (int) $e['points']; $delta = (int) $e['delta'];
			$expected_delta = in_array( $e['kind'], array( 'credit', 'restore' ), true ) ? $points : ( 'control' === $e['kind'] ? 0 : -$points );
			if ( $delta !== $expected_delta || ( 'control' === $e['kind'] && $points ) || ! is_array( $p['allocations'] ) || ! is_array( $p['details'] ) ) { throw new RuntimeException( esc_html__( 'Invalid journal payload.', 'nodevora-customer-rewards-for-woocommerce' ) ); }
			$allocated = 0;
			foreach ( $p['allocations'] as $allocation ) {
				if ( ! is_array( $allocation ) || ! isset( $allocation['points'], $allocation['expires_at'], $allocation['event_id'] ) || ! is_int( $allocation['points'] ) || $allocation['points'] <= 0 || ! is_int( $allocation['expires_at'] ) || $allocation['expires_at'] < 0 || ( $ids[ $allocation['event_id'] ] ?? 0 ) !== $uid || ( $journal[ $allocation['event_id'] ]['kind'] ?? '' ) !== 'credit' ) { throw new RuntimeException( esc_html__( 'Invalid journal payload.', 'nodevora-customer-rewards-for-woocommerce' ) ); }
				$allocated += $allocation['points'];
			}
			if ( $allocated !== ( 'debit' === $e['kind'] ? $points : 0 ) ) { throw new RuntimeException( esc_html__( 'Invalid journal payload.', 'nodevora-customer-rewards-for-woocommerce' ) ); }
			if ( in_array( $e['kind'], array( 'restore', 'reverse' ), true ) ) {
				$source = $journal[ (int) $e['source_id'] ] ?? null;
				if ( ! $source || $source['kind'] !== ( 'restore' === $e['kind'] ? 'debit' : 'credit' ) || $points > (int) $source['points'] || ( $p['details']['source_key'] ?? '' ) !== $source['operation_key'] ) { throw new RuntimeException( esc_html__( 'Journal source mismatch.', 'nodevora-customer-rewards-for-woocommerce' ) ); }
			}
			$journal[ $id ] = $e;
			if ( (int) $e['order_id'] ) {
				$o = wc_get_order( $e['order_id'] );
				if ( ! $o || (int) $o->get_user_id() !== $uid || ! isset( $d['orders'][ (int) $e['order_id'] ] ) ) {
					throw new RuntimeException( esc_html__( 'Backup order owner mismatch.', 'nodevora-customer-rewards-for-woocommerce' ) ); }
			}
			$balances[ $uid ] += (int) $e['delta'];
			if ( $balances[ $uid ] !== (int) $e['balance_after'] ) {
				throw new RuntimeException( esc_html__( 'Journal balance mismatch.', 'nodevora-customer-rewards-for-woocommerce' ) ); }
			$ids[ $id ]                  = $uid;
			$keys[ $e['operation_key'] ] = true;
		}
		$lot_ids = array();
		foreach ( $d['tables']['lots'] as $l ) {
			self::fields( $l, array( 'id', 'user_id', 'event_id', 'remaining', 'expires_at' ) );
			$uid = (int) $l['user_id'];
			if ( ! isset( $balances[ $uid ] ) || ( $ids[ (int) $l['event_id'] ] ?? 0 ) !== $uid || ( $journal[ (int) $l['event_id'] ]['kind'] ?? '' ) !== 'credit' || (int) $l['remaining'] < 0 || (int) $l['expires_at'] < 0 || (int) $l['id'] < 1 || isset( $lot_ids[ $l['id'] ] ) ) {
				throw new RuntimeException( esc_html__( 'Invalid points lot.', 'nodevora-customer-rewards-for-woocommerce' ) ); }
			$lot_ids[ $l['id'] ] = true;
			$lot_totals[ $uid ] += (int) $l['remaining'];
		}
		foreach ( $d['tables']['wallets'] as $w ) {
			$uid = (int) $w['user_id'];
			if ( $balances[ $uid ] !== (int) $w['balance'] || $lot_totals[ $uid ] !== max( 0, $balances[ $uid ] ) ) {
				throw new RuntimeException( esc_html__( 'Wallet and lots do not reconcile.', 'nodevora-customer-rewards-for-woocommerce' ) ); }
		}
		return $d;
	}
	private static function order_fingerprint( $id ) {
		$order = wc_get_order( $id );
		if ( ! $order instanceof WC_Order || $order instanceof WC_Order_Refund ) {
			throw new RuntimeException( esc_html__( 'Backup order owner mismatch.', 'nodevora-customer-rewards-for-woocommerce' ) );
		}
		$items = array(); $coupons = array(); $refunds = array();
		foreach ( $order->get_items() as $item ) {
			$items[ $item->get_id() ] = array( $item->get_product_id(), $item->get_variation_id(), $item->get_quantity(), $item->get_subtotal(), $item->get_total(), $item->get_total_tax() );
		}
		foreach ( $order->get_items( 'coupon' ) as $coupon ) { $coupons[ $coupon->get_id() ] = array( $coupon->get_code(), $coupon->get_discount(), $coupon->get_discount_tax() ); }
		foreach ( $order->get_refunds() as $refund ) { $refunds[ $refund->get_id() ] = $refund->get_amount(); }
		ksort( $items ); ksort( $coupons ); ksort( $refunds );
		return hash( 'sha256', wp_json_encode( array( $order->get_id(), $order->get_user_id(), $order->get_status(), $order->get_currency(), $order->get_total(), $order->get_discount_total(), $order->get_discount_tax(), $order->get_meta( '_nodevora_v2_contract' ), $items, $coupons, $refunds ) ) );
	}
	private static function fields( $row, $fields ) {
		if ( ! is_array( $row ) || count( $row ) !== count( $fields ) || array_diff( $fields, array_keys( $row ) ) ) {
			throw new RuntimeException( esc_html__( 'Invalid backup row.', 'nodevora-customer-rewards-for-woocommerce' ) ); }
		foreach ( $row as $key => $value ) {
			if ( ! is_scalar( $value ) || strlen( (string) $value ) > 1048576 ) {
				throw new RuntimeException( esc_html__( 'Invalid backup value.', 'nodevora-customer-rewards-for-woocommerce' ) ); }
			if ( ! in_array( $key, array( 'operation_key', 'kind', 'payload', 'created_at' ), true ) && ! preg_match( '/^-?[0-9]{1,12}$/D', (string) $value ) ) {
				throw new RuntimeException( esc_html__( 'Invalid backup number.', 'nodevora-customer-rewards-for-woocommerce' ) ); }
		}
	}
	public static function restore( $json ) {
		global $wpdb;
		$d = self::validate( $json );
		try {
			Nodevora_Store::begin( true );
			foreach ( array( 'wallets', 'events', 'lots' ) as $name ) {
				$rows = Nodevora_Store::rows( 'empty_lock', array( Nodevora_Store::table( $name ) ) );
				if ( $rows ) {
					throw new RuntimeException( esc_html__( 'Restore requires an empty V2 store. Existing points are never overwritten.', 'nodevora-customer-rewards-for-woocommerce' ) ); }
			}
			foreach ( $d['tables'] as $name => $rows ) {
				if ( ! in_array( $name, array( 'wallets', 'events', 'lots' ), true ) ) {
					throw new RuntimeException( esc_html__( 'Unknown backup table.', 'nodevora-customer-rewards-for-woocommerce' ) ); }
				foreach ( $rows as $row ) {
					$fields = array(
						'wallets' => array( 'user_id', 'balance', 'revision' ),
						'events'  => array( 'id', 'operation_key', 'user_id', 'kind', 'points', 'delta', 'balance_after', 'order_id', 'source_id', 'payload', 'created_at' ),
						'lots'    => array( 'id', 'user_id', 'event_id', 'remaining', 'expires_at' ),
					);
					$values = array( Nodevora_Store::table( $name ) );
					foreach ( $fields[ $name ] as $field ) {
						$values[] = $row[ $field ]; }
					$operations = array(
						'wallets' => 'restore_wallet',
						'events'  => 'restore_event',
						'lots'    => 'restore_lot',
					);
					Nodevora_Store::write( $operations[ $name ], $values );
				}
			}
			Nodevora_Store::commit();
		} catch ( Throwable $e ) {
			Nodevora_Store::rollback();
			throw $e; }
		foreach ( $d['tables']['wallets'] as $w ) {
			wp_cache_delete( 'history:' . $w['user_id'], 'nodevora' ); }
		// Post-commit verification also detects a reconnect/replayed COMMIT with rolled-back inserts.
		foreach ( $d['tables']['wallets'] as $w ) {
			if ( ! Nodevora_Store::rows( 'wallet', array( Nodevora_Store::table( 'wallets' ), $w['user_id'] ) ) || (int) Nodevora_Store::wallet( $w['user_id'] )['balance'] !== (int) $w['balance'] ) {
				throw new RuntimeException( esc_html__( 'Restore outcome is unconfirmed. Inspect the store before retrying.', 'nodevora-customer-rewards-for-woocommerce' ) ); }
		}
		foreach ( $d['tables']['events'] as $event ) {
			$saved = Nodevora_Store::event( $event['operation_key'] );
			if ( ! $saved || $saved['payload'] !== $event['payload'] || (int) $saved['delta'] !== (int) $event['delta'] ) {
				throw new RuntimeException( esc_html__( 'Restored journal could not be confirmed.', 'nodevora-customer-rewards-for-woocommerce' ) ); }
		}
		$settings = Nodevora_Settings::sanitize( $d['settings'] );
		if ( ! update_option( 'nodevora_v2_settings', $settings, false ) && get_option( 'nodevora_v2_settings' ) !== $settings ) {
			throw new RuntimeException( esc_html__( 'Wallet restored, but settings could not be saved. Restore settings separately.', 'nodevora-customer-rewards-for-woocommerce' ) ); }
		$prefs=Rewardly_Loyalty_Settings::sanitize_settings($d['preferences']);
		if(!update_option('nodevora_v2_preferences',$prefs,false)&&get_option('nodevora_v2_preferences')!==$prefs){throw new RuntimeException(esc_html__('Wallet restored, but settings could not be saved. Restore settings separately.','nodevora-customer-rewards-for-woocommerce'));}
		return count( $d['tables']['wallets'] );
	}
	public static function download() {
		Nodevora_Admin::authorize( 'nodevora_v2_export', true );
		check_admin_referer( 'nodevora_v2_export' );
		try {
			$json = self::export();
			nocache_headers();
			header( 'Content-Type: application/json; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="nodevora-v2-backup.json"' );
			wp_send_json( json_decode( $json, true, 32, JSON_THROW_ON_ERROR ) ); } catch ( Throwable $e ) {
			wp_die( esc_html( $e->getMessage() ), '', array( 'response' => 400 ) ); }
	}
	public static function upload() {
		Nodevora_Admin::authorize( 'nodevora_v2_import', true );
		check_admin_referer( 'nodevora_v2_import' );
		try {
			if ( empty( $_POST['confirm'] ) || ! isset( $_FILES['backup']['tmp_name'], $_FILES['backup']['error'], $_FILES['backup']['size'] ) || UPLOAD_ERR_OK !== $_FILES['backup']['error'] || $_FILES['backup']['size'] > self::MAX_BYTES ) {
				throw new RuntimeException( esc_html__( 'Choose a V2 backup and confirm restoration to an empty store.', 'nodevora-customer-rewards-for-woocommerce' ) ); }
			$path = sanitize_text_field( wp_unslash( $_FILES['backup']['tmp_name'] ) );
			if ( ! is_uploaded_file( $path ) ) {
				throw new RuntimeException( esc_html__( 'Invalid upload.', 'nodevora-customer-rewards-for-woocommerce' ) ); }
			$stream = new SplFileObject( $path, 'rb' );
			$json   = $stream->fread( self::MAX_BYTES + 1 );
			self::restore( $json );
			wp_safe_redirect( admin_url( 'admin.php?page=nodevora-v2&restored=1' ) );
			exit;
		} catch ( Throwable $e ) {
			wp_die( esc_html( $e->getMessage() ), '', array( 'response' => 400 ) ); }
	}
}
