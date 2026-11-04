<?php
/** Refus ciblés du parcours opérateur ; aucune action de migration autorisée ici. */
if(!defined('WP_CLI')||!WP_CLI||0!==strpos(DB_NAME,'nodevora_m06b_')){throw new RuntimeException('Disposable Mission06B database required.');}
class Nodevora_M06B_Setup_Denied extends RuntimeException {}
add_filter('wp_die_handler',static function(){return static function($message,$title='',$args=array()){throw new Nodevora_M06B_Setup_Denied('Denied',is_array($args)&&isset($args['response'])?(int)$args['response']:403);};});
$tests=array();$before=Nodevora_Financial_Setup::state();
$cases=array(array('customer',2,'POST',403),array('admin_get',1,'GET',405),array('admin_csrf',1,'POST',403));
foreach($cases as$case){wp_set_current_user($case[1]);$_SERVER['REQUEST_METHOD']=$case[2];$_REQUEST['_wpnonce']='invalid';try{Nodevora_Financial_Setup::handle();$tests[]=array('test'=>$case[0],'status'=>'FAIL');}catch(Nodevora_M06B_Setup_Denied $e){$tests[]=array('test'=>$case[0],'status'=>$e->getCode()===$case[3]?'PASS':'FAIL');}}
$tests[]=array('test'=>'denials_preserve_setup','status'=>Nodevora_Financial_Setup::state()===$before?'PASS':'FAIL');
echo json_encode(array('tests'=>$tests),JSON_PRETTY_PRINT);
