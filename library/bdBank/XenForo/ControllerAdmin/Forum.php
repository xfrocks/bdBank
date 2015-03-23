<?php

class bdBank_XenForo_ControllerAdmin_Forum extends XFCP_bdBank_XenForo_ControllerAdmin_Forum
{
    public function actionEdit()
    {
        $response = parent::actionEdit();

        if ($response instanceof XenForo_ControllerResponse_View) {
            $forum = &$response->params['forum'];

            if (!empty($forum['bdbank_options'])) {
                $response->params['bdBankOptions'] = bdBank_Model_Bank::helperUnserialize($forum['bdbank_options']);
            }
        }

        return $response;
    }

    public function actionSave()
    {
        $GLOBALS['bdBank_XenForo_ControllerAdmin_Forum::actionSave'] = $this;

        return parent::actionSave();
    }

    public function bdBank_actionSave(XenForo_DataWriter_Forum $dw)
    {
        $options = $this->_input->filterSingle('bdbank_options', XenForo_Input::ARRAY_SIMPLE);
        $dw->set('bdbank_options', $options);
    }

}
