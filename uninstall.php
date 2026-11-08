<?php
/** Points and audit history deliberately survive uninstall. @package Nodevora */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit; }
wp_clear_scheduled_hook( 'nodevora_v2_maintenance' );

if ('yes' === (get_option('nodevora_v2_preferences',array())['delete_data_on_uninstall']??'no')) { delete_option('nodevora_v2_preferences');delete_option('nodevora_v2_settings');delete_option('nodevora_access_policy'); }
