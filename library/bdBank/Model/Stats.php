<?php

class bdBank_Model_Stats extends XenForo_Model {
	
	const KEY_RICHEST = 'bdBank_richest';
	const KEY_GENERAL = 'bdBank_general';
	
	const GENERAL_TOTAL_MONEY = 'total';
	const GENERAL_TOTAL_BONUSES = 'totalBonuses';
	const GENERAL_TRANSACTION_TAX = 'transactionTax';
	
	public function getGeneral() {
		$data = $this->_load(self::KEY_GENERAL);
		
		if (empty($data)) {
			$data = $this->rebuildGeneral();
		}
		
		return $data;
	}
	
	public function rebuildGeneral() {
		$data = array();
		
		$bank = bdBank_Model_Bank::getInstance();
		$field = $bank->options('field');
		
		/* @var $db Zend_Db_Adapter_Abstract */
		$db = $this->_getDb();
		
		$data[self::GENERAL_TOTAL_MONEY] = $db->fetchOne("
			SELECT SUM({$field})
			FROM xf_user
		");
		
		$transactionBonuses = $db->fetchOne('
			SELECT SUM(amount)
			FROM xf_bdbank_transaction
			WHERE
				from_user_id = 0
				AND transaction_type = ?
				AND reversed = 0
		', array(bdBank_Model_Bank::TYPE_SYSTEM));
		$archiveBonuses = $db->fetchOne('
			SELECT SUM(amount)
			FROM xf_bdbank_archive
			WHERE
				from_user_id = 0
				AND transaction_type = ?
		', array(bdBank_Model_Bank::TYPE_SYSTEM));
		$data[self::GENERAL_TOTAL_BONUSES] = $transactionBonuses + $archiveBonuses;
		
		$transactionTax = $db->fetchOne('
			SELECT SUM(tax_amount)
			FROM xf_bdbank_transaction
			WHERE reversed = 0
		');
		$archiveTax = $db->fetchOne('
			SELECT SUM(tax_amount)
			FROM xf_bdbank_archive
		');
		$data[self::GENERAL_TRANSACTION_TAX] = $transactionTax + $archiveTax;
		
		$this->_save(self::KEY_GENERAL, $data);
		
		return $data;
	}
	
	public function getRichest() {
		$users = $this->_load(self::KEY_RICHEST);
		
		if (empty($users)) {
			$users = $this->rebuildRichest();
		}
		
		return $users;
	}
	
	public function rebuildRichest() {
		$bank = bdBank_Model_Bank::getInstance();
		$field = $bank->options('field');
		$limit = $bank->options('statsRichestLimit');
		
		$users = $this->fetchAllKeyed("
			SELECT user_id, username, {$field} AS money
			FROM xf_user
			WHERE
				user_state = 'valid'
				AND is_banned = 0
			ORDER BY {$field} DESC
			LIMIT ? 
		", 'user_id', array($limit));
		
		$this->_save(self::KEY_RICHEST, $users);
		
		return $users;
	}
	
	protected function _save($key, $value) {
		/* @var $dataRegistryModel XenForo_Model_DataRegistry */
		$dataRegistryModel = $this->getModelFromCache('XenForo_Model_DataRegistry');
		
		$dataRegistryModel->set($key, $value);
		
		// also save it to our table
		$this->_getDb()->query('
			REPLACE INTO `xf_bdbank_stats`
			(`stats_key`, `stats_date`, `stats_value`, `rebuild_date`)
			VALUES (?, ?, ?, ?)
		', array(
			$key, date('Y-m-d', XenForo_Application::$time),
			serialize($value),
			XenForo_Application::$time
		));
	}
	
	protected function _load($key) {
		/* @var $dataRegistryModel XenForo_Model_DataRegistry */
		$dataRegistryModel = $this->getModelFromCache('XenForo_Model_DataRegistry');
		
		$value = $dataRegistryModel->get($key);
		if (empty($value)) $value = array();
		
		return $value;
	}
	
}