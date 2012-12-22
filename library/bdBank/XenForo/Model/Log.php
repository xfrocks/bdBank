<?php

class bdBank_XenForo_Model_Log extends XFCP_bdBank_XenForo_Model_Log {
	public function pruneAdminLogEntries($pruneDate = null) {
		// this method will be called daily
		// we will call our methods from here (TRICKY!)
		$bank =bdBank_Model_Bank::getInstance(); 
		
		// archive transaction
		$bank->archiveTransactions();
		
		// rebuild general statistics
		$bank->stats()->rebuildGeneral();
		
		return parent::pruneAdminLogEntries($pruneDate);
	}
}