<?php

class bdBank_Model_Bank extends XenForo_Model {
	// the type contants are used in bdBank_ControllerPublic_Bank::actionHistory() to filter non-system transactions
	// please update it if you add more types here...
	const TYPE_SYSTEM = 0;
	const TYPE_PERSONAL = 1;
	const TYPE_ADMIN = 2;
	
	const TAX_MODE_KEY = 'taxMode';
	const TAX_MODE_RECEIVER_PAY = 'receiver';
	const TAX_MODE_SENDER_PAY = 'sender';
	const TAX_MODE_CHARGE_WAIVED = 'charge_waived';
	
	const TRANSACTION_OPTION_TEST = 'opt_test';
	const TRANSACTION_OPTION_REPLAY = 'opt_replay';
	const TRANSACTION_OPTION_FROM_BALANCE = 'opt_fromBalance';
	const TRANSACTION_OPTION_USERS = 'opt_users';
	
	const PERM_GROUP = 'bdbank';
	const PERM_PURCHASE = 'bdbank_purchase';
	const PERM_USE_ATTACHMENT_MANAGER = 'bdbank_use_attach_manager';
	
	const BALANCE_NOT_AVAILABLE = 'N/A';
	
	const ROUTE_PREFIX_NOT_FOUND = 'no-route-for-bank-found';
	
	const FETCH_USER = 0x01;

	/**
	 * Please read about TRANSACTION_OPTION_REPLAY in bdBank_Model_Personal::transfer()
	 * 
	 * @var bool
	 */
	public static $isReplaying = false;
	
	protected static $_reversedTransactions = array();
	protected static $_taxRules = false;
	protected static $_getMorePrices = false;
	protected static $_exchangeRates = false;
	
	public function canRefund(array $transaction, array $viewingUser = null) {
		$this->standardizeViewingUserReference($viewingUser);
		
		if ($transaction['transaction_type'] == self::TYPE_PERSONAL
			AND $transaction['reversed'] == 0
			AND $transaction['to_user_id'] == $viewingUser['user_id']
		) {
			return true;
		}
		
		return false;
	}
	
	/**
	 * Called from the listener to do extra stuff with the navigation tab
	 * 
	 * @param array $extraTabs the full array of extra tabs
	 * @param string $tabId tab id of [bd] Banking
	 * @param string $routePrefix the primary route prefix for [bd] Banking controller (you should use it in links)
	 * @param XenForo_Visitor $visitor the current visitor
	 */
	public function prepareNavigationTab(array &$extraTabs, $tabId, $routePrefix, XenForo_Visitor $visitor) {
		if ($visitor->hasPermission(self::PERM_GROUP, self::PERM_PURCHASE)) {
			$extraTabs[$tabId]['links'][XenForo_Link::buildPublicLink("full:$routePrefix/get-more")] = new XenForo_Phrase('bdbank_get_more_x', array('money' => new XenForo_Phrase('bdbank_money'))); 
		}
		if ($visitor->hasPermission(self::PERM_GROUP, self::PERM_USE_ATTACHMENT_MANAGER)) {
			$extraTabs[$tabId]['links'][XenForo_Link::buildPublicLink("full:$routePrefix/attachment-manager")] = new XenForo_Phrase('bdbank_attachment_manager'); 
		}
	}
	
	public function getActionBonus($action, $extraData = array()) {
		// I prefer a static method
		// but just in case... someone wants to extend this class?
		switch($action) {
			case 'thread':
			case 'post':
				// get the points from system options
				$points = self::options('bonus_' . $action);
				
				// try to get forum-based points if any
				if (empty($extraData['forum'])) {
					throw new bdBank_Exception('thread_and_post_bonus_requires_forum_info');
				} else {
					if (!empty($extraData['forum']['bdbank_options'])) {
						$tmpOptions = self::helperUnserialize($extraData['forum']['bdbank_options']);
						if (isset($tmpOptions['bonus_' . $action]) AND $tmpOptions['bonus_' . $action] !== '') {
							$points = intval($tmpOptions['bonus_' . $action]);
						}
					}
				}
				
				return $points;
			
			case 'attachment_post': return self::options('bonus_attachment_post');
			case 'liked': return self::options('bonus_liked');
			
			case 'unlike': return -1 * self::options('penalty_unlike'); // it's kinda funny here, but I'm lazy
			
			case 'attachment_downloaded':
				if (empty($extraData)) {
					throw new bdBank_Exception('attachment_downloaded_bonus_requires_file_extension_as_extra_data');
				} else {
					$extension = strtolower($extraData);
					$list = explode("\n", self::options('bonus_attachment_downloaded'));
					foreach ($list as $line) {
						$parts = explode("=", $line);
						if (count($parts) == 2) {
							$extensions = explode(',', str_replace(' ', '', strtolower($parts[0])));
							$point = intval($parts[1]);
							if (count($extensions) == 1 AND $extensions[0] == '*') {
								// match all rule
								return $point;
							} else if (in_array($extension, $extensions)) {
								return $point;
							}
						}
					}
				}
				return 0;
				break;
			
			default: return 0; break;
		}
	}
	
