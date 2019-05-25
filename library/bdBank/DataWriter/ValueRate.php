<?php

class bdBank_DataWriter_ValueRate extends XenForo_DataWriter
{

    /* Start auto-generated lines of code. Change made will be overwriten... */

    protected function _getFields()
    {
        return array(
            'xf_bdbank_value_rate' => array(
                'rate_id' => array('type' => XenForo_DataWriter::TYPE_UINT, 'autoIncrement' => true),
                'rate' => array('type' => XenForo_DataWriter::TYPE_FLOAT, 'required' => true),
                'valid_to' => array('type' => XenForo_DataWriter::TYPE_UINT, 'required' => true),
            )
        );
    }

    protected function _getExistingData($data)
    {
        if (!$id = $this->_getExistingPrimaryKey($data, 'rate_id')) {
            return false;
        }

        return array('xf_bdbank_value_rate' => $this->_getValueRateModel()->getValueRateById($id));
    }

    protected function _getUpdateCondition($tableName)
    {
        $conditions = array();

        foreach (array('rate_id') as $field) {
            $conditions[] = $field . ' = ' . $this->_db->quote($this->getExisting($field));
        }

        return implode(' AND ', $conditions);
    }

    protected function _getValueRateModel()
    {
        /** @var bdBank_Model_ValueRate $model */
        $model = $this->getModelFromCache('bdBank_Model_ValueRate');

        return $model;
    }

    /* End auto-generated lines of code. Feel free to make changes below */
}
