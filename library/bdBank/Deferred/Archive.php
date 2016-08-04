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

            foreach ($transactions as $transaction) {
                if ($transaction['transfered'] > $data['cutOff']) {
                    return true;
                }

                if ($transaction['reversed'] < 1) {
                    $archived = array_intersect_key($transaction, array(
                        'transaction_id' => 1,
                        'from_user_id' => 1,
                        'to_user_id' => 1,
                        'amount' => 1,
                        'tax_amount' => 1,
                        'comment' => 1,
                        'transaction_type' => 1,
                        'transfered' => 1,
                    ));
                    $db->insert('xf_bdbank_archive', $archived);
                }

                $db->delete('xf_bdbank_transaction', array('transaction_id = ?' => $transaction['transaction_id']));
            }
        }

        $actionPhrase = new XenForo_Phrase('rebuilding');
        $typePhrase = new XenForo_Phrase('bdbank_transactions');
        $status = sprintf('%s... %s (%s)', $actionPhrase, $typePhrase, XenForo_Locale::numberFormat($data['position']));

        return $data;
    }

    public function canCancel()
    {
        return true;
    }
}