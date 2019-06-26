<?php

class bdBank_XenForo_Model_Like extends XFCP_bdBank_XenForo_Model_Like
{
    public function likeContent($contentType, $contentId, $contentUserId, $likeUserId = null, $likeDate = null)
    {
        $result = parent::likeContent($contentType, $contentId, $contentUserId, $likeUserId, $likeDate);

        if ($result !== false) {
            $bank = bdBank_Model_Bank::getInstance();
            $point = $bank->getActionBonus('liked', $likeDate);
            if ($point != 0) {
                if ($likeUserId === null) {
                    $likeUserId = XenForo_Visitor::getUserId();
                }

                $comment = $bank->comment('liked_' . $contentType, $contentId, intval($likeUserId));
                $bank->personal()->give($contentUserId, $point, $comment);
            }
        }

        return $result;
    }

    public function unlikeContent(array $like)
    {
        $result = parent::unlikeContent($like);

        if ($result !== false) {
            $bank = bdBank_Model_Bank::getInstance();

            $likeBonus = $bank->getActionBonus('liked', $like['like_date']);
            if ($likeBonus > 0) {
                $bank->reverseSystemTransactionByComment(
                    $bank->comment('liked_' . $like['content_type'], $like['content_id'], $like['like_user_id'])
                );
            }

            $unlikePenalty = $bank->getActionBonus('unlike', $like['like_date']);
            if ($unlikePenalty !== 0) {
                $bank->personal()->give(
                    $like['like_user_id'],
                    $unlikePenalty,
                    $bank->comment('unlike_' . $like['content_type'], $like['content_id'], $like['like_id'])
                );
            }
        }

        return $result;
    }

    public function deleteContentLikes($contentType, $contentIds, $updateUserLikeCounter = true)
    {
        if (!is_array($contentIds)) {
            $contentIds = array($contentIds);
        }
        if (!$contentIds) {
            return;
        }

        $db = $this->_getDb();
        $contentIdsQuoted = $db->quote($contentIds);
        $likes = $db->fetchAll('
            SELECT content_type, content_id, like_user_id
            FROM xf_liked_content
            WHERE content_type = ? AND content_id IN (' . $contentIdsQuoted . ')
        ', $contentType);

        parent::deleteContentLikes($contentType, $contentIds, $updateUserLikeCounter);

        if (count($likes) > 0) {
            $bank = bdBank_Model_Bank::getInstance();
            $comments = array();
            foreach ($likes as $like) {
                $comments[] = $bank->comment(
                    'liked_' . $like['content_type'],
                    $like['content_id'],
                    $like['like_user_id']
                );
            }

            $bank->reverseSystemTransactionByComment($comments);
        }
    }
}
