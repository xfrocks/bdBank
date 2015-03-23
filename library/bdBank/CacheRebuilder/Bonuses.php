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

        switch ($options['bonus_type']) {
            case 'post_and_thread':
                $position = $this->_rebuildPostAndThread($position, $options);
                $detailedMessage = XenForo_Locale::numberFormat($position);
                return $position;
            case 'post_like':
                $position = $this->_rebuildPostLike($position, $options);
                $detailedMessage = XenForo_Locale::numberFormat($position);
                return $position;
            default:
                return true;
        }
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

        XenForo_Db::beginTransaction();
        bdBank_Model_Bank::$isReplaying = true;

        foreach ($postIds AS $postId) {
            $position = $postId;

            $post = $postModel->getPostById($postId, array('join' => XenForo_Model_Post::FETCH_THREAD));

            $dw = XenForo_DataWriter::create('XenForo_DataWriter_DiscussionMessage_Post', XenForo_DataWriter::ERROR_SILENT);
            if ($dw->setExistingData($post, true)) {
                $dw->setExtraData(bdBank_XenForo_DataWriter_DiscussionMessage_Post::DATA_THREAD, $post);

                if (isset($forums[$post['node_id']])) {
                    $dw->setExtraData(XenForo_DataWriter_DiscussionMessage_Post::DATA_FORUM, $forums[$post['node_id']]);
                }

                $dw->save();
            }
        }

        XenForo_Db::commit();

        return $position;
    }

    protected function _rebuildPostLike($position, array &$options)
    {
        /* @var $db Zend_Db_Adapter_Abstract */
        $db = XenForo_Application::get('db');

        $bank = bdBank_Model_Bank::getInstance();

        $likeIds = $db->fetchCol($db->limit('
			SELECT like_id
			FROM xf_liked_content
			WHERE like_id > ?
				AND content_type = "post"
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

        XenForo_Db::beginTransaction();
        bdBank_Model_Bank::$isReplaying = true;

        foreach ($likes AS $like) {
            $position = $like['like_id'];

            $comment = $bank->comment('liked_post', $like['content_id']);

            // first we have to reverse any previous transactions
            $bank->reverseSystemTransactionByComment($comment);

            $point = $bank->getActionBonus('liked');
            if ($point != 0) {
                $bank->personal()->give($like['content_user_id'], $point, $comment);
            }
        }

        XenForo_Db::commit();

        return $position;
    }

}
