<?php

class bdBank_Model_Import extends XFCP_bdBank_Model_Import {
	public function getImporter($name) {
		$extend = null;
		switch ($name) {
			case 'vBulletin':
				$extend = 'bdBank_Importer_vBulletin';
				break;
		}
		
		if ($extend === null) {
			return parent::getImporter($name);
		} else {
			// get the idea from XenForo_Application::resolveDynamicClass
			$original = 'XenForo_Importer_' . $name;
			XenForo_Application::autoload($original);
			eval('class XFCP_' . $extend . ' extends ' . $original . ' {}');
			XenForo_Application::autoload($extend);
			return new $extend();
		}
	}
}