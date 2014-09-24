<?php

class bdBank_CronEntry_Stats
{

	public static function rebuildRichest()
	{
		$bank = bdBank_Model_Bank::getInstance();
		$stats = $bank->stats();

		$stats->rebuildGeneral();
		$stats->rebuildRichest();
	}

}
