<?php

class bdBank_ControllerPublic_Paygate extends XenForo_ControllerPublic_Abstract {
	protected $_amount = 0;
	protected $_currencyUppercase = '';
	protected $_currencyLowercase = '';
	protected $_displayName = '';
	protected $_callback = '';
	protected $_data = '';
	protected $_redirect = '';

	protected $_calculatedMoney = 0;
	protected $_balanceAfter = 0;
	protected $_hash = '';
	protected $_confirmed = false;

	protected function _preDispatch($action) {
		$this->_assertRegistrationRequired();

		$input = $this->_input->filter(array(
				'client_id' => XenForo_Input::STRING,
				'amount' => XenForo_Input::FLOAT,
				'currency' => XenForo_Input::STRING,
				'display_name' => XenForo_Input::STRING,
				'callback' => XenForo_Input::STRING,
				'data' => XenForo_Input::STRING,
				'redirect' => XenForo_Input::STRING,
		));

		$this->_clientId = $input['client_id'];
		$this->_amount = strval($input['amount']);
		$this->_currencyUppercase = utf8_strtoupper($input['currency']);
		$this->_currencyLowercase = utf8_strtolower($input['currency']);
		$this->_displayName = $input['display_name'];
		$this->_callback = $input['callback'];
		$this->_data = $input['data'];
		$this->_redirect = (!empty($input['redirect']) ? $input['redirect'] : $this->getDynamicRedirect());

		$exchangeRates = bdBank_Model_Bank::options('exchangeRates');
		if (isset($exchangeRates[$this->_currencyLowercase])) {
			$this->_calculatedMoney = ceil(floatval($this->_amount) * $exchangeRates[$this->_currencyLowercase]);
		} else {
			throw new XenForo_Exception(
					new XenForo_Phrase(
							'bdbank_paygate_error_x_not_supported_currency',
							array('currency' => $this->_currencyUppercase)),
					true
			);
		}

		$personal = bdBank_Model_Bank::getInstance()->personal();
		$currentUserId = XenForo_Visitor::getUserId();
		$balance = bdBank_Model_Bank::balance();
		$options = array(
				bdBank_Model_Bank::TRANSACTION_OPTION_TEST => true,
				bdBank_Model_Bank::TRANSACTION_OPTION_FROM_BALANCE => $balance,
		);
		try {
			$result = $personal->transfer(
					$currentUserId, 0,
					$this->_calculatedMoney, $this->_displayName,
					bdBank_Model_Bank::TYPE_PERSONAL,
					true,
					$options
			);
		} catch (bdBank_Exception $e) {
			if ($e->getMessage() == bdBank_Exception::NOT_ENOUGH_MONEY) {
				// this will never happen because we turned on TEST mode
				// just throw an exeption to save it to server error log
				throw $e;
			} else {
				// display a generic error message
				throw $this->responseException($this->responseError(new XenForo_Phrase('bdbank_transfer_error_generic',
						array('error' => $e->getMessage())
				), 503));
			}
		}
		$this->_balanceAfter = $result['from_balance_after'];
		if ($this->_balanceAfter < 0) {
			// oops
			throw $this->responseException($this->responseError(new XenForo_Phrase('bdbank_transfer_error_not_enough',
					array(
							'total' => bdBank_Model_Bank::helperBalanceFormat($this->_calculatedMoney),
							'balance' => bdBank_Model_Bank::helperBalanceFormat($balance),
					)
			), 403));
		}

		$globalSalt = XenForo_Application::getConfig()->get('globalSalt');
		$this->_hash = md5($this->_amount . $this->_currencyLowercase . $this->_calculatedMoney . $this->_balanceAfter . $globalSalt);
		$confirmHash = $this->_input->filterSingle('hash', XenForo_Input::STRING);
		if ($confirmHash === $this->_hash) {
			$this->_confirmed = true;
		}

		$this->_verifier = bdBank_Model_Bank::getInstance()->generateClientVerifier($this->_clientId, $this->_amount, $this->_currencyUppercase);

		return parent::_preDispatch($action);
	}

	public function actionIndex() {
		$viewParams = array(
				'clientId' => $this->_clientId,
				'amount' => $this->_amount,
				'currency' => $this->_currencyUppercase,
				'displayName' => $this->_displayName,
				'callback' => $this->_callback,
				'data' => $this->_data,
				'redirect' => $this->_redirect,

				'calculatedMoney' => $this->_calculatedMoney,
				'balanceAfter' => $this->_balanceAfter,
				'hash' => $this->_hash,
		);

		return $this->responseView(
				'bdBank_ViewPublic_Paygate_Index',
				'bdbank_page_paygate',
				$viewParams
		);
	}

	public function actionPay() {
		$this->_assertPostOnly();

		if (!$this->_confirmed) {
			return $this->responseReroute(__CLASS__, 'index');
		}

		$personal = bdBank_Model_Bank::getInstance()->personal();
		$currentUserId = XenForo_Visitor::getUserId();

		try {
			$result = $personal->transfer(
					$currentUserId, 0,
					$this->_calculatedMoney, $this->_displayName,
					bdBank_Model_Bank::TYPE_PERSONAL,
					true
			);
		} catch (bdBank_Exception $e) {
			throw $this->responseException($this->responseError(new XenForo_Phrase('bdbank_transfer_error_generic',
					array('error' => $e->getMessage())
			), 503));
		}

		if (!empty($this->_callback)) {
			$client = XenForo_Helper_Http::getClient($this->_callback);
			$client->setParameterPost(array(
					'client_id' => $this->_clientId,
					'amount' => $this->_amount,
					'currency' => $this->_currencyUppercase,
					'display_name' => $this->_displayName,
					'data' => $this->_data,

					'transaction_id' => $result['transaction_id'],
					'calculated_money' => $this->_calculatedMoney,

					'verifier' => $this->_verifier,
			));
			$response = $client->request('POST');

			if ($response->getStatus() != 200) {
				XenForo_Error::logException(new XenForo_Exception(
				sprintf('Callback returned error: %s', $response->getBody())
				));
			}
		}

		return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, $this->_redirect);
	}
}