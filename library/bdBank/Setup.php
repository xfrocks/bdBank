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
				`amount` INT(11) NOT NULL,
				`comment` VARCHAR(255),
				`transaction_type` SMALLINT(5) UNSIGNED DEFAULT '0',
				`transfered` INT(10) UNSIGNED NOT NULL,
				`reversed` INT(10) UNSIGNED DEFAULT '0',
				PRIMARY KEY (`transaction_id`)
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
	}
	
	public static function Uninstall() {
		// TODO
	}
}