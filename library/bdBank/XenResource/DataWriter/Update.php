<?php

class bdBank_XenResource_DataWriter_Update extends XFCP_bdBank_XenResource_DataWriter_Update
{
    protected function _postSave()
    {
        parent::_postSave();

        if (!empty($this->_resource)
            && isset($this->_resource['user_id'])
        ) {
            $bank = bdBank_Model_Bank::getInstance();
            if (!$this->isInsert()) {
                // updating a post, first we will reverse the old transaction...
                $bank->reverseSystemTransactionByComment($this->_bdBankComment());
            }

            $bonusType = ($this->_bdBank_isResourceDescriptionUpdate() ? 'resource' : 'resourceUpdate');
            $point = $bank->getActionBonus($bonusType);
            if ($point != 0) {
                $bank->personal()->give($this->_resource['user_id'], $point, $this->_bdBankComment());
            }
        }
    }

    protected function _postDelete()
    {
        parent::_postDelete();

        bdBank_Model_Bank::getInstance()->reverseSystemTransactionByComment($this->_bdBankComment());
    }

    protected function _bdBankComment()
    {
        return bdBank_Model_Bank::getInstance()->comment('resource_update', $this->get('resource_update_id'));
    }

    protected function _bdBank_isResourceDescriptionUpdate()
    {
        if (!empty($this->_resource)
            && !empty($this->_resource['description_update_id'])
            && $this->_resource['description_update_id'] != $this->get('resource_update_id')
        ) {
            return false;
        }

        return true;
    }
}