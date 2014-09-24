<?php

class bdBank_XenForo_DataWriter_Discussion_Thread_Base extends XFCP_bdBank_XenForo_DataWriter_Discussion_Thread
{

	protected function _bdBank_reversePostTransactions(array $postIds)
	{
		$bank = bdBank_Model_Bank::getInstance();

		$comments = array();
		foreach ($postIds as $postId)
		{
			$comments[] = $bank->comment('post', $postId);
		}

		$bank->reverseSystemTransactionByComment($comments);
	}

}

if (XenForo_Application::$versionId < 1020000)
{
	// old versions
	class bdBank_XenForo_DataWriter_Discussion_Thread extends bdBank_XenForo_DataWriter_Discussion_Thread_Base
	{

		protected function _discussionPostDelete(array $messages)
		{
			$this->_bdBank_reversePostTransactions(array_keys($messages));

			return parent::_discussionPostDelete($messages);
		}

	}

}
else
{
	// v1.2+
	class bdBank_XenForo_DataWriter_Discussion_Thread extends bdBank_XenForo_DataWriter_Discussion_Thread_Base
	{

		protected function _discussionPostDelete()
		{
			$messages = $this->_getDiscussionMessages(false);
			// do not panic, this method caches its results
			if (!$messages)
			{
				return;
			}
			$this->_bdBank_reversePostTransactions(array_keys($messages));

			return parent::_discussionPostDelete();
		}

	}

}
