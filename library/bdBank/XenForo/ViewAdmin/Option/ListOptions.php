<?php

class bdBank_XenForo_ViewAdmin_Option_ListOptions extends XFCP_bdBank_XenForo_ViewAdmin_Option_ListOptions
{
    public function renderHtml()
    {
        parent::renderHtml();

        if (!empty($this->_params['renderedOptions'])
            && !empty($this->_params['group']['group_id'])
            && $this->_params['group']['group_id'] === 'bdbank'
        ) {
            $groupIds = array_keys($this->_params['renderedOptions']);
            $groupFirstOptionIds = array();

            foreach ($groupIds as $groupId) {
                $groupOptionIds = array_keys($this->_params['renderedOptions'][$groupId]);
                $groupFirstOptionId = reset($groupOptionIds);
                $groupFirstOptionIds[$groupId] = $groupFirstOptionId;
            }

            $tabConfigs = array(
                'bonuses' => array(
                    'name' => new XenForo_Phrase('bdbank_bonuses'),
                    'optionIds' => array(
                        'bdbank_bonus_register',
                        'bdbank_bonus_thread',
                        'bdbank_bonus_liked',
                        'bdbank_bonus_resource',
                    ),
                ),
                'economic' => array(
                    'name' => new XenForo_Phrase('bdbank_economics'),
                    'optionIds' => array(
                        'bdbank_getMorePrices',
                        'bdbank_useTax',
                    ),
                ),
                'advanced' => array(
                    'name' => new XenForo_Phrase('bdbank_advanced_options'),
                    'optionIds' => array(),
                ),
            );
            $defaultTab = 'advanced';

            $tabs = array();

            foreach ($tabConfigs as $tabId => $tabConfig) {
                $tab = $tabConfig;
                $tab['groupIds'] = array();

                foreach ($groupIds as $groupId) {
                    if (empty($groupFirstOptionIds[$groupId])) {
                        continue;
                    }

                    if (in_array($groupFirstOptionIds[$groupId], $tabConfig['optionIds'])) {
                        $tab['groupIds'][] = $groupId;

                        // make sure each group only appear in one tab
                        unset($groupFirstOptionIds[$groupId]);
                    }
                }

                $tabs[$tabId] = $tab;
            }

            if (!empty($groupFirstOptionIds)) {
                // some groups are left behind, put them all in the default tab
                if (empty($defaultTab)) {
                    $tabIds = array_keys($tabs);
                    $defaultTab = reset($tabIds);
                }

                if (!empty($tabs[$defaultTab])) {
                    foreach (array_keys($groupFirstOptionIds) as $groupId) {
                        $tabs[$defaultTab]['groupIds'][] = $groupId;
                    }
                }
            }
//var_dump($tabs, $groupIds, $groupFirstOptionIds);exit;
            $this->_params['bdBank_tabs'] = $tabs;
        }
    }

}