<?php

class bdBank_ViewPublic_Bank_TransferComplete extends XenForo_ViewPublic_Base {
	public function renderJson() {
		$output = array();
		
		if (!empty($this->_params['users'])) {
			$output['bdBank_users'] = array();
			
			foreach ($this->_params['users'] as $user) {
				$output['bdBank_users'][$user['user_id']] = array(
					'user_id' => $user['user_id'],
					'username' => $user['username'],
					'balance' => bdBank_Model_Bank::balance($user),
				);
				
				$output['bdBank_users'][$user['user_id']]['balance_formatted']
					= bdBank_Model_Bank::helperBalanceFormat($output['bdBank_users'][$user['user_id']]['balance']);
			}
		}
		
		$output['_redirectMessage'] = $this->_params['_redirectMessage'];
		$output['_redirectTarget'] = $this->_params['_redirectTarget'];
		
		return $output;
	}
}