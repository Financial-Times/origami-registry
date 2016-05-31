<?php
/**
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All rights reserved]
 */

namespace Controllers;

class Metrics extends BaseController {

	public function get() {
		$this->resp->setJSON(array(
			'schemaVersion' => 1,
			'metrics' => array(
				'knownRepoCount' => $this->counter("SELECT COUNT(*) FROM components", 'repos'),
				'origamiRepoCount' => $this->counter("SELECT COUNT(*) FROM components WHERE is_origami=1", 'repos'),
				'knownVersionCount' => $this->counter("SELECT COUNT(*) FROM componentversions", 'versions'),
				'origamiVersionCount' => $this->counter("SELECT COUNT(*) FROM componentversions WHERE is_valid=1", 'versions'),
				'cron_last_runtime' => $this->counter(array("SELECT (SELECT meta_value FROM meta WHERE meta_key=%s)-(SELECT meta_value FROM meta WHERE meta_key=%s)", 'cron_last_complete', 'cron_last_start'), 'seconds'),
				'cron_last_run_age' => $this->counter(array("SELECT UNIX_TIMESTAMP() - meta_value FROM meta WHERE meta_key=%s", 'cron_last_complete'), 'seconds'),
				'newest_version_age' => $this->counter("SELECT UNIX_TIMESTAMP() - MAX(UNIX_TIMESTAMP(datetime_created)) FROM componentversions", 'seconds'),
			)
		));
	}

	private function counter($queryargs, $unit, $since=null, $desc=null) {
		if (!is_array($queryargs)) $queryargs = array($queryargs);
		$op = array(
			'type'=>'counter',
			'val'=>(integer)call_user_func_array(array($this->app->db_read, 'querySingle'), $queryargs),
			'unit'=>$unit,
			'lastUpdated'=>date('c')
		);
		if ($since) $op['since'] = $since;
		if ($desc) $op['description'] = $desc;
		return $op;
	}
}
