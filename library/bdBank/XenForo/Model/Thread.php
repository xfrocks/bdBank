<?php
class bdBank_XenForo_Model_Thread extends XFCP_bdBank_XenForo_Model_Thread {
	protected static $_threads = array();
	
	public function getThreadById($threadId, array $fetchOptions = array()) {
		if (empty($fetchOptions)) {
			// only check for cached result if there is no special fetch options
			if (isset(self::$_threads[$threadId])) {
				return self::$_threads[$threadId];
			}	
		}
		
		self::$_threads[$threadId] = parent::getThreadById($threadId, $fetchOptions);
		
		return self::$_threads[$threadId];
	}
}