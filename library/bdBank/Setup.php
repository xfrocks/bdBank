<?php

class bdBank_Setup {
	public static function Install() {
		$db = XenForo_Application::get('db');
		
		/* since beta */
		
		$userMoneyColumn = $db->fetchOne("SHOW COLUMNS FROM `xf_user` LIKE 'bdbank_money'");
		if (empty($userMoneyColumn)) {
			$db->query("ALTER TABLE `xf_user` ADD COLUMN `bdbank_money` INT(10) UNSIGNED DEFAULT 0");
		}
		
		$db->query("
			CREATE TABLE IF NOT EXISTS `xf_bdbank_transaction` (
				`transaction_id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
				`from_user_id` INT(10) UNSIGNED NOT NULL,
				`to_user_id` INT(10) UNSIGNED NOT NULL,
				`amount` INT(10) UNSIGNED NOT NULL,
				`tax_amount` INT(10) UNSIGNED NOT NULL DEFAULT 0,
				`comment` VARCHAR(255) NOT NULL,
				`transaction_type` SMALLINT(5) UNSIGNED NOT NULL DEFAULT '0',
				`transfered` INT(10) UNSIGNED NOT NULL,
				`reversed` INT(10) UNSIGNED NOT NULL DEFAULT '0',
				PRIMARY KEY (`transaction_id`),
				KEY (`comment`)
			) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;
		");
		
		/* since beta 2 */
		
		$bdbankTransactionCommentKey = $db->fetchOne("SHOW KEYS FROM `xf_bdbank_transaction` WHERE key_name = 'comment'");
		if (empty($bdbankTransactionCommentKey)) {
			$db->query("ALTER TABLE `xf_bdbank_transaction` ADD KEY `comment` (`comment`)");
		}
		
		/* since beta 3 */
		
		$db->query("
			CREATE TABLE IF NOT EXISTS `xf_bdbank_attachment_downloaded` (
				`attachment_id` INT(10) UNSIGNED NOT NULL,
				`user_id` INT(10) UNSIGNED NOT NULL,
				`download_date` INT(10) UNSIGNED DEFAULT 0,
				PRIMARY KEY (`attachment_id`, `user_id`)
			) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;
		");
		
		/* since 0.9.4 */
		
		$attachmentPriceColumn = $db->fetchOne("SHOW COLUMNS FROM `xf_attachment` LIKE 'bdbank_price'");
		if (empty($attachmentPriceColumn)) {
			$db->query("ALTER TABLE `xf_attachment` ADD COLUMN `bdbank_price` INT(10) UNSIGNED DEFAULT 0");
		}
		
		/* since 0.9.9 */
		
		$forumOptionsColumn = $db->fetchOne("SHOW COLUMNS FROM `xf_forum` LIKE 'bdbank_options'");
		if (empty($forumOptionsColumn)) {
			$db->query("ALTER TABLE `xf_forum` ADD COLUMN `bdbank_options` MEDIUMBLOB");
		}
		
		$transactionTaxColumn = $db->fetchOne("SHOW COLUMNS FROM `xf_bdbank_transaction` LIKE 'tax_amount'");
		if (empty($transactionTaxColumn)) {
			$db->query("ALTER TABLE `xf_bdbank_transaction` ADD COLUMN `tax_amount` INT(10) UNSIGNED NOT NULL DEFAULT 0");
		}
		
		$db->query("
			CREATE TABLE IF NOT EXISTS `xf_bdbank_archive` (
				`transaction_id` INT(10) UNSIGNED NOT NULL,
				`from_user_id` INT(10) UNSIGNED NOT NULL,
				`to_user_id` INT(10) UNSIGNED NOT NULL,
				`amount` INT(10) UNSIGNED NOT NULL,
				`tax_amount` INT(10) UNSIGNED NOT NULL DEFAULT 0,
				`comment` VARCHAR(255),
				`transaction_type` SMALLINT(5) UNSIGNED NOT NULL DEFAULT '0',
				`transfered` INT(10) UNSIGNED NOT NULL
			) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;
		");
		
		$userOptionShowColumn = $db->fetchOne("SHOW COLUMNS FROM `xf_user_option` LIKE 'bdbank_show_money'");
		if (empty($userOptionShowColumn)) {
			$db->query("ALTER TABLE `xf_user_option` ADD COLUMN `bdbank_show_money` TINYINT(3) UNSIGNED NOT NULL DEFAULT 1");
		}
		
		$db->query("REPLACE INTO `xf_content_type` (content_type, addon_id, fields) VALUES ('bdbank_transaction', 'bdbank', '')");
		$db->query("REPLACE INTO `xf_content_type_field` (content_type, field_name, field_value) VALUES ('bdbank_transaction', 'alert_handler_class', 'bdBank_AlertHandler_Transaction')");
		XenForo_Model::create('XenForo_Model_ContentType')->rebuildContentTypeCache();
		
		/* since 0.9.10 */
		$archiveTransactionIdKey = $db->fetchOne("SHOW KEYS FROM `xf_bdbank_archive` WHERE key_name = 'transaction_id'");
		if (empty($archiveTransactionIdKey)) {
			$db->query("ALTER TABLE xf_bdbank_archive ADD UNIQUE KEY transaction_id (transaction_id)");
		}
		$archiveCommentKey = $db->fetchOne("SHOW KEYS FROM `xf_bdbank_archive` WHERE key_name = 'comment'");
		if (empty($archiveCommentKey)) {
			$db->query("ALTER TABLE xf_bdbank_archive ADD KEY comment (comment)");
		}
		
		/* since 0.11 */
		$db->query("
			CREATE TABLE IF NOT EXISTS `xf_bdbank_stats` (
				`stats_key` VARCHAR(245) NOT NULL,
				`stats_date` VARCHAR(10) NOT NULL,
				`stats_value` MEDIUMBLOB,
				`rebuild_date` INT(10) UNSIGNED NOT NULL,
				PRIMARY KEY (`stats_key`, `stats_date`)
			) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;
		");
	}
	
	public static function Uninstall() {
		// TODO
	}
}