	public function parseComment($comment) {
		// just in case someone extends this
		$parts = explode(' ',$comment);
		$link = false;
		if (count($parts) == 2) {
			// all default system commment have 2 parts only
			switch ($parts[0]) {
				case 'post':
				case 'attachment_post':
				case 'liked_post':
				case 'unlike_post':
					$comment = new XenForo_Phrase('bdbank_explain_comment_' . $parts[0]);
					$link = XenForo_Link::buildPublicLink('posts', array('post_id' => $parts[1])); 
					break;
				case 'attachment_downloaded':
				case 'attachment_downloaded_paid':	
					$comment = new XenForo_Phrase('bdbank_explain_comment_' . $parts[0]);
					$link = XenForo_Link::buildPublicLink('attachments', array('attachment_id' => $parts[1])); 
					break; 
				case 'manually_edited':
					$comment = new XenForo_Phrase('bdbank_explain_comment_manually_edited_by_admin_x', array('admin_id' => $parts[1]));
					break;
				case 'bdbank_purchase':
				case 'bdbank_purchase_revert':
					$comment = new XenForo_Phrase('bdbank_explain_comment_' . $parts[0], array(
						'amount' => XenForo_Template_Helper_Core::callHelper('bdbank_balanceformat', array($parts[1])) 
					));
					$routePrefix = bdBank_Model_Bank::routePrefix();
					if ($routePrefix != self::ROUTE_PREFIX_NOT_FOUND) {
						$link = XenForo_Link::buildPublicLink($routePrefix . '/get-more');
					} 
					break;
			}
		}
		
		return array($comment, $link);
	}
	
