<?php

class bdBank_CronEntry_Transaction {

	public static function archive() {
		$bank = bdBank_Model_Bank::getInstance();

		$bank->archiveTransactions();
	}
}