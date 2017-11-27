<?php

class bdBank_Deferred_Archive extends XenForo_Deferred_Abstract
{
    public function execute(array $deferred, array $data, $targetRunTime, &$status)
    {
        $data = array_merge(array(
            'batch' => 1000,
            'cutOff' => 0
        ), $data);
        $data['batch'] = max(1, $data['batch']);

        if (empty($data['cutOff'])) {
            $daysOfHistory = bdBank_Model_Bank::options('daysOfHistory');
            if (empty($daysOfHistory)) {
                // admin disabled this feature
                return true;
            }

            $data['cutOff'] = XenForo_Application::$time - 86400 * $daysOfHistory;
        }

        $db = XenForo_Application::getDb();
        $startTime = microtime(true);
        $limitTime = ($targetRunTime > 0);
        $transactionId = 0;

        while (true) {
            if ($limitTime) {
                $remainingTime = $targetRunTime - (microtime(true) - $startTime);
                if ($remainingTime < 1) {
                    break;
                }
            }

            $transactions = $db->fetchAll($db->limit('
                SELECT *
                FROM xf_bdbank_transaction
                ORDER BY transaction_id ASC
            ', $data['batch']));

            if (count($transactions) === 0) {
                // nothing left to process
                return true;
            }

            foreach ($transactions as $transaction) {
                $transactionId = max($transactionId, $transaction['transaction_id']);

                if ($transaction['transfered'] > $data['cutOff']) {
                    return true;
                }

                if ($transaction['reversed'] < 1) {
                    $db->query('
                        INSERT IGNORE INTO `xf_bdbank_archive`
                        (transaction_id, from_user_id, to_user_id, amount, tax_amount, comment, transaction_type, transfered)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ', array(
                        $transaction['transaction_id'],
                        $transaction['from_user_id'],
                        $transaction['to_user_id'],
                        $transaction['amount'],
                        $transaction['tax_amount'],
                        $transaction['comment'],
                        $transaction['transaction_type'],
                        $transaction['transfered'],
                    ));
                }

                $db->delete('xf_bdbank_transaction', array('transaction_id = ?' => $transaction['transaction_id']));
            }
        }

        $actionPhrase = new XenForo_Phrase('rebuilding');
        $typePhrase = new XenForo_Phrase('bdbank_transactions');
        $status = sprintf('%s... %s (%s)', $actionPhrase, $typePhrase, XenForo_Locale::numberFormat($transactionId));

        return $data;
    }

    public function canCancel()
    {
        return true;
    }
}
