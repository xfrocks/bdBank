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
        if ($postSaveGive !== null
            && $postSaveGive !== 0
        ) {
            $adminUserId = XenForo_Visitor::getUserId();
            bdBank_Model_Bank::getInstance()->personal()->give(
                $this->get('user_id'),
                $postSaveGive,
                'manually_edited ' . $adminUserId
            );
        }

        if ($this->isChanged('user_state')) {
            $bank = bdBank_Model_Bank::getInstance();
            $bonusType = 'register';
            $userId = $this->get('user_id');
            $comment = $bank->comment($bonusType, $userId);
            $bank->reverseSystemTransactionByComment($comment);

            if ($this->get('user_state') === 'valid') {
                $point = $bank->getActionBonus($bonusType);
                if ($point != 0) {
                    $bank->personal()->give($userId, $point, $comment);
                }
            }
        }

        parent::_postSave();
    }

    public function save()
    {
        if (isset($GLOBALS['bdBank_XenForo_ControllerAdmin_User::actionSave'])) {
            /** @var bdBank_XenForo_ControllerAdmin_User $controller */
            $controller = $GLOBALS['bdBank_XenForo_ControllerAdmin_User::actionSave'];
            $controller->bdBank_actionSave($this);
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
