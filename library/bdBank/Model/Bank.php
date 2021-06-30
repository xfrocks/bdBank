<?php

class bdBank_Model_Bank extends XenForo_Model
{
    // the type constants are used in bdBank_ControllerPublic_Bank::actionHistory() to
    // filter non-system transactions
    // please update it if you add more types here...
    const TYPE_SYSTEM = 0;
    const TYPE_PERSONAL = 1;
    const TYPE_ADMIN = 2;

    // After a CREDITABLE transaction,
    //   each involved user is granted an amount of credit points equal to the amount of money that he/she LOST
    const TYPE_CREDITABLE = 3;
    // ADJUSTMENT transactions are adjustments on systematic transactions
    // Note: adjustment transactions are also creditable
    const TYPE_ADJUSTMENT = 4;

    const SYSTEMATIC_TRANSACTION_TYPES = array(self::TYPE_SYSTEM, self::TYPE_ADJUSTMENT);

    const TAX_MODE_KEY = 'taxMode';
    const TAX_MODE_RECEIVER_PAY = 'receiver';
    const TAX_MODE_SENDER_PAY = 'sender';
    const TAX_MODE_CHARGE_WAIVED = 'charge_waived';

    const TRANSACTION_OPTION_TEST = 'opt_test';
    const TRANSACTION_OPTION_REPLAY = 'opt_replay';
    const TRANSACTION_OPTION_FROM_BALANCE = 'opt_fromBalance';
    const TRANSACTION_OPTION_USERS = 'opt_users';
    const TRANSACTION_OPTION_TIMESTAMP = 'opt_timestamp';

    const PERM_GROUP = 'bdbank';
    const PERM_TRANSFER = 'bdbank_transfer';
    const PERM_PURCHASE = 'bdbank_purchase';

    const BALANCE_NOT_AVAILABLE = 'N/A';

    const FETCH_USER = 0x01;

    const CONFIG_BONUSES_BY_TIME_PERIOD = 'bdBank_bonusesByTimePeriod';

    /**
     * Please read about TRANSACTION_OPTION_REPLAY in
     * bdBank_Model_Personal::transfer()
     *
     * @var bool
     */
    public static $isReplaying = false;

    protected static $_reversedTransactions = array();
    protected static $_taxRules = false;
    protected static $_getMorePrices = false;
    protected static $_exchangeRates = false;

    public function generateClientVerifier($clientId, $amount, $currency, array $extra = array())
    {
        $extra['amount'] = $amount;
        $extra['currency'] = $currency;
        $extra['client_secret'] = $this->getClientSecret($clientId);
        ksort($extra);

        return md5(implode(
            '&',
            array_map(function ($key, $value) {
                return "$key=$value";
            }, array_keys($extra), $extra)
        ));
    }

    public function getClientSecret($clientId)
    {
        return XenForo_Application::getConfig()->get('globalSalt');
    }

    public function canRefund(array $transaction, array $viewingUser = null)
    {
        $this->standardizeViewingUserReference($viewingUser);

        if ($transaction['transaction_type'] == self::TYPE_PERSONAL
            && $transaction['reversed'] == 0
            && $transaction['to_user_id'] == $viewingUser['user_id']) {
            return true;
        }

        return false;
    }

