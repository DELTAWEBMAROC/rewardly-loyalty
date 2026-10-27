<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/** Explicit, local/CLI-only importer. It never alters legacy financial data. */
class Nodevora_Financial_Migration {
	private $factory;
	public function __construct($factory=null){$this->factory=$factory?:function(){return new Nodevora_Financial_DB();};}
	private function connect(){$f=$this->factory;return $f();}
	private function source($db,$user,$lock=false){
		global $wpdb;
		if(!preg_match('/^[a-zA-Z0-9_]+$/D',$wpdb->usermeta)){throw new Nodevora_Financial_Error('Unsupported legacy metadata table.');}
		$engine=$db->one('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',array($wpdb->usermeta));if(!$engine||'InnoDB'!==$engine['ENGINE']){throw new Nodevora_Financial_Error('Legacy metadata is not transactional.');}
		return $db->query('SELECT umeta_id,meta_key,meta_value FROM `'.$wpdb->usermeta.'` WHERE user_id=? AND meta_key REGEXP ? ORDER BY umeta_id'.($lock?' FOR UPDATE':''),array($user,'^(rewardly_loyalty_|_rewardly_bonus_)'));
	}
	private static function integer($raw){
		if(!is_scalar($raw)||!preg_match('/^(0|[1-9][0-9]*)$/D',(string)$raw)){throw new Nodevora_Financial_Error('Invalid legacy integer.');}
		$limit=(string)intdiv(PHP_INT_MAX,4);$raw=(string)$raw;if(strlen($raw)>strlen($limit)||(strlen($raw)===strlen($limit)&&strcmp($raw,$limit)>0)){throw new Nodevora_Financial_Error('Legacy integer exceeds supported bounds.');}return (int)$raw;
	}
	private function inspect_rows($rows,$days,$timezone){
		$meta=array();$reasons=array();$lots=array();$balance=0;
		foreach($rows as $row){$meta[$row['meta_key']][]=$row['meta_value'];}
		foreach($meta as $key=>$values){if(count($values)!==1){$reasons[]='duplicate_meta:'.$key;}}
		try{$balance=self::integer(isset($meta['rewardly_loyalty_points_balance'][0])?$meta['rewardly_loyalty_points_balance'][0]:'0');}catch(Nodevora_Financial_Error $e){$reasons[]='invalid_balance';}
		$raw=isset($meta['rewardly_loyalty_point_lots'][0])?$meta['rewardly_loyalty_point_lots'][0]:'';
		$decoded=''===$raw?array():@unserialize($raw,array('allowed_classes'=>false));
		if(!is_array($decoded)){$reasons[]='invalid_lot_serialization';$decoded=array();}
		$total=0;
		foreach($decoded as $index=>$lot){
			try{
				if(!is_array($lot)||!isset($lot['points_remaining'],$lot['earned_at'])){throw new Nodevora_Financial_Error('Invalid lot.');}
				$remaining=self::integer($lot['points_remaining']);$date=$lot['earned_at'];if(!is_string($date)){throw new Nodevora_Financial_Error('Invalid lot date.');}
				$earned=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$date,new DateTimeZone($timezone));if(!$earned||$earned->format('Y-m-d H:i:s')!==$date){throw new Nodevora_Financial_Error('Ambiguous lot date.');}
				$expires=$days>0?$earned->modify('+'.$days.' days')->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'):null;
				// Newly enforcing expiry on already-overdue legacy funds requires reviewed policy.
				if($remaining&&$expires&&$expires<=gmdate('Y-m-d H:i:s')){$reasons[]='overdue_legacy_lot:'.$index;}
				$total+=$remaining;Nodevora_Financial_Wallet::points($total);
				$lots[]=array('remaining'=>$remaining,'earned_at'=>$earned->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),'expires_at'=>$expires,'original'=>$lot);
			}catch(Throwable $e){$reasons[]='invalid_lot:'.$index;}
		}
		if($balance!==$total){$reasons[]='balance_lot_mismatch';}
		foreach(array('_rewardly_bonus_registration_awarded','_rewardly_bonus_first_order_awarded') as $flag){if(isset($meta[$flag][0])&&!in_array($meta[$flag][0],array('yes','no',''),true)){$reasons[]='invalid_claim:'.$flag;}}
		return array('state'=>$reasons?'needs_review':'ready','balance'=>$balance,'lot_total'=>$total,'lots'=>$lots,'meta'=>$meta,'reasons'=>array_values(array_unique($reasons)));
	}
	private function orders($user){
		$orders=wc_get_orders(array('customer_id'=>$user,'limit'=>101,'orderby'=>'ID','order'=>'ASC'));$out=array();$reasons=array();
		if(count($orders)>100){$reasons[]='legacy_order_scan_requires_batch_review';}
		foreach($orders as $order){$meta=array();foreach($order->get_meta_data() as $item){$data=$item->get_data();if(0===strpos($data['key'],'_rewardly')){$meta[$data['key']]=$data['value'];}}
			if($meta){$out[]=array('order_id'=>$order->get_id(),'status'=>$order->get_status(),'total'=>$order->get_total(),'currency'=>$order->get_currency(),'meta'=>$meta);$reasons[]='legacy_order_baseline_required:'.$order->get_id();}
		}return array('orders'=>$out,'reasons'=>$reasons);
	}
	public function inspect($user){
		Nodevora_Financial_Wallet::points($user);$db=$this->connect();$rows=$this->source($db,$user);$db->close();$settings=Rewardly_Loyalty_Helpers::get_settings();$days=max(0,(int)$settings['points_expiration_days']);$timezone=wp_timezone_string();$data=$this->inspect_rows($rows,$days,$timezone);$orders=$this->orders($user);$data['reasons']=array_merge($data['reasons'],$orders['reasons']);if($data['reasons']){$data['state']='needs_review';}
		return array('user_id'=>$user,'state'=>$data['state'],'balance'=>$data['balance'],'lot_total'=>$data['lot_total'],'reasons'=>$data['reasons'],'checksum'=>hash('sha256',json_encode(array('rows'=>$rows,'orders'=>$orders['orders'],'expiration_days'=>$days,'timezone'=>wp_timezone_string()),JSON_THROW_ON_ERROR)));
	}
	public function migrate($user,$expected_checksum=null){
		if(!defined('WP_CLI')||!WP_CLI||is_multisite()||'local'!==wp_get_environment_type()||!defined('NODEVORA_FINANCIAL_MIGRATION_ALLOWED')||true!==NODEVORA_FINANCIAL_MIGRATION_ALLOWED||!defined('NODEVORA_FINANCIAL_WRITERS_DRAINED')||true!==NODEVORA_FINANCIAL_WRITERS_DRAINED){throw new Nodevora_Financial_Error('Migration is disabled outside an explicitly isolated local CLI cutover.');}
		Nodevora_Financial_Wallet::points($user);if(!$user||!get_user_by('id',$user)){throw new Nodevora_Financial_Error('Legacy user does not exist.');}
		$settings=Rewardly_Loyalty_Helpers::get_settings();$days=max(0,(int)$settings['points_expiration_days']);$timezone=wp_timezone_string();$orders=$this->orders($user);$db=$this->connect();Nodevora_Financial_Schema::verify($db);
		try{
			$db->begin();$db->query('INSERT IGNORE INTO '.$db->table('wallet').' (user_id,state) VALUES (?,?)',array($user,'pending'));
			$wallet=$db->one('SELECT * FROM '.$db->table('wallet').' WHERE user_id=? FOR UPDATE',array($user));if('ready'===$wallet['state']){$db->rollback();$db->close();return array('state'=>'already_migrated','checksum'=>$wallet['checksum']);}
			$rows=$this->source($db,$user,true);$payload=json_encode(array('rows'=>$rows,'orders'=>$orders['orders'],'expiration_days'=>$days,'timezone'=>$timezone),JSON_THROW_ON_ERROR);$hash=hash('sha256',$payload);$data=$this->inspect_rows($rows,$days,$timezone);$data['reasons']=array_merge($data['reasons'],$orders['reasons']);if($data['reasons']){$data['state']='needs_review';}if(null!==$expected_checksum&&!hash_equals($expected_checksum,$hash)){throw new Nodevora_Financial_Error('Dry-run checksum changed.');}
			$prior=$db->one('SELECT checksum FROM '.$db->table('snapshot').' WHERE user_id=? AND version=1',array($user));
			if($prior&&!hash_equals($prior['checksum'],$hash)){throw new Nodevora_Financial_Error('Legacy snapshot changed; reviewed reconciliation is required.');}
			if(!$prior){$this->write($db,'INSERT INTO '.$db->table('snapshot').' (user_id,version,checksum,payload,created_at) VALUES (?,1,?,?,UTC_TIMESTAMP())',array($user,$hash,$payload));}
			if('ready'!==$data['state']){$db->query('UPDATE '.$db->table('wallet').' SET state=?,checksum=? WHERE user_id=?',array('needs_review',$hash,$user));$db->commit();$db->close();return array('state'=>'needs_review','legacy_data_preserved'=>true,'parsed_balance'=>$data['balance'],'reasons'=>$data['reasons'],'checksum'=>$hash);}
			$result=array('status'=>'committed','balance'=>$data['balance'],'reserved'=>0,'debt'=>0,'migration_version'=>1,'checksum'=>$hash);
			$this->write($db,'INSERT INTO '.$db->table('event').' (user_id,operation_key,kind,payload_hash,gross_points,delta_balance,result,created_at) VALUES (?,?,?,?,?,?,?,UTC_TIMESTAMP())',array($user,'migration:v1:user:'.$user,'opening',$hash,$data['balance'],$data['balance'],json_encode($result,JSON_THROW_ON_ERROR)));$event=$db->id();$leg=0;
			foreach($data['lots'] as $lot){$this->write($db,'INSERT INTO '.$db->table('lot').' (user_id,source_event_id,remaining,earned_at,expires_at,provenance) VALUES (?,?,?,?,?,?)',array($user,$event,$lot['remaining'],$lot['earned_at'],$lot['expires_at'],json_encode($lot['original'],JSON_THROW_ON_ERROR)));$id=$db->id();$this->write($db,'INSERT INTO '.$db->table('allocation').' (event_id,lot_id,leg,reason,delta_remaining,delta_reserved) VALUES (?,?,?,?,?,0)',array($event,$id,++$leg,'opening',$lot['remaining']));}
			foreach(array('registration'=>'_rewardly_bonus_registration_awarded','first_order'=>'_rewardly_bonus_first_order_awarded') as $type=>$flag){if(isset($data['meta'][$flag][0])&&'yes'===$data['meta'][$flag][0]){$claim=array('status'=>'legacy_claim','source'=>$flag);$this->write($db,'INSERT INTO '.$db->table('event').' (user_id,operation_key,kind,payload_hash,gross_points,result,created_at) VALUES (?,?,?,?,0,?,UTC_TIMESTAMP())',array($user,'user:'.$user.':'.$type.':v1','legacy_claim',hash('sha256',json_encode($claim)),json_encode($claim)));}}
			$this->write($db,'UPDATE '.$db->table('wallet').' SET balance=?,state=?,migration_version=1,checksum=? WHERE user_id=?',array($data['balance'],'ready',$hash,$user));$db->commit();$db->close();return array('state'=>'migrated','checksum'=>$hash,'balance'=>$data['balance']);
		}catch(Throwable $e){$db->rollback();$db->close();throw $e;}
	}
	private function write($db,$sql,$params){if(1!==$db->query($sql,$params)){throw new Nodevora_Financial_Error('Migration write was not persisted.');}}
}
