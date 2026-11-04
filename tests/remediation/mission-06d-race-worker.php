<?php
if(!defined('WP_CLI')||!WP_CLI||DB_NAME!=='local'||home_url()!=='http://localhost:10004'){throw new RuntimeException('Authorized demo required.');}
$dir=getenv('M06D_BARRIER');$slot=getenv('M06D_SLOT');file_put_contents($dir.'/'.$slot.'.ready','ready');$start=microtime(true);while(count(glob($dir.'/*.ready'))<2){if(microtime(true)-$start>20){throw new RuntimeException('Barrier timeout.');}usleep(10000);}
class M06D_Barrier_DB extends Nodevora_Financial_DB {
 public function query($sql,$params=array()){$result=parent::query($sql,$params);if(getenv('M06D_PAUSE_WRITER')&&strpos($sql,'LOCK IN SHARE MODE')!==false){file_put_contents(getenv('M06D_BARRIER').'/locked','ready');usleep(1500000);}return$result;}
}
try{$wallet=new Nodevora_Financial_Wallet(static function(){return new M06D_Barrier_DB();});$result=$wallet->apply((int)getenv('M06D_USER'),getenv('M06D_KEY'),getenv('M06D_KIND'),(int)getenv('M06D_AMOUNT'));echo json_encode(array('outcome'=>'committed','event_id'=>$result['event_id']));}catch(Nodevora_Financial_Error$e){echo json_encode(array('outcome'=>'rejected','reason'=>$e->getMessage()));}