    public function getActionBonus($action, $actionDate, $extraData = array())
    {
        $points = 0;
        $isPenalty = false;

        if (!empty($actionDate)) {
            $bonusesByTimePeriod = XenForo_Application::getConfig()->get(static::CONFIG_BONUSES_BY_TIME_PERIOD);
            if ($bonusesByTimePeriod !== null) {
                foreach ($bonusesByTimePeriod as $bonusesConfig) {
                    if ($actionDate >= $bonusesConfig->get('start_date', 0) && $actionDate < $bonusesConfig->get('end_date', 0)) {
                        $bonuses = $bonusesConfig->get('bonuses', array());
                        if ($bonuses->get($action)) {
                            return bdBank_Helper_Number::add(0, $bonuses->get($action));
                        }
                    }
                }
            }
        }

        switch ($action) {
            case 'thread':
            case 'post':
                // get the points from system options
                $points = self::options('bonus_' . $action);

                // try to get forum-based points if any
                if (empty($extraData['forum'])) {
                    throw new bdBank_Exception('thread_and_post_bonus_requires_forum_info');
                } else {
                    if (!empty($extraData['forum']['bdbank_options'])) {
                        $tmpOptions = self::helperUnserialize($extraData['forum']['bdbank_options']);
                        if (isset($tmpOptions['bonus_' . $action]) AND $tmpOptions['bonus_' . $action] !== '') {
                            $points = $tmpOptions['bonus_' . $action];
                        }
                    }
                }
                break;

            case 'attachment_downloaded':
                if (empty($extraData) || !is_string($extraData)) {
                    throw new bdBank_Exception('attachment_downloaded_bonus_requires_file_extension_as_extra_data');
                } else {
                    $extension = strtolower($extraData);
                    $list = explode("\n", self::options('bonus_attachment_downloaded'));
                    foreach ($list as $line) {
                        $parts = explode("=", $line);
                        if (count($parts) == 2) {
                            $extensions = explode(',', str_replace(' ', '', strtolower($parts[0])));
                            $point = $parts[1];
                            if (count($extensions) == 1 AND $extensions[0] == '*') {
                                // match all rule
                                $points = $point;
                                break;
                            } elseif (in_array($extension, $extensions)) {
                                $points = $point;
                                break;
                            }
                        }
                    }
                }
                break;

            case 'unlike':
                $isPenalty = true;
                $points = self::options('penalty_' . $action);
                break;

            default:
                $points = self::options('bonus_' . $action);
        }

        if ($isPenalty) {
            $points = bdBank_Helper_Number::mul($points, -1);
        }

        return $points;
    }

    public function parseComment($comment)
    {
        $parts = explode(' ', $comment);
        $link = false;

        if (count($parts) >= 2) {
            // all default system comment have at least 2 parts
            switch ($parts[0]) {
                case 'register':
                    $comment = new XenForo_Phrase('bdbank_explain_comment_register');
                    break;
                case 'login':
                    $comment = new XenForo_Phrase('bdbank_explain_comment_login', array(
                        'date' => XenForo_Template_Helper_Core::date($parts[1] * 86400)
                    ));
                    break;
                case 'post':
                case 'attachment_post':
                case 'liked_post':
                case 'unlike_post':
                    // new XenForo_Phrase('bdbank_explain_comment_post');
                    // new XenForo_Phrase('bdbank_explain_comment_attachment_post');
                    // new XenForo_Phrase('bdbank_explain_comment_liked_post');
                    // new XenForo_Phrase('bdbank_explain_comment_unlike_post');
                    $comment = new XenForo_Phrase(
                        'bdbank_explain_comment_' . $parts[0]
                    );
                    $link = XenForo_Link::buildPublicLink('posts', array('post_id' => $parts[1]));
                    break;
                case 'attachment_downloaded':
                    // new XenForo_Phrase('bdbank_explain_comment_attachment_downloaded');
                    $comment = new XenForo_Phrase(
                        'bdbank_explain_comment_' . $parts[0]
                    );
                    $link = XenForo_Link::buildPublicLink('attachments', array('attachment_id' => $parts[1]));
                    break;
                case 'manually_edited':
                    $comment = new XenForo_Phrase(
                        'bdbank_explain_comment_manually_edited_by_admin_x',
                        array('admin_id' => $parts[1])
                    );
                    break;
                case 'bdbank_purchase':
                case 'bdbank_purchase_revert':
                    // new XenForo_Phrase('bdbank_explain_comment_bdbank_purchase');
                    // new XenForo_Phrase('bdbank_explain_comment_bdbank_purchase_revert');
                    $comment = new XenForo_Phrase(
                        'bdbank_explain_comment_' . $parts[0],
                        array(
                            'amount' => XenForo_Template_Helper_Core::callHelper(
                                'bdbank_balanceformat',
                                array($parts[1])
                            )
                        )
                    );
                    $link = XenForo_Link::buildPublicLink('bank/get-more');
                    break;
                case 'resource_update':
                case 'liked_resource_update':
                case 'unlike_resource_update':
                    // new XenForo_Phrase('bdbank_explain_comment_resource_update');
                    // new XenForo_Phrase('bdbank_explain_comment_liked_resource_update');
                    // new XenForo_Phrase('bdbank_explain_comment_unlike_resource_update');
                    $comment = new XenForo_Phrase(
                        'bdbank_explain_comment_' . $parts[0]
                    );
                    $link = XenForo_Link::buildPublicLink(
                        'resources/update',
                        null,
                        array('resource_update_id' => $parts[1])
                    );
                    break;

                default:
                    if (substr($parts[0], 0, 6) === 'liked_') {
                        $comment = new XenForo_Phrase('bdbank_explain_comment_liked');
                    }
            }
        }

        return array(
            $comment,
            $link
        );
    }

