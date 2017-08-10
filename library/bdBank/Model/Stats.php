<?php

class bdBank_Model_Stats extends XenForo_Model_Stats
{

    const KEY_RICHEST = 'bdBank_richest';
    const KEY_GENERAL = 'bdBank_general';

    const GENERAL_TOTAL_MONEY = 'total';

    public function getStatsByDate($date)
    {
        $data = $this->fetchAllKeyed('
            SELECT *
            FROM xf_bdbank_stats
            WHERE rebuild_date = ?
            ORDER BY rebuild_date
        ', 'stats_key', $date);

        foreach ($data as &$stat) {
            $stat['stats_value'] = unserialize($stat['stats_value']);
        }

        if (!empty($data)) {
            $first = reset($data);
            $data['_first'] = $first;
        }

        return $data;
    }

    public function getPreviousStatsDate($date)
    {
        return $this->_getDb()->fetchOne('
            SELECT rebuild_date
            FROM xf_bdbank_stats
            WHERE rebuild_date < ?
            ORDER BY rebuild_date DESC
            LIMIT 1
        ', $date);
    }

    public function getNextStatsDate($date)
    {
        return $this->_getDb()->fetchOne('
            SELECT rebuild_date
            FROM xf_bdbank_stats
            WHERE rebuild_date > ?
            ORDER BY rebuild_date
            LIMIT 1
        ', $date);
    }

    public function getStatsData($start, $end, array $statsTypes, $grouping = 'daily')
    {
        $data = $this->_getDb()->fetchAll('
            SELECT *
            FROM xf_bdbank_stats
            WHERE rebuild_date BETWEEN ? AND ?
            ORDER BY rebuild_date
        ', array($start, $end));

        $dataGrouped = array();

        foreach ($data AS $stat) {
            $value = unserialize($stat['stats_value']);
            $date = intval(floor($stat['rebuild_date'] / 86400) * 86400);

            switch ($stat['stats_key']) {
                case 'bdBank_general':
                    foreach ($value as $type => $typeValue) {
                        if (in_array($type, $statsTypes, true)) {
                            $dataGrouped[$type][$date] = $typeValue;
                        }
                    }
                    break;
                case 'bdBank_richest':
                    $userIds = array_keys($value);
                    for ($i = 0; $i < 3; $i++) {
                        $type = 'richest_' . $i;
                        if (in_array($type, $statsTypes, true)
                            && isset($userIds[$i])
                        ) {
                            $dataGrouped[$type][$date] = $value[$userIds[$i]]['money'];
                        }
                    }
                    break;
            }
        }

        return $dataGrouped;
    }

    public function getStatsTypeOptions(array $selected = array())
    {
        return array(
            'general' => array(
                array(
                    'name' => "statsTypes[]",
                    'value' => self::GENERAL_TOTAL_MONEY,
                    'label' => new XenForo_Phrase('bdbank_stats_total'),
                    'selected' => in_array('total', $selected)
                ),
            ),
            'richest' => array(
                array(
                    'name' => "statsTypes[]",
                    'value' => 'richest_0',
                    'label' => new XenForo_Phrase('bdbank_stats_richest_0'),
                    'selected' => in_array('richest_0', $selected)
                ),
                array(
                    'name' => "statsTypes[]",
                    'value' => 'richest_1',
                    'label' => new XenForo_Phrase('bdbank_stats_richest_1'),
                    'selected' => in_array('richest_1', $selected)
                ),
                array(
                    'name' => "statsTypes[]",
                    'value' => 'richest_2',
                    'label' => new XenForo_Phrase('bdbank_stats_richest_2'),
                    'selected' => in_array('richest_2', $selected)
                ),
            )
        );
    }

    public function getStatsTypePhrases(array $statsTypes)
    {
        return array(
            self::GENERAL_TOTAL_MONEY => new XenForo_Phrase('bdbank_stats_total'),
            'richest_0' => new XenForo_Phrase('bdbank_stats_richest_0'),
            'richest_1' => new XenForo_Phrase('bdbank_stats_richest_1'),
            'richest_2' => new XenForo_Phrase('bdbank_stats_richest_2'),
        );
    }

    public function getGeneral()
    {
        $data = $this->_load(self::KEY_GENERAL);

        if (empty($data)) {
            $data = $this->rebuildGeneral();
        }

        return $data;
    }

    public function rebuildGeneral()
    {
        $data = array();

        $bank = bdBank_Model_Bank::getInstance();
        $field = $bank->options('field');

        /* @var $db Zend_Db_Adapter_Abstract */
        $db = $this->_getDb();

        $data[self::GENERAL_TOTAL_MONEY] = $db->fetchOne("
			SELECT SUM({$field})
			FROM xf_user
		");

        $this->_save(self::KEY_GENERAL, $data);

        return $data;
    }

    public function getRichest()
    {
        $users = $this->_load(self::KEY_RICHEST);

        if (empty($users)) {
            $users = $this->rebuildRichest();
        }

        return $users;
    }

    public function rebuildRichest()
    {
        $bank = bdBank_Model_Bank::getInstance();
        $field = $bank->options('field');
        $limit = $bank->options('statsRichestLimit');

        $users = $this->fetchAllKeyed("
			SELECT user_id, username, {$field} AS money
			FROM xf_user
			WHERE
				user_state = 'valid'
				AND is_banned = 0
			ORDER BY {$field} DESC
			LIMIT ? 
		", 'user_id', array($limit));

        $this->_save(self::KEY_RICHEST, $users);

        return $users;
    }

    protected function _save($key, $value)
    {
        /* @var $dataRegistryModel XenForo_Model_DataRegistry */
        $dataRegistryModel = $this->getModelFromCache('XenForo_Model_DataRegistry');

        $dataRegistryModel->set($key, $value);

        // also save it to our table
        $this->_getDb()->query('
			REPLACE INTO `xf_bdbank_stats`
			(`stats_key`, `stats_date`, `stats_value`, `rebuild_date`)
			VALUES (?, ?, ?, ?)
		', array(
            $key,
            date('Y-m-d', XenForo_Application::$time),
            serialize($value),
            XenForo_Application::$time
        ));
    }

    protected function _load($key)
    {
        /* @var $dataRegistryModel XenForo_Model_DataRegistry */
        $dataRegistryModel = $this->getModelFromCache('XenForo_Model_DataRegistry');

        $value = $dataRegistryModel->get($key);
        if (empty($value)) {
            $value = array();
        }

        return $value;
    }
}
