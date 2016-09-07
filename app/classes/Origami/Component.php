<?php
/**
 * Origami Component
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All rights reserved]
 */

namespace Origami;

use \Model;
use \FTLabs\MySqlConnection;
use \FTLabs\MySqlQueryException;

final class Component extends Model {

	const REBUILD_DEPENDENTS_DEPTH = 30;

	protected $fields = array('id', 'module_name', 'keywords', 'origami_category', 'git_repo_url', 'is_origami', 'host_type', 'datetime_last_discovered', 'recent_commit_count');
	protected $datefields = array('datetime_last_discovered');
	protected static $categories = array('primitives', 'components', 'layouts', 'utilities', 'imagesets', 'uncategorised');

	public function __construct() {
		parent::__construct();
	}

	/**
	 * PHP Magic Method: Overloaded property getter
	 *
	 * @param string $propertyName The name of the requested property
	 * @return mixed
	 */
	public function __get($propertyName) {
		if ($ret = parent::__get($propertyName)) return $ret;
		switch ($propertyName) {
			case 'versions':
			case 'latest_version':
			case 'latest_stable_version':
			case 'latest_unstable_version':
				$this->_initialiseVersions();
		}
		return parent::__get($propertyName);
	}

	private function _initialiseVersions() {
		$this->data['versions'] = ComponentVersion::findAllForComponent($this);
		$this->setVersionLinks();
	}

	public function save() {
		$this->data['is_origami'] = isset($this->data['is_origami']) ? (int)$this->data['is_origami'] : null;
		self::$app->db_write->query('INSERT INTO components SET {module_name}, {keywords}, {is_origami}, {origami_category}, {git_repo_url}, {host_type}, {recent_commit_count}, {datetime_last_discovered|date} ON DUPLICATE KEY UPDATE {keywords}, {git_repo_url}, {is_origami}, {origami_category}, {host_type}, {recent_commit_count}, {datetime_last_discovered|date}', $this->data);
		if (!$this->id) {
			$this->id = self::$app->db_write->querySingle('SELECT id FROM components WHERE {module_name}', $this->data);
		}
	}


	public function discoverVersions() {
		if (!$this->id) throw new \Exception('Component must be saved first');
		self::$app->logger->debug('Searching for new versions', array('component'=>$this->module_name));
		$apiclass = '\\GitAPIClients\\'.$this->host_type;
		$api = new $apiclass(self::$app->logger);
		$discover_versions_start = microtime(true);
		$newversions = $api->discoverVersions($this->git_repo_url, array_keys($this->versions));

		$discover_versions_diff = microtime(true) - $discover_versions_start;
		self::$app->metrics->timing(self::$app->metrics_prefix . '.discoverVersions.apiRequest', $discover_versions_diff);

		$update_versions_start = microtime(true);
		foreach ($newversions as $version) {
			$versionobj = ComponentVersion::findOrCreate($this, $version['tag_name']);

			if (!$versionobj->id) {
				$versionobj->datetime_created = $version['datetime_created'];
				$versionobj->save();
				$this->data['versions'][$version['tag_name']] = $versionobj;

				self::$app->metrics->increment(self::$app->metrics_prefix . 'component.discoverVersions.new');
				self::$app->logger->info('Discovered new version', array(
					'module_name' => $this->module_name,
					'tag' => $version['tag_name'],
				));
			}
		}

		$update_versions_diff = microtime(true) - $update_versions_start;
		self::$app->metrics->timing(self::$app->metrics_prefix . '.discoverVersions.updateDB', $update_versions_diff);

		$this->_initialiseVersions();
	}