    public function saveTransaction(&$data)
    {
        static $required = array(
            'from_user_id',
            'to_user_id',
            'amount',
            'comment',
            'tax_amount',
            'transaction_type'
        );
        foreach ($required as $column) {
            if (!isset($data[$column])) {
                throw new bdBank_Exception('transaction_data_missing');
            }
        }

        $rtFound = false;
        if ($data['transaction_type'] == self::TYPE_SYSTEM) {
            // only looking for system type transaction
            // normal transaction doesn't have much reversion
            foreach (self::$_reversedTransactions as &$rt) {
                $mismatched = false;
                foreach ($required as $column) {
                    if ($rt[$column] != $data[$column]) {
                        $mismatched = true;
                        break;
                    }
                }
                if (!$mismatched) {
                    $rtFound = $rt['transaction_id'];
                    break;
                }
            }
        }

        if ($rtFound) {
            // found a reversed transaction with the same data
            // this happens a lot, we will update the old transaction
            // instead of creating a lot of reversed transactions
            $this->_getDb()->update(
                'xf_bdbank_transaction',
                array('reversed' => 0),
                array('transaction_id = ?' => $rtFound)
            );
            $data['transaction_id'] = $rtFound;
        } else {
            if (empty($data['transfered'])) {
                $data['transfered'] = time();
            }
            $this->_getDb()->insert('xf_bdbank_transaction', $data);
            $data['transaction_id'] = $this->_getDb()->lastInsertId();
        }
    }

    public function reverseTransaction($transaction)
    {
        $personal = $this->personal();

        // get back the fund from receiver account
        // this may throw not_enough_money exception...
        $personal->transfer(
            $transaction['to_user_id'],
            0,
            bdBank_Helper_Number::sub($transaction['amount'], $transaction['tax_amount']),
            null,
            self::TYPE_SYSTEM,
            false
        );

        // actual refund
        $personal->give($transaction['from_user_id'], $transaction['amount'], null, self::TYPE_SYSTEM, false);

        $this->_getDb()->update(
            'xf_bdbank_transaction',
            array('reversed' => XenForo_Application::$time),
            array('transaction_id = ?' => $transaction['transaction_id'])
        );
    }

