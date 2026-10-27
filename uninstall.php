<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) { exit; }
/* Les portefeuilles, journaux, instantanés et soldes historiques sont conservés.
 * Leur purge nécessite un export et une procédure de réconciliation distincte.
 * Les métadonnées utilisateurs partagées ne doivent jamais être effacées en réseau.
 */
if ( is_multisite() ) { return; }
$settings = get_option( 'rewardly_loyalty_settings', array() );
if ( is_array( $settings ) && 'yes' === ( $settings['delete_data_on_uninstall'] ?? 'no' ) ) {
	delete_option( 'rewardly_loyalty_settings' );
	delete_option( 'rewardly_loyalty_installed_version' );
}
