<?php

class bdBank_XenForo_ControllerPublic_Thread extends XFCP_bdBank_XenForo_ControllerPublic_Thread
{
	protected function _getPostFetchOptions(array $thread, array $forum)
	{
		$fetchOptions = parent::_getPostFetchOptions($thread, $forum);

		$fetchOptions['join'] |= XenForo_Model_Post::FETCH_USER_OPTIONS;

		return $fetchOptions;
	}

}
