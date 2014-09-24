<?php

class bdBank_XenForo_ControllerAdmin_User extends XFCP_bdBank_XenForo_ControllerAdmin_User
{
	public function actionSave()
	{
		$GLOBALS['bdBank_XenForo_ControllerAdmin_User::actionSave'] = $this;

		return parent::actionSave();
	}

	public function bdBank_actionSave(XenForo_DataWriter_User $dw)
	{
		$showMoney = $this->_input->filterSingle('bdbank_show_money', XenForo_Input::UINT);
		$dw->set('bdbank_show_money', $showMoney);

		if (bdBank_Model_Bank::options('field') == 'bdbank_money')
		{
			// process to save user money
			$inputValue = $this->_input->filterSingle('bdbank_money', XenForo_Input::UINT);
			$inputIncluded = $this->_input->filterSingle('bdbank_money_included', XenForo_Input::UINT);

			if (!empty($inputIncluded))
			{
				$data = $dw->getMergedExistingData();

				if (!empty($data))
				{
					$oldValue = bdBank_Model_Bank::balance($data);
					if ($oldValue == bdBank_Model_Bank::BALANCE_NOT_AVAILABLE)
					{
						$diff = $inputValue;
					}
					else
					{
						$diff = bdBank_Helper_Number::sub($inputValue, $oldValue);
					}
				}
				else
				{
					// creating new user?
					$diff = $inputValue;
				}

				if ($diff !== 0)
				{
					$dw->setPostSaveGive($diff);
				}
			}
		}
	}

}
