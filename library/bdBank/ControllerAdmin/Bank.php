<?php

class bdBank_ControllerAdmin_Bank extends XenForo_ControllerAdmin_Abstract {
	public function actionTransfer() {
		$formData = $this->_input->filter(array(
			'receivers' => XenForo_Input::STRING,
			'amount' => XenForo_Input::INT,
			'comment' => XenForo_Input::STRING,
		));
		
		if ($this->_request->isPost()) {
			// process the transfer request
			// this code is very similar with bdBank_ControllerPublic_Bank::actionTransfer()
			
			$receiverUsernames = explode(',',$formData['receivers']);
			$userModel = XenForo_Model::create('XenForo_Model_User');
			$receivers = array();
			foreach ($receiverUsernames as $username) {
				$username = trim($username);
				if (empty($username)) continue; 
				$receiver = $userModel->getUserByName($username);
				if (empty($receiver)) {
					return $this->responseError(new XenForo_Phrase('bdbank_transfer_error_receiver_not_found_x', array('username' => $username)));
				}
				$receivers[$receiver['user_id']] = $receiver;
			}
			if (count($receivers) == 0) {
				return $this->responseError(new XenForo_Phrase('bdbank_transfer_error_no_receivers'));
			}
			if ($formData['amount'] == 0) {
				return $this->responseError(new XenForo_Phrase('bdbank_transfer_error_zero_amount'));
			}
			
			$personal = XenForo_Application::get('bdBank')->personal();
			
			foreach ($receivers as $receiver) {
				$personal->give($receiver['user_id'], $formData['amount'], $formData['comment'], bdBank_Model_Bank::TYPE_ADMIN);
			}
			
			return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, XenForo_Link::buildAdminLink('bank/history'));
		} else {
			return $this->responseView('bdBank_ViewAdmin_Transfer', 'bdbank_transfer');
		}
	}
	
	protected function _preDispatch($action) {
		$this->assertAdminPermission('bdbank');
	}
}