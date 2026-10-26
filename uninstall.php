<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$rewardly_loyalty_settings = get_option( 'rewardly_loyalty_settings', array() );
if ( ! is_array( $rewardly_loyalty_settings ) || 'yes' !== ( $rewardly_loyalty_settings['delete_data_on_uninstall'] ?? 'no' ) ) {
	return;
}

global $wpdb;
$rewardly_loyalty_table_name = $wpdb->prefix . 'rewardly_loyalty_log';

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- One-time uninstall cleanup of the plugin's own custom table.
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $rewardly_loyalty_table_name ) );

delete_option( 'rewardly_loyalty_settings' );
delete_option( 'rewardly_loyalty_installed_version' );

$rewardly_loyalty_user_ids = get_users( array( 'fields' => 'ids' ) );
foreach ( $rewardly_loyalty_user_ids as $rewardly_loyalty_user_id ) {
	delete_user_meta( $rewardly_loyalty_user_id, 'rewardly_loyalty_points_balance' );
	delete_user_meta( $rewardly_loyalty_user_id, 'rewardly_loyalty_points_earned_total' );
	delete_user_meta( $rewardly_loyalty_user_id, 'rewardly_loyalty_points_spent_total' );
	delete_user_meta( $rewardly_loyalty_user_id, 'rewardly_loyalty_point_lots' );
}
