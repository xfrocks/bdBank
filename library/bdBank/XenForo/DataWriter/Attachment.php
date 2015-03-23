<?php

class bdBank_XenForo_DataWriter_Attachment extends XFCP_bdBank_XenForo_DataWriter_Attachment
{
    protected function _postDelete()
    {
        parent::_postDelete();

        if ($this->get('content_id') > 0) {
            $bank = bdBank_Model_Bank::getInstance();
            $comment = $bank->comment('attachment_' . $this->get('content_type'), $this->get('content_id'));
            $reversed = $bank->reverseSystemTransactionByComment($comment);

            if (bdBank_Helper_Number::comp($reversed, 0) === 1) {
                $transaction = $bank->getTransactionByComment($comment);
                if (!empty($transaction)) {
                    $bank->macro_bonusAttachment($this->get('content_type'), $this->get('content_id'), $transaction['to_user_id']);
                }
            }
        }
    }

}
