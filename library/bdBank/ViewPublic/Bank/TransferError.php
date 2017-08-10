<?php

class bdBank_ViewPublic_Bank_TransferError extends XenForo_ViewPublic_Base
{
    public function prepareParams()
    {
        if ($this->_renderer instanceof XenForo_ViewRenderer_Json) {
            $this->_params['showHeading'] = true;
        }

        if (isset($this->_params['subView'])) {
            $this->_params['_subView'] = $this->_renderer->renderSubView($this->_params['subView']);

            if ($this->_renderer instanceof XenForo_ViewRenderer_Json) {
                $json = json_decode($this->_params['_subView'], true);
                $this->_params['_subView'] = $json['templateHtml'];
            }
        }

        parent::prepareParams();
    }
}
