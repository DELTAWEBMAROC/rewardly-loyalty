<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/** Pure database coordination. Called after the wallet lock and before any lot lock. */
class Nodevora_Financial_Order {
	private $row;
	private $quote;
	private $action;
	public function prepare($db, $user, $kind, $amount, $details) {
		$q = $details['order']; $this->quote = $q; $this->action = $details['order_action'];
		foreach (array('order_id','points','discount_minor','scale') as $field) { if (!isset($q[$field])) { throw new Nodevora_Financial_Error('Incomplete order binding.'); } Nodevora_Financial_Wallet::points($q[$field]); }
		if (!$q['order_id'] || !isset($q['intent'],$q['fingerprint'],$q['currency']) || !preg_match('/^[a-f0-9]{32}$/D',$q['intent']) || !preg_match('/^[a-f0-9]{64}$/D',$q['fingerprint']) || !preg_match('/^[A-Z]{3}$/D',$q['currency'])) { throw new Nodevora_Financial_Error('Invalid order binding.'); }
		$db->query('INSERT IGNORE INTO '.$db->table('order_state').' (order_id,user_id,intent,state,fingerprint,points,discount_minor,currency,data,updated_at) VALUES (?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP())',array($q['order_id'],$user,$q['intent'],'bound',$q['fingerprint'],$q['points'],$q['discount_minor'],$q['currency'],json_encode($q,JSON_THROW_ON_ERROR)));
		$this->row = $db->one('SELECT * FROM '.$db->table('order_state').' WHERE order_id=? FOR UPDATE',array($q['order_id'])); $r = $this->row;
		if ((int)$r['user_id'] !== $user || in_array($r['state'],array('legacy_review','exception'),true)) { throw new Nodevora_Financial_Error('Order requires financial reconciliation.'); }
		if ('fund' === $this->action && 'closed' === $r['state'] && $r['intent'] !== $q['intent']) {
			$this->write($db,'UPDATE '.$db->table('order_state').' SET intent=?,state=?,fingerprint=?,points=?,discount_minor=?,currency=?,debit_event_id=NULL,generation=generation+1,revision=revision+1,data=?,updated_at=UTC_TIMESTAMP() WHERE order_id=?',array($q['intent'],'bound',$q['fingerprint'],$q['points'],$q['discount_minor'],$q['currency'],json_encode($q,JSON_THROW_ON_ERROR),$q['order_id']));
			$this->row=$db->one('SELECT * FROM '.$db->table('order_state').' WHERE order_id=?',array($q['order_id'])); $r=$this->row;
		}
		if ($r['intent'] !== $q['intent'] || $r['fingerprint'] !== $q['fingerprint'] || (int)$r['points'] !== $q['points'] || (int)$r['discount_minor'] !== $q['discount_minor'] || $r['currency'] !== $q['currency']) { throw new Nodevora_Financial_Error('Order financial binding changed; confirmation required.'); }
		if ('fund' === $this->action) {
			if ('debit' !== $kind || $amount !== $q['points'] || !in_array($r['state'],array('bound','funded'),true)) { throw new Nodevora_Financial_Error('Order cannot use this funding intent.'); }
			if ('funded' === $r['state']) { $this->funding($db); }
		} elseif ('dispatch' === $this->action) {
			if ('funded' !== $r['state']) { throw new Nodevora_Financial_Unknown('Payment already dispatched or requires reconciliation; no automatic redispatch.'); }
			$this->funding($db);
		} elseif ('paid' === $this->action) {
			if (!in_array($r['state'],array('funded','dispatched','paid'),true)) { throw new Nodevora_Financial_Error('Paid order has no current funding.'); } $this->funding($db);
		} elseif ('compensate' === $this->action) {
			if ('restore' !== $kind || $amount !== $q['points'] || (int)$r['debit_event_id'] !== (int)$details['original_event_id'] || !in_array($r['state'],array('funded','dispatched','paid','closed'),true)) { throw new Nodevora_Financial_Error('Compensation binding is invalid.'); }
		} else { throw new Nodevora_Financial_Error('Unsupported order operation.'); }
	}
	private function funding($db) {
		$r=$this->row;
		$e=$db->one('SELECT * FROM '.$db->table('event').' WHERE id=? AND user_id=? AND kind=?',array((int)$r['debit_event_id'],(int)$r['user_id'],'debit'));
		$restored=$db->one('SELECT COALESCE(SUM(gross_points),0) AS amount FROM '.$db->table('event').' WHERE original_event_id=? AND kind=?',array((int)$r['debit_event_id'],'restore'));
		if (!$e || (int)$e['gross_points'] !== (int)$r['points'] || (int)$restored['amount'] !== 0) { throw new Nodevora_Financial_Error('Current order debit is absent or compensated.'); }
	}
	public function finish($db, $event, &$result) {
		$states=array('fund'=>'funded','dispatch'=>'dispatched','paid'=>'paid','compensate'=>'closed'); $state=$states[$this->action];
		$debit='fund'===$this->action?$event:$this->row['debit_event_id'];
		$this->write($db,'UPDATE '.$db->table('order_state').' SET state=?,debit_event_id=?,revision=revision+1,updated_at=UTC_TIMESTAMP() WHERE order_id=?',array($state,$debit,$this->quote['order_id']));
		$result['order_id']=$this->quote['order_id']; $result['order_state']=$state; $result['intent']=$this->quote['intent'];
	}
	private function write($db,$sql,$params) { if (1 !== $db->query($sql,$params)) { throw new Nodevora_Financial_Error('Order coordination write failed.'); } }
	public static function read($id) {
		$db=new Nodevora_Financial_DB(); try { return $db->one('SELECT * FROM '.$db->table('order_state').' WHERE order_id=?',array((int)$id)); } finally { $db->close(); }
	}
}
