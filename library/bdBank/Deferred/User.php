<?php

class bdBank_Deferred_User extends XenForo_Deferred_Abstract
{
    public function execute(array $deferred, array $data, $targetRunTime, &$status)
    {
        $data = array_merge(array(
            'position' => 0,
            'batch' => 100,
        ), $data);
        $data['batch'] = max(1, $data['batch']);

        /* @var $userModel XenForo_Model_User */
        $userModel = XenForo_Model::create('XenForo_Model_User');

        $bank = bdBank_Model_Bank::getInstance();
        $field = $bank->options('field');
        if (empty($field)) {
            return true;
        }

        $userIds = $userModel->getUserIdsInRange($data['position'], $data['batch']);
        if (sizeof($userIds) == 0) {
            return true;
        }

        $db = XenForo_Application::getDb();

        foreach ($userIds AS $userId) {
            $data['position'] = $userId;

            $incomingTransactions = $this->_fetchTransactions('to_user_id = ' . $userId);
            $outgoingTransactions = $this->_fetchTransactions('from_user_id = ' . $userId);

            $incomingMoney = 0;
            $outgoingMoney = 0;
            $incomingCredit = 0;
            $outgoingCredit = 0;

            foreach ($incomingTransactions as $transaction) {
                $money = bdBank_Helper_Number::sub($transaction['amount'], $transaction['tax_amount']);
                $incomingMoney = bdBank_Helper_Number::add($incomingMoney, $money);
                if ($transaction['transaction_type'] === bdBank_Model_Bank::TYPE_CREDITABLE
                    || $transaction['transaction_type'] === bdBank_Model_Bank::TYPE_ADJUSTMENT
                ) {
                    $incomingCredit = bdBank_Helper_Number::sub($incomingCredit, $money);
                }
            }

            foreach ($outgoingTransactions as $transaction) {
                $money = $transaction['amount'];
                $outgoingMoney = bdBank_Helper_Number::add($outgoingMoney, $money);
                if ($transaction['transaction_type'] === bdBank_Model_Bank::TYPE_CREDITABLE
                    || $transaction['transaction_type'] === bdBank_Model_Bank::TYPE_ADJUSTMENT
                ) {
                    $outgoingCredit = bdBank_Helper_Number::sub($outgoingCredit, $money);
                }
            }

            $db->update(
                'xf_user',
                array(
                    $field => bdBank_Helper_Number::sub($incomingMoney, $outgoingMoney),
                    'bdbank_credit' => bdBank_Helper_Number::sub($incomingCredit, $outgoingCredit),
                ),
                array('user_id = ?' => $userId)
            );
        }

        $actionPhrase = new XenForo_Phrase('rebuilding');
        $typePhrase = new XenForo_Phrase('users');
        $status = sprintf('%s... %s (%s)', $actionPhrase, $typePhrase, XenForo_Locale::numberFormat($data['position']));

        return $data;
    }

    public function canCancel()
    {
        return true;
    }

    protected function _fetchTransactions($where)
    {
        $db = XenForo_Application::getDb();

        $liveTransactions = $db->fetchAll("
            SELECT transaction_type, SUM(amount) amount, SUM(tax_amount) tax_amount
            FROM xf_bdbank_transaction
            WHERE {$where} AND reversed= 0
            GROUP BY transaction_type
        ");

        $archivedTransactions = $db->fetchAll("
            SELECT transaction_type, SUM(amount) amount, SUM(tax_amount) tax_amount
            FROM xf_bdbank_archive
            WHERE {$where}
            GROUP BY transaction_type
        ");

        return array_merge($liveTransactions, $archivedTransactions);
    }
}
