<?php

class bdBank_bdPaygate_Model_Processor extends XFCP_bdBank_bdPaygate_Model_Processor {
	protected function _processIntegratedAction($action, $user, $data, bdPaygate_Processor_Abstract $processor, $amount, $currency) {
		if ($action == 'bdbank_purchase') {
			$requestedAmount = intval($data[0]);

			if ($amount !== false AND $currency !== false) {
				// amount and currency information is available
				// let's verify them
				$prices = bdBank_Model_Bank::options('getMorePrices');
				$verified = false;
				foreach ($prices as $price) {
					$priceAmount = intval($price[0]);
					$priceCost = $price[1];
					$priceCurrency = $price[2];
					
					if ($priceAmount === $requestedAmount) {
						if ($this->_verifyPaymentAmount($processor, $amount, $currency, $priceCost, $priceCurrency)) {
							// great, we found at least one match
							$verified = true;
						}
					}
				}
			} else {
				// couldn't verify so we will just assume it's good
				$verified = true;
			}

			if (!$verified) {
				return '[ERROR] Invalid payment amount';
			}

			$personal = bdBank_Model_Bank::getInstance()->personal();
			$personal->give($user['user_id'], $requestedAmount, 'bdbank_purchase ' . $requestedAmount);
			
			return 'Transfered ' . $requestedAmount . ' to user #' . $user['user_id'];
		}
		
		return parent::_processIntegratedAction($action, $user, $data, $processor, $amount, $currency);
	}
	
	protected function _revertIntegratedAction($action, $user, $data, bdPaygate_Processor_Abstract $processor, $amount, $currency)
	{
		if ($action == 'bdbank_purchase') {
			$personal = bdBank_Model_Bank::getInstance()->personal();
			$personal->give($user['user_id'], -1 * $data[0], 'bdbank_purchase_revert ' . $data[0]);
			
			return 'Taken away ' . $data[0] . ' from user #' . $user['user_id'];
		}
		
		return parent::_revertIntegratedAction($action, $user, $data, $processor, $amount, $currency);
	}
}