<?php

class bdBank_Model_Personal extends XenForo_Model {
	/**
	 * @var bdBank_Model_Bank
	 */
	protected $_bank = null;
	
	public function __construct() {
		parent::__construct();
		
		$this->_bank = bdBank_Model_Bank::getInstance(); // load the Bank model
	}
	
	public function calculateTax($from, $to, $amount, $type, $taxMode) {
		$useTax = bdBank_Model_Bank::options('useTax');
		if (empty($useTax)) {
			// the system has been configured to not use tax
			return 0;
		}
		
		if ($type != bdBank_Model_Bank::TYPE_PERSONAL) {
			// only personal transaction will have tax
			return 0;
		}
		
		if ($taxMode == bdBank_Model_Bank::TAX_MODE_CHARGE_WAIVED) {
			// charge waived for this transaction, no tax
			return 0;
		}
		
		if ($from == 0) {
			// no tax for Bank transaction
			return 0;
		}
		
		// calculates by tax rules
		$taxRules = bdBank_Model_Bank::options('taxRules');
		$remaining = $amount;
		$tax = 0;
		foreach ($taxRules as $taxRule) {
			if ($taxRule[0] != '*') {
				$tmp = min($remaining, $taxRule[0]);
			} else {
				$tmp = $remaining;
			}
			
			$tax += $tmp * $taxRule[1] / 100;
			$remaining -= $tmp;
			
			if ($remaining <= 0) break; // stop looping
		}
		$tax = ceil($tax);
		
		// take the minimum tax into account
		$minimumTax = bdBank_Model_Bank::options('minimumTax');
		$tax = max($tax, $minimumTax);
		
		if ($taxMode == bdBank_Model_Bank::TAX_MODE_RECEIVER_PAY) {
			$tax = min($tax, $amount); // just to be logical...
		}
		
		$tax = max(0, $tax); // just to be safe...
		
		return $tax;
	}
	
	public function transfer(
		$from,
		$to,
		$amount,
		$comment = null,
		$type = bdBank_Model_Bank::TYPE_PERSONAL,
		$saveTransaction = true,
		array $options = array()
	) {
		$db = $this->_getDb();
		$from = intval($from);
		$to = intval($to);
		$amount = intval($amount);
		
		// merge specified options with the default options set
		$defaultOptions = array(
			bdBank_Model_Bank::TAX_MODE_KEY => bdBank_Model_Bank::TAX_MODE_RECEIVER_PAY,
		);
		$options = XenForo_Application::mapMerge($defaultOptions, $options);
		
		/* @var $userModel XenForo_Model_User */
		$userModel = $this->getModelFromCache('XenForo_Model_User');
		
		if ($amount == 0 OR ($from == $to)) {
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
		
		// get live record from database
		$users = $userModel->getUsersByIds(array($from, $to), array(
			'join' => XenForo_Model_Post::FETCH_USER_OPTIONS | XenForo_Model_Post::FETCH_USER_PROFILE
		));
		if ($from > 0) {
			if (empty($users[$from])) {
				throw new bdBank_Exception('user_not_found', $from);
			}
			$userFrom = $users[$from];
		}
		if ($to > 0) {
			if (empty($users[$to])) {
				throw new bdBank_Exception('user_not_found', $to);
			}
			$userTo = $users[$to];
		}
		
		// calculate amount for each party
		$taxAmount = $this->calculateTax($from, $to, $amount, $type, $options[bdBank_Model_Bank::TAX_MODE_KEY]);
		$fromAmount = -1 * $amount;
		$toAmount = $amount;
		
		if ($taxAmount > 0) {
			switch ($options[bdBank_Model_Bank::TAX_MODE_KEY]) {
				case bdBank_Model_Bank::TAX_MODE_RECEIVER_PAY:
					$toAmount -= $taxAmount;
					break;
				case bdBank_Model_Bank::TAX_MODE_SENDER_PAY:
					$fromAmount -= $taxAmount;
					break;
			}
		}
		
		// checks for sufficient money
		if ($from > 0) {
			if ($userFrom[self::field()] + $fromAmount < 0) {
				throw new bdBank_Exception('not_enough_money', $from);
			}
		}
		
		$db->beginTransaction();
		
		if ($saveTransaction) {
			$transaction = array(
				'from_user_id' => $from,
				'to_user_id' => $to,
				'amount' => $toAmount + $taxAmount,
				'tax_amount' => $taxAmount,
				'comment' => (string) $comment,
				'transaction_type' => $type,
			);
			$this->_bank->saveTransaction($transaction);
		}
		
		if ($from > 0) {
			$this->_getDb()->query("
				UPDATE xf_user
				SET `" . self::field() . "` = `" . self::field() . "` + $fromAmount
				WHERE user_id = $from
			");
		}
		if ($to > 0) {
			$this->_getDb()->query("
				UPDATE xf_user
				SET `" . self::field() . "` = `" . self::field() . "` + $toAmount
				WHERE user_id = $to
			");
		}
		
		if ($type == bdBank_Model_Bank::TYPE_PERSONAL AND !empty($userFrom) AND !empty($userTo)) {
			// send an alert if this is a personal transaction
			// between 2 real users
			if (!$userModel->isUserIgnored($userTo, $userFrom['user_id'])
				&& XenForo_Model_Alert::userReceivesAlert($userTo, 'bdbank_transaction', 'transfer')
			)
			{
				XenForo_Model_Alert::alert($userTo['user_id'],
					$userFrom['user_id'], $userFrom['username'],
					'bdbank_transaction', $transaction['transaction_id'],
					'transfer'
				);
			}
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