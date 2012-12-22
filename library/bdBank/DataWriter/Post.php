<?php

class bdBank_DataWriter_Post extends XFCP_bdBank_DataWriter_Post {
	protected function _messagePostSave() {
		parent::_messagePostSave();

		if ($this->isInsert()) {
			// only give bonus money if posting new post
			$user_id = $this->get('user_id');
			if (!empty($user_id)) {
				// guest sometimes has posting permission
				if ($this->getOption(XenForo_DataWriter_DiscussionMessage::OPTION_UPDATE_PARENT_DISCUSSION)) {
					// the idea of getOption comes from reading through XenForo_DataWriter_Discussion::_saveFirstMessageDw
					// not the first post
					$point = XenForo_Application::get('bdBank')->getActionBonus('post');
					if ($point != 0) {
						XenForo_Application::get('bdBank')->personal()->give($user_id, $point, $this->_bdBankComment());
					}
				} else {
					// this is the first post of a thread
					$point = XenForo_Application::get('bdBank')->getActionBonus('thread');
					if ($point != 0) {
						XenForo_Application::get('bdBank')->personal()->give($user_id, $point, $this->_bdBankComment());
					}
				}
			}
		}
	}
	
	protected function _messagePostDelete() {
		XenForo_Application::get('bdBank')->reverseSystemTransactionByComment($this->_bdBankComment());
	}
	
	protected function _associateAttachments($attachmentHash) {
		parent::_associateAttachments($attachmentHash);
		
		$bank = XenForo_Application::get('bdBank');
		$comment = $bank->comment('attachment_post', $this->get('post_id'));
		$bank->reverseSystemTransactionByComment($comment); // always do reverse for attachments
		$bank->macro_bonusAttachment('post', $this->get('post_id'), $this->get('user_id'));
	}
	
	protected function _deleteAttachments() {
		// the work (should be done) here will be done in bdBank_Model_Attachment (extends XenForo_Model_Attachment)
		return parent::_deleteAttachments();
	}
	
	protected function _bdBankComment() {
		return XenForo_Application::get('bdBank')->comment('post', $this->get('post_id'));
	}
}