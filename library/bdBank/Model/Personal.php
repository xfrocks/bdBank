<?php

class bdBank_Model_Personal extends XenForo_Model {
	private $_bank = null;
	
	public function __construct() {
		parent::__construct();
		
		$this->_bank = XenForo_Application::get('bdBank'); // load the Bank model
	}
	
	public function transfer($from, $to, $amount, $comment = null, $type = bdBank_Model_Bank::TYPE_PERSONAL, $save = true) {
		$db = $this->_getDb();
		$from = intval($from);
		$to = intval($to);
		$amount = intval($amount);
		
		if ($amount == 0 OR ($from == 0 AND $to == 0)) {
			throw new bdBank_Exception('nothing_to_do');
		}
		if ($amount < 0) {
			$tmp = $from;
			$from = $to;
			$to = $tmp;
			// damn it, I forgot the magic swap trick 
			// which doesn't need a temporary variable
			$amount *= -1;
		}
		
		$inhand = $this->fetchAllKeyed("
			SELECT user_id, " . self::field() . " AS money
			FROM xf_user
			WHERE user_id IN ($from,$to) 
		",'user_id');
		if ($from > 0) {
			if (!isset($inhand["$from"])) {
				throw new bdBank_Exception('user_not_found',$from);
			} else if ($inhand["$from"]['money'] < $amount) {
				throw new bdBank_Exception('not_enough_money',$from);
			}
		}
		if ($to > 0) {
			if (!isset($inhand["$to"])) {
				throw new bdBank_Exception('user_not_found',$to);
			}
		}
		
		$db->beginTransaction();
		if ($save) {
			$transaction = array(
				'from_user_id' => intval($from),
				'to_user_id' => intval($to),
				'amount' => intval($amount),
				'comment' => (string)$comment,
				'transaction_type' => $type,
			);
			$this->_bank->saveTransaction($transaction);
		}
		if ($from > 0) {
			$this->_getDb()->query("
				UPDATE xf_user
				SET `" . self::field() . "` = `" . self::field() . "` - $amount
				WHERE user_id = $from
			");
		}
		if ($to > 0) {
			$this->_getDb()->query("
				UPDATE xf_user
				SET `" . self::field() . "` = `" . self::field() . "` + $amount
				WHERE user_id = $to
			");
		}
		$db->commit();
	}
	
	public function give($user_id, $amount, $reason, $type = bdBank_Model_Bank::TYPE_SYSTEM) {
		try {
			$this->transfer(0, $user_id, $amount, $reason, $type);
		} catch (bdBank_Exception $e) {
			// keep silent
		}
	}
	
	public static function field() {
		// just a shortcut to access the field name
		return bdBank_Model_Bank::options('field');
	}
}