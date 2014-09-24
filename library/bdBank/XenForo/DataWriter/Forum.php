<?php

class bdBank_XenForo_DataWriter_Forum extends XFCP_bdBank_XenForo_DataWriter_Forum
{
	public function save()
	{
		if (isset($GLOBALS['bdBank_XenForo_ControllerAdmin_Forum::actionSave']))
		{
			$GLOBALS['bdBank_XenForo_ControllerAdmin_Forum::actionSave']->bdBank_actionSave($this);
		}

		return parent::save();
	}

	protected function _getFields()
	{
		$fields = parent::_getFields();

		$fields['xf_forum']['bdbank_options'] = array(
			'type' => XenForo_DataWriter::TYPE_SERIALIZED,
			'default' => '',
		);

		return $fields;
	}

}
