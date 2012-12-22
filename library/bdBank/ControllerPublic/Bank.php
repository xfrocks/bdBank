<?php

class bdBank_ControllerPublic_Bank extends XenForo_ControllerPublic_Abstract {
	public function actionIndex() {
		// index has nothing
		// temporary redirect to history page
		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL,
			XenForo_Link::buildPublicLink(bdBank_Model_Bank::routePrefix() . '/history')
		);
		
		$this->_assertRegistrationRequired();
		
		$viewParams = array();
		
		return $this->responseView(
			'bdBank_ViewPublic_Bank_Index',
			'bdbank_page_index',
			$viewParams
		);
	}
	
	public function actionHistory($isPopup = false) {
		$this->_assertRegistrationRequired();
		
		$visitor = XenForo_Visitor::getInstance();
		$userId = $visitor->get('user_id');
		$bank = bdBank_Model_Bank::getInstance();

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
		
		if ($isPopup) {
			$conditions['reversed'] = array('=', 0);
		}
		
		$page = max(1, $this->_input->filterSingle('page', XenForo_Input::UINT));
		$transactionPerPage = bdBank_Model_Bank::options($isPopup ? 'perPagePopup' : 'perPage');
		$fetchOptions['page'] = $page; // this may be changed later...
		$fetchOptions['limit'] = $transactionPerPage;
		
		// redirects to the correct page if necessary
		$transactionId = $this->_input->filterSingle('transaction_id', XenForo_Input::UINT);
		if (!empty($transactionId)) {
			$transaction = $bank->getTransactionById($transactionId);
			if (empty($transaction)) {
				throw new XenForo_Exception(new XenForo_Phrase('bdbank_requested_transaction_not_found'), true);
			}
			
			// found the transaction, now we will count number of transactions newer that that
			// and get the target page
			$count = 0;
			switch ($fetchOptions['order']) {
				case 'date':
					$tmpConditions = $conditions;
					$tmpConditions['transfered'] = array('>', $transaction['transfered']);
					$count = $bank->countTransactions($tmpConditions);
					break;
			}
			
			$page = ceil(($count + 1) / $transactionPerPage);
			
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL,
				XenForo_Link::buildPublicLink(
					bdBank_Model_Bank::routePrefix() . '/history',
					null,
					array('page' => $page)
				) . '#transaction-' . $transaction['transaction_id']
			);
		}
		
		$transactions = $bank->getTransactions($conditions, $fetchOptions);
		$totalTransactions = $bank->countTransactions($conditions, $fetchOptions);
		
		$viewParams = array(
			'breadCrumbs' => array(
				'history' => array(
					'href' => XenForo_Link::buildPublicLink(bdBank_Model_Bank::routePrefix() . '/history'),
					'value' => new XenForo_Phrase('bdbank_history'),
					'node_id' => 'history',
				)
			),
			'transactions' => $transactions,
			
			'page' => $page,
			'perPage' => $transactionPerPage,
			'transactionStartOffset' => ($page - 1) * $transactionPerPage + 1,
			'transactionEndOffset' => ($page - 1) * $transactionPerPage + count($transactions),
			'total' => $totalTransactions,
			'pagenavLink' => bdBank_Model_Bank::routePrefix() . '/history',
		);
		
		return $this->responseView(
			'bdBank_ViewPublic_Bank_History',
			$isPopup ? 'bdbank_page_history_popup' : 'bdbank_page_history',
			$viewParams
		);
	}
	
	public function actionHistoryPopup() {
		return $this->actionHistory(true);
	}
	
	public function actionTransfer() {
		$this->_assertRegistrationRequired();
		
		$formData = array();
		$link = XenForo_Link::buildPublicLink(bdBank_Model_Bank::routePrefix() . '/transfer');
		
		$formData = $this->_input->filter(array(
			'receivers' => XenForo_Input::STRING,
			'amount' => XenForo_Input::UINT,
			'comment' => XenForo_Input::STRING,
			'hash' => XenForo_Input::STRING,
			'to' => XenForo_Input::STRING,
			'rtn' => XenForo_Input::STRING, // rtn stands for "return"
			'sender_pays_tax' => XenForo_Input::UINT, // since 0.10
		));
		
		if ($formData['rtn'] == 'ref' AND !empty($_SERVER['HTTP_REFERER'])) {
			$formData['rtn'] = $_SERVER['HTTP_REFERER'];
		}
		
		if ($this->_request->isPost()) {
			// process transfer request
			// please take time to update bdBank_ControllerAdmin_Bank::actionTransfer() if you change this
			
			$currentUser = XenForo_Visitor::getInstance();
			$currentUserId = $currentUser->get('user_id');
			$senderPaysTax = $formData['sender_pays_tax']; // since 0.10
			$receiverUsernames = explode(',',$formData['receivers']);
			
			/* @var $userModel XenForo_Model_user */
			$userModel = $this->getModelFromCache('XenForo_Model_User');
			
			$receivers = array();
			foreach ($receiverUsernames as $username) {
				$username = trim($username);
				if (empty($username)) continue; 
				$receiver = $userModel->getUserByName($username);
				if (empty($receiver)) {
					return $this->responseError(new XenForo_Phrase('bdbank_transfer_error_receiver_not_found_x', array('username' => $username)));
				} else if ($receiver['user_id'] == $currentUserId) {
					return $this->responseError(new XenForo_Phrase('bdbank_transfer_error_self', array('money' => new XenForo_Phrase('bdbank_money'))));
				}
				$receivers[$receiver['user_id']] = $receiver;
			}
			if (count($receivers) == 0) {
				return $this->responseError(new XenForo_Phrase('bdbank_transfer_error_no_receivers', array('money' => new XenForo_Phrase('bdbank_money'))));
			}
			if ($formData['amount'] == 0) {
				// it shouldn't be negative because we used filter
				return $this->responseError(new XenForo_Phrase('bdbank_transfer_error_zero_amount'));
			}
			
			// prepare data for transfer() calls
			$personal = bdBank_Model_Bank::getInstance()->personal();
			
			$taxMode = bdBank_Model_Bank::TAX_MODE_RECEIVER_PAY; // default
			if (!empty($senderPaysTax)) $taxMode = bdBank_Model_Bank::TAX_MODE_SENDER_PAY; // switched!
			
			$senderAndReceivers = array();
			$senderAndReceivers[$currentUserId] = $currentUser->toArray();
			$senderAndReceivers = array_merge($senderAndReceivers, $receivers);
			
			$balance = bdBank_Model_Bank::balance();
			$options = array(
				bdBank_Model_Bank::TAX_MODE_KEY => $taxMode,
			);
			$optionsTest = array_merge($options, array(
				bdBank_Model_Bank::TRANSACTION_OPTION_TEST => true,
				bdBank_Model_Bank::TRANSACTION_OPTION_FROM_BALANCE => $balance,
				bdBank_Model_Bank::TRANSACTION_OPTION_USERS => $senderAndReceivers,
			));
			
			foreach ($receivers as $receiver) {
				try {
					$result = $personal->transfer(
						$currentUserId, $receiver['user_id'],
						$formData['amount'], $formData['comment'],
						bdBank_Model_Bank::TYPE_PERSONAL,
						true,
						$optionsTest
					);
				} catch (bdBank_Exception $e) {
					if ($e->getMessage() == bdBank_Exception::NOT_ENOUGH_MONEY) {
						// this will never happen because we turned on REPLAY mode
						// just throw an exeption to save it to server error log
						throw $e;
					} else {
						// display a generic error message
						return $this->responseError(new XenForo_Phrase('bdbank_transfer_error_generic', array('error' => $e->getMessage())));
					}
				}
				
				// use the new balance in next calls (if any)
				$optionsTest[bdBank_Model_Bank::TRANSACTION_OPTION_FROM_BALANCE] = $result['from_balance_after'];
			}
			
			$balanceAfter = $optionsTest[bdBank_Model_Bank::TRANSACTION_OPTION_FROM_BALANCE];
			$total = $balance - $balanceAfter;
			if ($balanceAfter < 0) {
				// oops
				return $this->responseError(new XenForo_Phrase('bdbank_transfer_error_not_enough',
					array(
						'balance' => bdBank_Model_Bank::helperBalanceFormat($balance),
						'total' => bdBank_Model_Bank::helperBalanceFormat($total),
					)
				));
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
								'value' => new XenForo_Phrase('bdbank_transfer', array('money' => new XenForo_Phrase('bdbank_money'))),
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
						'senderPaysTax' => $senderPaysTax, // since 0.10
					)
				);
			} else {
				foreach ($receivers as $receiver) {
					$personal->transfer(
						$currentUserId, $receiver['user_id'],
						$formData['amount'], $formData['comment'],
						bdBank_Model_Bank::TYPE_PERSONAL,
						true,
						$options
					);
				}
				
				if (!$this->_noRedirect()) {
					return $this->responseRedirect(
						XenForo_ControllerResponse_Redirect::SUCCESS,
						empty($formData['rtn']) ? $link : $formData['rtn'],
						new XenForo_Phrase('bdbank_transfer_completed_total_x',array('total' => $total))
					);
				} else {
					// this is an AJAX request
					$userIds = array_keys($receivers);
					$userIds[] = XenForo_Visitor::getUserId();
					
					$users = $userModel->getUsersByIds($userIds);
					
					$viewParams = array(
						'users' => $users,
						'receivers' => $receivers,
					
						'_redirectTarget' => empty($formData['rtn']) ? $link : $formData['rtn'],
						'_redirectMessage' => new XenForo_Phrase('bdbank_transfer_completed_total_x',array('total' => $total)),
					);
					
					return $this->responseView(
						'bdBank_ViewPublic_Bank_TransferComplete',
						'',
						$viewParams
					);
				}
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
						'value' => new XenForo_Phrase('bdbank_transfer', array('money' => new XenForo_Phrase('bdbank_money'))),
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
		$bank = bdBank_Model_Bank::getInstance();
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
						'href' => XenForo_Link::buildPublicLink(bdBank_Model_Bank::routePrefix() . '/attachment-manager'),
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
				'pagenavLink' => bdBank_Model_Bank::routePrefix() . '/attachment-manager',
			)
		);
	}
}