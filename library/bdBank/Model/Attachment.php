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
		if (XenForo_Application::$versionId < 1010000) {
			// suppports XenForo 1.0
			$whereClause = $this->prepareAttachmentConditions($conditions, $fetchOptions);
			$joinOptions = $this->prepareAttachmentFetchOptions($fetchOptions);
	
			return $this->_getDb()->fetchOne('
				SELECT COUNT(*)
				FROM xf_attachment AS attachment
				' . $joinOptions['joinTables'] . '
				WHERE ' . $whereClause . '
			');
		} else {
			// supports XenForo 1.1 and beyond
			return parent::countAttachments($conditions, $fetchOptions);
		}
	}
	
	public function getAttachments(array $conditions = array(), array $fetchOptions = array()) {
		if (XenForo_Application::$versionId < 1010000) {
			// suppports XenForo 1.0
			$whereClause = $this->prepareAttachmentConditions($conditions, $fetchOptions);

			$sqlClauses = $this->prepareAttachmentFetchOptions($fetchOptions);
			$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);
	
			return $this->fetchAllKeyed($this->limitQueryResults(
				'
					SELECT attachment.*
						' . $sqlClauses['selectFields'] . '
					FROM xf_attachment AS attachment
					' . $sqlClauses['joinTables'] . '
					WHERE ' . $whereClause . '
					' . $sqlClauses['orderClause'] . '
				', $limitOptions['limit'], $limitOptions['offset']
			), 'attachment_id');
		} else {
			// supports XenForo 1.1 and beyond
			return parent::getAttachments($conditions, $fetchOptions);
		}
	}
	
	public function prepareAttachmentFetchOptions(array $fetchOptions) {
		if (XenForo_Application::$versionId < 1010000) {
			// suppports XenForo 1.0
			$selectFields = '';
			$joinTables = '';
			
			$selectFields .= ',' . self::$dataColumns;
			$joinTables .= ' INNER JOIN xf_attachment_data AS data ON (data.data_id = attachment.data_id)';
			
			if (isset($fetchOptions['order']))
				{
					switch ($fetchOptions['order'])
					{
						case 'recent':
							$orderBy = 'attachment_data.upload_date DESC';
							break;
		
						case 'size':
							$orderBy = 'attachment_data.file_size DESC';
							break;
					}
				}
	
			return array(
				'selectFields' => $selectFields,
				'joinTables'   => $joinTables,
				'orderClause' => ($orderBy ? "ORDER BY $orderBy" : '')
			);
		} else {
			// supports XenForo 1.1 and beyond
			return parent::prepareAttachmentFetchOptions($fetchOptions);
		}
	}

	public function prepareAttachmentConditions(array $conditions, array &$fetchOptions) {
		if (XenForo_Application::$versionId < 1010000) {
			// suppports XenForo 1.0
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
				$sqlConditions[] = 'data.user_id = ' . $db->quote($conditions['user_id']);
			}
	
			return $this->getConditionsForClause($sqlConditions);
		} else {
			// supports XenForo 1.1 and beyond
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
}

if (strpos(XenForo_Model_Attachment::$dataColumns, 'data.user_id') === false) {
	XenForo_Model_Attachment::$dataColumns .= ', data.user_id';
}