<?php
/** WooCommerce CRUD, virtual fixed-cart coupon and lifecycle adapter. @package Nodevora */
if ( ! defined( 'ABSPATH' ) ) {
	exit; }
class Nodevora_Commerce {
	public static function init() {
		add_filter( 'woocommerce_get_shop_coupon_data', array( __CLASS__, 'coupon_data' ), 10, 2 );
		add_filter( 'woocommerce_coupon_is_valid', array( __CLASS__, 'coupon_valid' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'project' ), 30 );
		add_action( 'woocommerce_store_api_checkout_update_order_meta', array( __CLASS__, 'project' ), 30 );
		add_action(
			'woocommerce_checkout_order_processed',
			static function ( $id, $posted, $order ) {
				self::fund( $order );
			},
			5,
			3
		);
		add_action( 'woocommerce_store_api_checkout_order_processed', array( __CLASS__, 'fund' ), 5 );
		add_action( 'woocommerce_before_pay_action', array( __CLASS__, 'fund' ), 5 );
		add_action(
			'woocommerce_order_status_changed',
			static function ( $id ) {
				self::reconcile( $id );
			},
			20
		);
		add_action(
			'woocommerce_order_refunded',
			static function ( $id ) {
				self::reconcile( $id );
			},
			20
		);
		add_action(
			'woocommerce_payment_complete',
			static function ( $id ) {
				self::reconcile( $id );
			},
			5
		);
		add_action( 'woocommerce_before_order_object_save', array( __CLASS__, 'protect_fulfillment' ), 5 );
		add_action( 'user_register', array( __CLASS__, 'registration' ) );
		add_action(
			'comment_post',
			static function ( $id ) {
				self::review( $id );
			},
			20
		);
		add_action(
			'transition_comment_status',
			static function ( $new, $old, $comment ) {
				self::review( $comment->comment_ID );
			},
			20,
			3
		);
		add_action(
			'delete_comment',
			static function ( $id ) {
				self::review( $id, true );
			},
			5
		);
		add_action( 'nodevora_v2_maintenance', array( __CLASS__, 'maintenance' ) );
		add_action( 'nodevora_v2_retry_order', array( __CLASS__, 'reconcile' ) );
		add_action( 'nodevora_v2_retry_registration', array( __CLASS__, 'registration' ) );
		add_action( 'nodevora_v2_retry_review', array( __CLASS__, 'review' ), 10, 2 );
		$register_blocks = static function () {
			woocommerce_store_api_register_update_callback(
				array(
					'namespace' => 'nodevora',
					'callback'  => static function ( $data ) {
						self::select( $data['points'] ?? 0 );
					},
				)
			);
		};
		if ( did_action( 'woocommerce_blocks_loaded' ) ) {
			$register_blocks();
		} else {
			add_action( 'woocommerce_blocks_loaded', $register_blocks ); }
	}
	public static function coupon_code( $user, $points, $rate ) {
		$body = $user . '-' . $points . '-' . $rate;
		return 'nodevora-' . $body . '-' . substr( hash_hmac( 'sha256', $body . get_woocommerce_currency(), wp_salt( 'auth' ) ), 0, 16 );
	}
	private static function parse( $code ) {
		if ( ! preg_match( '/^nodevora-([0-9]{1,10})-([0-9]{1,10})-([0-9]{1,7})-([a-f0-9]{16})$/D', $code, $m ) || (int) $m[3] < 1 ) {
			return null; }
		if ( ! hash_equals( self::coupon_code( (int) $m[1], (int) $m[2], (int) $m[3] ), $code ) ) {
			return null; }
		return array(
			'user'   => (int) $m[1],
			'points' => (int) $m[2],
			'rate'   => (int) $m[3],
		);
	}
	public static function amount( $points, $rate ) {
		$scale = 10 ** wc_get_price_decimals();
		return intdiv( $points * $scale, $rate ) / $scale;
	}
public static function selected() {
 if(!WC()->cart){return 0;}foreach(WC()->cart->get_applied_coupons() as$code){$p=self::parse($code);if($p && (int)$p['user']===get_current_user_id()){return (int)$p['points'];}}return 0;
}
public static function maximum() {
 if(!WC()->cart||!is_user_logged_in()){return 0;}$s=Nodevora_Settings::get();$amount=max(0,(float)WC()->cart->get_cart_contents_total());if($s['maximum_discount']){$amount=min($amount,(float)$s['maximum_discount']);}return min(Nodevora_Engine::balance(get_current_user_id()),(int)floor($amount*$s['redeem_rate']));
}
	public static function select( $raw ) {
		if ( ! is_user_logged_in() || ! WC()->cart || ! WC()->session || ! Nodevora_Settings::get()['enabled'] ) {
			throw new RuntimeException( esc_html__( 'Points redemption is unavailable.', 'nodevora-customer-rewards-for-woocommerce' ) ); }
		if ( ! is_scalar( $raw ) || ! preg_match( '/^[0-9]{1,10}$/D', (string) $raw ) ) {
			throw new RuntimeException( esc_html__( 'Enter a whole number of points.', 'nodevora-customer-rewards-for-woocommerce' ) ); }
		$n   = (int) $raw;
		$s   = Nodevora_Settings::get();
		$uid = get_current_user_id();
		if ( $n && ( $n < $s['minimum'] || $n > Nodevora_Engine::balance( $uid ) || $n > Nodevora_Engine::LIMIT || ( $s['maximum_discount'] && self::amount( $n, $s['redeem_rate'] ) > $s['maximum_discount'] ) || self::amount( $n, $s['redeem_rate'] ) <= 0 ) ) {
			throw new RuntimeException( esc_html__( 'This points amount cannot be used.', 'nodevora-customer-rewards-for-woocommerce' ) ); }
		foreach ( WC()->cart->get_applied_coupons() as $code ) {
			if ( Nodevora_Parity::starts( $code, 'nodevora-' ) ) {
				WC()->cart->remove_coupon( $code ); }
		}
		if ( $n && ! WC()->cart->apply_coupon( self::coupon_code( $uid, $n, $s['redeem_rate'] ) ) ) {
			throw new RuntimeException( esc_html__( 'The rewards coupon could not be applied.', 'nodevora-customer-rewards-for-woocommerce' ) ); }
		WC()->cart->calculate_totals();
	}
	public static function coupon_data( $data, $coupon ) {
		$p = self::parse( is_string( $coupon ) ? $coupon : $coupon->get_code() );
		if ( ! $p ) {
			return $data; }
		return array(
			'discount_type'  => 'fixed_cart',
			'amount'         => self::amount( $p['points'], $p['rate'] ),
			'individual_use' => false,
			'product_ids'    => array(),
			'usage_limit'    => 0,
			'free_shipping'  => false,
		);
	}
	public static function coupon_valid( $valid, $coupon ) {
		if ( ! Nodevora_Parity::starts( $coupon->get_code(), 'nodevora-' ) ) {
			return $valid; }
		$p = self::parse( $coupon->get_code() );
		$s = Nodevora_Settings::get();
		if ( ! $p || ! $s['enabled'] || $p['user'] !== get_current_user_id() || $p['rate'] !== (int) $s['redeem_rate'] || $p['points'] < $s['minimum'] || $p['points'] > Nodevora_Engine::balance( $p['user'] ) || ( $s['maximum_discount'] && (float) $coupon->get_amount() > $s['maximum_discount'] ) ) {
			throw new Exception( esc_html__( 'The rewards coupon is no longer valid. Select your points again.', 'nodevora-customer-rewards-for-woocommerce' ) ); }
		if ( WC()->cart ) {
			foreach ( WC()->cart->get_applied_coupons() as $code ) {
				if ( Nodevora_Parity::starts( $code, 'nodevora-' ) && $code !== $coupon->get_code() ) {
					throw new Exception( esc_html__( 'Use one rewards coupon per order.', 'nodevora-customer-rewards-for-woocommerce' ) ); }
			}
		}
		return $valid;
	}
	public static function project( $order ) {
		$s           = Nodevora_Settings::get();
		$earn        = 0;
		$points      = 0;
		$coupon_code = '';
		$face        = 0;
		foreach ( $order->get_items() as $item ) {
			$earn += (int) floor( (float) $item->get_subtotal() * Nodevora_Settings::rate( $item->get_product() ) ); }
		foreach ( $order->get_items( 'coupon' ) as $coupon ) {
			if ( ! Nodevora_Parity::starts( $coupon->get_code(), 'nodevora-' ) ) {
				continue; }
			$p = self::parse( $coupon->get_code() );
			if ( ! $p || $coupon_code || $p['user'] !== (int) $order->get_user_id() || ! $s['enabled'] ) {
				throw new RuntimeException( esc_html__( 'Invalid rewards coupon ownership.', 'nodevora-customer-rewards-for-woocommerce' ) ); }
			$face = (float) $coupon->get_discount() + ( wc_prices_include_tax() ? (float) $coupon->get_discount_tax() : 0 );
			if ( $face > self::amount( $p['points'], $p['rate'] ) + 0.00001 ) {
				throw new RuntimeException( esc_html__( 'Rewards discount mismatch.', 'nodevora-customer-rewards-for-woocommerce' ) ); }
			$scale       = 10 ** wc_get_price_decimals();
			$minor       = (int) round( $face * $scale );
			$points      = min( $p['points'], (int) ceil( $minor * $p['rate'] / $scale ) );
			$coupon_code = $coupon->get_code();
		}
		$contract = array(
			'user'     => (int) $order->get_user_id(),
			'points'   => $points,
			'coupon'   => $coupon_code,
			'discount' => wc_format_decimal( $face, wc_get_price_decimals() ),
			'currency' => $order->get_currency(),
			'earn'     => min( Nodevora_Engine::LIMIT, $earn ),
			'bonus'    => (int) $s['first_order_bonus'],
			'trigger'  => $s['trigger'],
		);
		$old      = $order->get_meta( '_nodevora_v2_contract' );
		if ( $old && $order->get_id() && Nodevora_Store::event( 'order:' . $order->get_id() . ':fund' ) && $old !== $contract ) {
			throw new RuntimeException( esc_html__( 'Funded order cannot change its rewards contract.', 'nodevora-customer-rewards-for-woocommerce' ) ); }
		$order->update_meta_data( '_nodevora_v2_contract', $contract );
	}
	public static function fund( $order ) {
		if ( is_numeric( $order ) ) {
			$order = wc_get_order( $order ); }
		if ( ! $order instanceof WC_Order || $order instanceof WC_Order_Refund ) {
			return; }
		$c = $order->get_meta( '_nodevora_v2_contract' );
		if ( ! $c ) {
			self::project( $order );
			$order->save();
			$c = $order->get_meta( '_nodevora_v2_contract' ); }
		if ( ! $c['points'] ) {
			return; }
		if ( (int) $order->get_user_id() !== $c['user'] || $order->get_currency() !== $c['currency'] || Nodevora_Store::event( 'order:' . $order->get_id() . ':close' ) ) {
			throw new RuntimeException( esc_html__( 'This rewards order is closed. Please create a new order.', 'nodevora-customer-rewards-for-woocommerce' ) ); }
		// The persisted coupon must still match the immutable checkout contract.
		$found = false;
		foreach ( $order->get_items( 'coupon' ) as $coupon ) {
			if ( $coupon->get_code() === $c['coupon'] ) {
				$found = true;
				$face  = (float) $coupon->get_discount() + ( wc_prices_include_tax() ? (float) $coupon->get_discount_tax() : 0 );
				if ( wc_format_decimal( $face, wc_get_price_decimals() ) !== $c['discount'] ) {
					throw new RuntimeException( esc_html__( 'Rewards discount changed.', 'nodevora-customer-rewards-for-woocommerce' ) ); }
			}
		}
		if ( ! $found ) {
			throw new RuntimeException( esc_html__( 'Rewards coupon missing.', 'nodevora-customer-rewards-for-woocommerce' ) ); }
		Nodevora_Engine::apply(
			$c['user'],
			'order:' . $order->get_id() . ':fund',
			'debit',
			$c['points'],
			array(
				'order_id' => $order->get_id(),
				'contract' => $c,
			)
		);
	}
	public static function protect_fulfillment( $order ) {
		if ( ! $order instanceof WC_Order || $order instanceof WC_Order_Refund || ! $order->get_id() || ! in_array( $order->get_status(), array( 'processing', 'completed' ), true ) ) {
			return; }
		$c = $order->get_meta( '_nodevora_v2_contract' );
		if ( ! $c ) {
			return; }
		try {
			$closed = Nodevora_Store::event( 'order:' . $order->get_id() . ':close' );
			$funded = ! $c['points'] || Nodevora_Store::event( 'order:' . $order->get_id() . ':fund' );
			if ( $closed || ! $funded || $order->get_meta( '_nodevora_v2_issue' ) ) {
				$order->set_status( 'on-hold' ); }
		} catch ( Throwable $e ) {
			$order->set_status( 'on-hold' );
			$order->update_meta_data( '_nodevora_v2_issue', 'Unable to verify points funding.' ); }
	}
	public static function reconcile( $id ) {
		$order = wc_get_order( $id );
		if ( ! $order || $order instanceof WC_Order_Refund || ! $order->get_user_id() ) {
			return; }
		try {
			$c = $order->get_meta( '_nodevora_v2_contract' );
			if ( ! $c ) {
				if ( ! Nodevora_Settings::get()['enabled'] ) {
					return;
				} self::project( $order );
				$order->save();
				$c = $order->get_meta( '_nodevora_v2_contract' ); }
			$uid  = $c['user'];
			$base = 'order:' . $id . ':';
			if ( in_array( $order->get_status(), array( 'cancelled', 'refunded' ), true ) ) {
				Nodevora_Engine::apply( $uid, $base . 'close', 'control', 0, array( 'order_id' => (int) $id ) );
				foreach ( array( $base . 'earn', 'user:' . $uid . ':first-order' ) as $key ) {
					$e = Nodevora_Store::event( $key );
					if ( $e && (int) $e['order_id'] === (int) $id ) {
						Nodevora_Engine::apply(
							$uid,
							'reverse:' . $e['id'],
							'reverse',
							0,
							array(
								'source_key' => $key,
								'order_id'   => (int) $id,
							)
						); }
				}
				$fund = Nodevora_Store::event( $base . 'fund' );
				if ( $fund ) {
					Nodevora_Engine::apply(
						$uid,
						'restore:' . $fund['id'],
						'restore',
						0,
						array(
							'source_key' => $base . 'fund',
							'order_id'   => (int) $id,
						)
					); }
				$order->delete_meta_data( '_nodevora_v2_issue' );
				$order->save();
				return;
			}
			if ( $order->get_refunds() ) {
				foreach ( $order->get_refunds() as $refund ) {
					if ( Nodevora_Store::event( 'refund:' . $refund->get_id() . ':reviewed' ) ) {
						continue; }
					Nodevora_Engine::apply(
						$uid,
						'refund:' . $refund->get_id() . ':review',
						'control',
						0,
						array(
							'order_id'  => (int) $id,
							'refund_id' => $refund->get_id(),
						)
					);
					throw new RuntimeException( esc_html__( 'Partial refund requires an administrator points adjustment and review.', 'nodevora-customer-rewards-for-woocommerce' ) );
				}
			}
			if ( Nodevora_Store::event( $base . 'close' ) ) {
				throw new RuntimeException( esc_html__( 'Closed rewards order requires a new checkout.', 'nodevora-customer-rewards-for-woocommerce' ) ); }
			if ( $order->get_status() !== $c['trigger'] ) {
				if ( $order->get_meta( '_nodevora_v2_issue' ) ) {
					self::fund( $order );
					$order->delete_meta_data( '_nodevora_v2_issue' );
					$order->save();
				} return; }
			if ( ! Nodevora_Settings::get()['enabled'] ) {
				return; }
			self::fund( $order );
			Nodevora_Engine::apply( $uid, $base . 'earn', 'credit', $c['earn'], array( 'order_id' => (int) $id ) );
			if ( $c['bonus'] && ! Nodevora_Store::event( 'user:' . $uid . ':first-order' ) ) {
				try {
					Nodevora_Engine::apply( $uid, 'user:' . $uid . ':first-order', 'credit', $c['bonus'], array( 'order_id' => (int) $id ) ); } catch ( Throwable $e ) {
					if ( ! Nodevora_Store::event( 'user:' . $uid . ':first-order' ) ) {
						throw $e; }
					}
			}
			$order->delete_meta_data( '_nodevora_v2_issue' );
			$order->save();
		} catch ( Throwable $e ) {
			$order->update_meta_data( '_nodevora_v2_issue', sanitize_text_field( $e->getMessage() ) );
			if ( in_array( $order->get_status(), array( 'processing', 'completed' ), true ) ) {
				$order->set_status( 'on-hold' ); }
			$order->save();
			if ( ! wp_next_scheduled( 'nodevora_v2_retry_order', array( (int) $id ) ) ) {
				wp_schedule_single_event( time() + 300, 'nodevora_v2_retry_order', array( (int) $id ) ); }
			wc_get_logger()->error(
				$e->getMessage(),
				array(
					'source'   => 'nodevora',
					'order_id' => $id,
				)
			);
		}
	}
	public static function registration( $user ) {
		$s = Nodevora_Settings::get();
		$pending = get_user_meta( $user, '_nodevora_v2_registration_pending', true );
		if ( '' === $pending && ( ! $s['enabled'] || ! $s['registration_bonus'] ) ) { return; }
		$amount = '' !== $pending ? (int) $pending : (int) $s['registration_bonus'];
		update_user_meta( $user, '_nodevora_v2_registration_pending', $amount );
		try {
			if ( ! Nodevora_Store::event( 'user:' . $user . ':registration' ) ) { Nodevora_Engine::apply( (int) $user, 'user:' . $user . ':registration', 'credit', $amount ); }
			delete_user_meta( $user, '_nodevora_v2_registration_pending' );
		} catch ( Throwable $e ) {
			if ( ! wp_next_scheduled( 'nodevora_v2_retry_registration', array( (int) $user ) ) ) { wp_schedule_single_event( time() + 300, 'nodevora_v2_retry_registration', array( (int) $user ) ); }
			wc_get_logger()->error( $e->getMessage(), array( 'source' => 'nodevora', 'user_id' => $user ) );
		}
	}
	public static function review( $id, $deleted = false ) {
		$c = get_comment( $id ); $s = Nodevora_Settings::get(); $key = 'review:' . $id;
		if ( $c && ( ! $c->user_id || $c->comment_parent || 'product' !== get_post_type( $c->comment_post_ID ) || ! in_array( $c->comment_type, array( 'review', '' ), true ) ) ) { return; }
		try {
			$old = Nodevora_Store::event( $key );
			if ( $old && ( $deleted || ! $c || '1' !== $c->comment_approved ) ) {
				Nodevora_Engine::apply( (int) $old['user_id'], 'reverse:' . $old['id'], 'reverse', 0, array( 'source_key' => $key ) );
			} elseif ( ! $old && ! $deleted && $c && '1' === $c->comment_approved ) {
				$pending = get_comment_meta( $id, '_nodevora_v2_review_pending', true );
				if ( '' !== $pending || ( $s['enabled'] && $s['review_bonus'] ) ) {
					$amount = '' !== $pending ? (int) $pending : (int) $s['review_bonus'];
					update_comment_meta( $id, '_nodevora_v2_review_pending', $amount );
					Nodevora_Engine::apply( (int) $c->user_id, $key, 'credit', $amount );
				}
			}
			delete_comment_meta( $id, '_nodevora_v2_review_pending' );
		} catch ( Throwable $e ) {
			if ( ! wp_next_scheduled( 'nodevora_v2_retry_review', array( (int) $id, (bool) $deleted ) ) ) { wp_schedule_single_event( time() + 300, 'nodevora_v2_retry_review', array( (int) $id, (bool) $deleted ) ); }
			wc_get_logger()->error( $e->getMessage(), array( 'source' => 'nodevora', 'comment_id' => $id ) );
		}
	}
	public static function maintenance() {
		global $wpdb;
		try {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Bounded native metadata lookup recovers durable pending awards after a failure.
			foreach ( get_users( array( 'meta_key' => '_nodevora_v2_registration_pending', 'fields' => 'ID', 'number' => 50 ) ) as $user ) { self::registration( $user ); }
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Bounded native metadata lookup recovers durable pending awards after a failure.
			foreach ( get_comments( array( 'meta_key' => '_nodevora_v2_review_pending', 'fields' => 'ids', 'number' => 50 ) ) as $comment ) { self::review( $comment ); }
			$users = Nodevora_Store::rows( 'due', array( Nodevora_Store::table( 'lots' ), time() ) );
			foreach ( $users as $row ) {
				Nodevora_Engine::apply( (int) $row['user_id'], 'expiry:' . $row['user_id'] . ':' . wp_generate_uuid4(), 'expire', 0 ); }
		} catch ( Throwable $e ) {
			wc_get_logger()->error( $e->getMessage(), array( 'source' => 'nodevora' ) ); }
	}
}
