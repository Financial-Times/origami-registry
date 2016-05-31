<?php
/**
 * FT health check
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All rights reserved]
 */

namespace Controllers;

use \FTLabs\HTTPRequest;

class Health extends BaseController {

	public function get() {
		$mysqlDatabaseUrl = getenv("DATABASE_URL");
        $parsedUrl = parse_url($mysqlDatabaseUrl);
        $mySQLServer = $parsedUrl["host"];
        $metrics = $this->app->metrics;

		$op = array(
			'schemaVersion'=>1,
			'name'=>'Origami registry',
			'description'=>'Provides component directory, hosts demos, and provides a bower registry for front-end web developers at the FT',
			'checks'=>array(
				array(
					'name'=>'Cron running on schedule',
					'fn'=>function($app) {
						$lastrun = $app->db_read->querySingle('SELECT meta_value FROM meta WHERE meta_key=%s', 'cron_last_complete');
						$age = time() - $lastrun;
						if ($age < (60*60*24)) {
							return true;
						} else {
							return 'Last run '.$age.' seconds ago.';
						}
					},
					'businessImpact'=>'Reduced developer productivity',
					'technicalSummary'=>'The cron job that updates the registry doesn\'t seem to be running.  As a result, the registry may not discover new component versions when they are released, and developers may not be able to install new components in their projects.',
					'severity'=>3,
					'panicGuide'=>'Notify Origami team - origami.support@ft.com'
				),
				array(
					'name'=>'Build service is reachable',
					'fn'=>function($app) {
						$http = new HTTPRequest('https://'.getenv('BUILD_SERVICE_HOST').'/__about');
						$resp = $http->send();
						$data = $resp->getData('json');
						if (!empty($data['name'])) {
							return true;
						} else {
							return $resp->getResponse();
						}
					},
					'businessImpact'=>'Reduced developer productivity',
					'technicalSummary'=>'The registry tries to connect to the build service at '.getenv('BUILD_SERVICE_HOST').' on port 80.  If it can\'t connect, it will be unable to retrieve metadata about new components or versions of components, so demos and other rich metadata will be unavailable.  When it comes back, those components will need to be manually refreshed using the Refresh button in the UI.  This test loads the /__about endpoint, which should always return JSON data.',
					'severity'=>3,
					'panicGuide'=>'Notify Origami team - origami.support@ft.com'
				),
				array(
					'name'=>'MySQL is usable',
					'fn'=>function($app) {
						if ($app->db_write->querySingle('SELECT 1')) {
							return true;
						}
					},
					'businessImpact'=>'Reduced developer produtivity.  Over longer term, more severe impacts may be experienced downstream in public-facing services that depend on the registry, such as the Origami build service.',
					'technicalSummary'=>'The registry relies on MySQL running on '.$mySQLServer.'.  Unavailabity of MySQL will render the registry completely inoperable, which will make it hard for front-end developers to work.',
					'severity'=>2,
					'panicGuide'=>'Notify Origami team - origami.support@ft.com'
				),
				array(
					'name'=>'Git sources are reachable',
					'fn'=>function($app) {
						$data = json_decode($app->db_read->querySingle('SELECT meta_value FROM meta WHERE meta_key=%s', 'module_sources'));
						foreach ($data->sources as $source) {
							$repo = $source->healthCheck;
							$result = shell_exec('git ls-remote '.escapeshellarg($repo).' HEAD 2>&1');
							if (!preg_match('/^\s*[0-9a-f]{40}\s+HEAD\s*$/i', $result)) {
								return $repo.': '.$result;
							}
						}
						return true;
					},
					'businessImpact'=>'Reduced developer productivity',
					'technicalSummary'=>'The registry crawls known Git repos for new and updated components.  If it\'s unable to connect to git sources, it will not be able to find new or updated components from that source',
					'severity'=>3,
					'panicGuide'=>'Notify Origami team - origami.support@ft.com'
				)
			)
		);

		$gtgok = true;
		$metric_start = 0;

		foreach ($op['checks'] as &$check) {
			try {
				$metric_start = microtime(true);
				$result = $check['fn']($this->app);
			} catch (\Exception $e) {
				$result = $e->getMessage();
			}
			if ($result !== true) {
				$metrics->increment($this->app->metrics_prefix . 'health.'.strtolower(str_replace(' ', '-', $check['name'])).'.fail');
				$check['ok'] = false;
				if ($check['severity'] <= 2) $gtgok = false;
				if ($result) {
					$check['checkOutput'] = (string)$result;
				}
			} else {
				$metrics->increment($this->app->metrics_prefix . 'health.'.strtolower(str_replace(' ', '-', $check['name'])).'.success');
				$check['ok'] = true;
			}

			$metric_diff = microtime(true) - $metric_start;
			$metrics->timing($this->app->metrics_prefix . 'health.'.strtolower(str_replace(' ', '-', $check['name'])).'.time', $metric_diff);
			$check['lastUpdated'] = date('r');
			unset($check['fn']);
		}

		$this->resp->setCacheTTL(5);

		if ($this->req->getPath() == '/__gtg') {
			if ($gtgok) {
				$this->resp->setStatus(200);
				$this->resp->setContent('OK');
			} else {
				$this->resp->setStatus(503);
				$this->resp->setContent('See /__health');
			}
		} else {
			$this->resp->setJSON($op);
		}
	}

}
