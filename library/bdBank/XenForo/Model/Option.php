<?php

class bdBank_XenForo_Model_Option extends XFCP_bdBank_XenForo_Model_Option
{
	// this property must be static because
	// XenForo_ControllerAdmin_UserUpgrade::actionIndex
	// for no apparent reason use XenForo_Model::create to create the optionModel
	// (instead of using XenForo_Controller::getModelFromCache)
	private static $_bdBank_hijackOptions = false;

	public function getOptionsByIds(array $optionIds, array $fetchOptions = array())
	{
		if (self::$_bdBank_hijackOptions === true)
		{
			$optionIds[] = 'bdbank_exchangeRates';
		}

		$options = parent::getOptionsByIds($optionIds, $fetchOptions);

		self::$_bdBank_hijackOptions = false;

		return $options;
	}

	public function bdBank_hijackOptions()
	{
		self::$_bdBank_hijackOptions = true;
	}

}
