<?php

class bdBank_bdPaygate_Model_Processor extends XFCP_bdBank_bdPaygate_Model_Processor
{

    public function getCurrencies()
    {
        $currencies = parent::getCurrencies();

        $currencies[bdBank_bdPaygate_Processor::CURRENCY_BDBANK] = new XenForo_Phrase('bdbank_money');

        return $currencies;
    }

    public function formatCost($amount, $currency)
    {
        if ($currency === bdBank_bdPaygate_Processor::CURRENCY_BDBANK) {
            return bdBank_Model_Bank::helperBalanceFormat($amount);
        }

        $parentFunc = array('parent', 'formatCost');
        if (is_callable($parentFunc)) {
            return call_user_func($parentFunc, $amount, $currency);
        }

        // [bd] Paygates is out of date, we have to mimics it
        $currencies = $this->getCurrencies();
        if (!isset($currencies[$currency])) {
            $currencies[$currency] = utf8_strtoupper($currency);
        }

        return sprintf('%s %s', XenForo_Locale::numberFormat($amount, 2), $currencies[$currency]);
    }

    public function getProcessorNames()
    {
        $names = parent::getProcessorNames();

        $names['bdbank'] = 'bdBank_bdPaygate_Processor';

        return $names;
    }

    protected function _processIntegratedAction($action, $user, $data, bdPaygate_Processor_Abstract $processor, $amount, $currency)
    {
        if ($action == 'bdbank_purchase') {
            $requestedAmount = $data[0];

            if ($amount !== false AND $currency !== false) {
                // amount and currency information is available
                // let's verify them
                $prices = bdBank_Model_Bank::options('getMorePrices');
                $verified = false;
                foreach ($prices as $price) {
                    $priceAmount = $price[0];
                    $priceCost = $price[1];
                    $priceCurrency = $price[2];

                    if (bdBank_Helper_Number::comp($priceAmount, $requestedAmount) === 0) {
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
            $personal->give($user['user_id'], bdBank_Helper_Number::mul($data[0], -1), 'bdbank_purchase_revert ' . $data[0]);

            return 'Taken away ' . $data[0] . ' from user #' . $user['user_id'];
        }

        return parent::_revertIntegratedAction($action, $user, $data, $processor, $amount, $currency);
    }

}
