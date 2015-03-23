<?php

class bdBank_XenForo_DataWriter_Discussion_Thread extends XFCP_bdBank_XenForo_DataWriter_Discussion_Thread
{

    protected function _bdBank_reversePostTransactions(array $postIds)
    {
        $bank = bdBank_Model_Bank::getInstance();

        $comments = array();
        foreach ($postIds as $postId) {
            $comments[] = $bank->comment('post', $postId);
        }

        $bank->reverseSystemTransactionByComment($comments);
    }

    protected function _discussionPostDelete()
    {
        // do not panic, this method caches its results
        $messages = $this->_getDiscussionMessages(false);
        if (!empty($messages)) {
            $this->_bdBank_reversePostTransactions(array_keys($messages));
        }

        parent::_discussionPostDelete();
    }

}
