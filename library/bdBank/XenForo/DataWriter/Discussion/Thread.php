<?php

class bdBank_XenForo_DataWriter_Discussion_Thread extends XFCP_bdBank_XenForo_DataWriter_Discussion_Thread {
	protected function _discussionPostDelete(array $messages) {
		$bank = bdBank_Model_Bank::getInstance(); 
		$comments = array();
		foreach ($messages as $post_id => $post) {
			$comments[] = $bank->comment('post',$post_id);
		}
		$bank->reverseSystemTransactionByComment($comments);
		
		return parent::_discussionPostDelete($messages);
	}
}