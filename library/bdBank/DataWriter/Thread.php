<?php

class bdBank_DataWriter_Thread extends XFCP_bdBank_DataWriter_Thread {
	protected function _discussionPostDelete(array $messages) {
		$bank = XenForo_Application::get('bdBank'); 
		$comments = array();
		foreach ($messages as $post_id => $post) {
			$comments[] = $bank->comment('post',$post_id);
		}
		$bank->reverseSystemTransactionByComment($comments);
	}
}