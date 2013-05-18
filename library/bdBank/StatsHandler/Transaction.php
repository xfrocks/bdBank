<?php

class bdBank_StatsHandler_Transaction extends XenForo_StatsHandler_Abstract
{
	public function getStatsTypes()
	{
		return array(
				'bdbank_bank_out' => new XenForo_Phrase('bdbank_stats_bank_out'),
				'bdbank_bank_in' => new XenForo_Phrase('bdbank_stats_bank_in'),
		);
	}

	public function getData($startDate, $endDate)
	{
		$db = $this->_getDb();

		$outLive = $db->fetchPairs(
			$this->_getBasicDataQuery('xf_bdbank_transaction', 'transfered', 'from_user_id = 0', 'SUM(amount)'),
			array($startDate, $endDate)
		);
		
		$outReversed = $db->fetchPairs(
				$this->_getBasicDataQuery('xf_bdbank_transaction', 'reversed', 'to_user_id = 0', 'SUM(amount)'),
				array($startDate, $endDate)
		);
		
		$outArchived = $db->fetchPairs(
				$this->_getBasicDataQuery('xf_bdbank_archive', 'transfered', 'from_user_id = 0', 'SUM(amount)'),
				array($startDate, $endDate)
		);
		
		$outTax = $db->fetchPairs(
				$this->_getBasicDataQuery('xf_bdbank_transaction', 'reversed', '', 'SUM(tax_amount)'),
				array($startDate, $endDate)
		);
		
		$inLive = $db->fetchPairs(
			$this->_getBasicDataQuery('xf_bdbank_transaction', 'transfered', 'to_user_id = 0', 'SUM(amount)'),
			array($startDate, $endDate)
		);
		
		$inReversed = $db->fetchPairs(
				$this->_getBasicDataQuery('xf_bdbank_transaction', 'reversed', 'from_user_id = 0', 'SUM(amount)'),
				array($startDate, $endDate)
		);
		
		$inArchived = $db->fetchPairs(
				$this->_getBasicDataQuery('xf_bdbank_archive', 'transfered', 'to_user_id = 0', 'SUM(amount)'),
				array($startDate, $endDate)
		);
		
		$inTax = $db->fetchPairs(
			$this->_getBasicDataQuery('xf_bdbank_transaction', 'transfered', 'reversed = 0', 'SUM(tax_amount)'),
			array($startDate, $endDate)
		);
		
		$inTaxArchived = $db->fetchPairs(
				$this->_getBasicDataQuery('xf_bdbank_archive', 'transfered', '', 'SUM(tax_amount)'),
				array($startDate, $endDate)
		);
		
		$bankOut = $this->_mergeData(array(), $outLive);
		$bankOut = $this->_mergeData($bankOut, $outReversed);
		$bankOut = $this->_mergeData($bankOut, $outArchived);
		$bankOut = $this->_mergeData($bankOut, $outTax);
		
		$bankIn = $this->_mergeData(array(), $inLive);
		$bankIn = $this->_mergeData($bankIn, $inReversed);
		$bankIn = $this->_mergeData($bankIn, $inArchived);
		$bankIn = $this->_mergeData($bankIn, $inTax);
		$bankIn = $this->_mergeData($bankIn, $inTaxArchived);

		return array(
				'bdbank_bank_out' => $bankOut,
				'bdbank_bank_in' => $bankIn,
		);
	}

	protected function _mergeData($dataDst, $dataSrc)
	{
		foreach ($dataSrc as $key => $value)
		{
			if (isset($dataDst[$key]))
			{
				$dataDst[$key] += $value;
			}
			else
			{
				$dataDst[$key] = $value;
			}
		}
		
		return $dataDst;
	}
}