	public function buildVersions($unbuiltonly = true, $depth = 5) {
		$versions = $this->versions;
		if (empty($versions)) return;

		$build_versions_start = microtime(true);

		$version = $latest = end($versions);
		while ($version && ($version->is_valid === null or !$unbuiltonly) && $depth) {
			$isnew = ($version->is_valid === null);
			try {
				$version->build();
				$version->save();

				$this->data['keywords'] = $latest->keywords;
				$this->data['is_origami'] = $latest->is_valid;
				$this->data['origami_category'] = $latest->origami_category;
				$this->save();

				// If this is the latest version of a module which is valid (or we're doing a deep scan), rebuild last REBUILD_DEPENDENTS_DEPTH versions of all direct dependents
				if (($isnew or !$unbuiltonly) && $version === $latest) {
					foreach ($version->dependents as $dep) {
						$depobj = Component::find($dep['module_name']);
						if ($depobj->is_origami) {
							self::$app->logger->info('New version triggered rebuild of dependents', array(
								'new_module' => $this->module_name,
								'new_version' => $version->tag_name,
								'dep_module' => $depobj->module_name
							));
							$depobj->buildVersions(false, self::REBUILD_DEPENDENTS_DEPTH);
						}
					}
				}
				if (!$version->is_valid and $unbuiltonly) break;
			} catch (\Exception $e) {
				if ($e->getMessage() === 'Tag not found') {
					self::$app->metrics->increment(self::$app->metrics_prefix . 'component.buildVersions.delete');
					$version->delete();
					return;
				}
			}

			$version = prev($versions);
			$depth--;
		}

		// Update the recent commit count
		if ($latest->is_valid) {
			$apiclass = '\\GitAPIClients\\'.$this->host_type;
			$api = new $apiclass(self::$app->logger);
			$this->data['recent_commit_count'] = $api->getRecentCommitCount($this->git_repo_url);
			$this->save();
		}

		$versiontags = array_keys($versions);

		$build_versions_diff = microtime(true) - $build_versions_start;
		self::$app->metrics->timing(self::$app->metrics_prefix . '.buildVersions.timing', $build_versions_diff);
		self::$app->logger->info('buildVersions', array(
			'module' => $this->module_name,
			'latest_tag' => end($versiontags),
			'tag_count' => count($versiontags),
			'is_origami' => $this->is_origami
		));
	}

	private function setVersionLinks() {
		uksort($this->data['versions'], "version_compare");
		$this->data['latest_version'] = end($this->data['versions']);
		$this->data['latest_stable_version'] = $this->data['latest_version'];
		while ($this->data['latest_stable_version'] && !$this->data['latest_stable_version']->isStable()) {
			$this->data['latest_stable_version'] = prev($this->data['versions']);
		}
	}

	public static function getOrigamiComponentsByCategory() {
		$components = array();

		foreach(self::$categories as $category) {
			$components[$category] = array(
					'title' => $category,
					'modules' => array(),
				);
		}

		foreach (self::findAll('c.is_origami IS TRUE') as $component) {
			$cat = $component->origami_category;
			if (!$cat || !in_array($cat, self::$categories)) {
				$cat = 'uncategorised';
			}

			if ($component->latest_stable_version) {
				$components[$cat]['modules'][] = array_merge(
					$component->toArray(),
					$component->latest_stable_version->toArray()
				);
			} elseif ($component->latest_version) {
				$components[$cat]['modules'][] = array_merge(
					$component->toArray(),
					$component->latest_version->toArray()
				);
			}
		}

		return $components;
	}

	public static function findOrCreate($name) {
		$component = new self();
		$component->module_name = $name;
		$data = self::$app->db_read->queryRow('SELECT * FROM components WHERE module_name = %s LIMIT 1', $name);
		if ($data) $component->edit($data);
		return $component;
	}

	public static function getByID($id) {
		$data = self::$app->db_read->queryRow('SELECT * FROM components WHERE id = %d LIMIT 1', $id);
		return ($data) ? self::_createFromDatabaseRow($data) : null;
	}

	public static function find($name) {
		$data = self::$app->db_read->queryRow('SELECT * FROM components WHERE module_name = %s LIMIT 1', $name);
		return ($data) ? self::_createFromDatabaseRow($data) : null;
	}

	public static function findAll($sql=null, $data=null) {
		$components = array();
		if (empty($sql) or !is_string($sql)) $sql = '';
		if (empty($data) or !is_array($data)) $data = array();
		$results = self::$app->db_read->queryAllRows('SELECT c.*, MAX(cv.datetime_created) as latest_version_release_date FROM components c LEFT JOIN componentversions cv ON c.id=cv.component_id WHERE '.$sql.' GROUP BY c.id ORDER BY module_name ASC', $data);
		foreach ($results as $data) {
			$components[] = self::_createFromDatabaseRow($data);
		}
		return $components;
	}

	private static function _createFromDatabaseRow(array $databaseRow) {
		$component = new self();
		$component->edit($databaseRow);
		return $component;
	}

}
