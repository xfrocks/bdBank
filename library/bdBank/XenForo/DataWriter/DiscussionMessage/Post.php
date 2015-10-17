<?php

class bdBank_XenForo_DataWriter_DiscussionMessage_Post extends XFCP_bdBank_XenForo_DataWriter_DiscussionMessage_Post
{

    const DATA_THREAD = '_bdBank_threadInfo';

    protected function _messagePostSave()
    {
        $bank = bdBank_Model_Bank::getInstance();

        $userId = $this->get('user_id');
        $forum = $this->getExtraData(XenForo_DataWriter_DiscussionMessage_Post::DATA_FORUM);

        if (!empty($userId) AND !empty($forum)) {
            if (!$this->isInsert()) {
                // updating a post, first we will reverse the old transaction...
                $bank->reverseSystemTransactionByComment($this->_bdBankComment());
            }

            // process bonus money
            if (bdBank_AntiCheating::checkPostQuality($this)) {
                if ($this->_bdBank_isDiscussionFirstMessage()) {
                    // inserting a thread, this post is the first one
                    $bonusType = 'thread';
                } elseif ($this->isInsert()) {
                    // inserting new post but not a thread?
                    // obviously just a normal post
                    $bonusType = 'post';
                } else {
                    // we have to check for the thread
                    // the result should be cached so no queries will be executed
                    /** @var bdBank_XenForo_Model_Thread $threadModel */
                    $threadModel = $this->_getThreadModel();
                    $thread = $threadModel->bdBank_getThreadById($this->get('thread_id'));

                    if (empty($thread)) {
                        // oops, this shouldn't happen
                        $bonusType = 'thread';
                    } else {
                        if ($thread['first_post_id'] == $this->get('post_id')) {
                            $bonusType = 'thread';
                        } else {
                            $bonusType = 'post';
                        }
                    }
                }

                $point = $bank->getActionBonus($bonusType, array('forum' => $forum));
                if ($point != 0) {
                    $bank->personal()->give($userId, $point, $this->_bdBankComment());
                }
            }
        }

        parent::_messagePostSave();
    }

    protected function _messagePostDelete()
    {
        bdBank_Model_Bank::getInstance()->reverseSystemTransactionByComment($this->_bdBankComment());

        parent::_messagePostDelete();
    }

    protected function _associateAttachments($attachmentHash)
    {
        parent::_associateAttachments($attachmentHash);

        $bank = bdBank_Model_Bank::getInstance();
        $comment = $bank->comment('attachment_post', $this->get('post_id'));
        $bank->reverseSystemTransactionByComment($comment);
        // always do reverse for attachments
        $bank->macro_bonusAttachment('post', $this->get('post_id'), $this->get('user_id'));
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
