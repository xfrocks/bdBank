<?php

class bdBank_XenForo_Model_Attachment extends XFCP_bdBank_XenForo_Model_Attachment
{
    public function deleteAttachmentsFromContentIds($contentType, array $contentIds)
    {
        $bank = bdBank_Model_Bank::getInstance();
        foreach ($contentIds as $contentId) {
            $bank->reverseSystemTransactionByComment($bank->comment('attachment_' . $contentType, $contentId));
        }

        parent::deleteAttachmentsFromContentIds($contentType, $contentIds);
    }

    public function bdBank_isDownloaded(array $attachment, $userId)
    {
        $found = $this->_getDb()->fetchOne('
            SELECT user_id
            FROM `xf_bdbank_attachment_downloaded`
            WHERE attachment_id = ? AND user_id = ?
        ', array(
            $attachment['attachment_id'],
            $userId
        ));
        return $found == $userId;
    }

    public function bdBank_markDownloaded(array $attachment, $userId)
    {
        $this->_getDb()->query("
			REPLACE INTO `xf_bdbank_attachment_downloaded`
			(attachment_id, user_id, download_date)
			VALUES
			(?, ?, ?)
		", array(
            $attachment['attachment_id'],
            $userId,
            XenForo_Application::$time
        ));
    }

    public function prepareAttachmentConditions(array $conditions, array &$fetchOptions)
    {
        $result = parent::prepareAttachmentConditions($conditions, $fetchOptions);
        $db = $this->_getDb();
        $sqlConditions = array($result);

        if (!empty($conditions['attachment_id'])) {
            if (is_array($conditions['attachment_id'])) {
                $sqlConditions[] = 'attachment.attachment_id IN (' . $db->quote($conditions['attachment_id']) . ')';
            } else {
                $sqlConditions[] = 'attachment.attachment_id = ' . $db->quote($conditions['attachment_id']);
            }
        }

        if (count($sqlConditions) > 1) {
            // our condition is found
            return $this->getConditionsForClause($sqlConditions);
        } else {
            // default conditions only?
            return $result;
        }
    }
}

if (strpos(XenForo_Model_Attachment::$dataColumns, 'data.user_id') === false) {
    XenForo_Model_Attachment::$dataColumns .= ', data.user_id';
}
