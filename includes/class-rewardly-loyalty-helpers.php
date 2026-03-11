<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rewardly_Loyalty_Helpers {

	/**
	 * Retourner les réglages du programme.
	 *
	 * @return array
	 */
	public static function get_settings() {
		$defaults = array(
			'enabled'                => 'yes',
			'earn_points_per_dh'     => 1,
			'redeem_points_per_dh'   => 20,
			'min_points_to_redeem'   => 100,
			'max_discount_per_order' => 0,
			'order_status_trigger'   => 'completed',
			'points_expiration_days' => 365,
			'email_notifications'    => 'yes',
		);

		$saved = get_option( 'rewardly_loyalty_settings', array() );

		return wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );
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
	 * @param int    $user_id    ID utilisateur.
	 * @param string $new_key    Clé actuelle.
	 * @param string $legacy_key Clé historique.
	 * @return mixed
	 */
	private static function get_user_meta_value( $user_id, $key ) {
		return get_user_meta( $user_id, $key, true );
	}

	/**
	 * Écrire une méta utilisateur sur la clé actuelle.
	 *
	 * @param int    $user_id    ID utilisateur.
	 * @param string $new_key    Clé actuelle.
	 * @param string $legacy_key Clé historique.
	 * @param mixed  $value      Valeur.
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

	public static function calculate_earned_points( $amount ) {
		$settings = self::get_settings();
		$ratio    = max( 1, (int) $settings['earn_points_per_dh'] );

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
	 * Récupérer les lots de points FIFO.
	 *
	 * @param int $user_id ID utilisateur.
	 * @return array
	 */
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

	/**
	 * Déterminer si le mouvement doit alimenter le total des points gagnés.
	 *
	 * @param string $type Type du mouvement.
	 * @return bool
	 */
	private static function should_count_as_earned_total( $type ) {
		return in_array( $type, array( 'earn' ), true );
	}

	/**
	 * Déterminer si le mouvement doit alimenter le total des points utilisés.
	 *
	 * @param string $type Type du mouvement.
	 * @return bool
	 */
	private static function should_count_as_spent_total( $type ) {
		return in_array( $type, array( 'spend' ), true );
	}

	/**
	 * Normaliser le type source stocké dans les lots FIFO.
	 *
	 * @param string $type Type brut du mouvement.
	 * @return string
	 */
	private static function normalize_lot_source_type( $type ) {
		$allowed = array( 'earn', 'adjust', 'adjust_add', 'restore', 'migration' );
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
			$total_earned = self::get_total_earned( $user_id );
			self::update_user_meta_value( $user_id, 'rewardly_loyalty_points_earned_total', $total_earned + $points );
		}

		$lots   = self::get_user_point_lots( $user_id );
		$source = self::normalize_lot_source_type( $type );

		$lots[] = array(
			'id'               => uniqid( 'lot_', true ),
			'points_total'     => $points,
			'points_remaining' => $points,
			'earned_at'        => current_time( 'mysql' ),
			'source_type'      => $source,
			'order_id'         => (int) $order_id,
			'note'             => $note,
		);

		self::set_user_point_lots( $user_id, $lots );

		$amount = self::convert_points_to_amount( $points );
		self::insert_log( $user_id, $order_id, $type, $points, $amount, $note );

		Rewardly_Loyalty_Emails::maybe_send_points_notification( $user_id, $type, $points, $amount, $note );

		return $points;
	}

	public static function subtract_points( $user_id, $points, $order_id = 0, $type = 'spend', $note = '' ) {
		$requested = (int) $points;
		if ( $requested <= 0 ) {
			return 0;
		}

		self::maybe_bootstrap_legacy_lots( $user_id );

		$current = self::get_user_points( $user_id );
		$actual  = min( $requested, max( 0, $current ) );
		if ( $actual <= 0 ) {
			return 0;
		}

		return self::subtract_points_internal( $user_id, $actual, $order_id, $type, $note );
	}

	/**
	 * Retirer exactement un nombre de points ou échouer sans retrait partiel.
	 *
	 * @param int    $user_id ID utilisateur.
	 * @param int    $points Nombre exact de points à retirer.
	 * @param int    $order_id ID de commande lié.
	 * @param string $type Type du mouvement.
	 * @param string $note Note du mouvement.
	 * @return int
	 */
	public static function subtract_points_exact( $user_id, $points, $order_id = 0, $type = 'spend', $note = '' ) {
		$requested = (int) $points;
		if ( $requested <= 0 ) {
			return 0;
		}

		self::maybe_bootstrap_legacy_lots( $user_id );

		$current = self::get_user_points( $user_id );
		if ( $current < $requested ) {
			return 0;
		}

		$available_in_lots = 0;
		$lots              = self::get_user_point_lots( $user_id );

		foreach ( $lots as $lot ) {
			$available_in_lots += isset( $lot['points_remaining'] ) ? max( 0, (int) $lot['points_remaining'] ) : 0;
		}

		if ( $available_in_lots < $requested ) {
			return 0;
		}

		return self::subtract_points_internal( $user_id, $requested, $order_id, $type, $note );
	}

	/**
	 * Exécuter le retrait réel des points et journaliser le mouvement.
	 *
	 * @param int    $user_id ID utilisateur.
	 * @param int    $actual Nombre réel de points à retirer.
	 * @param int    $order_id ID de commande lié.
	 * @param string $type Type du mouvement.
	 * @param string $note Note du mouvement.
	 * @return int
	 */
	private static function subtract_points_internal( $user_id, $actual, $order_id = 0, $type = 'spend', $note = '' ) {
		$actual = (int) $actual;
		if ( $actual <= 0 ) {
			return 0;
		}

		$current   = self::get_user_points( $user_id );
		$lots      = self::get_user_point_lots( $user_id );
		$remaining = $actual;

		foreach ( $lots as $index => $lot ) {
			$available = isset( $lot['points_remaining'] ) ? (int) $lot['points_remaining'] : 0;
			if ( $available <= 0 ) {
				continue;
			}

			$consume = min( $available, $remaining );
			$lots[ $index ]['points_remaining'] = $available - $consume;
			$remaining -= $consume;

			if ( $remaining <= 0 ) {
				break;
			}
		}

		self::set_user_point_lots( $user_id, $lots );
		self::set_user_points( $user_id, max( 0, $current - $actual ) );

		if ( self::should_count_as_spent_total( $type ) ) {
			$total_spent = self::get_total_spent( $user_id );
			self::update_user_meta_value( $user_id, 'rewardly_loyalty_points_spent_total', $total_spent + $actual );
		}

		$amount = self::convert_points_to_amount( $actual );
		self::insert_log( $user_id, $order_id, $type, $actual, $amount, $note );

		Rewardly_Loyalty_Emails::maybe_send_points_notification( $user_id, $type, $actual, $amount, $note );

		return $actual;
	}

	public static function insert_log( $user_id, $order_id, $type, $points, $amount_dh, $note ) {
		global $wpdb;

		$table_name = self::get_log_table_name();

		$wpdb->insert(
			$table_name,
			array(
				'user_id'    => (int) $user_id,
				'order_id'   => (int) $order_id,
				'type'       => sanitize_text_field( $type ),
				'points'     => (int) $points,
				'amount_dh'  => (float) $amount_dh,
				'note'       => wp_strip_all_tags( $note ),
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%d', '%f', '%s', '%s' )
		);
	}

	/**
	 * Exécuter l’expiration quotidienne.
	 *
	 * @return void
	 */
	public static function run_daily_points_expiration() {
		if ( ! self::is_enabled() ) {
			return;
		}

		$settings = self::get_settings();
		$days     = isset( $settings['points_expiration_days'] ) ? (int) $settings['points_expiration_days'] : 0;

		if ( $days <= 0 ) {
			return;
		}

		$cutoff_ts = current_time( 'timestamp' ) - ( $days * DAY_IN_SECONDS );
		$offset    = 0;
		$limit     = 200;

		do {
			$users = get_users(
				array(
					'meta_key'     => 'rewardly_loyalty_points_balance',
					'meta_compare' => 'EXISTS',
					'fields'       => array( 'ID' ),
					'number'       => $limit,
					'offset'       => $offset,
				)
			);

			foreach ( $users as $user ) {
				$user_id        = (int) $user->ID;
				$expired_points = 0;

				self::maybe_bootstrap_legacy_lots( $user_id );
				$lots = self::get_user_point_lots( $user_id );

				foreach ( $lots as $index => $lot ) {
					$remaining = isset( $lot['points_remaining'] ) ? (int) $lot['points_remaining'] : 0;
					$earned_at = isset( $lot['earned_at'] ) ? strtotime( $lot['earned_at'] ) : false;

					if ( $remaining <= 0 || ! $earned_at ) {
						continue;
					}

					if ( $earned_at <= $cutoff_ts ) {
						$expired_points += $remaining;
						$lots[ $index ]['points_remaining'] = 0;
					}
				}

				if ( $expired_points <= 0 ) {
					continue;
				}

				self::set_user_point_lots( $user_id, $lots );

				$current     = self::get_user_points( $user_id );
				$new_balance = max( 0, $current - $expired_points );
				self::set_user_points( $user_id, $new_balance );

				$amount = self::convert_points_to_amount( $expired_points );
				$note   = sprintf( 'Points expirés automatiquement après %d jours.', $days );

				self::insert_log( $user_id, 0, 'expire', $expired_points, $amount, $note );
				Rewardly_Loyalty_Emails::maybe_send_points_notification( $user_id, 'expire', $expired_points, $amount, $note );
			}

			$offset += $limit;
		} while ( count( $users ) === $limit );
	}

}
