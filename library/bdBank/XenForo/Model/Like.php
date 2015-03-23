<?php

class bdBank_XenForo_Model_Like extends XFCP_bdBank_XenForo_Model_Like
{
    public function likeContent($contentType, $contentId, $contentUserId, $likeUserId = null, $likeDate = null)
    {
        $result = parent::likeContent($contentType, $contentId, $contentUserId, $likeUserId, $likeDate);

        if ($result !== false) {
            // of course, we won't do anything if the parent method fail to like the content
            if ($contentType == 'post') {
                // currently, we only deal with posts
                $bank = bdBank_Model_Bank::getInstance();
                $point = $bank->getActionBonus('liked');
                if ($point != 0) {
                    $bank->personal()->give($contentUserId, $point, $bank->comment('liked_post', $contentId));
                }
            }
        }

        return $result;
    }

    public function unlikeContent(array $like)
    {
        $result = parent::unlikeContent($like);

        if ($result !== false) {
            if ($like['content_type'] == 'post') {
                // post only for now
                $bank = bdBank_Model_Bank::getInstance();

                $linkBonus = $bank->getActionBonus('liked');
                if ($linkBonus > 0) {
                    $bank->reverseSystemTransactionByComment($bank->comment('liked_post', $like['content_id']));
                }

                $unlikePenalty = $bank->getActionBonus('unlike');
                if ($unlikePenalty > 0) {
                    $bank->personal()->give($like['like_user_id'], $unlikePenalty, $bank->comment('unlike_post', $like['content_id']));
                }
            }
        }

        return $result;
    }

    public function deleteContentLikes($contentType, $contentIds, $updateUserLikeCounter = true)
    {
        parent::deleteContentLikes($contentType, $contentIds, $updateUserLikeCounter);

        if ($contentType == 'post') {
            if (!is_array($contentIds))
                $contentIds = array($contentIds);
            if (!$contentIds)
                return;
            $bank = bdBank_Model_Bank::getInstance();
            $comments = array();
            foreach ($contentIds as $post_id) {
                $comments[] = $bank->comment('liked_post', $post_id);
            }
            $bank->reverseSystemTransactionByComment($comments);
        }
    }

}
