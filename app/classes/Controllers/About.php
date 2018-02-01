<?php
/**
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All rights reserved]
 */

namespace Controllers;

class About extends BaseController {

	public function get() {

		$version = @file_get_contents($_SERVER['DOCUMENT_ROOT'].'/../appversion');
		if ($version) {
			$deploytime = date('c', filemtime($_SERVER['DOCUMENT_ROOT'].'/../appversion'));
		} else {
			$deploytime = null;
			$version = 'unknown';
		}

		$this->resp->setJSON(array(
			'schemaVersion' => 1,
			'name' => 'Origami Registry',
			'purpose' => 'Provides information about Origami components and a bower-compatible registry API for the Origami build tools client',
			'audience' => 'internal',
			'primaryUrl' => 'http://registry.origami.ft.com',
			'appVersion' => trim($version),
			'serviceTier' => 'bronze',
			'_hostname' => trim(shell_exec('hostname')),
			'dateCreated' => '2013-12-09T15:16:00Z',
			'dateDeployed' => $deploytime,
			'contacts' => array(
				array("name"=>"Origami team", "email"=>"origami-support@ft.com", "rel"=>"owner", "domain"=>"All support enquiries")
			),
			'links' => array(
				array('category'=>'repo', 'url'=>'http://git.svc.ft.com/projects/OT/repos/registry'),
				array('category'=>'documentation', 'url'=>'http://git.svc.ft.com/projects/OT/repos/registry/browse/README.md', 'description'=>'README'),
				array('category'=>'monitoring', 'url'=>'https://app.getsentry.com/nextftcom/registry/', 'description'=>'PHP error logs via Sentry'),
				array('category'=>'deployment', 'url'=>'https://dashboard.heroku.com/apps/origami-registry-eu', 'description'=>'Prod hosting environment control panel on Heroku'),
				array('category'=>'monitoring', 'url'=>'https://my.pingdom.com/reports/uptime#check=1157746', 'description'=>'Pingdom check'),
				array('category'=>'documentation', 'url'=>'https://docs.google.com/drawings/d/1dP1nrX6H2VLQoeDt3Y1TWYOTZSUexESY3QUmPupMpxA/edit', 'description'=>'Architecture diagram'),
			),
			'lastScanCompleted' => date('c', $this->app->db_read->querySingle("SELECT meta_value FROM meta WHERE meta_key=%s", 'cron_last_complete'))
		));
	}

}
