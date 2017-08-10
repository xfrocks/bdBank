<?php

class bdBank_CacheRebuilder_User extends XenForo_CacheRebuilder_Abstract
{
    public function getRebuildMessage()
    {
        return new XenForo_Phrase('users');
    }

    public function rebuild($position = 0, array &$options = array(), &$detailedMessage = '')
    {
        $options['batch'] = isset($options['batch']) ? $options['batch'] : 100;
        $options['batch'] = max(1, $options['batch']);

        /* @var $userModel XenForo_Model_User */
        $userModel = XenForo_Model::create('XenForo_Model_User');

        /* @var $db Zend_Db_Adapter_Abstract */
        $db = XenForo_Application::get('db');

        $bank = bdBank_Model_Bank::getInstance();
        $field = $bank->options('field');

        $userIds = $userModel->getUserIdsInRange($position, $options['batch']);
        if (sizeof($userIds) == 0) {
            return true;
        }

        foreach ($userIds AS $userId) {
            $position = $userId;

            /* @var $userDw XenForo_DataWriter_User */
            $userDw = XenForo_DataWriter::create('XenForo_DataWriter_User', XenForo_DataWriter::ERROR_SILENT);
            if ($userDw->setExistingData($userId)) {
                $userMoney = $db->fetchOne('
					SELECT SUM(IF(to_user_id = ?, amount - tax_amount, -1 * amount))
					FROM `xf_bdbank_transaction`
					WHERE (from_user_id = ? OR to_user_id = ?)
						AND reversed = 0
				', array(
                    $userId,
                    $userId,
                    $userId
                ));

                $userMoneyArchived = $db->fetchOne('
					SELECT SUM(IF(to_user_id = ?, amount - tax_amount, -1 * amount))
					FROM `xf_bdbank_archive`
					WHERE (from_user_id = ? OR to_user_id = ?)
				', array(
                    $userId,
                    $userId,
                    $userId
                ));

                $userDw->set($field, bdBank_Helper_Number::add($userMoney, $userMoneyArchived));

                $userDw->save();
            }
        }

        $detailedMessage = XenForo_Locale::numberFormat($position);

        return $position;
    }
}
