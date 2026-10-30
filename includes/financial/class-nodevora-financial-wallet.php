<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/** All monetary state is owned by this service; no WordPress hooks run within its transactions. */
class Nodevora_Financial_Wallet {
	private $factory;
	private $db;
	private $wallet;
	private $event;
	private $leg = 0;
	public function __construct( $factory = null ) { $this->factory = $factory ?: function() { return new Nodevora_Financial_DB(); }; }
	private function connect() { $factory = $this->factory; return $factory(); }
	public static function points( $value ) {
		if ( ! is_int( $value ) || $value < 0 || $value > intdiv( PHP_INT_MAX, 4 ) ) { throw new Nodevora_Financial_Error( 'Points must be a bounded nonnegative integer.' ); }
		return $value;
	}
	private static function canonical( $value ) {
		if ( is_array( $value ) ) { ksort( $value ); foreach ( $value as $key => $item ) { $value[$key] = self::canonical( $item ); } }
		if ( is_object( $value ) || is_float( $value ) || is_resource( $value ) ) { throw new Nodevora_Financial_Error( 'Noncanonical financial payload.' ); }
		return $value;
	}
	public function read( $user_id ) {
		$db = $this->connect(); $w = $db->one( 'SELECT * FROM ' . $db->table('wallet') . ' WHERE user_id=?', array( self::points($user_id) ) );
		if ( ! $w ) { $db->close(); Nodevora_Installation::ensure_wallet( (int) $user_id ); $db = $this->connect(); $w = $db->one( 'SELECT * FROM ' . $db->table('wallet') . ' WHERE user_id=?', array( self::points($user_id) ) ); }
		if ( ! $w || 'ready' !== $w['state'] ) { throw new Nodevora_Financial_Error( 'Account requires migration or reconciliation.' ); }
		$eligible = $db->one( 'SELECT COALESCE(SUM(remaining-reserved),0) AS amount FROM ' . $db->table('lot') . ' WHERE user_id=? AND (expires_at IS NULL OR expires_at>UTC_TIMESTAMP())', array($user_id) );
		$w['available'] = max( 0, (int) $eligible['amount'] - (int) $w['debt'] ); $db->close(); return $w;
	}
	public function apply( $user_id, $key, $kind, $amount, $details = array() ) {
		self::points($user_id); self::points($amount);
		if ( $user_id < 1 || ! is_string($key) || strlen($key)>191 || ! preg_match('/^[A-Za-z0-9:_\-.]+$/D',$key) || ! in_array($kind,array('credit','debit','reserve','commit','release','reverse','restore','expire','control'),true) ) { throw new Nodevora_Financial_Error('Invalid operation identity.'); }
		Nodevora_Installation::ensure_wallet( (int) $user_id );
		$writes_paused=Nodevora_Financial_Setup::paused();
		$payload = json_encode(self::canonical(array('user'=>$user_id,'kind'=>$kind,'amount'=>$amount,'details'=>$details)),JSON_THROW_ON_ERROR); $hash=hash('sha256',$payload);
		for($attempt=0;$attempt<3;$attempt++) {
			$this->db=$this->connect(); $this->leg=0; $committing=false;
			try {
				Nodevora_Financial_Schema::verify($this->db);
				$this->db->begin();
				$control = Nodevora_Financial_Setup::lock_state( $this->db );
				if ( isset( $control['phase'] ) && in_array( $control['phase'], array( 'restoring', 'restore_review' ), true ) ) { throw new Nodevora_Financial_Error('Restore review is required.'); }
				if ( isset( $control['phase'] ) && 'active' !== $control['phase'] ) { $writes_paused = true; }
				$this->wallet=$this->db->one('SELECT * FROM '.$this->db->table('wallet').' WHERE user_id=? FOR UPDATE',array($user_id));
				if(!$this->wallet || 'ready'!==$this->wallet['state']){throw new Nodevora_Financial_Error('Account requires migration or reconciliation.');}
				$coordinator=null;
				if(isset($details['order'],$details['order_action'])){$coordinator=new Nodevora_Financial_Order();$coordinator->prepare($this->db,$user_id,$kind,$amount,$details);}
				$prior=$this->db->one('SELECT * FROM '.$this->db->table('event').' WHERE operation_key=?',array($key));
				if($prior){if(!hash_equals($prior['payload_hash'],$hash)){throw new Nodevora_Financial_Error('Operation identity conflict.');}$this->db->rollback();$this->db->close();return json_decode($prior['result'],true,512,JSON_THROW_ON_ERROR);}
				if($writes_paused&&(in_array($kind,array('credit','debit','reserve','commit'),true)||(isset($details['order_action'])&&'dispatch'===$details['order_action']))){throw new Nodevora_Financial_Error('New financial writes are paused.');}
				$before=$this->wallet;
				$this->write('INSERT INTO '.$this->db->table('event').' (user_id,operation_key,kind,payload_hash,payload,original_event_id,gross_points,result,created_at) VALUES (?,?,?,?,?,?,?,?,UTC_TIMESTAMP())',array($user_id,$key,$kind,$hash,$payload,isset($details['original_event_id'])?$details['original_event_id']:null,$amount,'{}'));
				$this->event=$this->db->id(); $result=array('status'=>'committed','event_id'=>$this->event,'operation_key'=>$key,'amount'=>$amount);
				$this->expire();
				if('credit'===$kind){$this->credit($amount,$details);}
				elseif('debit'===$kind||'reserve'===$kind){
					if($amount<1 || (int)$this->wallet['balance']-(int)$this->wallet['reserved']-(int)$this->wallet['debt']<$amount){throw new Nodevora_Financial_Error('Insufficient available points.');}
					$this->consume($amount,'reserve'===$kind?'reserve':'spend','reserve'===$kind?$key:null);
					if('reserve'===$kind){$result['reservation']=$key;$lease=isset($details['lease_seconds'])?self::points($details['lease_seconds']):300;if($lease<1||$lease>300){throw new Nodevora_Financial_Error('Invalid pre-dispatch lease.');}$result['deadline']=gmdate('Y-m-d H:i:s',time()+$lease);}
				}
				elseif('commit'===$kind||'release'===$kind){$this->held($kind,$amount,$details);}
				elseif('reverse'===$kind){$collect=$this->validate_reversal($amount,$details);$result['expired_offset']=$amount-$collect;$take=min($collect,(int)$this->wallet['balance']-(int)$this->wallet['reserved']);$this->consume($take,'reverse');$this->wallet['debt']+=( $collect-$take );}
				elseif('restore'===$kind){$this->restore($amount,$details);}
				$this->expire(); $this->settle(); $this->assert_invariants();
				$result['revision']=(int)$before['revision']+1;$result['balance']=(int)$this->wallet['balance'];$result['reserved']=(int)$this->wallet['reserved'];$result['debt']=(int)$this->wallet['debt'];
				$result['available']=max(0,$result['balance']-$result['reserved']-$result['debt']);
				$n=$this->write('UPDATE '.$this->db->table('wallet').' SET balance=?,reserved=?,debt=?,revision=revision+1 WHERE user_id=? AND revision=?',array($result['balance'],$result['reserved'],$result['debt'],$user_id,(int)$before['revision']));if(1!==$n){throw new Nodevora_Financial_Error('Wallet revision conflict.');}
				if($coordinator){$coordinator->finish($this->db,$this->event,$result);}
				$this->write('UPDATE '.$this->db->table('event').' SET delta_balance=?,delta_reserved=?,delta_debt=?,result=? WHERE id=?',array($result['balance']-(int)$before['balance'],$result['reserved']-(int)$before['reserved'],$result['debt']-(int)$before['debt'],json_encode($result,JSON_THROW_ON_ERROR),$this->event));
				$this->write('INSERT INTO '.$this->db->table('outbox').' (event_id,user_id,effect,payload) VALUES (?,?,?,?)',array($this->event,$user_id,'project',json_encode($result,JSON_THROW_ON_ERROR)));
				if($amount && in_array($kind,array('credit','debit','commit','reverse','restore'),true)){$this->write('INSERT INTO '.$this->db->table('outbox').' (event_id,user_id,effect,payload) VALUES (?,?,?,?)',array($this->event,$user_id,'notify',json_encode($result,JSON_THROW_ON_ERROR)));}
				$committing=true;$this->db->commit();$this->db->close();return $result;
			} catch(Nodevora_Financial_Unknown $e){
				if(!$committing){$this->db->rollback();$this->db->close();throw $e;}
				$this->db->close();$check=$this->connect();$row=$check->one('SELECT payload_hash,result FROM '.$check->table('event').' WHERE operation_key=?',array($key));$check->close();
				if($row&&hash_equals($row['payload_hash'],$hash)){return json_decode($row['result'],true,512,JSON_THROW_ON_ERROR);}throw $e;
			} catch(Throwable $e){$this->db->rollback();$this->db->close();if(in_array($e->getCode(),array(1205,1213),true)&&$attempt<2){usleep(10000*($attempt+1));continue;}throw $e;}
		}
		throw new Nodevora_Financial_Error('Financial retry exhausted.');
	}
	private function validate_reversal($amount,$details){
		$id=isset($details['original_event_id'])?$details['original_event_id']:0;$origin=$this->db->one('SELECT * FROM '.$this->db->table('event').' WHERE id=? AND user_id=? AND kind=?',array($id,(int)$this->wallet['user_id'],'credit'));if(!$origin){throw new Nodevora_Financial_Error('Original credit required.');}$prior=$this->db->one('SELECT COALESCE(SUM(gross_points),0) AS amount FROM '.$this->db->table('event').' WHERE original_event_id=? AND kind=? AND id<>?',array($id,'reverse',$this->event));if((int)$prior['amount']+$amount>(int)$origin['gross_points']){throw new Nodevora_Financial_Error('Reversal exceeds original credit.');}
		$expired=$this->db->one('SELECT COALESCE(SUM(-a.delta_remaining),0) AS amount FROM '.$this->db->table('allocation').' a JOIN '.$this->db->table('lot').' l ON l.id=a.lot_id WHERE (l.source_event_id=? OR JSON_UNQUOTE(JSON_EXTRACT(l.provenance,?))=?) AND a.reason=?',array($id,'$.entitlement_event_id',(string)$id,'expire'));$offset=0;foreach($this->db->query('SELECT result FROM '.$this->db->table('event').' WHERE original_event_id=? AND kind=? AND id<>?',array($id,'reverse',$this->event)) as $row){$old=json_decode($row['result'],true,512,JSON_THROW_ON_ERROR);$offset+=isset($old['expired_offset'])?(int)$old['expired_offset']:0;}return $amount-min($amount,max(0,(int)$expired['amount']-$offset));
	}
	private function write($sql,$params){if(1!==$this->db->query($sql,$params)){throw new Nodevora_Financial_Error('Financial write was not persisted.');}return 1;}
	private function lots() { return $this->db->query('SELECT * FROM '.$this->db->table('lot').' WHERE user_id=? AND remaining>0 ORDER BY expires_at IS NULL,expires_at,id FOR UPDATE',array((int)$this->wallet['user_id'])); }
	private function move($lot,$remaining,$reserved,$reason,$reservation=null,$original=null,$original_lot=null){
		$n=$this->write('UPDATE '.$this->db->table('lot').' SET remaining=remaining+?,reserved=reserved+? WHERE id=? AND remaining+?>=0 AND reserved+?>=0 AND remaining+?>=reserved+?',array($remaining,$reserved,(int)$lot['id'],$remaining,$reserved,$remaining,$reserved));if(1!==$n){throw new Nodevora_Financial_Error('Lot invariant rejected.');}
		$this->write('INSERT INTO '.$this->db->table('allocation').' (event_id,lot_id,leg,reservation_key,original_event_id,original_lot_id,reason,delta_remaining,delta_reserved) VALUES (?,?,?,?,?,?,?,?,?)',array($this->event,(int)$lot['id'],++$this->leg,$reservation,$original,$original_lot,$reason,$remaining,$reserved));
		$this->wallet['balance']+=$remaining;$this->wallet['reserved']+=$reserved;
	}
	private function expire(){foreach($this->lots() as $lot){if($lot['expires_at']&&$lot['expires_at']<=gmdate('Y-m-d H:i:s')){$qty=(int)$lot['remaining']-(int)$lot['reserved'];if($qty){$this->move($lot,-$qty,0,'expire');}}}}
	private function consume($amount,$reason,$reservation=null){foreach($this->lots() as $lot){$take=min($amount,(int)$lot['remaining']-(int)$lot['reserved']);if($take){$this->move($lot,'reserve'===$reason?0:-$take,'reserve'===$reason?$take:0,$reason,$reservation);$amount-=$take;}if(!$amount){break;}}if($amount){throw new Nodevora_Financial_Error('Exact lot allocation failed.');}}
	private function credit($amount,$details,$reason='credit',$original=null,$original_lot=null){
		if(!$amount){return;}self::points((int)$this->wallet['balance']+$amount);
		$earned=isset($details['earned_at'])?$details['earned_at']:gmdate('Y-m-d H:i:s');$expires=isset($details['expires_at'])?$details['expires_at']:null;
		if('credit'===$reason){$details['entitlement_event_id']=$this->event;}
		foreach(array($earned,$expires) as $date){if(null!==$date&&(!is_string($date)||!preg_match('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/D',$date))){throw new Nodevora_Financial_Error('Invalid lot date.');}}
		$this->write('INSERT INTO '.$this->db->table('lot').' (user_id,source_event_id,remaining,earned_at,expires_at,provenance) VALUES (?,?,0,?,?,?)',array((int)$this->wallet['user_id'],$this->event,$earned,$expires,json_encode($details,JSON_THROW_ON_ERROR)));$id=$this->db->id();$this->move(array('id'=>$id),$amount,0,$reason,null,$original,$original_lot);
	}
	private function settle(){$settle=min((int)$this->wallet['debt'],(int)$this->wallet['balance']-(int)$this->wallet['reserved']);if($settle){$this->consume($settle,'debt');$this->wallet['debt']-=$settle;}}
	private function held($kind,$amount,$details){
		if(empty($details['reservation'])){throw new Nodevora_Financial_Error('Reservation identity required.');}$key=$details['reservation'];$origin=$this->db->one('SELECT * FROM '.$this->db->table('event').' WHERE operation_key=? AND user_id=? AND kind=?',array($key,(int)$this->wallet['user_id'],'reserve'));
		if(!$origin){throw new Nodevora_Financial_Error('Unknown reservation.');}$result=json_decode($origin['result'],true,512,JSON_THROW_ON_ERROR);if('commit'===$kind&&$result['deadline']<=gmdate('Y-m-d H:i:s')){throw new Nodevora_Financial_Error('Reservation lease expired.');}
		$held=$this->db->query('SELECT lot_id,SUM(delta_reserved) AS held FROM '.$this->db->table('allocation').' WHERE reservation_key=? GROUP BY lot_id HAVING held>0 ORDER BY lot_id',array($key));$total=array_sum(array_column($held,'held'));if($total!==$amount){throw new Nodevora_Financial_Error('Exact reservation amount mismatch.');}
		foreach($held as $row){$qty=(int)$row['held'];$this->move(array('id'=>$row['lot_id']),'commit'===$kind?-$qty:0,-$qty,'commit'===$kind?'spend':'release',$key);}
	}
	private function restore($amount,$details){
		$id=isset($details['original_event_id'])?$details['original_event_id']:0;$origin=$this->db->one('SELECT * FROM '.$this->db->table('event').' WHERE id=? AND user_id=?',array($id,(int)$this->wallet['user_id']));if(!$origin||!in_array($origin['kind'],array('debit','commit'),true)){throw new Nodevora_Financial_Error('Original debit required.');}
		$prior=$this->db->one('SELECT COALESCE(SUM(gross_points),0) AS amount FROM '.$this->db->table('event').' WHERE original_event_id=? AND kind=? AND id<>?',array($id,'restore',$this->event));if((int)$prior['amount']+$amount>(int)$origin['gross_points']){throw new Nodevora_Financial_Error('Restoration exceeds original debit.');}
		$spent=$this->db->query('SELECT a.lot_id,SUM(-a.delta_remaining) AS spent,l.earned_at,l.expires_at,l.source_event_id,l.provenance FROM '.$this->db->table('allocation').' a JOIN '.$this->db->table('lot').' l ON l.id=a.lot_id WHERE a.event_id=? AND a.reason=? GROUP BY a.lot_id,l.earned_at,l.expires_at,l.source_event_id,l.provenance ORDER BY a.lot_id',array($id,'spend'));
		foreach($spent as $lot){$restored=$this->db->one('SELECT COALESCE(SUM(delta_remaining),0) AS amount FROM '.$this->db->table('allocation').' WHERE original_event_id=? AND original_lot_id=? AND reason=?',array($id,(int)$lot['lot_id'],'restore'));$take=min($amount,(int)$lot['spent']-(int)$restored['amount']);if($take){$provenance=json_decode($lot['provenance'],true,512,JSON_THROW_ON_ERROR);$root=isset($provenance['entitlement_event_id'])?(int)$provenance['entitlement_event_id']:(int)$lot['source_event_id'];$this->credit($take,array('earned_at'=>$lot['earned_at'],'expires_at'=>$lot['expires_at'],'original_event_id'=>$id,'entitlement_event_id'=>$root),'restore',$id,(int)$lot['lot_id']);$amount-=$take;}}
		if($amount){throw new Nodevora_Financial_Error('Restoration provenance is incomplete.');}
	}
	private function assert_invariants(){
		$lots=$this->db->one('SELECT COALESCE(SUM(remaining),0) AS balance,COALESCE(SUM(reserved),0) AS reserved FROM '.$this->db->table('lot').' WHERE user_id=?',array((int)$this->wallet['user_id']));
		foreach(array('balance','reserved','debt') as $field){self::points((int)$this->wallet[$field]);}if((int)$lots['balance']!==(int)$this->wallet['balance']||(int)$lots['reserved']!==(int)$this->wallet['reserved']||$this->wallet['balance']<$this->wallet['reserved']){throw new Nodevora_Financial_Error('Financial aggregates are inconsistent.');}
	}
}
