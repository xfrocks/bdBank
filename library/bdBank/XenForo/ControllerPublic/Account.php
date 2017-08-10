<?php

class bdBank_XenForo_ControllerPublic_Account extends XFCP_bdBank_XenForo_ControllerPublic_Account
{

    protected $_isPrivacySave = false;

    public function actionPrivacySave()
    {
        $this->_isPrivacySave = true;

        return parent::actionPrivacySave();
    }

    protected function _saveVisitorSettings($settings, &$errors, $extras = array())
    {
        if ($this->_isPrivacySave) {
            // user_option
            $tmp = $this->_input->filter(array('bdbank_show_money' => XenForo_Input::UINT));
            $settings = array_merge($settings, $tmp);
        }

        return parent::_saveVisitorSettings($settings, $errors, $extras);
    }
}
