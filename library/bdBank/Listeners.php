<?php

class bdBank_Listeners
{
    public static function init_dependencies(XenForo_Dependencies_Abstract $dependencies, array $data)
    {
        /** @var XenForo_Application $app */
        $app = XenForo_Application::getInstance();
        $app->addLazyLoader('bdBank', array(__CLASS__, 'createBankModel'));

        XenForo_Template_Helper_Core::$helperCallbacks['bdbank_haspermission'] = array(
            'bdBank_Model_Bank',
            'helperHasPermission'
        );
        XenForo_Template_Helper_Core::$helperCallbacks['bdbank_balance'] = array(
            'bdBank_Model_Bank',
            'balance'
        );
        XenForo_Template_Helper_Core::$helperCallbacks['bdbank_balanceformat'] = array(
            'bdBank_Model_Bank',
            'helperBalanceFormat'
        );
        XenForo_Template_Helper_Core::$helperCallbacks['bdbank_options'] = array(
            'bdBank_Model_Bank',
            'options'
        );
        XenForo_Template_Helper_Core::$helperCallbacks['bdbank_number'] = array(
            'bdBank_Helper_Template',
            'number'
        );

        XenForo_CacheRebuilder_Abstract::$builders['bdBank_Bonuses'] = 'bdBank_CacheRebuilder_Bonuses';
        XenForo_CacheRebuilder_Abstract::$builders['bdBank_User'] = 'bdBank_CacheRebuilder_User';

        if (isset($data['routesAdmin'])) {
            bdBank_ShippableHelper_Updater::onInitDependencies($dependencies, null, 'bdbank');
        }
    }

    public static function navigation_tabs(array &$extraTabs, $selectedTabId)
    {
        if (XenForo_Visitor::getUserId() > 0) {
            $tabId = 'bdbank';
            $defaultPosition = 'middle';
            $position = XenForo_Template_Helper_Core::styleProperty('bdbank_navigationInsertNavtab');
            if ($position === '1') {
                // legacy support
                $position = $defaultPosition;
            }

            if ($position !== '0' || $selectedTabId == $tabId) {
                // only display the tab if it's enabled globally or user is accessing our pages
                $extraTabs[$tabId] = array(
                    'position' => ($position !== '0' ? $position : $defaultPosition),
                    'href' => XenForo_Link::buildPublicLink('full:bank'),
                    'title' => new XenForo_phrase('bdbank_bank'),
                    'linksTemplate' => 'bdbank_links',
                    'selected' => ($selectedTabId == $tabId),
                );
            }
        }
    }

    public static function load_class($class, array &$extend)
    {
        static $classes = array(
            'bdPaygate_Model_Processor',

            'XenForo_ControllerAdmin_Forum',
            'XenForo_ControllerAdmin_User',

            'XenForo_ControllerPublic_Account',
            'XenForo_ControllerPublic_Attachment',
            'XenForo_ControllerPublic_Thread',

            'XenForo_DataWriter_Discussion_Thread',
            'XenForo_DataWriter_DiscussionMessage_Post',
            'XenForo_DataWriter_Forum',
            'XenForo_DataWriter_User',

            'XenForo_Model_Attachment',
            'XenForo_Model_Import',
            'XenForo_Model_Ip',
            'XenForo_Model_Like',
            'XenForo_Model_Thread',

            'XenForo_ViewAdmin_Option_ListOptions',

            'XenResource_DataWriter_Update',
        );

        if (in_array($class, $classes)) {
            $extend[] = 'bdBank_' . $class;
        }
    }

    public static function load_class_importer($class, array &$extend)
    {
        static $extended = false;

        // extend all vbulletin importers
        if ($extended === false AND strpos(strtolower($class), 'vbulletin') !== false) {
            $extend[] = 'bdBank_Importer_vBulletin';
            $extended = true;
        }
    }

    public static function file_health_check(XenForo_ControllerAdmin_Abstract $controller, array &$hashes)
    {
        $ourHashes = bdBank_FileSums::getHashes();
        $hashes = array_merge($hashes, $ourHashes);
    }

    public static function criteria_user($rule, array $data, array $user, &$returnValue)
    {
        switch ($rule) {
            case 'bdbank':
                $moneyField = bdBank_Model_Bank::getInstance()->options('field');
                $money = isset($user[$moneyField]) ? $user[$moneyField] : 0;
                $credit = isset($user['bdbank_credit']) ? $user['bdbank_credit'] : 0;

                if ($money + $credit >= $data['money']) {
                    $returnValue = true;
                }
                break;
        }
    }

    public static function widget_framework_ready(array &$renderers)
    {
        $renderers[] = 'bdBank_WidgetRenderer_RichestUsers';
    }

    public static function createBankModel()
    {
        return XenForo_Model::create('bdBank_Model_Bank');
    }
}
