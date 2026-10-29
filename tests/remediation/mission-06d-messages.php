<?php
if(!defined('WP_CLI')||!WP_CLI||home_url()!=='http://localhost:10004'||DB_NAME!=='local'){throw new RuntimeException('Authorized demo required.');}
$method=new ReflectionMethod('Nodevora_Data','message');$method->setAccessible(true);$tests=array();
foreach(array('en_US','fr_FR')as$locale){switch_to_locale($locale);$instruction=__('Review the restored data before enabling points.','nodevora-customer-rewards-for-woocommerce');$trusted=$method->invoke(null,new Nodevora_User_Error($instruction));$mapped=$method->invoke(null,new Nodevora_Financial_Error('Dry-run checksum changed.'));$hidden=$method->invoke(null,new RuntimeException('SELECT synthetic_private_diagnostic'));$tests[]=array('locale'=>$locale,'trusted_preserved'=>$trusted===$instruction,'mapped'=>$mapped,'database_details_hidden'=>strpos($hidden,'SELECT')===false,'localized'=>$locale!=='fr_FR'||strpos($mapped,'Les données')===0);restore_previous_locale();}
echo json_encode($tests,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
