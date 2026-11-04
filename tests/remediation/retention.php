<?php
/* Vérifier les frontières destructives sans charger une base de données réelle. */
define('WP_UNINSTALL_PLUGIN',true);$network=false;$deleted=array();$preferences=array('delete_data_on_uninstall'=>'yes');
function is_multisite(){global $network;return $network;}
function get_option($key,$default=array()){global $preferences;return $preferences;}
function delete_option($key){global $deleted;$deleted[]=$key;}
require dirname(__DIR__,2).'/uninstall.php';
$tests=array(array('test'=>'single_site_cleanup_preferences_only','status'=>$deleted===array('rewardly_loyalty_settings','rewardly_loyalty_installed_version')?'PASS':'FAIL'));
$network=true;$deleted=array();require dirname(__DIR__,2).'/uninstall.php';$tests[]=array('test'=>'network_cleanup_refused','status'=>$deleted===array()?'PASS':'FAIL');
$network=false;$preferences=array('delete_data_on_uninstall'=>'no');require dirname(__DIR__,2).'/uninstall.php';$tests[]=array('test'=>'default_retention_no_cleanup','status'=>$deleted===array()?'PASS':'FAIL');
echo json_encode(array('tests'=>$tests),JSON_PRETTY_PRINT);
