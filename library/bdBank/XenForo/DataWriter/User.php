<?php

class bdBank_XenForo_DataWriter_User extends XFCP_bdBank_XenForo_DataWriter_User
{

	const EXTRA_DATA_POST_SAVE_GIVE = 'postSaveGive';

	public function setPostSaveGive($amount)
	{
		$this->setExtraData(self::EXTRA_DATA_POST_SAVE_GIVE, $amount);
	}

	protected function _postSave()
	{
		$postSaveGive = $this->getExtraData(self::EXTRA_DATA_POST_SAVE_GIVE);
		if ($postSaveGive !== null AND $postSaveGive !== 0)
		{
			$adminUserId = XenForo_Visitor::getUserId();
			bdBank_Model_Bank::getInstance()->personal()->give($this->get('user_id'), $postSaveGive, 'manually_edited ' . $adminUserId);
		}

		return parent::_postSave();
	}

	public function save()
	{
		if (isset($GLOBALS['bdBank_XenForo_ControllerAdmin_User::actionSave']))
		{
			$GLOBALS['bdBank_XenForo_ControllerAdmin_User::actionSave']->bdBank_actionSave($this);
		}

		return parent::save();
	}

	protected function _getFields()
	{
		$fields = parent::_getFields();

		$fields['xf_user']['bdbank_money'] = array(
			'type' => self::TYPE_STRING,
			'default' => 0
		);
		$fields['xf_user_option']['bdbank_show_money'] = array(
			'type' => self::TYPE_BOOLEAN,
			'default' => 1
		);

		return $fields;
	}

}
