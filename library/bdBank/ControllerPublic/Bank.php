<?php

class bdBank_ControllerPublic_Bank extends XenForo_ControllerPublic_Abstract {
	public function actionIndex() {
		$this->_assertRegistrationRequired();
		
		return $this->responseView(
			'bdBank_ViewPublic_Bank_Index',
			'bdbank_page_index',
			array(
				'balance' => bdBank_Model_Bank::balance(),
				'accounts' => bdBank_Model_Bank::accounts(), 
			)
		);
	}
	
	public function actionHistory() {
		$this->_assertRegistrationRequired();
		
		$visitor = XenForo_Visitor::getInstance();
		$userId = $visitor->get('user_id');
		$bank = XenForo_Application::get('bdBank');

		// please take time to update bdBank_ControllerAdmin_Bank::actionHistory() if you change this
		$conditions = array(
			'user_id' => $userId
		);
		$fetchOptions = array(
			'join' => bdBank_Model_Bank::FETCH_USER,
			'order' => 'date',
			'direction' => 'desc',
		);
		
		if (bdBank_Model_Bank::options('showSystemTransactions')) {
			// show system transactions, do nothing here
		} else {
			// restrict to show non-system transactions only...
			$conditions['transaction_type'] = array(bdBank_Model_Bank::TYPE_PERSONAL, bdBank_Model_Bank::TYPE_ADMIN);
		}
		
		$page = max(1, $this->_input->filterSingle('page', XenForo_Input::UINT));
		$transactionPerPage = bdBank_Model_Bank::options('perPage');
		$fetchOptions['page'] = $page;
		$fetchOptions['limit'] = $transactionPerPage;
		
		$transactions = $bank->getTransactions($conditions, $fetchOptions);
		$totalTransactions = $bank->countTransactions($conditions, $fetchOptions);
		
		return $this->responseView(
			'bdBank_ViewPublic_Bank_History',
			'bdbank_page_history',
			array(
				'breadCrumbs' => array(
					'history' => array(
						'href' => XenForo_Link::buildPublicLink(bdBank_Model_Bank::options('route_prefix') . '/history'),
						'value' => new XenForo_Phrase('bdbank_history'),
						'node_id' => 'history',
					)
				),
				'balance' => bdBank_Model_Bank::balance(), 
				'accounts' => bdBank_Model_Bank::accounts(),
				'transactions' => $transactions,
				
				'page' => $page,
				'perPage' => $transactionPerPage,
				'transactionStartOffset' => ($page - 1) * $transactionPerPage + 1,
				'transactionEndOffset' => ($page - 1) * $transactionPerPage + count($transactions),
				'total' => $totalTransactions,
				'pagenavLink' => bdBank_Model_Bank::options('route_prefix') . '/history',
			)
		);
	}
	
	public function actionTransfer() {
		$this->_assertRegistrationRequired();
		
		$formData = array();
		$link = XenForo_Link::buildPublicLink(bdBank_Model_Bank::options('route_prefix') . '/transfer');
		
		$formData = $this->_input->filter(array(
			'receivers' => XenForo_Input::STRING,
			'amount' => XenForo_Input::UINT,
			'comment' => XenForo_Input::STRING,
			'hash' => XenForo_Input::STRING,
			'to' => XenForo_Input::STRING,
			'rtn' => XenForo_Input::STRING, // rtn stands for "return"
		));
		
		if ($formData['rtn'] == 'ref' AND !empty($_SERVER['HTTP_REFERER'])) {
			$formData['rtn'] = $_SERVER['HTTP_REFERER'];
		}
		
		if ($this->_request->isPost()) {
			// process transfer request
			// please take time to update bdBank_ControllerAdmin_Bank::actionTransfer() if you change this
			
			$currentUserId = XenForo_Visitor::getInstance()->get('user_id');
			$receiverUsernames = explode(',',$formData['receivers']);
			$userModel = XenForo_Model::create('XenForo_Model_User');
			
			$receivers = array();
			foreach ($receiverUsernames as $username) {
				$username = trim($username);
				if (empty($username)) continue; 
				$receiver = $userModel->getUserByName($username);
				if (empty($receiver)) {
					return $this->responseError(new XenForo_Phrase('bdbank_transfer_error_receiver_not_found_x', array('username' => $username)));
				} else if ($receiver['user_id'] == $currentUserId) {
					return $this->responseError(new XenForo_Phrase('bdbank_transfer_error_self'));
				}
				$receivers[$receiver['user_id']] = $receiver;
			}
			if (count($receivers) == 0) {
				return $this->responseError(new XenForo_Phrase('bdbank_transfer_error_no_receivers'));
			}
			if ($formData['amount'] == 0) {
				// it shouldn't be negative because we used filter
				return $this->responseError(new XenForo_Phrase('bdbank_transfer_error_zero_amount'));
			}
			
			$balance = bdBank_Model_Bank::balance();
			$total = $formData['amount'] * count($receivers);
			$balanceAfter = $balance - $total;
			if ($balanceAfter < 0) {
				// oops
				return $this->responseError(new XenForo_Phrase('bdbank_transfer_error_not_enough',array('balance' => $balance, 'total' => $total)));
			}
			$hash = md5(implode(',',array_keys($receivers)) . $formData['amount'] . $balanceAfter);
			if ($formData['hash'] != $hash) {
				// display confirmation
				return $this->responseView(
					'bdBank_ViewPublic_Bank_TransferConfirm',
					'bdbank_page_transfer_confirm',
					array(
						'breadCrumbs' => array(
							'transfer' => array(
								'href' => $link,
								'value' => new XenForo_Phrase('bdbank_transfer'),
								'node_id' => 'transfer',
							),
							'transfer_confirm' => array(
								'href' => $link,
								'value' => new XenForo_Phrase('bdbank_transfer_confirm'),
								'node_id' => 'confirm',
							),
						),
						'formAction' => $link,
						'formData' => $formData,
						'receivers' => $receivers,
						'total' => $total,
						'balance' => $balance,
						'balanceAfter' => $balanceAfter,
						'hash' => $hash,
					)
				);
			} else {
				$personal = XenForo_Application::get('bdBank')->personal();
				foreach ($receivers as $receiver) {
					$personal->transfer($currentUserId, $receiver['user_id'], $formData['amount'], $formData['comment']);
				}
				
				return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::SUCCESS,
					empty($formData['rtn']) ? $link : $formData['rtn'],
					new XenForo_Phrase('bdbank_transfer_completed_total_x',array('total' => $total))
				);
			}
		} else {
			if (empty($formData['receivers']) AND !empty($formData['to'])) {
				// apply the more user friendly param `to` for `receivers`
				// for high security measure, do this with GET request only (?)
				$formData['receivers'] = $formData['to'];
			}
		}
		
