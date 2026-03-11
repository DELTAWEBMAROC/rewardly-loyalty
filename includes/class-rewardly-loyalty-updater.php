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
		add_filter( 'upgrader_source_selection', array( __CLASS__, 'ensure_stable_plugin_folder' ), 10, 4 );
		add_filter( 'upgrader_post_install', array( __CLASS__, 'ensure_post_install_plugin_location' ), 10, 3 );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'clear_update_cache_after_upgrade' ), 10, 2 );
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

		if ( is_array( $cached ) && ! empty( $cached['version'] ) && self::is_valid_release_package_url( $cached ) ) {
			return $cached;
		}

		delete_site_transient( $cache_key );

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

				$asset_name = strtolower( (string) $asset['name'] );

				// (FR) Utiliser uniquement l’archive ZIP officielle du plugin.
				if ( false !== strpos( $asset_name, 'rewardly-loyalty' ) && '.zip' === substr( $asset_name, -4 ) ) {
					$download_url = $asset['browser_download_url'];
					break;
				}
			}
		}

		$data = array(
			'version'      => ltrim( (string) $body['tag_name'], 'vV' ),
			'download_url' => esc_url_raw( $download_url ),
			'body'         => isset( $body['body'] ) ? (string) $body['body'] : '',
		);

		if ( ! self::is_valid_release_package_url( $data ) ) {
			return array();
		}

		set_site_transient( $cache_key, $data, 12 * HOUR_IN_SECONDS );

		return $data;
	}

	/**
	 * Forcer un nom de dossier stable pendant la mise à jour du plugin.
	 *
	 * @param string|WP_Error $source        Chemin source extrait.
	 * @param string          $remote_source Chemin parent temporaire.
	 * @param object          $upgrader      Instance de l’upgrader.
	 * @param array           $hook_extra    Contexte de l’opération.
	 * @return string|WP_Error
	 */
	public static function ensure_stable_plugin_folder( $source, $remote_source, $upgrader, $hook_extra ) {
		if ( is_wp_error( $source ) || empty( $hook_extra['type'] ) || 'plugin' !== $hook_extra['type'] ) {
			return $source;
		}

		$target_plugins = array();

		if ( ! empty( $hook_extra['plugin'] ) && is_string( $hook_extra['plugin'] ) ) {
			$target_plugins[] = $hook_extra['plugin'];
		}

		if ( ! empty( $hook_extra['plugins'] ) && is_array( $hook_extra['plugins'] ) ) {
			$target_plugins = array_merge( $target_plugins, $hook_extra['plugins'] );
		}

		if ( empty( $target_plugins ) || ! in_array( REWARDLY_LOYALTY_BASENAME, $target_plugins, true ) ) {
			return $source;
		}

		$expected_dir = trailingslashit( $remote_source ) . 'rewardly-loyalty';

		if ( wp_normalize_path( $source ) === wp_normalize_path( $expected_dir ) ) {
			return $source;
		}

		global $wp_filesystem;

		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		if ( ! $wp_filesystem ) {
			return new WP_Error( 'rewardly_loyalty_fs_unavailable', __( 'Unable to prepare the plugin update folder.', 'rewardly-loyalty' ) );
		}

		if ( $wp_filesystem->exists( $expected_dir ) ) {
			$wp_filesystem->delete( $expected_dir, true );
		}

		if ( ! $wp_filesystem->move( $source, $expected_dir, true ) ) {
			return new WP_Error( 'rewardly_loyalty_folder_rename_failed', __( 'Unable to normalize the plugin update folder.', 'rewardly-loyalty' ) );
		}

		return $expected_dir;
	}


	/**
	 * Vérifier qu’une URL de package correspond bien à l’archive officielle.
	 *
	 * @param array $release Données de release.
	 * @return bool
	 */
	private static function is_valid_release_package_url( $release ) {
		if ( empty( $release['download_url'] ) || ! is_string( $release['download_url'] ) ) {
			return false;
		}

		$download_url = strtolower( (string) $release['download_url'] );

		if ( false === strpos( $download_url, '.zip' ) ) {
			return false;
		}

		if ( false === strpos( $download_url, 'rewardly-loyalty' ) ) {
			return false;
		}

		if ( false !== strpos( $download_url, '/zipball/' ) || false !== strpos( $download_url, '/tarball/' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Garantir le dossier canonique après l’installation de la mise à jour.
	 *
	 * @param bool|WP_Error $response   Résultat actuel.
	 * @param array         $hook_extra Contexte de l’opération.
	 * @param array         $result     Résultat détaillé de l’installation.
	 * @return bool|WP_Error
	 */
	public static function ensure_post_install_plugin_location( $response, $hook_extra, $result ) {
		if ( is_wp_error( $response ) || empty( $hook_extra['type'] ) || 'plugin' !== $hook_extra['type'] ) {
			return $response;
		}

		$target_plugins = array();

		if ( ! empty( $hook_extra['plugin'] ) && is_string( $hook_extra['plugin'] ) ) {
			$target_plugins[] = $hook_extra['plugin'];
		}

		if ( ! empty( $hook_extra['plugins'] ) && is_array( $hook_extra['plugins'] ) ) {
			$target_plugins = array_merge( $target_plugins, $hook_extra['plugins'] );
		}

		if ( empty( $target_plugins ) || ! in_array( REWARDLY_LOYALTY_BASENAME, $target_plugins, true ) ) {
			return $response;
		}

		if ( empty( $result['destination'] ) || ! is_string( $result['destination'] ) ) {
			return $response;
		}

		$expected_dir = trailingslashit( WP_PLUGIN_DIR ) . 'rewardly-loyalty';
		$current_dir  = untrailingslashit( $result['destination'] );

		if ( wp_normalize_path( $current_dir ) === wp_normalize_path( $expected_dir ) ) {
			return $response;
		}

		global $wp_filesystem;

		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		if ( ! $wp_filesystem ) {
			return new WP_Error( 'rewardly_loyalty_post_install_fs_unavailable', __( 'Unable to finalize the plugin install path.', 'rewardly-loyalty' ) );
		}

		if ( $wp_filesystem->exists( $expected_dir ) ) {
			$wp_filesystem->delete( $expected_dir, true );
		}

		if ( ! $wp_filesystem->move( $current_dir, $expected_dir, true ) ) {
			return new WP_Error( 'rewardly_loyalty_post_install_move_failed', __( 'Unable to normalize the installed plugin folder.', 'rewardly-loyalty' ) );
		}

		return $response;
	}

	/**
	 * Nettoyer le cache d’update après une mise à jour du plugin.
	 *
	 * @param object $upgrader   Instance de l’upgrader.
	 * @param array  $hook_extra Contexte de l’opération.
	 * @return void
	 */
	public static function clear_update_cache_after_upgrade( $upgrader, $hook_extra ) {
		if ( empty( $hook_extra['type'] ) || 'plugin' !== $hook_extra['type'] ) {
			return;
		}

		$target_plugins = array();

		if ( ! empty( $hook_extra['plugin'] ) && is_string( $hook_extra['plugin'] ) ) {
			$target_plugins[] = $hook_extra['plugin'];
		}

		if ( ! empty( $hook_extra['plugins'] ) && is_array( $hook_extra['plugins'] ) ) {
			$target_plugins = array_merge( $target_plugins, $hook_extra['plugins'] );
		}

		if ( empty( $target_plugins ) || ! in_array( REWARDLY_LOYALTY_BASENAME, $target_plugins, true ) ) {
			return;
		}

		delete_site_transient( 'rewardly_loyalty_latest_release' );
		delete_site_transient( 'update_plugins' );

		if ( function_exists( 'wp_clean_plugins_cache' ) ) {
			wp_clean_plugins_cache( true );
		}
	}

}
