<?php
class bdBank_Model_Attachment extends XFCP_bdBank_Model_Attachment {
	public function deleteAttachmentsFromContentIds($contentType, array $contentIds) {
		$bank = XenForo_Application::get('bdBank');
		foreach ($contentIds as $contentId) {
			$bank->reverseSystemTransactionByComment($bank->comment('attachment_' . $contentType, $contentId));
		}
		
		return parent::deleteAttachmentsFromContentIds($contentType, $contentIds);
	}
	
	public function isDownloaded(array $attachment, $userId) {
		$found = $this->_getDb()->fetchOne("SELECT user_id FROM `xf_bdbank_attachment_downloaded` WHERE attachment_id = ? AND user_id = ?"
			, array($attachment['attachment_id'], $userId));
		return $found == $userId;
	}
	
	public function markDownloaded(array $attachment, $userId) {
		$this->_getDb()->query("
			REPLACE INTO `xf_bdbank_attachment_downloaded`
			(attachment_id, user_id, download_date)
			VALUES
			(?, ?, ?)
		", array($attachment['attachment_id'], $userId, XenForo_Application::$time));
	}
	
	public function countAttachments(array $conditions = array(), array $fetchOptions = array()) {
		$whereClause = $this->prepareAttachmentConditions($conditions, $fetchOptions);
		$joinOptions = $this->prepareAttachmentFetchOptions($fetchOptions);

		return $this->_getDb()->fetchOne('
			SELECT COUNT(*)
			FROM xf_attachment AS attachment
			' . $joinOptions['joinTables'] . '
			WHERE ' . $whereClause . '
		');
	}
	
	public function getAttachments(array $conditions = array(), array $fetchOptions = array()) {
		$whereClause = $this->prepareAttachmentConditions($conditions, $fetchOptions);

		$orderClause = $this->prepareAttachmentOrderOptions($fetchOptions);
		$joinOptions = $this->prepareAttachmentFetchOptions($fetchOptions);
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		return $this->fetchAllKeyed($this->limitQueryResults(
			'
				SELECT attachment.*
					' . $joinOptions['selectFields'] . '
				FROM xf_attachment AS attachment
				' . $joinOptions['joinTables'] . '
				WHERE ' . $whereClause . '
				' . $orderClause . '
			', $limitOptions['limit'], $limitOptions['offset']
		), 'attachment_id');
	}
	
	public function prepareAttachmentFetchOptions(array $fetchOptions) {
		$selectFields = '';
		$joinTables = '';
		
		$selectFields .= ',' . self::$dataColumns;
		$joinTables .= ' INNER JOIN xf_attachment_data AS data ON (data.data_id = attachment.data_id)';

		return array(
			'selectFields' => $selectFields,
			'joinTables'   => $joinTables
		);
	}

	public function prepareAttachmentConditions(array $conditions, array &$fetchOptions) {
		$db = $this->_getDb();
		$sqlConditions = array();
		
		if (!empty($conditions['attachment_id'])) {
			if (is_array($conditions['attachment_id'])) {
				$sqlConditions[] = 'attachment.attachment_id IN (' . $db->quote($conditions['attachment_id']) . ')';
			} else {
				$sqlConditions[] = 'attachment.attachment_id = ' . $db->quote($conditions['attachment_id']);
			}
		}
		
		if (!empty($conditions['user_id'])) {
			if (is_array($conditions['user_id'])) {
				$sqlConditions[] = 'data.user_id IN (' . $db->quote($conditions['user_id']) . ')';
			} else {
				$sqlConditions[] = 'data.user_id = ' . $db->quote($conditions['user_id']);
			}
		}

		return $this->getConditionsForClause($sqlConditions);
	}

	public function prepareAttachmentOrderOptions(array &$fetchOptions) {
		$choices = array(
			'attach_date' => 'attachment.attach_date',
			'upload_date' => 'data.upload_date',
		);
		return $this->getOrderByClause($choices, $fetchOptions);
	}
}

if (strpos(XenForo_Model_Attachment::$dataColumns, 'data.user_id') === false) {
	XenForo_Model_Attachment::$dataColumns .= ', data.user_id';
}