<?php

class bdBank_ControllerAdmin_Stats extends XenForo_ControllerAdmin_Stats
{
    public function actionIndex()
    {
        if (!$this->_input->filterSingle('statsTypes', XenForo_Input::ARRAY_SIMPLE)) {
            $this->_request->setParam('statsTypes', array(
                'total',
                'richest_0',
            ));
        }

        $viewParams = $this->getStatsData('daily', strtotime('-1 month'));

        return $this->responseView('bdBank_ViewAdmin_Stats_Daily', 'bdbank_stats_daily', $viewParams);
    }

    /**
     * @return bdBank_Model_Stats
     */
    protected function _getStatsModel()
    {
        return $this->getModelFromCache('bdBank_Model_Stats');
    }
}