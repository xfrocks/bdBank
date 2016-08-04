<?php

class bdBank_bdPaygate_Processor extends bdPaygate_Processor_Abstract
{

    const CURRENCY_BDBANK = 'bdb';

    public function isAvailable()
    {
        try {
            XenForo_Application::get('bdBank');
        } catch (Zend_Exception $e) {
            return false;
        }

        $supportedCurrencies = $this->getSupportedCurrencies();
        return !empty($supportedCurrencies);
    }

    public function getSupportedCurrencies()
    {
        $exchangeRates = bdBank_Model_Bank::options('exchangeRates');

        $currencies = array_keys($exchangeRates);

        if (bdBank_Model_Bank::options('moneyAsCurrency')) {
            $currencies[] = self::CURRENCY_BDBANK;
        }

        return $currencies;
    }

    public function isRecurringSupported()
    {
        return false;
    }

    public function validateCallback(Zend_Controller_Request_Http $request, &$transactionId, &$paymentStatus, &$transactionDetails, &$itemId)
    {
        $amount = false;
        $currency = false;

        return $this->validateCallback2($request, $transactionId, $paymentStatus, $transactionDetails, $itemId, $amount, $currency);
    }

    public function validateCallback2(Zend_Controller_Request_Http $request, &$transactionId, &$paymentStatus, &$transactionDetails, &$itemId, &$amount, &$currency)
    {
        $input = new XenForo_Input($request);
        $filtered = $input->filter(array(
            'client_id' => XenForo_Input::STRING,
            'amount' => XenForo_Input::STRING,
            'currency' => XenForo_Input::STRING,
            'display_name' => XenForo_Input::STRING,
            'data' => XenForo_Input::STRING,

            'transaction_id' => XenForo_Input::STRING,
            'calculated_money' => XenForo_Input::STRING,

            'verifier' => XenForo_Input::STRING,
        ));

        $transactionId = (!empty($filtered['transaction_id']) ? ('bdbank_' . $filtered['transaction_id']) : '');
        $paymentStatus = bdPaygate_Processor_Abstract::PAYMENT_STATUS_OTHER;
        $transactionDetails = array_merge($_POST, $filtered);
        $itemId = $filtered['data'];
        $amount = $filtered['amount'];
        $currency = $filtered['currency'];

        $verifier = bdBank_Model_Bank::getInstance()->generateClientVerifier($filtered['client_id'], $filtered['amount'], $filtered['currency']);

        if ($verifier != $filtered['verifier']) {
            $this->_setError('Cannot verify `verifier`');
            return false;
        }

        $paymentStatus = bdPaygate_Processor_Abstract::PAYMENT_STATUS_ACCEPTED;
        return true;
    }

    public function generateFormData($amount, $currency, $itemName, $itemId, $recurringInterval = false, $recurringUnit = false, array $extraData = array())
    {
        $this->_assertAmount($amount);
        $this->_assertCurrency($currency);
        $this->_assertItem($itemName, $itemId);
        $this->_assertRecurring($recurringInterval, $recurringUnit);

        $returnUrl = $this->_generateReturnUrl($extraData);
        $callbackUrl = $this->_generateCallbackUrl($extraData);

        $formAction = XenForo_Link::buildPublicLink('full:bank/paygate', null, array(
            'amount' => $amount,
            'currency' => $currency,
            'display_name' => $itemName,
            'callback' => $callbackUrl,
            'data' => $itemId,
            'redirect' => $returnUrl,
        ));
        $callToAction = new XenForo_Phrase('bdbank_bdpaygate_call_to_action', array('money' => new XenForo_Phrase('bdbank_money')));

        $form = <<<EOF
<a href="{$formAction}" class="OverlayTrigger button">{$callToAction}</a>
EOF;

        return $form;
    }

}
