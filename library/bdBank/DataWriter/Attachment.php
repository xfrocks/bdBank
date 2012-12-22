<?php
class bdBank_DataWriter_Attachment extends XFCP_bdBank_DataWriter_Attachment {
	protected function _postDelete() {
		parent::_postDelete();
		
		if ($this->get('content_id') > 0) {
			$bank = XenForo_Application::get('bdBank');
			$comment = $bank->comment('attachment_' . $this->get('content_type'), $this->get('content_id'));
			$reversed = $bank->reverseSystemTransactionByComment($comment);
			if ($reversed > 0) {
				$transaction = $bank->getTransactionByComment($comment);
				if (!empty($transaction)) {
					$bank->macro_bonusAttachment($this->get('content_type'), $this->get('content_id'), $transaction['to_user_id']);
				}
			}
		}
	}
}