<?php
if(!defined('WP_CLI')||!WP_CLI||home_url()!=='http://localhost:10004'){throw new RuntimeException('Dedicated test site required.');}
require_once getenv('NODEVORA_TEST_SOURCE').'/includes/financial/bootstrap.php';
$dir=getenv('NODEVORA_BARRIER');$slot=getenv('NODEVORA_SLOT');file_put_contents($dir.'/'.$slot.'.ready','ready');$t=microtime(true);while(count(glob($dir.'/*.ready'))<2){if(microtime(true)-$t>20){throw new RuntimeException('Barrier timeout.');}usleep(10000);}
try{$w=new Nodevora_Financial_Wallet();$r=$w->apply((int)getenv('NODEVORA_USER'),getenv('NODEVORA_OPERATION'),getenv('NODEVORA_KIND'),(int)getenv('NODEVORA_AMOUNT'));echo json_encode(array('outcome'=>'committed','result'=>$r));}catch(Nodevora_Financial_Error $e){echo json_encode(array('outcome'=>'rejected','reason'=>$e->getMessage()));}
