<?php

class bdBank_Route_PrefixAdmin_Bank implements XenForo_Route_Interface
{
	public function match($routePath, Zend_Controller_Request_Http $request, XenForo_Router $router)
	{
		return $router->getRouteMatch('bdBank_ControllerAdmin_Bank', $routePath, 'bdbank');
	}

}
