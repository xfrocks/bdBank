<?php

class bdBank_XenForo_Model_Like extends XFCP_bdBank_XenForo_Model_Like
{
    public function likeContent($contentType, $contentId, $contentUserId, $likeUserId = null, $likeDate = null)
    {
        $result = parent::likeContent($contentType, $contentId, $contentUserId, $likeUserId, $likeDate);

        if ($result !== false) {
            $bank = bdBank_Model_Bank::getInstance();
            $point = $bank->getActionBonus('liked');
            if ($point != 0) {
                $bank->personal()->give($contentUserId, $point, $bank->comment('liked_' . $contentType, $contentId));
            }
        }

        return $result;
    }

    public function unlikeContent(array $like)
    {
        $result = parent::unlikeContent($like);

        if ($result !== false) {
            $bank = bdBank_Model_Bank::getInstance();

            $likeBonus = $bank->getActionBonus('liked');
            if ($likeBonus > 0) {
                $bank->reverseSystemTransactionByComment(
                    $bank->comment('liked_' . $like['content_type'], $like['content_id']));
            }

            $unlikePenalty = $bank->getActionBonus('unlike');
            if ($unlikePenalty > 0) {
                $bank->personal()->give($like['like_user_id'], $unlikePenalty,
                    $bank->comment('unlike_' . $like['content_type'], $like['content_id']));
            }
        }

        return $result;
    }

    public function deleteContentLikes($contentType, $contentIds, $updateUserLikeCounter = true)
    {
        parent::deleteContentLikes($contentType, $contentIds, $updateUserLikeCounter);

        if (!is_array($contentIds)) {
            $contentIds = array($contentIds);
        }

        if ($contentIds) {
            $bank = bdBank_Model_Bank::getInstance();
            $comments = array();
            foreach ($contentIds as $contentId) {
                $comments[] = $bank->comment('liked_' . $contentType, $contentId);
            }
            $bank->reverseSystemTransactionByComment($comments);
        }
    }

}