    public function reverseSystemTransactionByComment($comments, $skipIfAmountEqualsTo = null)
    {
        $result = [
            'deleted' => [],
            'reversed' => [],
            'skipped' => [],
            'skippedByComment' => [],
            'totalReversed' => 0,
        ];

        if (!is_array($comments)) {
            $comments = array($comments);
        }
        if (empty($comments)) {
            return $result;
        }

        $db = $this->_getDb();
        $commentsQuoted = $db->quote($comments);

        $reversibleTransactionTypes = $db->quote(self::SYSTEMATIC_TRANSACTION_TYPES);

        $found = $db->fetchAll('
            (
                SELECT transaction_id, from_user_id, to_user_id, amount, `comment`, "transaction" AS found_table,
                    transaction_type, tax_amount
                FROM xf_bdbank_transaction
                WHERE `comment` IN (' . $commentsQuoted . ')
                    AND reversed = 0
                    AND transaction_type IN (' . $reversibleTransactionTypes . ')
            )
            UNION
            (
                SELECT transaction_id, from_user_id, to_user_id, amount, `comment`, "archive" AS found_table,
                    transaction_type, tax_amount
                FROM xf_bdbank_archive
                WHERE `comment` IN (' . $commentsQuoted . ')
                    AND transaction_type IN (' . $reversibleTransactionTypes . ')
            )
        ');

        if (count($found) > 0) {
            $personal = $this->personal();

            XenForo_Db::beginTransaction();

            foreach ($found as $info) {
                if ($skipIfAmountEqualsTo !== null &&
                    empty($result['skippedByComment'][$info['comment']]) &&
                    bdBank_Helper_Number::comp($info['amount'], $skipIfAmountEqualsTo) === 0
                ) {
                    $result['skipped'][$info['transaction_id']] = $info['comment'];
                    $result['skippedByComment'][$info['comment']] = $info['transaction_id'];
                    continue;
                }

                try {
                    $personal->transfer(
                        $info['to_user_id'],
                        $info['from_user_id'],
                        $info['amount'],
                        null,
                        self::TYPE_SYSTEM,
                        false
                    );

                    if ($info['found_table'] == 'transaction') {
                        self::$_reversedTransactions[$info['transaction_id']] = $info;
                        $result['reversed'][$info['transaction_id']] = $info['comment'];
                    } else {
                        $result['deleted'][$info['transaction_id']] = $info['comment'];
                    }

                    $result['totalReversed'] = bdBank_Helper_Number::add($result['totalReversed'], $info['amount']);
                } catch (bdBank_Exception $e) {
                    // simply ignore it
                }
            }

            if (count($result['reversed']) > 0) {
                $db->update(
                    'xf_bdbank_transaction',
                    array('reversed' => XenForo_Application::$time),
                    'transaction_id IN (' . implode(',', array_keys($result['reversed'])) . ')'
                );
            }

            if (count($result['deleted']) > 0) {
                $db->delete(
                    'xf_bdbank_archive',
                    'transaction_id IN (' . implode(',', array_keys($result['deleted'])) . ')'
                );
            }

            XenForo_Db::commit();
        }

        if (XenForo_Application::debugMode()) {
            XenForo_Helper_File::log(__METHOD__, json_encode([$comments, $result]));
        }

        return $result;
    }

    public function clearReversedTransactionsCache()
    {
        self::$_reversedTransactions = array();
    }

    // Adjust the bonus points for a certain action (the action is identified by `comment`)
    // Should only use this to handle adjustments in **bonuses**,
    //   e.g. when the bonus for a `like` is adjusted from 1 to 3
    public function makeTransactionAdjustments($comments, $targetAmount)
    {
        $db = $this->_getDb();

        if (!is_array($comments)) {
            $comments = array($comments);
        }
        if (empty($comments)) {
            return;
        }

        // make sure $targetAmount is number
        $targetAmount = bdBank_Helper_Number::add(0, $targetAmount);
        $commentsQuoted = $db->quote($comments);

        $adjustableTransactionTypes = $db->quote(self::SYSTEMATIC_TRANSACTION_TYPES);

        $db->query("
            INSERT INTO xf_bdbank_transaction (`comment`, from_user_id, to_user_id, amount, transaction_type, transfered)
            SELECT `comment`, from_user_id, to_user_id, $targetAmount - SUM(amount), ?, ?
            FROM (
                (
                    SELECT `comment`, from_user_id, to_user_id, SUM(amount) amount
                    FROM xf_bdbank_transaction
                    WHERE `comment` IN ($commentsQuoted)
                        AND reversed = 0
                        AND transaction_type IN ($adjustableTransactionTypes)
                    GROUP BY 1,2,3
                )
                UNION ALL
                (
                    SELECT `comment`, from_user_id, to_user_id, SUM(amount) amount
                    FROM xf_bdbank_archive
                    WHERE `comment` IN ($commentsQuoted)
                        AND transaction_type IN ($adjustableTransactionTypes)
                    GROUP BY 1,2,3
                )
            ) t
            GROUP BY 1,2,3
            HAVING SUM(amount) <> $targetAmount
        ", array(self::TYPE_ADJUSTMENT, XenForo_Application::$time));
    }

    public function getTransactionByComment($comment, array $fetchOptions = array())
    {
        $fetchOptions['order'] = 'transaction_id';
        $fetchOptions['direction'] = 'desc';
        $fetchOptions['limit'] = 1;

        $transactions = $this->getTransactions(array('comment' => $comment), $fetchOptions);

        return reset($transactions);
    }

    public function getTransactionById($transactionId, array $fetchOptions = array())
    {
        $transactions = $this->getTransactions(array('transaction_id' => $transactionId), $fetchOptions);

        return reset($transactions);
    }

    public function countTransactions(array $conditions = array(), array $fetchOptions = array())
    {
        $tableName = $this->prepareTableName($conditions);
        $whereClause = $this->prepareTransactionConditions($conditions, $fetchOptions);
        $joinOptions = $this->prepareTransactionFetchOptions($fetchOptions);

        return $this->_getDb()->fetchOne('
				SELECT COUNT(*)
				FROM `' . $tableName . '` AS transaction
				' . $joinOptions['joinTables'] . '
				WHERE ' . $whereClause . '
		');
    }

    public function getTransactions(array $conditions = array(), array $fetchOptions = array())
    {
        $tableName = $this->prepareTableName($conditions);
        $whereClause = $this->prepareTransactionConditions($conditions, $fetchOptions);

        $orderClause = $this->prepareTransactionOrderOptions($fetchOptions);
        $joinOptions = $this->prepareTransactionFetchOptions($fetchOptions);
        $limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

        $transactions = $this->fetchAllKeyed($this->limitQueryResults('
				SELECT transaction.*
					' . $joinOptions['selectFields'] . '
				FROM `' . $tableName . '` AS transaction
				' . $joinOptions['joinTables'] . '
				WHERE ' . $whereClause . '
				' . $orderClause . '
			', $limitOptions['limit'], $limitOptions['offset']), 'transaction_id');

        $users = array();
        if (!empty($fetchOptions['join']) AND ($fetchOptions['join'] & self::FETCH_USER)) {
            $userIds = array();
            foreach ($transactions as $transaction) {
                $userIds[] = $transaction['from_user_id'];
                $userIds[] = $transaction['to_user_id'];
            }
            $userIds = array_unique($userIds);
            if (count($userIds) > 0) {
                $users = $this->_getUserModel()->getUsersByIds($userIds);
                foreach ($userIds as $userId) {
                    if (!isset($users[$userId])) {
                        // deleted users
                        $users[$userId] = array(
                            'user_id' => 0,
                            'username' => new XenForo_Phrase('bdbank_user_x', array('id' => $userId))
                        );
                    }
                }
            }
            $users['0'] = array(
                'user_id' => 'hmm', // user_id can not be 0 or gravatar won't show up...
                'username' => new XenForo_Phrase('bdbank_system_username'),
                'gravatar' => self::options('gravatar'),
            );
        }

        foreach ($transactions as &$transaction) {
            if (!empty($fetchOptions['join']) AND ($fetchOptions['join'] & self::FETCH_USER)) {
                $transaction['from_user'] = $users[$transaction['from_user_id']];
                $transaction['to_user'] = $users[$transaction['to_user_id']];
                if (isset($conditions['user_id'])) {
                    $transaction['other_user'] = ($users[$transaction['from_user_id'] == $conditions['user_id'] ?
                        $transaction['to_user_id'] :
                        $transaction['from_user_id']]);
                }
            }

            if ($transaction['transaction_type'] == self::TYPE_SYSTEM) {
                list($transaction['comment'], $transaction['comment_link']) = $this->parseComment($transaction['comment']);
            }

            if (isset($conditions['user_id'])) {
                $isSending = $transaction['from_user_id'] == $conditions['user_id'];
                $transaction['is_sending'] = $isSending;
            }
        }

        return $transactions;
    }

    public function prepareTableName(array $conditions)
    {
        if (empty($conditions['archive'])) {
            return 'xf_bdbank_transaction';
        } else {
            return 'xf_bdbank_archive';
        }
    }

    public function prepareTransactionConditions(array $conditions, array &$fetchOptions)
    {
        $db = $this->_getDb();
        $sqlConditions = array();

        if (!empty($conditions['transaction_id'])) {
            if (is_array($conditions['transaction_id'])) {
                $sqlConditions[] = 'transaction.transaction_id IN (' . $db->quote($conditions['transaction_id']) . ')';
            } else {
                $sqlConditions[] = 'transaction.transaction_id = ' . $db->quote($conditions['transaction_id']);
            }
        }

        if (!empty($conditions['user_id'])) {
            $userIdQuoted = $db->quote($conditions['user_id']);
            if (is_array($conditions['user_id'])) {
                $sqlConditions[] = '(transaction.from_user_id IN (' . $userIdQuoted . ') OR transaction.to_user_id IN (' . $userIdQuoted . '))';
            } else {
                $sqlConditions[] = '(transaction.from_user_id = ' . $userIdQuoted . ' OR transaction.to_user_id = ' . $userIdQuoted . ')';
            }
        }

        if (!empty($conditions['transaction_type'])) {
            if (is_array($conditions['transaction_type'])) {
                $sqlConditions[] = 'transaction.transaction_type IN (' . $db->quote($conditions['transaction_type']) . ')';
            } else {
                $sqlConditions[] = 'transaction.transaction_type = ' . $db->quote($conditions['transaction_type']);
            }
        }

        if (!empty($conditions['comment'])) {
            if (is_array($conditions['comment'])) {
                $sqlConditions[] = 'transaction.comment IN (' . $db->quote($conditions['comment']) . ')';
            } else {
                $sqlConditions[] = 'transaction.comment = ' . $db->quote($conditions['comment']);
            }
        }

        if (!empty($conditions['amount']) && is_array($conditions['amount'])) {
            list($operator, $cutOff) = $conditions['amount'];

            $this->assertValidCutOffOperator($operator);
            $sqlConditions[] = "transaction.amount $operator " . $db->quote($cutOff);
        }

        if (!empty($conditions['transfered']) && is_array($conditions['transfered'])) {
            list($operator, $cutOff) = $conditions['transfered'];

            $this->assertValidCutOffOperator($operator);
            $sqlConditions[] = "transaction.transfered $operator " . $db->quote($cutOff);
        }

        if (!empty($conditions['reversed']) && is_array($conditions['reversed'])) {
            list($operator, $cutOff) = $conditions['reversed'];

            $this->assertValidCutOffOperator($operator);
            $sqlConditions[] = "transaction.reversed $operator " . $db->quote($cutOff);
        }

        return $this->getConditionsForClause($sqlConditions);
    }

    public function prepareTransactionFetchOptions(array $fetchOptions)
    {
        $selectFields = '';
        $joinTables = '';

        return array(
            'selectFields' => $selectFields,
            'joinTables' => $joinTables
        );
    }

    public function prepareTransactionOrderOptions(array &$fetchOptions)
    {
        $choices = array(
            'date' => 'transaction.transfered',
            'transaction_id' => 'transaction.transaction_id',
        );
        return $this->getOrderByClause($choices, $fetchOptions);
    }

    /**
     * @return bdBank_Model_Personal
     */
    public function personal()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getModelFromCache('bdBank_Model_Personal');
    }

    /**
     * @return bdBank_Model_Stats
     */
    public function stats()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getModelFromCache('bdBank_Model_Stats');
    }

    /**
     * @return XenForo_Model_User
     */
    protected function _getUserModel()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getModelFromCache('XenForo_Model_User');
    }

    /* STATIC METHODS */

    /**
     * @return bdBank_Model_Bank
     */
    public static function getInstance()
    {
        $bank = XenForo_Application::get('bdBank');

        // sometimes the hook system is not setup properly
        // our global model won't be initialized at all
        // check for it here and create a new instance if necessary
        if (empty($bank)) {
            static $localBank = false;

            if ($localBank === false) {
                $localBank = XenForo_Model::create('bdBank_Model_Bank');
            }

            $bank = $localBank;
        }

        return $bank;
    }

    public static function options($optionId)
    {
        $xenOptions = XenForo_Application::getOptions();

        switch ($optionId) {
            case 'perPage':
                return 50;
            case 'perPagePopup':
                return 5;

            case 'statsRichestLimit':
                return 50;

            case 'taxRules':
                if (self::$_taxRules === false) {
                    $taxRules = $xenOptions->get('bdbank_taxRules');
                    $lines = explode("\n", $taxRules);
                    self::$_taxRules = array();

                    foreach ($lines as $line) {
                        if (!empty($line)) {
                            $parts = explode(':', trim($line));
                            if (count($parts) == 2 AND (is_numeric($parts[0]) OR $parts[0] == '*') AND is_numeric($parts[1])) {
                                self::$_taxRules[] = array(
                                    0,
                                    $parts[0],
                                    $parts[1]
                                );
                            }
                        }
                    }

                    for ($i = 0; $i < count(self::$_taxRules) - 1; $i++) {
                        self::$_taxRules[$i + 1][0] = self::$_taxRules[$i][1];
                    }

                    // in the end, $_taxRules will be an array of array
                    // each array has 3 elements: previous-level-cutoff, cutoff, percent
                    // or can be easier to understand: min, max, percent

                    foreach (self::$_taxRules as &$taxRule) {
                        if ($taxRule[1] != '*') {
                            $taxRule = array(
                                $taxRule[1] - $taxRule[0],
                                $taxRule[2]
                            );
                        } else {
                            $taxRule = array(
                                '*',
                                $taxRule[2]
                            );
                        }
                    }

                    // in the real end (oops), $_taxRules is still an array of array
                    // each array has 2 elements: range, precent
                }
                return self::$_taxRules;

            case 'getMorePrices':
                if (self::$_getMorePrices === false) {
                    $prices = $xenOptions->get('bdbank_getMorePrices');
                    $lines = explode("\n", $prices);
                    self::$_getMorePrices = array();

                    foreach ($lines as $line) {
                        $parts = explode('=', utf8_strtolower(utf8_trim($line)));
                        if (count($parts) == 2
                            && is_numeric($parts[0])
                            && preg_match('/^([0-9]+)([a-z]+)$/', $parts[1], $matches)
                        ) {
                            self::$_getMorePrices[] = array(
                                $parts[0],
                                $matches[1],
                                $matches[2]
                            );
                        }
                    }
                }
                return self::$_getMorePrices;
            case 'exchangeRates':
                if (self::$_exchangeRates === false) {
                    $rates = $xenOptions->get('bdbank_exchangeRates');
                    $lines = explode("\n", $rates);
                    self::$_exchangeRates = array();

                    foreach ($lines as $line) {
                        $parts = explode('=', utf8_strtolower(utf8_trim($line)));
                        if (count($parts) == 2 AND preg_match('/^([a-z]+)$/', $parts[0]) AND is_numeric($parts[1])) {
                            self::$_exchangeRates[$parts[0]] = $parts[1];
                        }
                    }
                }
                return self::$_exchangeRates;
        }

        return $xenOptions->get('bdbank_' . $optionId);
    }

    /**
     * Gets user balance
     * @param array $viewingUser
     * @return string
     */
    public static function balance(array $viewingUser = null)
    {
        self::getInstance()->standardizeViewingUserReference($viewingUser);
        $field = self::options('field');

        if (isset($viewingUser[$field])) {
            return $viewingUser[$field];
        } else {
            return self::BALANCE_NOT_AVAILABLE;
        }
    }

    /**
     * Gets current user accounts
     * @param array $viewingUser
     * @return array
     */
    public static function accounts(array $viewingUser = null)
    {
        return array();
    }

    /**
     * @param string $type
     * @param string $id
     * @param string $subId
     * @return string
     */
    public static function comment($type, $id, $subId = '')
    {
        $comment = "$type $id";
        if (strlen($subId) > 0) {
            $comment .= ' ' . $subId;
        }

        if (strlen($comment) > 255) {
            // possible?
            $comment = substr($comment, 0, 255 - 32) . md5($comment);
        }
        return $comment;
    }

    public static function helperUnserialize($str)
    {
        if (is_array($str)) {
            $array = $str;
        } else {
            $array = @unserialize($str);
            if (empty($array)) {
                $array = array();
            }
        }

        return $array;
    }

    public static function helperStrLen($str)
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($str);
        } else {
            return strlen($str);
        }
    }

