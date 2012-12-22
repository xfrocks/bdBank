<?php

class bdBank_Listeners {
	public static function init_dependencies(XenForo_Dependencies_Abstract $dependencies, array $data) {
		if ($dependencies instanceof XenForo_Dependencies_Public) {
			foreach ($data['routesPublic'] as $prefix => $route) {
				if ($route['route_class'] == 'bdBank_Route_Prefix_Public') {
					define('BDBANK_PREFIX', $prefix);
				}
			}
		}
		
		XenForo_Application::autoload('bdBank_Exception');
		XenForo_Application::set('bdBank',XenForo_Model::create('bdBank_Model_Bank'));
	}
	
	public static function visitor_setup(XenForo_Visitor &$visitor) {
		// temporary do nothing
	}
	
	public static function navigation_tabs(array &$extraTabs, $selectedTabId) {
		$visitor = XenForo_Visitor::getInstance();
		
		if ($visitor->get('user_id')) {
			$tabId = 'bdbank';
			
			if (XenForo_Template_Helper_Core::styleProperty('bdbank_navtab') OR $selectedTabId == $tabId) {
				// only display the tab if it's enabled globally or user is accessing our pages
				$route_prefix = bdBank_Model_Bank::options('route_prefix');
				if ($selectedTabId == $tabId) {
					$title = new XenForo_phrase('bdbank_bank');
				} else {
					$title = new XenForo_Phrase('bdbank_bank_x',array('balance' => XenForo_Template_Helper_Core::numberFormat(bdBank_Model_Bank::balance())));
				}
				$extraTabs[$tabId] = array(
					'href' => XenForo_Link::buildPublicLink("full:$route_prefix"),
					'title' => $title,
					'linksTemplate' => 'bdbank_links',
					'selected' => ($selectedTabId == $tabId),
					'links' => array(
						XenForo_Link::buildPublicLink("full:$route_prefix") => new XenForo_Phrase('bdbank_home'),
						XenForo_Link::buildPublicLink("full:$route_prefix/history") => new XenForo_Phrase('bdbank_history'),
						XenForo_Link::buildPublicLink("full:$route_prefix/transfer") => new XenForo_Phrase('bdbank_transfer'),
						XenForo_Link::buildPublicLink("full:$route_prefix/attachment-manager") => new XenForo_Phrase('bdbank_attachment_manager'),
					),
				);
			}
		}
	}
	
	public static function load_class_controller($class, array &$extend) {
		switch ($class) {
			case 'XenForo_ControllerPublic_Attachment':
				$extend[] = 'bdBank_ControllerPublic_Attachment';
				break;
		}
	}
	
	public static function load_class_datawriter($class, array &$extend) {
		switch ($class) {
			case 'XenForo_DataWriter_Discussion_Thread':
				$extend[] = 'bdBank_DataWriter_Thread';
				break;
			case 'XenForo_DataWriter_DiscussionMessage_Post':
				$extend[] = 'bdBank_DataWriter_Post';
				break;
			case 'XenForo_DataWriter_Attachment':
				$extend[]= 'bdBank_DataWriter_Attachment';
				break;
		}
	}
	
	public static function load_class_model($class, array &$extend) {
		switch ($class) {
			case 'XenForo_Model_Like':
				$extend[] = 'bdBank_Model_Like';
				break;
			case 'XenForo_Model_Attachment':
				$extend[] = 'bdBank_Model_Attachment';
				break;
			case 'XenForo_Model_Import':
				$extend[] = 'bdBank_Model_Import';
				break;
		}
	}
	
	public static function template_create($templateName, array &$params, XenForo_Template_Abstract $template) {
		if (!defined('BDBANK_CACHED_TEMPLATES')) {
			define('BDBANK_CACHED_TEMPLATES', true);
			$template->preloadTemplate('bdbank_message_user_info_extra');
		}
	}
	
	public static function template_hook($hookName, &$contents, array $hookParams, XenForo_Template_Abstract $template) {
		switch ($hookName) {
			case 'message_user_info_extra':
				$ourTemplate = $template->create('bdbank_message_user_info_extra', $hookParams);
				$ourTemplate->setParam('bdBankField', XenForo_Application::get('bdBank')->options('field'));
				$ourTemplate->setParam('bdBankRoutePrefix', XenForo_Application::get('bdBank')->options('route_prefix'));
				$contents .= $ourTemplate->render();
				break;
		}
	}
}