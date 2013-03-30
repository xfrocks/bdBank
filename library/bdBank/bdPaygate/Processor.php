<?php

class bdBank_bdPaygate_Processor extends bdPaygate_Processor_Abstract {
	public function isAvailable() {
		if (!XenForo_Application::isRegistered('bdBank')) {
			// the system is not working
			return false;
		}

		$supportedCurrencies = $this->getSupportedCurrencies();
		return !empty($supportedCurrencies);
	}
	
	public function getSupportedCurrencies() {
		$exchangeRates = bdBank_Model_Bank::options('exchangeRates');

		return array_keys($exchangeRates);
	}
	
	public function isRecurringSupported() {
		return false;
	}
	
	public function validateCallback(Zend_Controller_Request_Http $request, &$transactionId, &$paymentStatus, &$transactionDetails, &$itemId) {
		throw new XenForo_Exception('Callback is not supported for this pay gate');
	}
	
	public function generateFormData($amount, $currency, $itemName, $itemId, $recurringInterval = false, $recurringUnit = false, array $extraData = array()) {
		$this->_assertAmount($amount);
		$this->_assertCurrency($currency);
		$this->_assertItem($itemName, $itemId);
		$this->_assertRecurring($recurringInterval, $recurringUnit);
		
		$returnUrl = $this->_generateReturnUrl($extraData);
		$formAction = XenForo_Link::buildPublicLink(
			sprintf('full:%s/paygate', bdBank_Model_Bank::routePrefix()),
			null,
			array(
				'amount' => $amount,
				'currency' => $currency,
				'display_name' => $itemName,
				'data' => json_encode(array('item_id' => $itemId)),
				'redirect' => $returnUrl,
			)
		);
		$callToAction = new XenForo_Phrase('bdbank_bdpaygate_call_to_action',array('money' => new XenForo_Phrase('bdbank_money')));
		
		$form = <<<EOF
<a href="{$formAction}" class="OverlayTrigger button">{$callToAction}</a>
EOF;
		
		return $form;
	}
}