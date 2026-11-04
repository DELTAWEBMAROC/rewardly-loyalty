<?php
/** Targeted regression: real user_register, then the format-2 row validator. */
if(!defined('WP_CLI')||!WP_CLI||DB_NAME!=='local'||home_url()!=='http://localhost:10004'){throw new RuntimeException('Authorized disposable Mission06D site required.');}
$settings=Rewardly_Loyalty_Helpers::get_settings();$settings['pro_points_registration']=0;add_filter('pre_option_rewardly_loyalty_settings',static function()use($settings){return $settings;});
$user=wp_insert_user(array('user_login'=>'qa_m06d_registration_'.bin2hex(random_bytes(5)),'user_pass'=>wp_generate_password(40),'role'=>'customer'));
if(is_wp_error($user)){throw new RuntimeException('Fixture registration failed.');}
$db=new Nodevora_Financial_DB();$wallet=$db->one('SELECT * FROM '.$db->table('wallet').' WHERE user_id=?',array($user));$snapshot=$db->one('SELECT * FROM '.$db->table('snapshot').' WHERE user_id=? AND version=1',array($user));
$check=new ReflectionMethod('Nodevora_Restore','verify_rows');$check->setAccessible(true);$error=null;try{$check->invoke(null,$db);}catch(Nodevora_Financial_Error$e){$error=$e->getMessage();}$db->close();
echo json_encode(array('fixture_user'=>$user,'checksum_is_null'=>$wallet['checksum']===null,'snapshot_checksum_valid'=>hash('sha256',$snapshot['payload'])===$snapshot['checksum'],'wallet_matches_snapshot'=>$wallet['checksum']===$snapshot['checksum'],'format2_validation_error'=>$error),JSON_PRETTY_PRINT);
