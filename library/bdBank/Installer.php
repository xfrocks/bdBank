<?php

class bdBank_Installer
{
    /* Start auto-generated lines of code. Change made will be overwriten... */

    protected static $_tables = array(
        'transaction' => array(
            'createQuery' => 'CREATE TABLE IF NOT EXISTS `xf_bdbank_transaction` (
                `transaction_id` INT(10) UNSIGNED AUTO_INCREMENT
                ,`from_user_id` INT(10) UNSIGNED NOT NULL
                ,`to_user_id` INT(10) UNSIGNED NOT NULL
                ,`amount` DECIMAL(13,4) NOT NULL
                ,`tax_amount` DECIMAL(13,4) DEFAULT \'0\'
                ,`comment` VARCHAR(255)
                ,`transaction_type` INT(10) UNSIGNED DEFAULT \'0\'
                ,`transfered` INT(10) UNSIGNED NOT NULL
                ,`reversed` INT(10) UNSIGNED DEFAULT \'0\'
                , PRIMARY KEY (`transaction_id`)
                ,INDEX `comment` (`comment`)
            ) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;',
            'dropQuery' => 'DROP TABLE IF EXISTS `xf_bdbank_transaction`',
        ),
        'attachment_downloaded' => array(
            'createQuery' => 'CREATE TABLE IF NOT EXISTS `xf_bdbank_attachment_downloaded` (
                `attachment_id` INT(10) UNSIGNED NOT NULL
                ,`user_id` INT(10) UNSIGNED NOT NULL
                ,`download_date` INT(10) UNSIGNED NOT NULL
                , PRIMARY KEY (`attachment_id`,`user_id`)
                
            ) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;',
            'dropQuery' => 'DROP TABLE IF EXISTS `xf_bdbank_attachment_downloaded`',
        ),
        'archive' => array(
            'createQuery' => 'CREATE TABLE IF NOT EXISTS `xf_bdbank_archive` (
                `transaction_id` INT(10) UNSIGNED NOT NULL
                ,`from_user_id` INT(10) UNSIGNED NOT NULL
                ,`to_user_id` INT(10) UNSIGNED NOT NULL
                ,`amount` DECIMAL(13,4) NOT NULL
                ,`tax_amount` DECIMAL(13,4) DEFAULT \'0\'
                ,`comment` VARCHAR(255)
                ,`transaction_type` INT(10) UNSIGNED DEFAULT \'0\'
                ,`transfered` INT(10) UNSIGNED NOT NULL
                , PRIMARY KEY (`transaction_id`)
                ,INDEX `comment` (`comment`)
            ) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;',
            'dropQuery' => 'DROP TABLE IF EXISTS `xf_bdbank_archive`',
        ),
        'stats' => array(
            'createQuery' => 'CREATE TABLE IF NOT EXISTS `xf_bdbank_stats` (
                `stats_key` VARCHAR(245)
                ,`stats_date` VARCHAR(245)
                ,`stats_value` MEDIUMBLOB
                ,`rebuild_date` INT(10) UNSIGNED NOT NULL
                
                
            ) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;',
            'dropQuery' => 'DROP TABLE IF EXISTS `xf_bdbank_stats`',
        ),
    );
    protected static $_patches = array(
        array(
            'table' => 'xf_user',
            'tableCheckQuery' => 'SHOW TABLES LIKE \'xf_user\'',
            'field' => 'bdbank_money',
            'checkQuery' => 'SHOW COLUMNS FROM `xf_user` LIKE \'bdbank_money\'',
            'addQuery' => 'ALTER TABLE `xf_user` ADD COLUMN `bdbank_money` DECIMAL(13,4) DEFAULT \'0\'',
            'dropQuery' => 'ALTER TABLE `xf_user` DROP COLUMN `bdbank_money`',
        ),
        array(
            'table' => 'xf_forum',
            'tableCheckQuery' => 'SHOW TABLES LIKE \'xf_forum\'',
            'field' => 'bdbank_options',
            'checkQuery' => 'SHOW COLUMNS FROM `xf_forum` LIKE \'bdbank_options\'',
            'addQuery' => 'ALTER TABLE `xf_forum` ADD COLUMN `bdbank_options` MEDIUMBLOB',
            'dropQuery' => 'ALTER TABLE `xf_forum` DROP COLUMN `bdbank_options`',
        ),
        array(
            'table' => 'xf_user_option',
            'tableCheckQuery' => 'SHOW TABLES LIKE \'xf_user_option\'',
            'field' => 'bdbank_show_money',
            'checkQuery' => 'SHOW COLUMNS FROM `xf_user_option` LIKE \'bdbank_show_money\'',
            'addQuery' => 'ALTER TABLE `xf_user_option` ADD COLUMN `bdbank_show_money` INT(10) UNSIGNED DEFAULT \'1\'',
            'dropQuery' => 'ALTER TABLE `xf_user_option` DROP COLUMN `bdbank_show_money`',
        ),
    );

    public static function install($existingAddOn, $addOnData)
    {
        $db = XenForo_Application::get('db');

        foreach (self::$_tables as $table) {
            $db->query($table['createQuery']);
        }

        foreach (self::$_patches as $patch) {
            $tableExisted = $db->fetchOne($patch['tableCheckQuery']);
            if (empty($tableExisted)) {
                continue;
            }

            $existed = $db->fetchOne($patch['checkQuery']);
            if (empty($existed)) {
                $db->query($patch['addQuery']);
            }
        }

        self::installCustomized($existingAddOn, $addOnData);
    }

    public static function uninstall()
    {
        $db = XenForo_Application::get('db');

        foreach (self::$_patches as $patch) {
            $tableExisted = $db->fetchOne($patch['tableCheckQuery']);
            if (empty($tableExisted)) {
                continue;
            }

            $existed = $db->fetchOne($patch['checkQuery']);
            if (!empty($existed)) {
                $db->query($patch['dropQuery']);
            }
        }

        foreach (self::$_tables as $table) {
            $db->query($table['dropQuery']);
        }

        self::uninstallCustomized();
    }

    /* End auto-generated lines of code. Feel free to make changes below */

    public static function installCustomized($existingAddOn, $addOnData)
    {
        if (XenForo_Application::$versionId < 1020000) {
            throw new XenForo_Exception('[bd] Banking requires XenForo 1.2.0+');
        }

        $effectiveVersionId = 0;
        if (!empty($existingAddOn)) {
            $effectiveVersionId = intval($existingAddOn['version_id']);
        }

        $db = XenForo_Application::getDb();

        if ($effectiveVersionId > 0 AND $effectiveVersionId < 21) {
            self::_changeMoneyColumns($db);
        }

        $db->query('REPLACE INTO `xf_content_type` (content_type, addon_id, fields) VALUES ("bdbank_transaction", "bdbank", "")');
        $db->query('REPLACE INTO `xf_content_type_field` (content_type, field_name, field_value) VALUES ("bdbank_transaction", "alert_handler_class", "bdBank_AlertHandler_Transaction")');
        $db->query('REPLACE INTO `xf_content_type_field` (content_type, field_name, field_value) VALUES ("bdbank_transaction", "stats_handler_class", "bdBank_StatsHandler_Transaction")');

        /** @var XenForo_Model_ContentType $contentTypeModel */
        $contentTypeModel = XenForo_Model::create('XenForo_Model_ContentType');
        $contentTypeModel->rebuildContentTypeCache();
    }

    public static function uninstallCustomized()
    {
        $db = XenForo_Application::getDb();

        $db->query('DELETE FROM `xf_content_type` WHERE addon_id = "bdbank"');
        $db->query('DELETE FROM `xf_content_type_field` WHERE content_type = "bdbank_transaction"');

        /** @var XenForo_Model_ContentType $contentTypeModel */
        $contentTypeModel = XenForo_Model::create('XenForo_Model_ContentType');
        $contentTypeModel->rebuildContentTypeCache();

        bdBank_ShippableHelper_Updater::onUninstall(bdBank_Listeners::UPDATER_URL, 'bdbank');
    }

    protected static function _changeMoneyColumns(Zend_Db_Adapter_Abstract $db)
    {
        $moneyColumns = array(
            'xf_bdbank_transaction' => array(
                'amount' => true,
                'tax_amount' => false,
            ),
            'xf_bdbank_archive' => array(
                'amount' => true,
                'tax_amount' => false,
            ),
            'xf_user' => array(
                'bdbank_money' => false,
            ),
            'xf_attachment' => array(
                'bdbank_price' => false,
            ),
        );

        foreach ($moneyColumns as $table => $tableColumns) {
            foreach ($tableColumns as $column => $isRequired) {
                $db->query(call_user_func_array('sprintf', array(
                    'ALTER TABLE `%1$s` CHANGE %2$s %2$s DECIMAL(13,4) %3$s',
                    $table,
                    $column,
                    $isRequired ? 'NOT NULL' : 'DEFAULT 0',
                )));
            }
        }
    }

}
