<?php

class bdBank_Deferred_Bonuses extends XenForo_Deferred_Abstract
{
    public function execute(array $deferred, array $data, $targetRunTime, &$status)
    {
        $data = array_merge(array(
            'position' => 0,
            'batch' => 500,
            'bonus_type' => '',
            'finishedBonusTypes' => array(),
        ), $data);
        $data['batch'] = max(1, $data['batch']);

        $bank = bdBank_Model_Bank::getInstance();
        /** @var bdBank_Model_Rebuilder $rebuilderModel */
        $rebuilderModel = $bank->getModelFromCache('bdBank_Model_Rebuilder');

        $rebuildEverything = false;
        $bonusType = '';
        if (empty($data['bonus_type'])) {
            $rebuildEverything = true;
            $bonusTypes = $rebuilderModel->getBonusTypes();

            foreach ($bonusTypes as $_bonusType) {
                if (empty($data['finishedBonusTypes'])
                    || !in_array($_bonusType, $data['finishedBonusTypes'])
                ) {
                    $bonusType = $_bonusType;
                    break;
                }
            }
        } else {
            $bonusType = $data['bonus_type'];
        }

        if (empty($bonusType)) {
            return true;
        }

        $rebuilt = $rebuilderModel->rebuildBonus($bonusType, $data['position'], $data);
        $bank->clearReversedTransactionsCache();

        if ($rebuilt !== true) {
            $rebuilt = intval($rebuilt);
            if ($rebuilt > $data['position']) {
                $status = sprintf('%s: %s...', $bonusType, XenForo_Locale::numberFormat($rebuilt));
                $data['position'] = $rebuilt;
                return $data;
            }
        }

        if ($rebuildEverything) {
            $status = sprintf('%s: done', $bonusType);
            $data['finishedBonusTypes'][] = $bonusType;
            $data['position'] = 0;
            return $data;
        }

        return true;
    }

    public function canCancel()
    {
        return true;
    }
}
