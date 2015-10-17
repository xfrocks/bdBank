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

    public function actionDetails()
    {
        $tz = new DateTimeZone('GMT');

        if (!$date = $this->_input->filterSingle('date', XenForo_Input::DATE_TIME, array('timeZone' => $tz))) {
            $date = strtotime('today');
        }

        $statsModel = $this->_getStatsModel();

        $dateStats = $statsModel->getStatsByDate($date);
        if (empty($dateStats)) {
            $date = $statsModel->getPreviousStatsDate($date);

            if (!empty($date)) {
                $dateStats = $statsModel->getStatsByDate($date);
            } else {
                $date = $statsModel->getNextStatsDate($date);
            }

            if (!empty($date)) {
                $dateStats = $statsModel->getStatsByDate($date);
            }
        }
        if (empty($dateStats)) {
            return $this->responseMessage(new XenForo_Phrase('bdbank_stats_details_no_data'));
        }

        $prevDate = $statsModel->getPreviousStatsDate($dateStats['_first']['rebuild_date']);
        $prevStats = array();
        if (!empty($prevDate)) {
            $prevStats = $statsModel->getStatsByDate($prevDate);
        }

        $nextDate = $statsModel->getNextStatsDate($dateStats['_first']['rebuild_date']);
        $nextStats = array();
        if (!empty($nextDate)) {
            $nextStats = $statsModel->getStatsByDate($nextDate);
        }

        $viewParams = array(
            'dateStats' => $dateStats,
            'prevStats' => $prevStats,
            'nextStats' => $nextStats,
        );

        return $this->responseView('bdBank_ViewAdmin_Stats_Details', 'bdbank_stats_details', $viewParams);
    }

    protected function _preDispatch($action)
    {
        $this->assertAdminPermission('bdbank');

        parent::_preDispatch($action);
    }


    /**
     * @return bdBank_Model_Stats
     */
    protected function _getStatsModel()
    {
        return $this->getModelFromCache('bdBank_Model_Stats');
    }
}