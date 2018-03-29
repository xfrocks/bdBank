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

            $db->update(
                'xf_user',
                array($field => bdBank_Helper_Number::sub($incoming, $outgoing)),
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
}
