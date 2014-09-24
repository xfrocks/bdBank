<?php

class bdBank_Option_Field
{
	public static function verifyOption(&$field, XenForo_DataWriter $dw, $fieldName)
	{
		$db = XenForo_Application::get('db');

		$column = $db->fetchRow('SHOW COLUMNS FROM `xf_user` LIKE ' . $db->quote($field));

		if (empty($column))
		{
			throw new XenForo_Exception(new XenForo_Phrase('bdbank_field_x_not_found_in_y_and_explain', array(
				'field' => $field,
				'table' => 'xf_user'
			)), true);
		}

		return true;
	}

}
