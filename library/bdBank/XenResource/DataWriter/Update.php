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

            $bonusType = ($this->_bdBank_isResourceDescriptionUpdate() ? 'resource' : 'resourceUpdate');
            $comment = $this->_bdBankComment();
            $point = $bank->getActionBonus($bonusType);

            if (!$this->isInsert()) {
                $bank->makeTransactionAdjustments($comment, $point);
                return;
            }

            if ($point != 0) {
                $bank->personal()->give(
                    $this->_resource['user_id'],
                    $point,
                    $comment,
                    bdBank_Model_Bank::TYPE_SYSTEM,
                    true,
                    array(
                        bdBank_Model_Bank::TRANSACTION_OPTION_TIMESTAMP => $this->get('post_date')
                    )
                );
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
