<?php
if(!defined('WP_CLI')||!WP_CLI||home_url()!=='http://localhost:10004'){throw new RuntimeException('Dedicated test site required.');}
if(!class_exists('Nodevora_Financial_DB')){require_once getenv('NODEVORA_TEST_SOURCE').'/includes/financial/bootstrap.php';}
class Nodevora_Test_Killed_DB extends Nodevora_Financial_DB{public function query($sql,$params=array()){$r=parent::query($sql,$params);if(0===strpos($sql,'UPDATE '.$this->table('wallet'))){file_put_contents(getenv('NODEVORA_CRASH_MARKER'),'uncommitted wallet updated');while(true){usleep(100000);}}return $r;}}
$w=new Nodevora_Financial_Wallet(function(){return new Nodevora_Test_Killed_DB();});$w->apply((int)getenv('NODEVORA_USER'),getenv('NODEVORA_OPERATION'),'credit',10);
