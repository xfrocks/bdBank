<?php

class bdBank_Importer_vBulletin extends XFCP_bdBank_Importer_vBulletin {
	public function getSteps() {
		$steps = parent::getSteps();
		
		$steps = array_merge($steps,array(
			'vbcredits' => array(
				'title' => 'Import vbCredits (I & II)',
				'depends' => array('users')
			),
			'kbank' => array(
				'title' => 'Import kBank',
				'depends' => array('users')
			),
		));

		return $steps;
	}
	
	public function stepVbcredits($start, array $options) {
		$options['field'] = 'credits';
		return $this->_bdBank($start, $options);
	}
	
	public function stepKbank($start, array $options) {
		$vbsetting = $this->_sourceDb->fetchRow('
			SELECT value
			FROM `' . $this->_prefix . 'setting`
			WHERE varname = ?
		', 'kbankf');
		if (empty($vbsetting)) return true; // do nothing, assume it's done
		$options['field'] = $vbsetting['value'];
		
		return $this->_bdBank($start,$options);
	}
	
	private function _bdBank($start, array $options) {
		$options = array_merge(array(
			'limit' => 500,
			'max' => false,
		), $options);

		$sDb = $this->_sourceDb;
		$prefix = $this->_prefix;

		/* @var $model XenForo_Model_Import */
		$model = $this->_importModel;

		if ($options['max'] === false)
		{
			$options['max'] = $sDb->fetchOne('
				SELECT MAX(userid)
				FROM ' . $prefix . 'user
			');
		}
		
		if (empty($options['field'])) return true;
		$records = $sDb->fetchAll($sDb->limit('
			SELECT userid, ' . $options['field'] . ' AS money
			FROM `' . $prefix . 'user`
			WHERE userid > ?
			ORDER BY userid
		', $options['limit']), $start);
		
		if (empty($records)) return true;

		$userIdMap = $model->getUserIdsMapFromArray($records, 'userid');

		$next = 0;
		$total = 0;

		XenForo_Db::beginTransaction();
		
		$db = XenForo_Application::get('db');
		$personal = bdBank_Model_Bank::getInstance()->personal();

		foreach ($records AS $record) {
			$next = $record['userid'];
			$newUserId = $this->_mapLookUp($userIdMap, $record['userid']);
			if (empty($newUserId)) continue; // user not found?
			
			if (!empty($record['money'])) $personal->give($newUserId,$record['money'],'Import');
			$total++;
		}

		XenForo_Db::commit();

		$this->_session->incrementStepImportTotal($total);

		return array($next, $options, "$next / $options[max]");
	}
}