<?php

class bdBank_WidgetRenderer_RichestUsers extends WidgetFramework_WidgetRenderer
{
    public function extraPrepareTitle(array $widget)
    {
        if (empty($widget['title'])) {
            return new XenForo_Phrase('bdbank_richest_users');
        }

        return parent::extraPrepareTitle($widget);
    }

    protected function _getConfiguration()
    {
        return array(
            'name' => '[bd] Bank: Richest Users',
            'options' => array(
                'limit' => XenForo_Input::UINT,
                'displayMode' => XenForo_Input::STRING,
            ),
            'useCache' => true,
        );
    }

    protected function _getOptionsTemplate()
    {
        return 'bdbank_widget_options_richest_users';
    }

    protected function _getRenderTemplate(array $widget, $positionCode, array $params)
    {
        return 'bdbank_widget_richest_users';
    }

    protected function _render(
        array $widget,
        $positionCode,
        array $params,
        XenForo_Template_Abstract $renderTemplateObject
    ) {
        $bank = bdBank_Model_Bank::getInstance();
        /** @var bdBank_Model_Stats $statsModel */
        $statsModel = $bank->getModelFromCache('bdBank_Model_Stats');
        $richest = $statsModel->getRichest();
        if (empty($richest)) {
            return '';
        }

        /** @var XenForo_Model_User $userModel */
        $userModel = WidgetFramework_Core::getInstance()->getModelFromCache('XenForo_Model_User');
        $richestUsers = $userModel->getUsers(
            array('user_id' => array_keys($richest)),
            array('join' => XenForo_Model_User::FETCH_USER_FULL)
        );

        $users = array();
        $limit = !empty($widget['options']['limit'])
            ? $widget['options']['limit']
            : $bank->options('statsRichestLimit');

        foreach ($richestUsers as $userId => $richestUser) {
            if (count($users) >= $limit) {
                continue;
            }

            $users[$userId] = $richestUser;
        }

        $renderTemplateObject->setParam('users', $users);

        return $renderTemplateObject->render();
    }
}
