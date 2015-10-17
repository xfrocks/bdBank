<?php

class bdBank_CacheRebuilder_Bonuses extends XenForo_CacheRebuilder_Abstract
{
    public function getRebuildMessage()
    {
        return new XenForo_Phrase('bdbank_rebuild_bonuses_message');
    }

    public function rebuild($position = 0, array &$options = array(), &$detailedMessage = '')
    {
        $options['batch'] = isset($options['batch']) ? $options['batch'] : 500;
        $options['batch'] = max(1, $options['batch']);

        $rebuildEverything = false;
        $bonusType = '';
        if (empty($options['bonus_type'])) {
            $rebuildEverything = true;
            $bonusTypes = array(
                'register',
                'post_and_thread',
                'resource_update',
                'like',
            );

            foreach ($bonusTypes as $_bonusType) {
                if (empty($options['finishedBonusTypes'])
                    || !in_array($_bonusType, $options['finishedBonusTypes'])
                ) {
                    $bonusType = $_bonusType;
                    break;
                }
            }
        } else {
            $bonusType = $options['bonus_type'];
        }

        $rebuilt = null;
        if (!empty($bonusType)) {
            $rebuilt = call_user_func(array($this, '_rebuild' .
                str_replace(' ', '', ucwords(str_replace('_', ' ', $bonusType)))),
                $position, $options);
        }

        if (is_numeric($rebuilt)) {
            $detailedMessage = XenForo_Locale::numberFormat($rebuilt);
            return $rebuilt;
        }

        if ($rebuilt === true) {
            if ($rebuildEverything) {
                if (empty($options['finishedBonusTypes'])) {
                    $options['finishedBonusTypes'] = array();
                }
                $options['finishedBonusTypes'][] = $bonusType;

                $detailedMessage = XenForo_Locale::numberFormat(0);
                return 0;
            } else {
                return $rebuilt;
            }
        }

        return true;
    }

    protected function _rebuildRegister($position, array &$options)
    {
        /* @var $userModel XenForo_Model_User */
        $userModel = XenForo_Model::create('XenForo_Model_User');

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
        /* @var $postModel XenForo_Model_Post */
        $postModel = XenForo_Model::create('XenForo_Model_Post');
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

        return $position;
    }

    protected function _rebuildResourceUpdate($position, array &$options)
    {
        /* @var $updateModel XenResource_Model_Update */
        $updateModel = XenForo_Model::create('XenResource_Model_Update');

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
