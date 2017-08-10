<?php

class bdBank_ViewAdmin_Transfer extends XenForo_ViewAdmin_Base
{
    public function prepareParams()
    {
        if (!empty($this->_params['errors'])) {
            $this->_params['receivers'] = implode(', ', array_keys($this->_params['errors']));
            $this->_params['errorsString'] = sprintf(
                '<ul><li>%s</li></ul>',
                implode('</li><li>', $this->_params['errors'])
            );
        }

        parent::prepareParams();
    }
}
