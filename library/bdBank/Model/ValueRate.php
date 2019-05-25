<?php

class bdBank_Model_ValueRate extends XenForo_Model
{

    /* Start auto-generated lines of code. Change made will be overwriten... */

    public function getList(array $conditions = array(), array $fetchOptions = array())
    {
        $valueRates = $this->getValueRates($conditions, $fetchOptions);
        $list = array();

        foreach ($valueRates as $id => $valueRate) {
            $list[$id] = $valueRate['rate_id'];
        }

        return $list;
    }

    public function getValueRateById($id, array $fetchOptions = array())
    {
        $valueRates = $this->getValueRates(array('rate_id' => $id), $fetchOptions);

        return reset($valueRates);
    }

    public function getValueRateIdsInRange($start, $limit)
    {
        $db = $this->_getDb();

        return $db->fetchCol($db->limit('
            SELECT rate_id
            FROM xf_bdbank_value_rate
            WHERE rate_id > ?
            ORDER BY rate_id
        ', $limit), $start);
    }

    public function getValueRates(array $conditions = array(), array $fetchOptions = array())
    {
        $whereConditions = $this->prepareValueRateConditions($conditions, $fetchOptions);

        $orderClause = $this->prepareValueRateOrderOptions($fetchOptions);
        $joinOptions = $this->prepareValueRateFetchOptions($fetchOptions);
        $limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

        $valueRates = $this->fetchAllKeyed($this->limitQueryResults("
            SELECT value_rate.*
                $joinOptions[selectFields]
            FROM `xf_bdbank_value_rate` AS value_rate
                $joinOptions[joinTables]
            WHERE $whereConditions
                $orderClause
            ", $limitOptions['limit'], $limitOptions['offset']), 'rate_id');

        $this->_getValueRatesCustomized($valueRates, $fetchOptions);

        return $valueRates;
    }

    public function countValueRates(array $conditions = array(), array $fetchOptions = array())
    {
        $whereConditions = $this->prepareValueRateConditions($conditions, $fetchOptions);

        $orderClause = $this->prepareValueRateOrderOptions($fetchOptions);
        $joinOptions = $this->prepareValueRateFetchOptions($fetchOptions);
        $limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

        return $this->_getDb()->fetchOne("
            SELECT COUNT(*)
            FROM `xf_bdbank_value_rate` AS value_rate
                $joinOptions[joinTables]
            WHERE $whereConditions
        ");
    }

    public function prepareValueRateConditions(array $conditions = array(), array $fetchOptions = array())
    {
        $sqlConditions = array();
        $db = $this->_getDb();

        if (isset($conditions['rate_id'])) {
            if (is_array($conditions['rate_id'])) {
                if (!empty($conditions['rate_id'])) {
                    // only use IN condition if the array is not empty (nasty!)
                    $sqlConditions[] = "value_rate.rate_id IN (" . $db->quote($conditions['rate_id']) . ")";
                }
            } else {
                $sqlConditions[] = "value_rate.rate_id = " . $db->quote($conditions['rate_id']);
            }
        }

        if (isset($conditions['valid_to'])) {
            if (is_array($conditions['valid_to'])) {
                if (!empty($conditions['valid_to'])) {
                    // only use IN condition if the array is not empty (nasty!)
                    $sqlConditions[] = "value_rate.valid_to IN (" . $db->quote($conditions['valid_to']) . ")";
                }
            } else {
                $sqlConditions[] = "value_rate.valid_to = " . $db->quote($conditions['valid_to']);
            }
        }

        $this->_prepareValueRateConditionsCustomized($sqlConditions, $conditions, $fetchOptions);

        return $this->getConditionsForClause($sqlConditions);
    }

    public function prepareValueRateFetchOptions(array $fetchOptions = array())
    {
        $selectFields = '';
        $joinTables = '';

        $this->_prepareValueRateFetchOptionsCustomized($selectFields, $joinTables, $fetchOptions);

        return array(
            'selectFields' => $selectFields,
            'joinTables' => $joinTables
        );
    }

    public function prepareValueRateOrderOptions(array $fetchOptions = array(), $defaultOrderSql = '')
    {
        $choices = array();

        $this->_prepareValueRateOrderOptionsCustomized($choices, $fetchOptions);

        return $this->getOrderByClause($choices, $fetchOptions, $defaultOrderSql);
    }

    /* End auto-generated lines of code. Feel free to make changes below */

    protected function _getAllValueRateCustomized(array &$data, array $fetchOptions)
    {
        // customized code goes here
    }

    protected function _prepareValueRateConditionsCustomized(array &$sqlConditions, array $conditions, array $fetchOptions)
    {
        // customized code goes here
    }

    protected function _prepareValueRateFetchOptionsCustomized(&$selectFields, &$joinTables, array $fetchOptions)
    {
        // customized code goes here
    }

    protected function _prepareValueRateOrderOptionsCustomized(array &$choices, array &$fetchOptions)
    {
        $choices = array(
            'valid_to' => 'value_rate.valid_to'
        );
    }
}
