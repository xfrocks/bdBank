<?php

class bdBank_XenForo_Model_Log extends XFCP_bdBank_XenForo_Model_Log {
	public function pruneAdminLogEntries($pruneDate = null) {
		// this method will be called daily
		// we will call our method from here (TRICKY!)
		bdBank_Model_Bank::getInstance()->archiveTransactions();
		
		return parent::pruneAdminLogEntries($pruneDate);
	}
}