    public static function helperBalanceFormat($value)
    {
        static $currencyName = false;

        if ($currencyName === false) {
            // the phrase is cached globally
            // but there's no reason to keep an object instead of a (cheaper) string
            // around...
            $tmp = new XenForo_Phrase('bdbank_currency_name');
            $currencyName = $tmp->render();
        }

        $valueFormatted = $value;
        $negative = false;

        if (is_numeric($value)) {
            if (bdBank_Helper_Number::comp($value, 0) === -1) {
                $negative = true;
                $value = bdBank_Helper_Number::mul($value, -1);
            }
            $valueFormatted = XenForo_Template_Helper_Core::numberFormat(
                $value,
                bdBank_Model_Bank::options('balanceDecimals')
            );
        }

        // check for option to include the currency after the number instead?
        return ($negative ? '-' : '') .
            ((self::options('balanceFormat') == 'currency_first') ?
                ($currencyName . $valueFormatted) :
                ($valueFormatted . $currencyName)
            );
    }

    public static function helperHasPermission($group, $permissionId = null)
    {
        if (empty($permissionId)) {
            $permissionId = $group;
            $group = self::PERM_GROUP;
        }

        if (strpos($permissionId, 'bdbank_') === false) {
            $permissionId = 'bdbank_' . $permissionId;
        }

        if ($group === 'admin') {
            return XenForo_Visitor::getInstance()->hasAdminPermission($permissionId);
        }

        if ($permissionId === 'bdbank_purchase') {
            $addOns = XenForo_Application::get('addOns');
            if (empty($addOns['bdPaygate'])) {
                return false;
            }
        }

        return XenForo_Visitor::getInstance()->hasPermission($group, $permissionId);
    }
}
