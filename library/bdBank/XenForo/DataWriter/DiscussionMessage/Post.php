<?php

class bdBank_XenForo_DataWriter_DiscussionMessage_Post extends XFCP_bdBank_XenForo_DataWriter_DiscussionMessage_Post
{
    const DATA_THREAD = '_bdBank_threadInfo';

    public function bdBank_doBonus()
    {
        $bank = bdBank_Model_Bank::getInstance();

        $userId = $this->get('user_id');
        $forum = $this->getExtraData(XenForo_DataWriter_DiscussionMessage_Post::DATA_FORUM);
        if (empty($userId) || empty($forum)) {
            return;
        }

        $comment = $this->_bdBankComment();
        $point = 0;
        if (bdBank_AntiCheating::checkPostQuality($this)) {
            $bonusType = $this->_bdBank_isDiscussionFirstMessage() ? 'thread' : 'post';
            $point = $bank->getActionBonus($bonusType, $this->get('post_date'), array('forum' => $forum));
        }

        if (!$this->isInsert()) {
            $bank->makeTransactionAdjustments($comment, $point);
            return;
        }

        if ($point != 0) {
            $bank->personal()->give(
                $userId,
                $point,
                $comment,
                bdBank_Model_Bank::TYPE_SYSTEM,
                true,
                array(
                    bdBank_Model_Bank::TRANSACTION_OPTION_TIMESTAMP => $this->get('post_date'),
                )
            );
        }
    }

    protected function _postSaveAfterTransaction()
    {
        parent::_postSaveAfterTransaction();

        $this->bdBank_doBonus();
    }

    protected function _messagePostDelete()
    {
        bdBank_Model_Bank::getInstance()->reverseSystemTransactionByComment($this->_bdBankComment());

        parent::_messagePostDelete();
    }

    protected function _bdBankComment()
    {
        return bdBank_Model_Bank::getInstance()->comment('post', $this->get('post_id'));
    }

    protected function _bdBank_isDiscussionFirstMessage()
    {
        $isDFM = $this->isDiscussionFirstMessage();

        if (!$isDFM AND $this->isUpdate()) {
            // need to check further...
            $thread = $this->getExtraData(self::DATA_THREAD);
            if (empty($thread)) {
                // the thread info should be cached before (if we are coming from
                // XenForo_ControllerPublic_Post::actionSave()
                /** @var bdBank_XenForo_Model_Thread $threadModel */
                $threadModel = $this->_getThreadModel();
                $thread = $threadModel->bdBank_getThreadById($this->get('thread_id'));
            }

            $isDFM = ($thread['first_post_id'] == $this->get('post_id'));
        }

        return $isDFM;
    }
}
