<?php

class bdBank_ControllerAdmin_Bank extends XenForo_ControllerAdmin_Abstract
{
    public function actionIndex()
    {
        return $this->responseRedirect(XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL, XenForo_Link::buildAdminLink('bank/history'));
    }

    public function actionHistory($isArchive = false)
    {
        // this code is very similar with bdBank_ControllerPublic_Bank::actionHistory()
        // please update that method if needed
        $bank = XenForo_Application::get('bdBank');

        $filters = $this->_input->filterSingle('filters', XenForo_Input::ARRAY_SIMPLE);

        $conditions = array('archive' => $isArchive,);
        $fetchOptions = array(
            'join' => bdBank_Model_Bank::FETCH_USER,
            'order' => 'date',
            'direction' => 'desc',
        );

        $page = max(1, $this->_input->filterSingle('page', XenForo_Input::UINT));
        $transactionPerPage = bdBank_Model_Bank::options('perPage');
        $linkParams = array();

        // sets pagination fetch options
        $fetchOptions['page'] = $page;
        $fetchOptions['limit'] = $transactionPerPage;

        // processes filters
        if (!empty($filters['username'])) {
            $user = $this->_getUserModel()->getUserByName($filters['username']);
            if (!empty($user)) {
                $filters['username'] = $user['username'];
                $conditions['user_id'] = $user['user_id'];
                $linkParams['filters[username]'] = $user['username'];
            } else {
                throw new XenForo_Exception(new XenForo_Phrase('requested_user_not_found'), true);
            }
        }
        if (!empty($filters['amount']) AND !empty($filters['amount_operator'])) {
            $conditions['amount'] = array(
                $filters['amount_operator'],
                $filters['amount']
            );
            $linkParams['filters[amount]'] = $filters['amount'];
            $linkParams['filters[amount_operator]'] = $filters['amount_operator'];
        } else {
            unset($filters['amount']);
            unset($filters['amount_operator']);
        }

        $transactions = $bank->getTransactions($conditions, $fetchOptions);
        $totalTransactions = $bank->countTransactions($conditions, $fetchOptions);

        $viewParams = array(
            'transactions' => $transactions,

            'page' => $page,
            'perPage' => $transactionPerPage,
            'total' => $totalTransactions,
            'linkParams' => $linkParams,

            'filters' => $filters,

            'isArchive' => $isArchive,
        );

        return $this->responseView('bdBank_ViewAdmin_History', 'bdbank_history', $viewParams);
    }

    public function actionArchive()
    {
        return $this->actionHistory(true);
    }

    public function actionTransfer()
    {
        $formData = $this->_input->filter(array(
            'receivers' => XenForo_Input::STRING,
            'amount' => XenForo_Input::STRING,
            'comment' => XenForo_Input::STRING,
            'errors' => array(XenForo_Input::STRING, 'array' => true),
        ));

        if ($this->_request->isPost()) {
            // process the transfer request
            // this code is very similar with bdBank_ControllerPublic_Bank::actionTransfer()
            $receiverUsernames = explode(',', $formData['receivers']);

            $receivers = array();
            foreach ($receiverUsernames as $username) {
                $username = trim($username);
                if (empty($username)) {
                    continue;
                }
                $receiver = $this->_getUserModel()->getUserByName($username);
                if (empty($receiver)) {
                    return $this->responseError(new XenForo_Phrase('bdbank_transfer_error_receiver_not_found_x', array('username' => $username)));
                }
                $receivers[$receiver['user_id']] = $receiver;
            }
            if (count($receivers) == 0) {
                return $this->responseError(new XenForo_Phrase('bdbank_transfer_error_no_receivers', array('money' => new XenForo_Phrase('bdbank_money'))));
            }
            if (doubleval($formData['amount']) == 0) {
                return $this->responseError(new XenForo_Phrase('bdbank_transfer_error_zero_amount'));
            }

            $personal = bdBank_Model_Bank::getInstance()->personal();
            $errors = array();

            foreach ($receivers as $receiver) {
                try {
                    $personal->transfer(
                        0,
                        $receiver['user_id'],
                        $formData['amount'],
                        $formData['comment'],
                        bdBank_Model_Bank::TYPE_ADMIN
                    );
                } catch (bdBank_Exception $e) {
                    switch ($e->getMessage()) {
                        case bdBank_Exception::NOT_ENOUGH_MONEY:
                            $error = new XenForo_Phrase('bdbank_error_user_not_enough_money', array(
                                'username' => $receiver['username'],
                                'money_lowercase' => new XenForo_Phrase('bdbank_money_lowercase'),
                                'balance' => bdBank_Model_Bank::helperBalanceFormat(bdBank_Model_Bank::balance($receiver)),
                            ));
                            break;
                        default:
                            $error = new XenForo_Phrase(
                                'bdbank_transfer_error_generic',
                                array('error' => $e->getMessage())
                            );
                    }

                    $errors[$receiver['username']] = $error;
                }
            }

            if (!empty($errors)) {
                return $this->responseRedirect(
                    XenForo_ControllerResponse_Redirect::RESOURCE_UPDATED,
                    XenForo_Link::buildAdminLink('bank/transfer', array(), array(
                        'amount' => $formData['amount'],
                        'comment' => $formData['comment'],
                        'errors' => $errors,
                    ))
                );
            } else {
                return $this->responseRedirect(
                    XenForo_ControllerResponse_Redirect::SUCCESS,
                    XenForo_Link::buildAdminLink('bank/history')
                );
            }
        } else {
            return $this->responseView('bdBank_ViewAdmin_Transfer', 'bdbank_transfer', $formData);
        }
    }

    protected function _preDispatch($action)
    {
        $this->assertAdminPermission('bdbank');
    }

    /**
     * @return XenForo_Model_User
     */
    protected function _getUserModel()
    {
        return $this->getModelFromCache('XenForo_Model_User');
    }

}
