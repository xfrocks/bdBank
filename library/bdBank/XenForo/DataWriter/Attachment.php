<?php

class bdBank_XenForo_DataWriter_Attachment extends XFCP_bdBank_XenForo_DataWriter_Attachment
{
    protected function _postDelete()
    {
        parent::_postDelete();

        if ($this->get('content_id') > 0) {
            $contentType = $this->get('content_type');
            $contentId = $this->get('content_id');
            $bank = bdBank_Model_Bank::getInstance();
            $point = $bank->getActionBonus('attachment_' . $contentType);
            if ($point == 0) {
                return;
            }

            $comment = $bank->comment('attachment_' . $contentType, $contentId);
            $reverseResult = $bank->reverseSystemTransactionByComment($comment);
            if (bdBank_Helper_Number::comp($reverseResult['totalReversed'], 0) === 1) {
                $transaction = $bank->getTransactionByComment($comment);
                if (!empty($transaction)) {
                    $bank->macro_bonusAttachment(
                        $contentType,
                        $contentId,
                        $transaction['to_user_id']
                    );
                }
            }
        }
    }
}