		return $this->responseView(
			'bdBank_ViewPublic_Bank_Transfer',
			'bdbank_page_transfer',
			array(
				'breadCrumbs' => array(
					'transfer' => array(
						'href' => $link,
						'value' => new XenForo_Phrase('bdbank_transfer'),
						'node_id' => 'transfer',
					)
				),
				'balance' => bdBank_Model_Bank::balance(), 
				'accounts' => bdBank_Model_Bank::accounts(),
				'formAction' => $link,
				'formData' => $formData,
			)
		);
	}
	
	public function actionAttachmentManager() {
		$this->_assertRegistrationRequired();
		
		$visitor = XenForo_Visitor::getInstance();
		$userId = $visitor->get('user_id');
		$bank = XenForo_Application::get('bdBank');
		$attachmentModel = $this->getModelFromCache('XenForo_Model_Attachment');
		
		// save operation
		if ($this->_request->isPost()) {
			$db = XenForo_Application::get('db');
			$price = $this->_input->filterSingle('price', array(XenForo_Input::UINT, 'array' => true));
			$attachments = $attachmentModel->getAttachments(array('attachment_id' => array_keys($price)));
			foreach ($attachments as $attachment) {
				if ($attachment['user_id'] == $userId) {
					// we have to check to make sure...
					$db->update('xf_attachment', array('bdbank_price' => $price[$attachment['attachment_id']]), array('attachment_id = ?' => $attachment['attachment_id']));
				}
			}
		}

		$conditions = array('user_id' => $userId);
		$fetchOptions = array(
			'order' => 'recent',
		);
		
		$page = max(1, $this->_input->filterSingle('page', XenForo_Input::UINT));
		$attachmentPerPage = 25;
		$fetchOptions['page'] = $page;
		$fetchOptions['limit'] = $attachmentPerPage;
		
		$attachments = $attachmentModel->getAttachments($conditions, $fetchOptions);
		$totalAttachments = $attachmentModel->countAttachments($conditions, $fetchOptions);
		
		foreach ($attachments as &$attachment) {
			$attachment['bdBankBonusPoint'] = $bank->getActionBonus('attachment_downloaded', XenForo_Helper_File::getFileExtension($attachment['filename']));
		}
		
		return $this->responseView(
			'bdBank_ViewPublic_Bank_AttachmentManager',
			'bdbank_page_attachment_manager',
			array(
				'breadCrumbs' => array(
					'attachment_manager' => array(
						'href' => XenForo_Link::buildPublicLink(bdBank_Model_Bank::options('route_prefix') . '/attachment-manager'),
						'value' => new XenForo_Phrase('bdbank_attachment_manager'),
						'node_id' => 'attachment_manager',
					)
				),
				'balance' => bdBank_Model_Bank::balance(), 
				'accounts' => bdBank_Model_Bank::accounts(),
				'attachments' => $attachments,
				
				'page' => $page,
				'perpage' => $attachmentPerPage,
				'attachmentStartOffset' => ($page - 1) * $attachmentPerPage + 1,
				'attachmentEndOffset' => ($page - 1) * $attachmentPerPage + count($attachments),
				'totalAttachments' => $totalAttachments,
				'pagenavLink' => bdBank_Model_Bank::options('route_prefix') . '/attachment-manager',
			)
		);
	}
}