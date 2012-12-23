<?php

class bdBank_bdPaygate_Model_Processor extends XFCP_bdBank_bdPaygate_Model_Processor {
	protected function _processIntegratedAction($action, $user, $data) {
		if ($action == 'bdbank_purchase') {
			$personal = bdBank_Model_Bank::getInstance()->personal();
			$personal->give($user['user_id'], $data[0], 'bdbank_purchase ' . $data[0]);
			
			return 'Transfered ' . $data[0] . ' to user #' . $user['user_id'];
		}
		
		return parent::_processIntegratedAction($action, $user, $data);
	}
	
	protected function _revertIntegratedAction($action, $user, $data)
	{
		if ($action == 'bdbank_purchase') {
			$personal = bdBank_Model_Bank::getInstance()->personal();
			$personal->give($user['user_id'], -1 * $data[0], 'bdbank_purchase_revert ' . $data[0]);
			
			return 'Taken away ' . $data[0] . ' from user #' . $user['user_id'];
		}
		
		return parent::_revertIntegratedAction($action, $user, $data);
	}
}