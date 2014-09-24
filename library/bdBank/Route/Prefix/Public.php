<?php

class bdBank_Route_Prefix_Public implements XenForo_Route_Interface
{
	public function match($routePath, Zend_Controller_Request_Http $request, XenForo_Router $router)
	{
		// this is over complicated since our link structure is different from XenForo's
		// step 1: parse the route path and extract the first string
		// and assign it as "action" param of the request
		$action = $router->resolveActionWithStringParam($routePath, $request, 'action');
		// step 2: parse the remaining route path and look for a page number
		// if it can be found, assign it as "page" param of the request (to be used
		// later, see controllers)
		$action = $router->resolveActionAsPageNumber($action, $request);
		// step 3: get back the action from request's params list
		if (empty($action))
			$action = $request->getParam('action');
		// step 4: RUN
		return $router->getRouteMatch('bdBank_ControllerPublic_Bank', $action, 'bdbank');
	}

	public function buildLink($originalPrefix, $outputPrefix, $action, $extension, $data, array &$extraParams)
	{
		// this is complicated also and it's reversed from the above method
		// step 1: setup a fake action built from page information
		// if there is page number, it should be page-x
		// otherwise, it will be blank
		$actionFaked = XenForo_Link::getPageNumberAsAction('', $extraParams);
		// build the link with a string param (it's actually our real action)
		// the fake action (if any) will be placed in also
		return XenForo_Link::buildBasicLinkWithStringParam($outputPrefix, $actionFaked, $extension, array('action' => $action), 'action');
	}

}
