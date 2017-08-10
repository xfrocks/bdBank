<?php

class bdBank_AlertHandler_Transaction extends XenForo_AlertHandler_Abstract
{
    public function getContentByIds(array $contentIds, $model, $userId, array $viewingUser)
    {
        $bank = bdBank_Model_Bank::getInstance();

        return $bank->getTransactions(array('transaction_id' => $contentIds));
    }

    protected function _getDefaultTemplateTitle($contentType, $action)
    {
        return 'bdbank_alert_transaction_' . $action;
    }
}
