<?php

class bdBank_CacheRebuilder_Bonuses extends XenForo_CacheRebuilder_Abstract
{
    public function getRebuildMessage()
    {
        return new XenForo_Phrase('bdbank_rebuild_bonuses_message');
    }

    public function rebuild($position = 0, array &$options = array(), &$detailedMessage = '')
    {
        $options['batch'] = isset($options['batch']) ? $options['batch'] : 500;
        $options['batch'] = max(1, $options['batch']);

        $bank = bdBank_Model_Bank::getInstance();
        /** @var bdBank_Model_Rebuilder $rebuilderModel */
        $rebuilderModel = $bank->getModelFromCache('bdBank_Model_Rebuilder');

        $rebuildEverything = false;
        $bonusType = '';
        if (empty($options['bonus_type'])) {
            $rebuildEverything = true;
            $bonusTypes = $rebuilderModel->getBonusTypes();

            foreach ($bonusTypes as $_bonusType) {
                if (empty($options['finishedBonusTypes'])
                    || !in_array($_bonusType, $options['finishedBonusTypes'])
                ) {
                    $bonusType = $_bonusType;
                    break;
                }
            }
        } else {
            $bonusType = $options['bonus_type'];
        }

        $rebuilt = null;
        if (!empty($bonusType)) {
            $rebuilt = $rebuilderModel->rebuildBonus($bonusType, $position, $options);
            $bank->clearReversedTransactionsCache();
        }

        if (is_numeric($rebuilt)) {
            $detailedMessage = XenForo_Locale::numberFormat($rebuilt);
            return $rebuilt;
        }

        if ($rebuilt === true) {
            if ($rebuildEverything) {
                if (empty($options['finishedBonusTypes'])) {
                    $options['finishedBonusTypes'] = array();
                }
                $options['finishedBonusTypes'][] = $bonusType;

                $detailedMessage = XenForo_Locale::numberFormat(0);
                return 0;
            } else {
                return $rebuilt;
            }
        }

        return true;
    }

}
