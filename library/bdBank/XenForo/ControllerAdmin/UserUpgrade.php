<?php

class bdBank_XenForo_ControllerAdmin_UserUpgrade extends XFCP_bdBank_XenForo_ControllerAdmin_UserUpgrade
{
	public function actionIndex()
	{
		$optionModel = $this->getModelFromCache('XenForo_Model_Option');
		$optionModel->bdBank_hijackOptions();

		return parent::actionIndex();
	}

}
