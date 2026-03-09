<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Vérificateur simple des mises à jour GitHub.
 */
class Rewardly_Loyalty_Updater {

	/**
	 * Initialiser les hooks d’update.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'inject_update' ) );
		add_filter( 'plugins_api', array( __CLASS__, 'inject_plugin_information' ), 20, 3 );
	}

	/**
	 * Injecter les informations d’update dans WordPress.
	 *
	 * @param object $transient Transient des plugins.
	 * @return object
	 */
	public static function inject_update( $transient ) {
		if ( ! is_object( $transient ) ) {
			return $transient;
		}

		if ( empty( $transient->checked ) || ! is_array( $transient->checked ) ) {
			return $transient;
		}

		$release = self::get_latest_release();
		if ( empty( $release['version'] ) || empty( $release['download_url'] ) ) {
			return $transient;
		}

		/* Préparer l'objet standard attendu par WordPress. */
		$plugin_data = (object) array(
			'id'          => REWARDLY_LOYALTY_REPO_URL,
			'slug'        => dirname( REWARDLY_LOYALTY_BASENAME ),
			'plugin'      => REWARDLY_LOYALTY_BASENAME,
			'new_version' => $release['version'],
			'url'         => REWARDLY_LOYALTY_REPO_URL,
			'package'     => $release['download_url'],
			'tested'      => '',
			'requires'    => '',
		);

		/* Nettoyer les anciennes entrées éventuelles avant réinjection. */
		if ( isset( $transient->response[ REWARDLY_LOYALTY_BASENAME ] ) ) {
			unset( $transient->response[ REWARDLY_LOYALTY_BASENAME ] );
		}

		if ( ! isset( $transient->no_update ) || ! is_array( $transient->no_update ) ) {
			$transient->no_update = array();
		} elseif ( isset( $transient->no_update[ REWARDLY_LOYALTY_BASENAME ] ) ) {
			unset( $transient->no_update[ REWARDLY_LOYALTY_BASENAME ] );
		}

		/* Déclarer une mise à jour disponible si la release GitHub est plus récente. */
		if ( version_compare( $release['version'], REWARDLY_LOYALTY_VERSION, '>' ) ) {
			$transient->response[ REWARDLY_LOYALTY_BASENAME ] = $plugin_data;
			return $transient;
		}

		/* Sinon, déclarer explicitement le plugin comme déjà à jour. */
		$transient->no_update[ REWARDLY_LOYALTY_BASENAME ] = $plugin_data;

		return $transient;
	}

	/**
	 * Injecter les informations détaillées du plugin.
	 *
	 * @param false|object|array $result Résultat actuel.
	 * @param string             $action Action demandée.
	 * @param object             $args   Arguments de la requête.
	 * @return false|object|array
	 */
	public static function inject_plugin_information( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || dirname( REWARDLY_LOYALTY_BASENAME ) !== $args->slug ) {
			return $result;
		}

		$release = self::get_latest_release();
		if ( empty( $release['version'] ) ) {
			return $result;
		}

		return (object) array(
			'name'          => 'Rewardly – WooCommerce Loyalty Program',
			'slug'          => dirname( REWARDLY_LOYALTY_BASENAME ),
			'version'       => $release['version'],
			'author'        => '<a href="' . esc_url( REWARDLY_LOYALTY_REPO_URL ) . '">Ahmed Ghanem</a>',
			'homepage'      => REWARDLY_LOYALTY_REPO_URL,
			'download_link' => $release['download_url'],
			'sections'      => array(
				'description' => '<p>Rewardly is an advanced WooCommerce loyalty points system with point expiration, admin adjustments and email notifications.</p>',
				'changelog'   => wp_kses_post( wpautop( $release['body'] ) ),
			),
		);
	}

	/**
	 * Récupérer la dernière release GitHub avec cache local.
	 *
	 * @return array
	 */
	private static function get_latest_release() {
		$cache_key = 'rewardly_loyalty_latest_release';
		$cached    = get_site_transient( $cache_key );

		if ( is_array( $cached ) && ! empty( $cached['version'] ) ) {
			return $cached;
		}

		$response = wp_remote_get(
			'https://api.github.com/repos/DELTAWEBMAROC/rewardly-loyalty/releases/latest',
			array(
				'timeout' => 20,
				'headers' => array(
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => 'Rewardly-Loyalty-Updater',
				),
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return array();
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['tag_name'] ) ) {
			return array();
		}

		$download_url = '';
		if ( ! empty( $body['assets'] ) && is_array( $body['assets'] ) ) {
			foreach ( $body['assets'] as $asset ) {
				if ( empty( $asset['browser_download_url'] ) || empty( $asset['name'] ) ) {
					continue;
				}

				if ( '.zip' === strtolower( substr( $asset['name'], -4 ) ) ) {
					$download_url = $asset['browser_download_url'];
					break;
				}
			}
		}

		if ( empty( $download_url ) && ! empty( $body['zipball_url'] ) ) {
			$download_url = $body['zipball_url'];
		}

		$data = array(
			'version'      => ltrim( (string) $body['tag_name'], 'vV' ),
			'download_url' => esc_url_raw( $download_url ),
			'body'         => isset( $body['body'] ) ? (string) $body['body'] : '',
		);

		set_site_transient( $cache_key, $data, 12 * HOUR_IN_SECONDS );

		return $data;
	}
}