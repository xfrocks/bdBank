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
			
			/**
			 * While in TEST mode, transaction will be validated as usual
			 * but the transaction won't be saved (regardless of $saveTransaction)
			 * and users' account information won't be updated
			 */
			bdBank_Model_Bank::TRANSACTION_OPTION_TEST => false,
			
			/**
			 * While in REPLAY mode, all transaction will be accepted
			 * regardless of account state (no money, etc.). After being
			 * in this mode, the account info must be calculated to reflect
			 * correct transaction information.
			 * 
			 * This can also be set by using the static variable bdBank_Model_Bank::$isReplaying
			 */
			bdBank_Model_Bank::TRANSACTION_OPTION_REPLAY => false,
			
			bdBank_Model_Bank::TRANSACTION_OPTION_FROM_BALANCE => false,
		);
		$options = XenForo_Application::mapMerge($defaultOptions, $options);
		$isTesting = empty($options[bdBank_Model_Bank::TRANSACTION_OPTION_TEST]) ? false : true;
		$isReplaying = (empty($options[bdBank_Model_Bank::TRANSACTION_OPTION_REPLAY]) AND empty(bdBank_Model_Bank::$isReplaying)) ? false : true;
		
		/* @var $userModel XenForo_Model_User */
		$userModel = $this->getModelFromCache('XenForo_Model_User');
		
		if ($amount == 0 OR ($from == $to)) {
			throw new bdBank_Exception(bdBank_Exception::NOTHING_TO_DO);
		}
		
		if ($amount < 0) {
			$tmp = $from;
			$from = $to;
			$to = $tmp;
			// damn it, I forgot the magic swap trick 
			// which doesn't need a temporary variable
			$amount *= -1;
		}
		
		// get user records
		$users = array();
		
		if (!empty($options[bdBank_Model_Bank::TRANSACTION_OPTION_USERS])) {
			// use the users array passed in options
			// in order for this to work properly, the array
			// should have both sender and receiver data
			// otherwise, they will be queried anyway!
			$users = $options[bdBank_Model_Bank::TRANSACTION_OPTION_USERS];
		}
		
		if (isset($users[$from]) AND isset($users[$to])) {
			// we already have the users information
			// assuming the required data exists
			// skip the query
		} else {
			$users = $userModel->getUsersByIds(array($from, $to), array(
				'join' => XenForo_Model_Post::FETCH_USER_OPTIONS | XenForo_Model_Post::FETCH_USER_PROFILE
			));
		}
		
		if ($from > 0) {
			if (empty($users[$from])) {
				throw new bdBank_Exception(bdBank_Exception::USER_NOT_FOUND, $from);
			}
			$userFrom = $users[$from];
		}
		if ($to > 0) {
			if (empty($users[$to])) {
				throw new bdBank_Exception(bdBank_Exception::USER_NOT_FOUND, $to);
			}
			$userTo = $users[$to];
		}
		
		// calculate amount for each party
		$fromAmount = -1 * $amount;
		$fromBalance = 0;
		$fromBalanceAfter = 0;
		$toAmount = $amount;
		$taxAmount = $this->calculateTax($from, $to, $amount, $type, $options[bdBank_Model_Bank::TAX_MODE_KEY]);
		
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
			$fromBalance = $userFrom[self::field()];
			// use the fromBalance from options if it exists
			if ($options[bdBank_Model_Bank::TRANSACTION_OPTION_FROM_BALANCE] !== false) {
				$fromBalance = $options[bdBank_Model_Bank::TRANSACTION_OPTION_FROM_BALANCE];
			}
			$fromBalanceAfter = $fromBalance + $fromAmount;
			
			if ($fromBalanceAfter < 0) {
				if ($isTesting OR $isReplaying) {
					// ignore this fatal error...
				} else {
					throw new bdBank_Exception(bdBank_Exception::NOT_ENOUGH_MONEY, $from);
				}
			}
		}
		
		if ($isTesting) {
			// skip all db updates
		} else {
			// only proceed with db interactions if this is not a transaction test
			XenForo_Db::beginTransaction();
		
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
			
			if ($isReplaying) {
				// REPLAY mode: do not update account information
			} else {
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
			
			XenForo_Db::commit();
		}
		
		return array(
			'from_amount' => $fromAmount,
			'from_balance' => $fromBalance,
			'from_balance_after' => $fromBalanceAfter,
			'to_amount' => $toAmount,
			'tax_amount' => $taxAmount
		);
	}
	
	public function give(
		$userId,
		$amount,
		$comment,
		$type = bdBank_Model_Bank::TYPE_SYSTEM,
		$saveTransaction = true,
		array $options = array()
	) {
		try {
			return $this->transfer(0, $userId, $amount, $comment, $type, $saveTransaction, $options);
		} catch (bdBank_Exception $e) {
			// keep silent
		}
	}
	
	public static function field() {
		// just a shortcut to access the field name
		return bdBank_Model_Bank::options('field');
	}
}