	public function archiveTransactions($thresholdDate = null) {
		if ($thresholdDate === null) {
			$daysOfHistory = self::options('daysOfHistory');
			if (empty($daysOfHistory)) {
				// admin disabled this feature
				return;
			}
			
			$thresholdDate = XenForo_Application::$time - 86400 * $daysOfHistory;
		}
		
		$this->_getDb()->query('
			INSERT INTO `xf_bdbank_archive`
			(transaction_id, from_user_id, to_user_id, amount, tax_amount, comment, transaction_type, transfered)
			SELECT transaction_id, from_user_id, to_user_id, amount, tax_amount, comment, transaction_type, transfered
			FROM `xf_bdbank_transaction`
			WHERE transfered < ?
				AND reversed = 0
		', $thresholdDate);
		
		$this->_getDb()->query('
			DELETE FROM `xf_bdbank_transaction`
			WHERE transfered < ?
		', $thresholdDate);
	}
	
	public function saveTransaction(&$data) {
		static $required = array('from_user_id', 'to_user_id', 'amount', 'comment', 'tax_amount', 'transaction_type');
		foreach ($required as $column) {
			if (!isset($data[$column])) {
				throw new bdBank_Exception('transaction_data_missing');
			}
		}
		
		$rtFound = false;
		if ($data['transaction_type'] == self::TYPE_SYSTEM) {
			// only looking for system type transaction
			// normal transaction doesn't have much reversion
			foreach (self::$_reversedTransactions as &$rt) {
				$mismatched = false;
				foreach ($required as $column) {
					if ($rt[$column] != $data[$column]) {
						$mismatched = true;
						break;
					}
				}
				if (!$mismatched) {
					$rtFound = $rt['transaction_id'];
					break;
				}
			}
		}

		if ($rtFound) {
			// found a reversed transaction with the same data
			// this happens a lot, we will update the old transaction
			// instead of creating a lot of reversed transactions
			$this->_getDb()->update('xf_bdbank_transaction', array('reversed' => 0), array('transaction_id = ?' => $rtFound));
			$data['transaction_id'] = $rtFound;
		} else {
			$data['transfered'] = time();
			$this->_getDb()->insert('xf_bdbank_transaction', $data);
			$data['transaction_id'] = $this->_getDb()->lastInsertId();
		}
	}
	
	public function reverseTransaction($transaction) {
		$personal = $this->personal();

		// get back the fund from receiver account
		// this may throw not_enough_money exception...
		$personal->transfer($transaction['to_user_id'], 0, $transaction['amount'] - $transaction['tax_amount'], null, self::TYPE_SYSTEM, false);

		// actual refund
		$personal->give($transaction['from_user_id'], $transaction['amount'], null, self::TYPE_SYSTEM, false);
		
		$this->_getDb()->update(
			'xf_bdbank_transaction'
			, array('reversed' => XenForo_Application::$time)
			, array('transaction_id = ?' => $transaction['transaction_id'])
		);
	}
	
	public function reverseSystemTransactionByComment($comments) {
		if (!is_array($comments)) $comments = array($comments);
		if (empty($comments)) return;
		
		$commentsQuoted = $this->_getDb()->quote($comments);
		$reversed = array();
			
		$transactionFound = $this->fetchAllKeyed('
			SELECT *, "transaction" AS found_table
			FROM xf_bdbank_transaction
			WHERE comment IN (' . $commentsQuoted . ')
				AND reversed = 0
				AND transaction_type = ' . self::TYPE_SYSTEM . '
		','transaction_id');
		
		$archivedFound = $this->fetchAllKeyed('
			SELECT *, "archive" AS found_table
			FROM xf_bdbank_archive
			WHERE comment IN (' . $commentsQuoted . ')
				AND transaction_type = ' . self::TYPE_SYSTEM . '
		','transaction_id');
		
		$found = array_merge($transactionFound, $archivedFound);
		
		if (count($found) > 0) {
			$personal = $this->personal();
			
			XenForo_Db::beginTransaction();
			
			foreach ($found as $info) {
				try {
					$personal->transfer($info['to_user_id'], $info['from_user_id'], $info['amount'], null, self::TYPE_SYSTEM, false);
					$reversed[$info['transaction_id']] = $info['amount'];
					
					if ($info['found_table'] == 'transaction') {
						self::$_reversedTransactions[$info['transaction_id']] = $info;
					}
				} catch (bdBank_Exception $e) {
					// simply ignore it
				}
			}
			
			if (count($reversed) > 0) {
				$this->_getDb()->update(
					'xf_bdbank_transaction'
					, array('reversed' => XenForo_Application::$time)
					, 'transaction_id IN (' . implode(',', array_keys($reversed)) . ')'
				);
				$this->_getDb()->delete(
					'xf_bdbank_archive'
					, 'transaction_id IN (' . implode(',', array_keys($reversed)) . ')'
				);
			}
			
			XenForo_Db::commit();
		}

		$totalReversed = 0;
		foreach ($reversed as $amount) {
			$totalReversed += $amount;
		}
		
		return $totalReversed;
	}
	
	public function getTransactionByComment($comment, array $fetchOptions = array()) {
		$fetchOptions['order'] = 'transaction_id';
		$fetchOptions['direction'] = 'desc';
		$fetchOptions['limit'] = 1;
		
		$transactions = $this->getTransactions(array('comment' => $comment), $fetchOptions);
		
		return reset($transactions);
	}
	
	public function getTransactionById($transactionId, array $fetchOptions = array()) {
		$transactions = $this->getTransactions(array('transaction_id' => $transactionId), $fetchOptions);
		
		return reset($transactions);
	}
	
	public function countTransactions(array $conditions = array(), array $fetchOptions = array()) {
		$tableName = $this->prepareTableName($conditions);
		$whereClause = $this->prepareTransactionConditions($conditions, $fetchOptions);
		$joinOptions = $this->prepareTransactionFetchOptions($fetchOptions);

		return $this->_getDb()->fetchOne('
				SELECT COUNT(*)
				FROM `' . $tableName . '` AS transaction
				' . $joinOptions['joinTables'] . '
				WHERE ' . $whereClause . '
		');
	}
	
	public function getTransactions(array $conditions = array(), array $fetchOptions = array()) {
		$tableName = $this->prepareTableName($conditions);
		$whereClause = $this->prepareTransactionConditions($conditions, $fetchOptions);

		$orderClause = $this->prepareTransactionOrderOptions($fetchOptions);
		$joinOptions = $this->prepareTransactionFetchOptions($fetchOptions);
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		$transactions = $this->fetchAllKeyed($this->limitQueryResults(
			'
				SELECT transaction.*
					' . $joinOptions['selectFields'] . '
				FROM `' . $tableName . '` AS transaction
				' . $joinOptions['joinTables'] . '
				WHERE ' . $whereClause . '
				' . $orderClause . '
			', $limitOptions['limit'], $limitOptions['offset']
		), 'transaction_id');
		
		if (!empty($fetchOptions['join']) AND ($fetchOptions['join'] & self::FETCH_USER)) {
			$userIds = array();
			$usernames = array();
			foreach ($transactions as $transaction) {
				$userIds[] = $transaction['from_user_id'];
				$userIds[] = $transaction['to_user_id'];
			}
			$userIds = array_unique($userIds);
			if (count($userIds) > 0) {
				$users = $this->_getUserModel()->getUsersByIds($userIds);
				foreach ($userIds as $userId) {
					if (!isset($users[$userId])) {
						$users[$userId] = array(
							'user_id' => 0,
							'username' => new XenForo_Phrase('bdbank_user_x', array('id' => $userId))
						); // handle deleted users
					}
				}
			}
			$users['0'] = array(
				'user_id' => 'hmm', // user_id can not be 0 or gravatar won't show up...
				'username' => new XenForo_Phrase('bdbank_bank'),
				'gravatar' => self::options('gravatar'),
			);
		}

		foreach ($transactions as &$transaction) {
			if (!empty($fetchOptions['join']) AND ($fetchOptions['join'] & self::FETCH_USER)) {
				$transaction['from_user'] = $users[$transaction['from_user_id']];
				$transaction['to_user'] = $users[$transaction['to_user_id']];
				if (isset($conditions['user_id'])) {
					$transaction['other_user'] = $users[$transaction['from_user_id'] == $conditions['user_id'] ? $transaction['to_user_id'] : $transaction['from_user_id']];
				}
			}
			
			if ($transaction['transaction_type'] == self::TYPE_SYSTEM) {
				list($transaction['comment'], $transaction['comment_link']) = $this->parseComment($transaction['comment']);
			}
			
			if (isset($conditions['user_id'])) {
				$isSending = $transaction['from_user_id'] == $conditions['user_id'];
				$transaction['is_sending'] = $isSending;
			}
		}
		
		return $transactions;
	}
	
	public function prepareTableName(array $conditions) {
		if (empty($conditions['archive'])) {
			return 'xf_bdbank_transaction';
		} else {
			return 'xf_bdbank_archive';
		}
	}

	public function prepareTransactionConditions(array $conditions, array &$fetchOptions) {
		$db = $this->_getDb();
		$sqlConditions = array();
		
		if (!empty($conditions['transaction_id'])) {
			if (is_array($conditions['transaction_id'])) {
				$sqlConditions[] = 'transaction.transaction_id IN (' . $db->quote($conditions['transaction_id']) . ')';
			} else {
				$sqlConditions[] = 'transaction.transaction_id = ' . $db->quote($conditions['transaction_id']);
			}
		}

		if (!empty($conditions['user_id'])) {
			$userIdQuoted = $db->quote($conditions['user_id']);
			if (is_array($conditions['user_id'])) {
				$sqlConditions[] = '(transaction.from_user_id IN (' . $userIdQuoted . ') OR transaction.to_user_id IN (' . $userIdQuoted . '))';
			} else {
				$sqlConditions[] = '(transaction.from_user_id = ' . $userIdQuoted . ' OR transaction.to_user_id = ' . $userIdQuoted . ')';
			}
		}
		
		if (!empty($conditions['transaction_type'])) {
			if (is_array($conditions['transaction_type'])) {
				$sqlConditions[] = 'transaction.transaction_type IN (' . $db->quote($conditions['transaction_type']) . ')';
			} else {
				$sqlConditions[] = 'transaction.transaction_type = ' . $db->quote($conditions['transaction_type']);
			}
		}
		
		if (!empty($conditions['comment'])) {
			if (is_array($conditions['comment'])) {
				$sqlConditions[] = 'transaction.comment IN (' . $db->quote($conditions['comment']) . ')';
			} else {
				$sqlConditions[] = 'transaction.comment = ' . $db->quote($conditions['comment']);
			}
		}
		
		if (!empty($conditions['amount']) && is_array($conditions['amount'])) {
			list($operator, $cutOff) = $conditions['amount'];

			$this->assertValidCutOffOperator($operator);
			$sqlConditions[] = "transaction.amount $operator " . $db->quote($cutOff);
		}
		
		if (!empty($conditions['transfered']) && is_array($conditions['transfered'])) {
			list($operator, $cutOff) = $conditions['transfered'];

			$this->assertValidCutOffOperator($operator);
			$sqlConditions[] = "transaction.transfered $operator " . $db->quote($cutOff);
		}
		
		if (!empty($conditions['reversed']) && is_array($conditions['reversed'])) {
			list($operator, $cutOff) = $conditions['reversed'];

			$this->assertValidCutOffOperator($operator);
			$sqlConditions[] = "transaction.reversed $operator " . $db->quote($cutOff);
		}

		return $this->getConditionsForClause($sqlConditions);
	}
	
	public function prepareTransactionFetchOptions(array $fetchOptions) {
		$selectFields = '';
		$joinTables = '';

		return array(
			'selectFields' => $selectFields,
			'joinTables'   => $joinTables
		);
	}

	public function prepareTransactionOrderOptions(array &$fetchOptions) {
		$choices = array(
			'date' => 'transaction.transfered',
			'transaction_id' => 'transaction.transaction_id',
		);
		return $this->getOrderByClause($choices, $fetchOptions);
	}
	
	/**
	 * @return bdBank_Model_Personal
	 */
	public function personal() {
		return $this->getModelFromCache('bdBank_Model_Personal');
	}
	
	/**
	 * @return bdBank_Model_Stats
	 */
	public function stats() {
		return $this->getModelFromCache('bdBank_Model_Stats');
	}
	
	public function macro_bonusAttachment($contentType, $contentId, $userId) {
		$db = XenForo_Application::get('db');
		
		$attachments = $db->fetchOne("SELECT COUNT(*) FROM `xf_attachment` WHERE content_type = ? AND content_id = ?", array($contentType, $contentId));
		if ($attachments > 0) {
			$point = $this->getActionBonus('attachment_' . $contentType);
			if ($point != 0) {
				$this->personal()->give($userId, $point * $attachments, $this->comment('attachment_' . $contentType, $contentId));
			}
		}
	}
	
	protected function _getUserModel() {
		return $this->getModelFromCache('XenForo_Model_User');
	}
	
	/* STATIC METHODS */
	
	/**
	 * @return bdBank_Model_Bank
	 */
	public static function getInstance() {
		$bank = XenForo_Application::get('bdBank');
		
		// sometimes the hook system is not setup properly
		// our global model won't be initialized at all
		// check for it here and create a new instance if neccessary
		if (empty($bank)) {
			static $localBank = false;
			
			if ($localBank === false) {
				$localBank = XenForo_Model::create('bdBank_Model_Bank');
			}
			
			$bank = $localBank;
		}
		
		return $bank;
	}
	
	public static function options($optionId) { 
		switch ($optionId) {
			case 'perPage': return 50;
			case 'perPagePopup': return 5;
			
			case 'statsRichestLimit': return 50;
			
			case 'taxRules':
				if (self::$_taxRules === false) {
					$taxRules = XenForo_Application::get('options')->get('bdbank_taxRules');
					$lines = explode("\n", $taxRules);
					self::$_taxRules = array();
					
					foreach ($lines as $line) {
						if (!empty($line)) {
							$parts = explode(':', trim($line));
							if (count($parts) == 2
								AND (is_numeric($parts[0]) OR $parts[0] == '*')
								AND is_numeric($parts[1])
							) {
								self::$_taxRules[] = array(0, $parts[0], $parts[1]);
							}
						}
					}
					
					for ($i = 0; $i < count(self::$_taxRules) - 1; $i++) {
						self::$_taxRules[$i + 1][0] = self::$_taxRules[$i][1];
					}
					
					// in the end, $_taxRules will be an array of array
					// each array has 3 elements: previous-level-cutoff, cutoff, percent
					// or can be easier to understand: min, max, percent
					
					foreach (self::$_taxRules as &$taxRule) {
						if ($taxRule[1] != '*') {
							$taxRule = array($taxRule[1] - $taxRule[0], $taxRule[2]);
						} else {
							$taxRule = array('*', $taxRule[2]);
						}
					}
					
					// in the real end (oops), $_taxRules is still an array of array
					// each array has 2 elements: range, precent
				}
				return self::$_taxRules;
				
			case 'getMorePrices':
				if (self::$_getMorePrices === false) {
					$prices = XenForo_Application::get('options')->get('bdbank_getMorePrices');
					$lines = explode("\n", $prices);
					self::$_getMorePrices = array();
					
					foreach ($lines as $line) {
						$parts = explode('=', utf8_strtolower(utf8_trim($line)));
						if (count($parts) == 2
							AND is_numeric($parts[0])
							AND preg_match('/^([0-9]+)([a-z]+)$/', $parts[1], $matches)) {
							self::$_getMorePrices[] = array(doubleval($parts[0]), doubleval($matches[1]), $matches[2]);
						}
					}
				}
				return self::$_getMorePrices;
			case 'exchangeRates':
				if (self::$_exchangeRates === false) {
					$rates = XenForo_Application::get('options')->get('bdbank_exchangeRates');
					$lines = explode("\n", $rates);
					self::$_exchangeRates = array();
					
					foreach ($lines as $line) {
						$parts = explode('=', utf8_strtolower(utf8_trim($line)));
						if (count($parts) == 2
							AND preg_match('/^([a-z]+)$/', $parts[0])
							AND is_numeric($parts[1])) {
							self::$_exchangeRates[$parts[0]] = doubleval($parts[1]);
						}
					}
				}
				return self::$_exchangeRates;
		}
		
		return XenForo_Application::get('options')->get('bdbank_' . $optionId);
	}
	
	public static function routePrefix() {
		return defined('BDBANK_PREFIX') ? BDBANK_PREFIX : self::ROUTE_PREFIX_NOT_FOUND; 
	}
	
	/**
	 * Gets user balance
	 */
	public static function balance(array $viewingUser = null) {
		self::getInstance()->standardizeViewingUserReference($viewingUser);
		$field = self::options('field');
		
		if (isset($viewingUser[$field])) {
			return $viewingUser[$field];
		} else {
			return self::BALANCE_NOT_AVAILABLE;
		}
	}
	
	/**
	 * Gets current user accounts
	 */
	public static function accounts(array $viewingUser = null) {
		return array();
	}
	
	public static function comment($type,$id) {
		$comment = "$type $id";
		if (strlen($comment) > 255) {
			// possible?
			$comment = substr($comment, 0, 255 - 32) . md5($comment);
		}
		return $comment;
	}
	
	public static function helperUnserialize($str) {
		if (is_array($str)) {
			$array = $str;
		} else {
			$array = @unserialize($str);
			if (empty($array)) $array = array();
		}
		
		return $array;
	}
	
	public static function helperStrLen($str) {
		if (function_exists('mb_strlen')) {
			return mb_strlen($str);
		} else {
			return strlen($str);
		}
	}
	
	public static function helperBalanceFormat($value) {
		static $currencyName = false;
		
		if ($currencyName === false) {
			// the phrase is cached globally
			// but there's no reason to keep an object instead of a (cheaper) string around...
			$tmp = new XenForo_Phrase('bdbank_currency_name');
			$currencyName = $tmp->render();
		}
		
		$valueFormatted = $value;
		$negative = false;
		
		if (is_numeric($value)) {
			if ($value < 0) {
				$negative = true;
				$value *= -1;
			}
			$valueFormatted = XenForo_Template_Helper_Core::numberFormat($value, 0);
		}
		
		// check for option to include the currency after the number instead?
		return ($negative ? '-' : '')
			. ((self::options('balanceFormat') == 'currency_first')
				? ($currencyName . $valueFormatted)
				: ($valueFormatted . $currencyName));
	}
}