<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$settings = get_option( 'rewardly_loyalty_settings', array() );
if ( ! is_array( $settings ) || 'yes' !== ( $settings['delete_data_on_uninstall'] ?? 'no' ) ) {
	return;
}

global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}rewardly_loyalty_log" );
delete_option( 'rewardly_loyalty_settings' );
delete_option( 'rewardly_loyalty_installed_version' );

$user_ids = get_users( array( 'fields' => 'ids' ) );
foreach ( $user_ids as $user_id ) {
	delete_user_meta( $user_id, 'rewardly_loyalty_points_balance' );
	delete_user_meta( $user_id, 'rewardly_loyalty_points_earned_total' );
	delete_user_meta( $user_id, 'rewardly_loyalty_points_spent_total' );
	delete_user_meta( $user_id, 'rewardly_loyalty_point_lots' );
}
