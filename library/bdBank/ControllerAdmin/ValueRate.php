<?php

class bdBank_ControllerAdmin_ValueRate extends XenForo_ControllerAdmin_Abstract
{

    /* Start auto-generated lines of code. Change made will be overwriten... */

    public function actionSave()
    {
        $this->_assertPostOnly();

        $id = $this->_input->filterSingle('rate_id', XenForo_Input::UINT);
        $dw = $this->_getValueRateDataWriter();
        if ($id) {
            $dw->setExistingData($id);
        }

        // get regular fields from input data
        $dwInput = $this->_input->filter(array('rate' => 'float', 'valid_to' => 'uint'));
        $dw->bulkSet($dwInput);

        $this->_prepareDwBeforeSaving($dw);

        $dw->save();

        return $this->responseRedirect(
            XenForo_ControllerResponse_Redirect::SUCCESS,
            XenForo_Link::buildAdminLink('value-rates')
        );
    }

    public function actionIndex()
    {
        $conditions = array();
        $fetchOptions = array();

        $valueRateModel = $this->_getValueRateModel();
        $valueRates = $valueRateModel->getValueRates($conditions, $fetchOptions);

        $viewParams = array(
            'valueRates' => $valueRates,
        );

        return $this->responseView('bdBank_ViewAdmin_ValueRate_List', 'bdbank_value_rate_list', $viewParams);
    }

    public function actionAdd()
    {
        $viewParams = array(
            'valueRate' => array(),
        );

        return $this->responseView('bdBank_ViewAdmin_ValueRate_Edit', 'bdbank_value_rate_edit', $viewParams);
    }

    public function actionEdit()
    {
        $id = $this->_input->filterSingle('rate_id', XenForo_Input::UINT);
        $valueRate = $this->_getValueRateOrError($id);

        $viewParams = array(
            'valueRate' => $valueRate,
        );

        return $this->responseView('bdBank_ViewAdmin_ValueRate_Edit', 'bdbank_value_rate_edit', $viewParams);
    }

    public function actionDelete()
    {
        $id = $this->_input->filterSingle('rate_id', XenForo_Input::UINT);
        $valueRate = $this->_getValueRateOrError($id);

        if ($this->isConfirmedPost()) {
            $dw = $this->_getValueRateDataWriter();
            $dw->setExistingData($id);
            $dw->delete();

            return $this->responseRedirect(
                XenForo_ControllerResponse_Redirect::SUCCESS,
                XenForo_Link::buildAdminLink('value-rates')
            );
        } else {
            $viewParams = array(
                'valueRate' => $valueRate,
            );

            return $this->responseView('bdBank_ViewAdmin_ValueRate_Delete', 'bdbank_value_rate_delete', $viewParams);
        }
    }

    protected function _getValueRateOrError($id, array $fetchOptions = array())
    {
        $valueRate = $this->_getValueRateModel()->getValueRateById($id, $fetchOptions);

        if (empty($valueRate)) {
            throw $this->responseException($this->responseError(new XenForo_Phrase('bdbank_value_rate_not_found'), 404));
        }

        return $valueRate;
    }

    protected function _getValueRateModel()
    {
        return $this->getModelFromCache('bdBank_Model_ValueRate');
    }

    protected function _getValueRateDataWriter()
    {
        return XenForo_DataWriter::create('bdBank_DataWriter_ValueRate');
    }

    /* End auto-generated lines of code. Feel free to make changes below */

    protected function _prepareDwBeforeSaving(bdBank_DataWriter_ValueRate $dw)
    {
        // customized code goes here
    }
}
