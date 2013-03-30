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
		
		XenForo_Template_Helper_Core::$helperCallbacks['bdbank_balance'] = array('bdBank_Model_Bank', 'balance');
		XenForo_Template_Helper_Core::$helperCallbacks['bdbank_balanceformat'] = array('bdBank_Model_Bank', 'helperBalanceFormat');
		XenForo_Template_Helper_Core::$helperCallbacks['bdbank_options'] = array('bdBank_Model_Bank', 'options');
		XenForo_Template_Helper_Core::$helperCallbacks['bdbank_routeprefix'] = array('bdBank_Model_Bank', 'routePrefix');
		
		XenForo_CacheRebuilder_Abstract::$builders['bdBank_Bonuses'] = 'bdBank_CacheRebuilder_Bonuses';
		XenForo_CacheRebuilder_Abstract::$builders['bdBank_User'] = 'bdBank_CacheRebuilder_User';
	}
	
	public static function visitor_setup(XenForo_Visitor &$visitor) {
		// temporary do nothing
	}
	
	public static function navigation_tabs(array &$extraTabs, $selectedTabId) {
		$visitor = XenForo_Visitor::getInstance();
		$bank = bdBank_Model_Bank::getInstance();
		
		if ($visitor->get('user_id')) {
			$tabId = 'bdbank';
			
			if (XenForo_Template_Helper_Core::styleProperty('bdbank_navigationInsertNavtab') OR $selectedTabId == $tabId) {
				// only display the tab if it's enabled globally or user is accessing our pages
				$routePrefix = bdBank_Model_Bank::routePrefix();

				$extraTabs[$tabId] = array(
					'href' => XenForo_Link::buildPublicLink("full:$routePrefix"),
					'title' => new XenForo_phrase('bdbank_bank'),
					'linksTemplate' => 'bdbank_links',
					'selected' => ($selectedTabId == $tabId),
					'links' => array(
						XenForo_Link::buildPublicLink("full:$routePrefix") => new XenForo_Phrase('bdbank_home'),
						XenForo_Link::buildPublicLink("full:$routePrefix/history") => new XenForo_Phrase('bdbank_history'),
						XenForo_Link::buildPublicLink("full:$routePrefix/transfer") => new XenForo_Phrase('bdbank_transfer', array('money' => new XenForo_Phrase('bdbank_money'))),
					),
				);
				
				$bank->prepareNavigationTab($extraTabs, $tabId, $routePrefix, $visitor);
			}
		}
	}
	
	public static function load_class($class, array &$extend) {
		static $classes = array(
			'bdPaygate_Model_Processor',
		
			'XenForo_ControllerAdmin_Forum',
			'XenForo_ControllerAdmin_User',
			'XenForo_ControllerAdmin_UserUpgrade',
		
			'XenForo_ControllerPublic_Account',
			'XenForo_ControllerPublic_Attachment',
			'XenForo_ControllerPublic_Thread',
		
			'XenForo_DataWriter_Attachment',
			'XenForo_DataWriter_Discussion_Thread',
			'XenForo_DataWriter_DiscussionMessage_Post',
			'XenForo_DataWriter_Forum',
			'XenForo_DataWriter_User',
		
			'XenForo_Model_Attachment',
			'XenForo_Model_Import',
			'XenForo_Model_Like',
			'XenForo_Model_Log',
			'XenForo_Model_Option',
			'XenForo_Model_Thread'
		);
		
		if (in_array($class, $classes)) {
			$extend[] = 'bdBank_' . $class;
		}
	}
	
	public static function load_class_importer($class, array &$extend) {
		static $extended = false;
		
		// extend all vbulletin importers
		if ($extended === false AND strpos(strtolower($class), 'vbulletin') !== false) {
			$extend[] = 'bdBank_Importer_vBulletin';
			$extended = true;
		}
	}
	
	public static function template_create($templateName, array &$params, XenForo_Template_Abstract $template) {
		if (!defined('BDBANK_CACHED_TEMPLATES')) {
			define('BDBANK_CACHED_TEMPLATES', true);
			$template->preloadTemplate('bdbank_message_user_info_extra');
			$template->preloadTemplate('bdbank_sidebar_visitor_panel_stats');
			$template->preloadTemplate('bdbank_navigation_visitor_tabs_end');
		}
		
		switch ($templateName) {
			case 'forum_edit': // AdminCP
				$template->preloadTemplate('bdbank_admin_forum_edit_tabs');
				$template->preloadTemplate('bdbank_admin_forum_edit_panes');
				break;
			case 'user_edit': // AdminCP
				$template->preloadTemplate('bdbank_user_edit_profile_info');
				$template->preloadTemplate('bdbank_user_edit_privacy');
				break;
			case 'trophy_edit': // AdminCP
				$template->preloadTemplate('bdbank_user_criteria_content');
				break;
			case 'account_alert_preferences':
				$template->preloadTemplate('bdbank_account_alerts_extra');
				break;
			case 'account_privacy':
				$template->preloadTemplate('bdbank_account_privacy_top');
				break;
			case 'member_view':
				$template->preloadTemplate('bdbank_member_view_info_block');
				
			case 'tools_rebuild': // AdminCP
				$template->preloadTemplate('bdbank_' . $templateName);
				break;
		}
	}
	
	public static function template_post_render($templateName, &$content, array &$containerData, XenForo_Template_Abstract $template) {
		switch ($templateName) {
			case 'tools_rebuild': // AdminCP
				$ourTemplate = $template->create('bdbank_' . $templateName, $template->getParams());
				$content .= $ourTemplate->render();
				break;
		}
	}
	
	public static function template_hook($hookName, &$contents, array $hookParams, XenForo_Template_Abstract $template) {
		switch ($hookName) {
			case 'admin_user_edit_panes': // AdminCP
				$profileInfoTemplate = $template->create('bdbank_user_edit_profile_info', $template->getParams());
				self::inject($contents, $profileInfoTemplate->render(), strpos($contents, '<!-- slot: pre_profile_info -->'));
				
				$privacyTemplate = $template->create('bdbank_user_edit_privacy', $template->getParams());
				self::inject($contents, $privacyTemplate->render(), strpos($contents, '<!-- slot: pre_privacy -->'));
				break;
				
			case 'message_user_info_extra':
				$ourTemplate = $template->create('bdbank_message_user_info_extra', $template->getParams());
				$ourTemplate->setParams($hookParams);
				$contents .= $ourTemplate->render();
				break;
			
			// below are hooks that insert to the top of the original contents
			case 'sidebar_visitor_panel_stats':
				$ourTemplate = $template->create('bdbank_' . $hookName, $template->getParams());
				$contents = $ourTemplate->render() . $contents;
				break;
				
			// below are hooks that append the contents
			case 'admin_forum_edit_tabs': // AdminCP
			case 'admin_forum_edit_panes': // AdminCP
			case 'user_criteria_content': // AdminCP
			case 'account_alerts_extra':
			case 'account_privacy_top':
			case 'member_view_info_block':
			case 'navigation_visitor_tabs_end':
				$ourTemplate = $template->create('bdbank_' . $hookName, $template->getParams());
				$contents .= $ourTemplate->render();
				break;
		}
	}
	
	public static function inject(&$target, $html, $offset = 0, $mark = '<!-- [bd] Banking / Mark -->') {
		if ($offset === false) return; // do nothing if invalid offset is given
		
		$startPos = strpos($html, $mark);
		if ($startPos !== false) {
			$endPos = strpos($html, $mark, $startPos + 1);
			if ($endPos !== false) {
				// found the two marks
				$markLen = strlen($mark);
				$marked = trim(substr($html, $startPos + $markLen, $endPos - $startPos - $markLen));
				
				$markedPos = strpos($target, $marked, $offset);
				if ($markedPos !== false) {
					// the marked text has been found
					// start injecting our html in place
					$target = substr_replace($target, $html, $markedPos, strlen($marked));
				}
			}
		}
	}
	
	public static function file_health_check(XenForo_ControllerAdmin_Abstract $controller, array &$hashes) {
		$ourHashes = bdBank_FileSums::getHashes();
		$hashes = array_merge($hashes, $ourHashes);
	}
	
	public static function criteria_user($rule, array $data, array $user, &$returnValue) {
		switch ($rule) {
			case 'bdbank':
				$field = bdBank_Model_Bank::getInstance()->options('field');

				if (isset($user[$field]) && $user[$field] >= $data['money']) {
					$returnValue = true;
				}
				break;
		}
	}
	
	public static function bdshop_register_pricing(array &$classes) {
		$classes[] = 'bdBank_bdShop_Pricing';
	}
}