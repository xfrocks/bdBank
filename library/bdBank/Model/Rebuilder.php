<?php

class bdBank_Model_Rebuilder
{
    public function getBonusTypes()
    {
        return array(
            'register',
            'post_and_thread',
            'resource_update',
            'like',
        );
    }

    /**
     * @param string $bonusType
     * @param int $position
     * @param array $options
     * @return int|true
     */
    public function rebuildBonus($bonusType, $position, array &$options)
    {
        switch ($bonusType) {
            case 'register':
                return $this->_rebuildRegister($position, $options);
            case 'post_and_thread':
                return $this->_rebuildPostAndThread($position, $options);
            case 'resource_update':
                return $this->_rebuildResourceUpdate($position, $options);
            case 'like':
                return $this->_rebuildLike($position, $options);
        }

        return true;
    }

    protected function _rebuildRegister($position, array &$options)
    {
        $bank = bdBank_Model_Bank::getInstance();
        /* @var $userModel XenForo_Model_User */
        $userModel = $bank->getModelFromCache('XenForo_Model_User');

        $userIds = $userModel->getUserIdsInRange($position, $options['batch']);
        if (sizeof($userIds) == 0) {
            return true;
        }

        $bank = bdBank_Model_Bank::getInstance();
        bdBank_Model_Bank::$isReplaying = true;

        $bonusType = 'register';
        $point = $bank->getActionBonus($bonusType);

        foreach ($userIds AS $userId) {
            $position = $userId;

            $comment = $bank->comment($bonusType, $userId);
            $bank->reverseSystemTransactionByComment($comment);

            if ($point != 0) {
                $bank->personal()->give($userId, $point, $comment);
            }
        }

        return $position;
    }

    protected function _rebuildPostAndThread($position, array &$options)
    {
        $bank = bdBank_Model_Bank::getInstance();
        /* @var $postModel XenForo_Model_Post */
        $postModel = $bank->getModelFromCache('XenForo_Model_Post');
        /** @var bdBank_XenForo_Model_Thread $threadModel */
        $threadModel = $postModel->getModelFromCache('XenForo_Model_Thread');
        /** @var XenForo_Model_Forum $forumModel */
        $forumModel = $postModel->getModelFromCache('XenForo_Model_Forum');

        $postIds = $postModel->getPostIdsInRange($position, $options['batch']);
        if (sizeof($postIds) == 0) {
            return true;
        }

        $forums = $forumModel->getForums();
        bdBank_Model_Bank::$isReplaying = true;

        foreach ($postIds AS $postId) {
            $position = $postId;

            $post = $postModel->getPostById($postId, array('join' => XenForo_Model_Post::FETCH_THREAD));

            $dw = XenForo_DataWriter::create('XenForo_DataWriter_DiscussionMessage_Post');
            if ($dw->setExistingData($post, true)) {
                $dw->setExtraData(bdBank_XenForo_DataWriter_DiscussionMessage_Post::DATA_THREAD, $post);

                if (isset($forums[$post['node_id']])) {
                    $dw->setExtraData(XenForo_DataWriter_DiscussionMessage_Post::DATA_FORUM, $forums[$post['node_id']]);
                }

                $dw->save();
            }
        }

        $threadModel->bdBank_clearThreadsCache();

        return $position;
    }

    protected function _rebuildResourceUpdate($position, array &$options)
    {
        $bank = bdBank_Model_Bank::getInstance();
        /* @var $updateModel XenResource_Model_Update */
        $updateModel = $bank->getModelFromCache('XenResource_Model_Update');

        $updateIds = $updateModel->getUpdateIdsInRange($position, $options['batch']);
        if (sizeof($updateIds) == 0) {
            return true;
        }

        bdBank_Model_Bank::$isReplaying = true;

        foreach ($updateIds AS $updateId) {
            $position = $updateId;

            $dw = XenForo_DataWriter::create('XenResource_DataWriter_Update');
            if ($dw->setExistingData($updateId)) {
                $dw->save();
            }
        }

        return $position;
    }

    protected function _rebuildLike($position, array &$options)
    {
        /* @var $db Zend_Db_Adapter_Abstract */
        $db = XenForo_Application::get('db');

        $bank = bdBank_Model_Bank::getInstance();

        $likeIds = $db->fetchCol($db->limit('
			SELECT like_id
			FROM xf_liked_content
			WHERE like_id > ?
			ORDER BY like_id
		', $options['batch']), $position);
        if (sizeof($likeIds) == 0) {
            return true;
        }

        $likes = $db->fetchAll('
			SELECT *
			FROM xf_liked_content
			WHERE like_id IN (' . $db->quote($likeIds) . ')
		');

        bdBank_Model_Bank::$isReplaying = true;

        foreach ($likes AS $like) {
            $position = $like['like_id'];

            $comment = $bank->comment('liked_' . $like['content_type'], $like['content_id']);

            // first we have to reverse any previous transactions
            $bank->reverseSystemTransactionByComment($comment);

            $point = $bank->getActionBonus('liked');
            if ($point != 0) {
                $bank->personal()->give($like['content_user_id'], $point, $comment);
            }
        }

        return $position;
    }
}