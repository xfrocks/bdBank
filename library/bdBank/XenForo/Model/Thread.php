<?php

class bdBank_XenForo_Model_Thread extends XFCP_bdBank_XenForo_Model_Thread
{
    protected static $_threads = array();

    public function bdBank_getThreadById($threadId)
    {
        if (isset(self::$_threads[$threadId])) {
            return self::$_threads[$threadId];
        }

        return $this->getThreadById($threadId);
    }

    public function getThreadById($threadId, array $fetchOptions = array())
    {
        self::$_threads[$threadId] = parent::getThreadById($threadId, $fetchOptions);

        return self::$_threads[$threadId];
    }

    public function bdBank_clearThreadsCache()
    {
        self::$_threads = array();
    }

}
