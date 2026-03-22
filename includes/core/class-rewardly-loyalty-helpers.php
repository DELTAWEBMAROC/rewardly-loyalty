<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rewardly_Loyalty_Helpers {

	public static function get_default_settings() {
		return array(
			'enabled'                      => 'yes',
			'earn_points_per_dh'           => 1,
			'redeem_points_per_dh'         => 20,
			'min_points_to_redeem'         => 100,
			'max_discount_per_order'       => 0,
			'order_status_trigger'         => 'completed',
			'points_expiration_days'       => 365,
			'email_notifications'          => 'yes',
			'notice_display_mode'          => 'both',
			'primary_color'                => '#111111',
			'accent_color'                 => '#f5a623',
			'border_radius'                => 12,
			'enable_front_styles'          => 'yes',
			'delete_data_on_uninstall'     => 'no',
			'design_template'              => 'default',
			'icon_style'                   => 'default',
			'button_style'                 => 'rounded',
			'card_style'                   => 'soft',
			'typography_preset'            => 'default',
			'license_key'                  => '',
			'license_status'               => 'inactive',
			'license_plan'                 => 'free',
			'license_instance_id'          => '',
			'license_last_check'           => '',
			'license_expires_at'           => '',
			'license_grace_until'          => '',
			'license_activated_domain'     => '',
			'pro_exclude_sale_products'    => 'no',
			'pro_excluded_product_ids'     => array(),
			'pro_excluded_category_ids'    => array(),
			'pro_category_rules'           => array(),
			'pro_points_registration'      => 0,
			'pro_points_first_order'       => 0,
			'pro_points_review'            => 0,
			'pro_points_birthday'          => 0,
			'pro_referral_points'          => 0,
			'pro_pending_points_notice_enabled' => 'no',
			'pro_pending_points_notice_text'    => 'You will earn {points} point(s) from your order for {product_name} once {order_link} is completed.',
			'pro_pending_points_statuses'       => array( 'pending', 'on-hold', 'processing' ),
			'pro_levels_enabled'           => 'no',
			'pro_level_bronze_threshold'   => 100,
			'pro_level_silver_threshold'   => 500,
			'pro_level_gold_threshold'     => 1000,
		);
	}

	/**
	 * Retourner les réglages du programme.
	 *
	 * @return array
	 */
	public static function get_settings() {
		$defaults = self::get_default_settings();
		$saved    = get_option( 'rewardly_loyalty_settings', array() );

		$settings = wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );
		$settings['pro_excluded_product_ids']  = self::sanitize_id_list( $settings['pro_excluded_product_ids'] ?? array() );
		$settings['pro_excluded_category_ids'] = self::sanitize_id_list( $settings['pro_excluded_category_ids'] ?? array() );
		$settings['pro_category_rules']        = self::sanitize_category_rules_array( $settings['pro_category_rules'] ?? array() );
		$settings['pro_pending_points_statuses'] = self::sanitize_status_list( $settings['pro_pending_points_statuses'] ?? array() );

		return $settings;
	}

	/**
	 * Normaliser une liste d'identifiants.
	 *
	 * @param mixed $value Valeur brute.
	 * @return array
	 */

	/**
	 * Retourner l'identifiant local de l'instance.
	 *
	 * @return string
	 */
	public static function get_license_instance_id() {
		$settings = self::get_settings();

		if ( ! empty( $settings['license_instance_id'] ) && is_string( $settings['license_instance_id'] ) ) {
			return sanitize_key( $settings['license_instance_id'] );
		}

		$instance_id = wp_generate_password( 24, false, false );
		$instance_id = strtolower( sanitize_key( $instance_id ) );

		$settings['license_instance_id'] = $instance_id;
		update_option( 'rewardly_loyalty_settings', $settings );

		return $instance_id;
	}

	/**
	 * Normaliser un statut de licence.
	 *
	 * @param mixed $status Statut brut.
	 * @return string
	 */
	public static function sanitize_license_status( $status ) {
		$allowed = array( 'inactive', 'active', 'invalid', 'expired', 'disabled', 'suspended' );
		$status  = is_string( $status ) ? sanitize_key( $status ) : '';

		if ( ! in_array( $status, $allowed, true ) ) {
			return 'inactive';
		}

		return $status;
	}

	/**
	 * Normaliser un plan de licence.
	 *
	 * @param mixed $plan Plan brut.
	 * @return string
	 */
	public static function sanitize_license_plan( $plan ) {
		$allowed = array( 'free', 'pro' );
		$plan    = is_string( $plan ) ? sanitize_key( $plan ) : '';

		if ( ! in_array( $plan, $allowed, true ) ) {
			return 'free';
		}

		return $plan;
	}

	/**
	 * Normaliser le code d'achat Envato saisi localement.
	 *
	 * @param mixed $purchase_code Code brut.
	 * @return string
	 */
	public static function sanitize_envato_purchase_code( $purchase_code ) {
		if ( ! is_string( $purchase_code ) ) {
			return '';
		}

		$purchase_code = trim( wp_strip_all_tags( $purchase_code ) );
		$purchase_code = preg_replace( '/\s+/', '', $purchase_code );

		return is_string( $purchase_code ) ? strtolower( $purchase_code ) : '';
	}

	/**
	 * Vérifier le format local du code d'achat Envato.
	 *
	 * @param mixed $purchase_code Code brut.
	 * @return bool
	 */
	public static function is_envato_purchase_code_format( $purchase_code ) {
		$purchase_code = self::sanitize_envato_purchase_code( $purchase_code );

		if ( '' === $purchase_code ) {
			return false;
		}

		return (bool) preg_match( '/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/', $purchase_code );
	}

	/**
	 * Retourner le domaine local normalisé pour la licence.
	 *
	 * @return string
	 */
	public static function get_license_site_domain() {
		$home = function_exists( 'home_url' ) ? home_url() : '';
		$host = wp_parse_url( $home, PHP_URL_HOST );

		return is_string( $host ) ? strtolower( $host ) : '';
	}

	public static function sanitize_id_list( $value ) {
		if ( is_string( $value ) ) {
			$value = preg_split( '/[^0-9]+/', $value );
		}

		if ( ! is_array( $value ) ) {
			return array();
		}

		$ids = array_filter( array_map( 'absint', $value ) );
		$ids = array_values( array_unique( $ids ) );

		return $ids;
	}

	/**
	 * Normaliser les règles catégories.
	 *
	 * @param mixed $rules Valeur brute.
	 * @return array
	 */
	public static function sanitize_category_rules_array( $rules ) {
		$normalized = array();

		if ( ! is_array( $rules ) ) {
			return $normalized;
		}

		foreach ( $rules as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}

			$term_id = absint( $rule['term_id'] ?? 0 );
			$rate    = max( 1, absint( $rule['rate'] ?? 0 ) );

			if ( $term_id <= 0 || $rate <= 0 ) {
				continue;
			}

			$normalized[] = array(
				'term_id' => $term_id,
				'rate'    => $rate,
			);
		}

		return $normalized;
	}

	/**
	 * Normaliser la liste des statuts de commande Pro.
	 *
	 * @param mixed $statuses Valeur brute.
	 * @return array
	 */
	public static function sanitize_status_list( $statuses ) {
		$allowed = array( 'pending', 'on-hold', 'processing' );

		if ( is_string( $statuses ) ) {
			$statuses = array_map( 'trim', explode( ',', $statuses ) );
		}

		if ( ! is_array( $statuses ) ) {
			return array( 'pending', 'on-hold', 'processing' );
		}

		$statuses = array_values( array_intersect( array_map( 'sanitize_key', $statuses ), $allowed ) );

		return ! empty( $statuses ) ? $statuses : array( 'pending', 'on-hold', 'processing' );
	}

	/**
	 * Retourner le nom de la table de logs active.
	 *
	 * @return string
	 */
	private static function get_log_table_name() {
		global $wpdb;

		return $wpdb->prefix . 'rewardly_loyalty_log';
	}

	/**
	 * Lire une méta utilisateur sans fallback legacy.
	 *
	 * @param int    $user_id ID utilisateur.
	 * @param string $key     Clé actuelle.
	 * @return mixed
	 */
	private static function get_user_meta_value( $user_id, $key ) {
		return get_user_meta( $user_id, $key, true );
	}

	/**
	 * Écrire une méta utilisateur sur la clé actuelle.
	 *
	 * @param int    $user_id ID utilisateur.
	 * @param string $key     Clé actuelle.
	 * @param mixed  $value   Valeur.
	 * @return void
	 */
	private static function update_user_meta_value( $user_id, $key, $value ) {
		update_user_meta( $user_id, $key, $value );
	}

	public static function is_enabled() {
		$settings = self::get_settings();
		return isset( $settings['enabled'] ) && 'yes' === $settings['enabled'];
	}

	public static function email_notifications_enabled() {
		$settings = self::get_settings();
		return isset( $settings['email_notifications'] ) && 'yes' === $settings['email_notifications'];
	}

	/**
	 * Retourner le code devise de la boutique.
	 *
	 * @return string
	 */
	public static function get_store_currency_code() {
		if ( function_exists( 'get_woocommerce_currency' ) ) {
			$code = get_woocommerce_currency();
			if ( is_string( $code ) && '' !== $code ) {
				return strtoupper( $code );
			}
		}
		return 'USD';
	}

	/**
	 * Formater un montant avec la devise WooCommerce.
	 *
	 * @param float $amount Montant brut.
	 * @return string
	 */
	public static function format_amount( $amount ) {
		if ( function_exists( 'wc_price' ) ) {
			return wc_price( $amount );
		}
		return number_format_i18n( (float) $amount, 2 ) . ' ' . self::get_store_currency_code();
	}

	public static function get_user_points( $user_id ) {
		return (int) self::get_user_meta_value( $user_id, 'rewardly_loyalty_points_balance' );
	}

	public static function set_user_points( $user_id, $points ) {
		self::update_user_meta_value( $user_id, 'rewardly_loyalty_points_balance', max( 0, (int) $points ) );
	}

	public static function get_total_earned( $user_id ) {
		return (int) self::get_user_meta_value( $user_id, 'rewardly_loyalty_points_earned_total' );
	}

	public static function get_total_spent( $user_id ) {
		return (int) self::get_user_meta_value( $user_id, 'rewardly_loyalty_points_spent_total' );
	}

	public static function get_user_logs( $user_id, $limit = 20 ) {
		global $wpdb;

		$table_name = self::get_log_table_name();
		$limit      = max( 1, (int) $limit );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE user_id = %d ORDER BY id DESC LIMIT %d",
				$user_id,
				$limit
			)
		);
	}

	/**
	 * Retourner le taux global de gain.
	 *
	 * @return int
	 */
	public static function get_global_earning_rate() {
		$settings = self::get_settings();
		return max( 1, (int) $settings['earn_points_per_dh'] );
	}

	/**
	 * Déterminer si un produit est exclu des gains Pro.
	 *
	 * @param WC_Product|int $product Produit ou ID.
	 * @return bool
	 */
	public static function is_product_excluded_from_earning( $product ) {
		if ( ! class_exists( 'Rewardly_Loyalty_Pro' ) || ! Rewardly_Loyalty_Pro::can_use_feature( 'advanced_rules' ) ) {
			return false;
		}

		$product = is_numeric( $product ) ? wc_get_product( $product ) : $product;
		if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
			return false;
		}

		$settings   = self::get_settings();
		$product_id = $product->get_id();

		if ( in_array( $product_id, $settings['pro_excluded_product_ids'], true ) ) {
			return true;
		}

		if ( 'yes' === ( $settings['pro_exclude_sale_products'] ?? 'no' ) && $product->is_on_sale() ) {
			return true;
		}

		$product_categories = wc_get_product_term_ids( $product_id, 'product_cat' );
		if ( ! empty( $product_categories ) && array_intersect( $product_categories, $settings['pro_excluded_category_ids'] ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Retourner le taux de gain effectif d'un produit.
	 *
	 * @param WC_Product|int $product Produit ou ID.
	 * @return int
	 */
	public static function get_effective_product_earning_rate( $product ) {
		$product = is_numeric( $product ) ? wc_get_product( $product ) : $product;
		$rate    = self::get_global_earning_rate();

		if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
			return $rate;
		}

		if ( self::is_product_excluded_from_earning( $product ) ) {
			return 0;
		}

		if ( class_exists( 'Rewardly_Loyalty_Pro' ) && Rewardly_Loyalty_Pro::can_use_feature( 'advanced_rules' ) ) {
			$product_id   = $product->get_id();
			$override_on  = 'yes' === get_post_meta( $product_id, '_rewardly_pro_enable_product_rule', true );
			$override_raw = absint( get_post_meta( $product_id, '_rewardly_pro_product_rate', true ) );
			if ( $override_on && $override_raw > 0 ) {
				return $override_raw;
			}

			$product_categories = wc_get_product_term_ids( $product_id, 'product_cat' );
			$settings           = self::get_settings();
			$category_rules     = $settings['pro_category_rules'];
			$matched_rate       = 0;

			foreach ( $category_rules as $rule ) {
				if ( in_array( (int) $rule['term_id'], $product_categories, true ) ) {
					$matched_rate = max( $matched_rate, (int) $rule['rate'] );
				}
			}

			if ( $matched_rate > 0 ) {
				return $matched_rate;
			}
		}

		return $rate;
	}

	/**
	 * Calculer les points d'un produit et d'un sous-total.
	 *
	 * @param WC_Product|int $product  Produit ou ID.
	 * @param float          $amount   Montant.
	 * @return int
	 */
	public static function calculate_product_earned_points( $product, $amount ) {
		$rate = self::get_effective_product_earning_rate( $product );
		if ( $rate <= 0 || $amount <= 0 ) {
			return 0;
		}
		return (int) floor( (float) $amount * $rate );
	}

	/**
	 * Calculer les points d'un panier en tenant compte des règles Pro.
	 *
	 * @param WC_Cart|null $cart Panier.
	 * @return int
	 */
	public static function calculate_cart_earned_points( $cart = null ) {
		if ( ! function_exists( 'WC' ) ) {
			return 0;
		}

		$cart = $cart ?: WC()->cart;
		if ( ! $cart ) {
			return 0;
		}

		$total_points = 0;
		foreach ( $cart->get_cart() as $cart_item ) {
			$product = $cart_item['data'] ?? null;
			$line    = isset( $cart_item['line_subtotal'] ) ? (float) $cart_item['line_subtotal'] : 0;
			$total_points += self::calculate_product_earned_points( $product, $line );
		}

		return max( 0, (int) $total_points );
	}

	/**
	 * Calculer les points d'une commande en tenant compte des règles Pro.
	 *
	 * @param WC_Order $order Commande.
	 * @return int
	 */
	public static function calculate_order_earned_points( $order ) {
		if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
			return 0;
		}

		$total_points = 0;
		foreach ( $order->get_items( 'line_item' ) as $item ) {
			$product = $item->get_product();
			$line    = (float) $item->get_subtotal();
			$total_points += self::calculate_product_earned_points( $product, $line );
		}

		return max( 0, (int) $total_points );
	}

	public static function calculate_earned_points( $amount ) {
		$ratio = self::get_global_earning_rate();
		return (int) floor( (float) $amount * $ratio );
	}

	public static function convert_points_to_amount( $points ) {
		$settings = self::get_settings();
		$ratio    = max( 1, (int) $settings['redeem_points_per_dh'] );

		return (float) floor( (int) $points / $ratio );
	}

	public static function convert_amount_to_points( $amount ) {
		$settings = self::get_settings();
		$ratio    = max( 1, (int) $settings['redeem_points_per_dh'] );

		return (int) floor( (float) $amount * $ratio );
	}

	/**
	 * Déterminer le niveau fidélité de l'utilisateur.
	 *
	 * @param int $user_id ID utilisateur.
	 * @return array
	 */
	public static function get_user_level_data( $user_id ) {
		$settings = self::get_settings();
		$enabled  = class_exists( 'Rewardly_Loyalty_Pro' ) && Rewardly_Loyalty_Pro::can_use_feature( 'levels_badges' ) && 'yes' === ( $settings['pro_levels_enabled'] ?? 'no' );
		/* (FR) Utiliser le solde actuel pour refléter la progression réellement disponible. */
		$earned   = self::get_user_points( $user_id );

		$levels = array(
			array(
				'slug'      => 'bronze',
				'label'     => __( 'Bronze', 'rewardly-loyalty' ),
				'threshold' => max( 0, absint( $settings['pro_level_bronze_threshold'] ?? 100 ) ),
			),
			array(
				'slug'      => 'silver',
				'label'     => __( 'Silver', 'rewardly-loyalty' ),
				'threshold' => max( 0, absint( $settings['pro_level_silver_threshold'] ?? 500 ) ),
			),
			array(
				'slug'      => 'gold',
				'label'     => __( 'Gold', 'rewardly-loyalty' ),
				'threshold' => max( 0, absint( $settings['pro_level_gold_threshold'] ?? 1000 ) ),
			),
		);

		$current_level = array(
			'slug'      => 'starter',
			'label'     => __( 'Starter', 'rewardly-loyalty' ),
			'threshold' => 0,
		);
		$next_level    = null;

		foreach ( $levels as $level ) {
			if ( $earned >= $level['threshold'] ) {
				$current_level = $level;
				continue;
			}

			$next_level = $level;
			break;
		}

		$progress_max = $next_level ? max( 1, (int) $next_level['threshold'] ) : max( 1, (int) $current_level['threshold'] );
		$progress     = min( 100, (int) floor( ( $earned / $progress_max ) * 100 ) );
		$remaining    = $next_level ? max( 0, (int) $next_level['threshold'] - $earned ) : 0;

		return array(
			'enabled'       => $enabled,
			'earned_total'  => $earned,
			'current_level' => $current_level,
			'next_level'    => $next_level,
			'progress'      => $progress,
			'remaining'     => $remaining,
		);
	}

	public static function get_user_point_lots( $user_id ) {
		$lots = self::get_user_meta_value( $user_id, 'rewardly_loyalty_point_lots' );
		if ( ! is_array( $lots ) ) {
			$lots = array();
		}
		return array_values( $lots );
	}

	public static function set_user_point_lots( $user_id, $lots ) {
		self::update_user_meta_value( $user_id, 'rewardly_loyalty_point_lots', array_values( $lots ) );
	}

	/**
	 * Créer un lot de migration si un ancien solde existe sans lots.
	 *
	 * @param int $user_id ID utilisateur.
	 * @return void
	 */
	public static function maybe_bootstrap_legacy_lots( $user_id ) {
		$lots    = self::get_user_point_lots( $user_id );
		$balance = self::get_user_points( $user_id );

		if ( ! empty( $lots ) || $balance <= 0 ) {
			return;
		}

		$lots[] = array(
			'id'               => uniqid( 'lot_', true ),
			'points_total'     => (int) $balance,
			'points_remaining' => (int) $balance,
			'earned_at'        => current_time( 'mysql' ),
			'source_type'      => 'migration',
			'order_id'         => 0,
			'note'             => 'Migration du solde existant.',
		);

		self::set_user_point_lots( $user_id, $lots );
	}

	private static function should_count_as_earned_total( $type ) {
		return in_array( $type, array( 'earn', 'bonus_registration', 'bonus_first_order', 'bonus_review', 'bonus_birthday', 'bonus_referral' ), true );
	}

	private static function should_count_as_spent_total( $type ) {
		return in_array( $type, array( 'spend' ), true );
	}

	private static function normalize_lot_source_type( $type ) {
		$allowed = array( 'earn', 'adjust', 'adjust_add', 'restore', 'migration', 'bonus_registration', 'bonus_first_order', 'bonus_review', 'bonus_birthday', 'bonus_referral' );
		return in_array( $type, $allowed, true ) ? $type : 'earn';
	}

	public static function add_points( $user_id, $points, $order_id = 0, $type = 'earn', $note = '' ) {
		$points = (int) $points;
		if ( $points <= 0 ) {
			return 0;
		}

		self::maybe_bootstrap_legacy_lots( $user_id );

		$current = self::get_user_points( $user_id );
		self::set_user_points( $user_id, $current + $points );

		if ( self::should_count_as_earned_total( $type ) ) {
			self::update_user_meta_value( $user_id, 'rewardly_loyalty_points_earned_total', self::get_total_earned( $user_id ) + $points );
		}

		if ( self::should_count_as_spent_total( $type ) ) {
			self::update_user_meta_value( $user_id, 'rewardly_loyalty_points_spent_total', self::get_total_spent( $user_id ) + $points );
		}

		$amount = self::convert_points_to_amount( $points );
		self::insert_log( $user_id, $order_id, $type, $points, $amount, $note );

		$lots   = self::get_user_point_lots( $user_id );
		$lots[] = array(
			'id'               => uniqid( 'lot_', true ),
			'points_total'     => $points,
			'points_remaining' => $points,
			'earned_at'        => current_time( 'mysql' ),
			'source_type'      => self::normalize_lot_source_type( $type ),
			'order_id'         => (int) $order_id,
			'note'             => (string) $note,
		);
		self::set_user_point_lots( $user_id, $lots );

		return $points;
	}

	public static function subtract_points( $user_id, $points, $order_id = 0, $type = 'spend', $note = '' ) {
		$points = (int) $points;
		if ( $points <= 0 ) {
			return 0;
		}

		$current = self::get_user_points( $user_id );
		if ( $current <= 0 ) {
			return 0;
		}

		$removed = min( $current, $points );
		self::set_user_points( $user_id, $current - $removed );

		if ( self::should_count_as_spent_total( $type ) ) {
			self::update_user_meta_value( $user_id, 'rewardly_loyalty_points_spent_total', self::get_total_spent( $user_id ) + $removed );
		}

		$amount = self::convert_points_to_amount( $removed );
		self::insert_log( $user_id, $order_id, $type, $removed, $amount, $note );
		self::consume_point_lots( $user_id, $removed );

		return $removed;
	}

	public static function subtract_points_exact( $user_id, $points, $order_id = 0, $type = 'spend', $note = '' ) {
		$points = (int) $points;
		if ( $points <= 0 ) {
			return 0;
		}

		$current = self::get_user_points( $user_id );
		if ( $current < $points ) {
			return 0;
		}

		return self::subtract_points( $user_id, $points, $order_id, $type, $note );
	}

	private static function consume_point_lots( $user_id, $points_to_consume ) {
		$points_to_consume = (int) $points_to_consume;
		if ( $points_to_consume <= 0 ) {
			return;
		}

		$lots = self::get_user_point_lots( $user_id );
		if ( empty( $lots ) ) {
			return;
		}

		foreach ( $lots as &$lot ) {
			$remaining = isset( $lot['points_remaining'] ) ? (int) $lot['points_remaining'] : 0;
			if ( $remaining <= 0 ) {
				continue;
			}

			if ( $points_to_consume >= $remaining ) {
				$points_to_consume         -= $remaining;
				$lot['points_remaining'] = 0;
			} else {
				$lot['points_remaining'] = $remaining - $points_to_consume;
				$points_to_consume         = 0;
			}

			if ( $points_to_consume <= 0 ) {
				break;
			}
		}
		unset( $lot );

		self::set_user_point_lots( $user_id, $lots );
	}

	private static function insert_log( $user_id, $order_id, $type, $points, $amount, $note ) {
		global $wpdb;

		$wpdb->insert(
			self::get_log_table_name(),
			array(
				'user_id'   => (int) $user_id,
				'order_id'  => (int) $order_id,
				'type'      => sanitize_key( $type ),
				'points'    => (int) $points,
				'amount_dh' => (float) $amount,
				'note'      => wp_strip_all_tags( (string) $note ),
				'created_at'=> current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%d', '%f', '%s', '%s' )
		);
	}

	public static function run_daily_points_expiration() {
		$settings        = self::get_settings();
		$expiration_days = isset( $settings['points_expiration_days'] ) ? (int) $settings['points_expiration_days'] : 0;

		if ( $expiration_days <= 0 ) {
			return;
		}

		$users = get_users(
			array(
				'fields' => 'ids',
			)
		);

		if ( empty( $users ) ) {
			return;
		}

		$now_timestamp = current_time( 'timestamp' );

		foreach ( $users as $user_id ) {
			$lots        = self::get_user_point_lots( $user_id );
			$new_lots    = array();
			$expired_sum = 0;

			foreach ( $lots as $lot ) {
				$remaining = isset( $lot['points_remaining'] ) ? (int) $lot['points_remaining'] : 0;
				if ( $remaining <= 0 ) {
					$new_lots[] = $lot;
					continue;
				}

				$earned_at_raw = isset( $lot['earned_at'] ) ? (string) $lot['earned_at'] : '';
				$earned_at_ts  = $earned_at_raw ? strtotime( $earned_at_raw ) : false;
				if ( ! $earned_at_ts ) {
					$new_lots[] = $lot;
					continue;
				}

				$age_in_days = (int) floor( ( $now_timestamp - $earned_at_ts ) / DAY_IN_SECONDS );
				if ( $age_in_days < $expiration_days ) {
					$new_lots[] = $lot;
					continue;
				}

				$expired_sum += $remaining;
				$lot['points_remaining'] = 0;
				$new_lots[] = $lot;
			}

			if ( $expired_sum <= 0 ) {
				continue;
			}

			$current_balance = self::get_user_points( $user_id );
			self::set_user_points( $user_id, max( 0, $current_balance - $expired_sum ) );
			self::set_user_point_lots( $user_id, $new_lots );
			self::insert_log(
				$user_id,
				0,
				'expire',
				$expired_sum,
				self::convert_points_to_amount( $expired_sum ),
				__( 'Points expired automatically.', 'rewardly-loyalty' )
			);
		}
	}
}
