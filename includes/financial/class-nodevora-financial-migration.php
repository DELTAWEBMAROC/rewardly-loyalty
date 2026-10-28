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
		foreach(array('rewardly_loyalty_points_earned_total','rewardly_loyalty_points_spent_total') as $key){try{if(isset($meta[$key][0])){self::integer($meta[$key][0]);}}catch(Nodevora_Financial_Error $e){$reasons[]='invalid_reporting_total:'.$key;}}
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
	private function reviews($user){
		$comments=get_comments(array('user_id'=>$user,'post_type'=>'product','meta_key'=>'_rewardly_bonus_review_awarded','number'=>101,'status'=>'all','orderby'=>'comment_ID','order'=>'ASC'));$out=array();$reasons=array();
		if(count($comments)>100){$reasons[]='legacy_review_scan_requires_batch_review';}
		foreach($comments as $comment){$values=get_comment_meta($comment->comment_ID,'_rewardly_bonus_review_awarded',false);$out[]=array('comment_id'=>(int)$comment->comment_ID,'post_id'=>(int)$comment->comment_post_ID,'values'=>$values);if(count($values)!==1||!in_array($values[0],array('yes','no',''),true)){$reasons[]='ambiguous_review_claim:'.$comment->comment_ID;}}
		return array('claims'=>$out,'reasons'=>$reasons);
	}
	public function inspect($user){
		Nodevora_Financial_Wallet::points($user);$db=$this->connect();$rows=$this->source($db,$user);$db->close();$settings=Rewardly_Loyalty_Helpers::get_settings();$days=max(0,(int)$settings['points_expiration_days']);$timezone=wp_timezone_string();$data=$this->inspect_rows($rows,$days,$timezone);$orders=$this->orders($user);$reviews=$this->reviews($user);$orders['reasons']=array_merge($orders['reasons'],$reviews['reasons']);$data['reasons']=array_merge($data['reasons'],$orders['reasons']);if($data['reasons']){$data['state']='needs_review';}
		return array('user_id'=>$user,'state'=>$data['state'],'balance'=>$data['balance'],'lot_total'=>$data['lot_total'],'reasons'=>$data['reasons'],'checksum'=>hash('sha256',json_encode(array('rows'=>$rows,'orders'=>$orders['orders'],'reviews'=>$reviews['claims'],'expiration_days'=>$days,'timezone'=>wp_timezone_string()),JSON_THROW_ON_ERROR)));
	}
	public function migrate($user,$expected_checksum=null){
		if(is_multisite()||(!Nodevora_Financial_Setup::permits('migration')&&(!defined('WP_CLI')||!WP_CLI||'local'!==wp_get_environment_type()||!defined('NODEVORA_FINANCIAL_MIGRATION_ALLOWED')||true!==NODEVORA_FINANCIAL_MIGRATION_ALLOWED||!defined('NODEVORA_FINANCIAL_WRITERS_DRAINED')||true!==NODEVORA_FINANCIAL_WRITERS_DRAINED))){throw new Nodevora_Financial_Error('Migration requires the reviewed backup/setup workflow.');}
		Nodevora_Financial_Wallet::points($user);if(!$user||!get_user_by('id',$user)){throw new Nodevora_Financial_Error('Legacy user does not exist.');}
		$settings=Rewardly_Loyalty_Helpers::get_settings();$days=max(0,(int)$settings['points_expiration_days']);$timezone=wp_timezone_string();$orders=$this->orders($user);$reviews=$this->reviews($user);$orders['reasons']=array_merge($orders['reasons'],$reviews['reasons']);$db=$this->connect();Nodevora_Financial_Schema::verify($db);
		try{
			$db->begin();$db->query('INSERT IGNORE INTO '.$db->table('wallet').' (user_id,state) VALUES (?,?)',array($user,'pending'));
			$wallet=$db->one('SELECT * FROM '.$db->table('wallet').' WHERE user_id=? FOR UPDATE',array($user));if('ready'===$wallet['state']){$db->rollback();$db->close();return array('state'=>'already_migrated','checksum'=>$wallet['checksum']);}
			$rows=$this->source($db,$user,true);$payload=json_encode(array('rows'=>$rows,'orders'=>$orders['orders'],'reviews'=>$reviews['claims'],'expiration_days'=>$days,'timezone'=>$timezone),JSON_THROW_ON_ERROR);$hash=hash('sha256',$payload);$data=$this->inspect_rows($rows,$days,$timezone);$data['reasons']=array_merge($data['reasons'],$orders['reasons']);if($data['reasons']){$data['state']='needs_review';}if(null!==$expected_checksum&&!hash_equals($expected_checksum,$hash)){throw new Nodevora_Financial_Error('Dry-run checksum changed.');}
			$prior=$db->one('SELECT checksum FROM '.$db->table('snapshot').' WHERE user_id=? AND version=1',array($user));
			if($prior&&!hash_equals($prior['checksum'],$hash)){throw new Nodevora_Financial_Error('Legacy snapshot changed; reviewed reconciliation is required.');}
			if(!$prior){$this->write($db,'INSERT INTO '.$db->table('snapshot').' (user_id,version,checksum,payload,created_at) VALUES (?,1,?,?,UTC_TIMESTAMP())',array($user,$hash,$payload));}
			// Snapshot unresolved order evidence without asserting that legacy flags prove funding.
			foreach($orders['orders'] as $order){
				$old=$db->one('SELECT * FROM '.$db->table('order_state').' WHERE order_id=? FOR UPDATE',array($order['order_id']));
				$baseline=json_encode(array('legacy_baseline'=>$order,'reason'=>'Historical entitlement/funding requires reviewed reconciliation.'),JSON_THROW_ON_ERROR);
				if($old){if((int)$old['user_id']!==$user||'legacy_review'!==$old['state']||$old['data']!==$baseline){throw new Nodevora_Financial_Error('Legacy order baseline conflict.');}}
				else{$this->write($db,'INSERT INTO '.$db->table('order_state').' (order_id,user_id,intent,state,fingerprint,currency,data,updated_at) VALUES (?,?,?,?,?,?,?,UTC_TIMESTAMP())',array($order['order_id'],$user,'legacy:v1','legacy_review',hash('sha256',$baseline),$order['currency'],$baseline));}
			}
			if('ready'!==$data['state']){$db->query('UPDATE '.$db->table('wallet').' SET state=?,checksum=? WHERE user_id=?',array('needs_review',$hash,$user));$db->commit();$db->close();return array('state'=>'needs_review','legacy_data_preserved'=>true,'parsed_balance'=>$data['balance'],'reasons'=>$data['reasons'],'checksum'=>$hash);}
			$result=array('status'=>'committed','balance'=>$data['balance'],'reserved'=>0,'debt'=>0,'migration_version'=>1,'checksum'=>$hash);
			$this->write($db,'INSERT INTO '.$db->table('event').' (user_id,operation_key,kind,payload_hash,gross_points,delta_balance,result,created_at) VALUES (?,?,?,?,?,?,?,UTC_TIMESTAMP())',array($user,'migration:v1:user:'.$user,'opening',$hash,$data['balance'],$data['balance'],json_encode($result,JSON_THROW_ON_ERROR)));$event=$db->id();$leg=0;
			foreach($data['lots'] as $lot){$this->write($db,'INSERT INTO '.$db->table('lot').' (user_id,source_event_id,remaining,earned_at,expires_at,provenance) VALUES (?,?,?,?,?,?)',array($user,$event,$lot['remaining'],$lot['earned_at'],$lot['expires_at'],json_encode($lot['original'],JSON_THROW_ON_ERROR)));$id=$db->id();$this->write($db,'INSERT INTO '.$db->table('allocation').' (event_id,lot_id,leg,reason,delta_remaining,delta_reserved) VALUES (?,?,?,?,?,0)',array($event,$id,++$leg,'opening',$lot['remaining']));}
			foreach(array('registration'=>'_rewardly_bonus_registration_awarded','first_order'=>'_rewardly_bonus_first_order_awarded') as $type=>$flag){if(isset($data['meta'][$flag][0])&&'yes'===$data['meta'][$flag][0]){$claim=array('status'=>'legacy_claim','source'=>$flag);$this->write($db,'INSERT INTO '.$db->table('event').' (user_id,operation_key,kind,payload_hash,gross_points,result,created_at) VALUES (?,?,?,?,0,?,UTC_TIMESTAMP())',array($user,'user:'.$user.':'.$type.':v1','legacy_claim',hash('sha256',json_encode($claim)),json_encode($claim)));}}
			foreach($reviews['claims'] as $review){if(array('yes')===$review['values']){$claim=array('status'=>'legacy_claim','source'=>'_rewardly_bonus_review_awarded','comment_id'=>$review['comment_id']);$this->write($db,'INSERT INTO '.$db->table('event').' (user_id,operation_key,kind,payload_hash,gross_points,result,created_at) VALUES (?,?,?,?,0,?,UTC_TIMESTAMP())',array($user,'review:'.$review['comment_id'].':bonus:v1','legacy_claim',hash('sha256',json_encode($claim)),json_encode($claim)));}}
			$this->write($db,'UPDATE '.$db->table('wallet').' SET balance=?,state=?,migration_version=1,checksum=? WHERE user_id=?',array($data['balance'],'ready',$hash,$user));$db->commit();$db->close();return array('state'=>'migrated','checksum'=>$hash,'balance'=>$data['balance']);
		}catch(Throwable $e){$db->rollback();$db->close();throw $e;}
	}
	/** Stable account verification under its wallet lock; no WC callbacks or legacy rewrites. */
	public function verify($user) {
		Nodevora_Financial_Wallet::points($user);$db=$this->connect();Nodevora_Financial_Schema::verify($db);
		try{$db->begin();$wallet=$db->one('SELECT * FROM '.$db->table('wallet').' WHERE user_id=? FOR UPDATE',array($user));if(!$wallet){throw new Nodevora_Financial_Error('Account is not migrated.');}
			$snapshot=$db->one('SELECT * FROM '.$db->table('snapshot').' WHERE user_id=? AND version=1',array($user));$errors=array();
			if(!$snapshot||!hash_equals($snapshot['checksum'],hash('sha256',$snapshot['payload']))){$errors[]='snapshot_checksum';}
			if('ready'!==$wallet['state']){$errors[]='account_'.$wallet['state'];}
			$journal=$db->one('SELECT COALESCE(SUM(delta_balance),0) AS balance,COALESCE(SUM(delta_reserved),0) AS reserved,COALESCE(SUM(delta_debt),0) AS debt FROM '.$db->table('event').' WHERE user_id=?',array($user));
			$lots=$db->query('SELECT l.id,l.remaining,l.reserved,COALESCE(SUM(a.delta_remaining),0) AS journal_remaining,COALESCE(SUM(a.delta_reserved),0) AS journal_reserved FROM '.$db->table('lot').' l LEFT JOIN '.$db->table('allocation').' a ON a.lot_id=l.id WHERE l.user_id=? GROUP BY l.id,l.remaining,l.reserved ORDER BY l.id',array($user));$b=0;$h=0;
			foreach($lots as $lot){$b+=(int)$lot['remaining'];$h+=(int)$lot['reserved'];if((int)$lot['remaining']!==(int)$lot['journal_remaining']||(int)$lot['reserved']!==(int)$lot['journal_reserved']||(int)$lot['remaining']<(int)$lot['reserved']||(int)$lot['reserved']<0){$errors[]='lot_allocation:'.$lot['id'];}}
			foreach(array('balance','reserved','debt') as $field){if((int)$wallet[$field]!== (int)$journal[$field]||(int)$wallet[$field]<0){$errors[]='journal_'.$field;}}
			if($b!==(int)$wallet['balance']||$h!==(int)$wallet['reserved']){$errors[]='lot_totals';}
			foreach($db->query('SELECT id,payload,payload_hash,kind FROM '.$db->table('event').' WHERE user_id=? ORDER BY id',array($user)) as $event){if(null!==$event['payload']&&!hash_equals($event['payload_hash'],hash('sha256',$event['payload']))){$errors[]='event_payload:'.$event['id'];}elseif(null===$event['payload']&&!in_array($event['kind'],array('opening','legacy_claim'),true)){$errors[]='unknown_event_payload:'.$event['id'];}}
			$db->rollback();return array('user_id'=>$user,'state'=>$wallet['state'],'verified'=>!$errors,'errors'=>array_values(array_unique($errors)),'snapshot_checksum'=>$snapshot?$snapshot['checksum']:null);
		}catch(Throwable $e){$db->rollback();throw $e;}finally{$db->close();}
	}
	private function write($db,$sql,$params){if(1!==$db->query($sql,$params)){throw new Nodevora_Financial_Error('Migration write was not persisted.');}}
}
