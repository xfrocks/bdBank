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

            $incoming = $this->_fetchSum('amount - tax_amount', 'to_user_id = ' . $userId);
            $outgoing = $this->_fetchSum('amount', 'from_user_id = ' . $userId);

            $incomingAdjustedSum = $this->_fetchAdjustedSum($userId, 'incoming');
            $outgoingAdjustedSum = $this->_fetchAdjustedSum($userId, 'outgoing');

            $incoming = bdBank_Helper_Number::add($incoming, $incomingAdjustedSum);
            $outgoing = bdBank_Helper_Number::add($outgoing, $outgoingAdjustedSum);

            $credit = bdBank_Helper_Number::add(
                // WARNING: credit is the NEGATED total adjusted amount
                bdBank_Helper_Number::sub($outgoingAdjustedSum, $incomingAdjustedSum),
                $this->_calculateAdditionalCredit($userId)
            );

            $db->update(
                'xf_user',
                array(
                    $field => bdBank_Helper_Number::sub($incoming, $outgoing),
                    'bdbank_credit' => $credit,
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

    protected function _fetchSum($formula, $where)
    {
        $db = XenForo_Application::getDb();

        $liveValue = $db->fetchOne("
            SELECT SUM({$formula})
            FROM xf_bdbank_transaction
            WHERE {$where} AND reversed= 0
        ");

        $archiveValue = $db->fetchOne("
            SELECT SUM({$formula})
            FROM xf_bdbank_archive
            WHERE {$where}
        ");

        return bdBank_Helper_Number::add($liveValue, $archiveValue);
    }

    protected function _fetchAdjustedSum($userId, $direction)
    {
        if ($direction == 'incoming') {
            $userIdCol = 'to_user_id';
        } elseif ($direction == 'outgoing') {
            $userIdCol = 'from_user_id';
        } else {
            throw new XenForo_Exception('Unsupported direction');
        }
        $db = XenForo_Application::getDb();

        $totalAdjustedAmount = $db->fetchOne("
            SELECT SUM(adj.amount)
            FROM xf_bdbank_transaction_adjustment adj
                LEFT JOIN xf_bdbank_transaction t
                    ON adj.comment = t.comment
                        AND t.reversed = 0
                LEFT JOIN xf_bdbank_archive a
                    ON adj.comment = a.comment 
            WHERE t.$userIdCol = $userId
                OR a.$userIdCol = $userId
        ");

        return $totalAdjustedAmount;
    }

    protected function _calculateAdditionalCredit($userId)
    {
        return 0;
    }
}
