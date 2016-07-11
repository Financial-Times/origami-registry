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

	protected $fields = array('id', 'module_name', 'origami_group', 'git_repo_url', 'is_origami', 'host_type', 'datetime_last_discovered', 'recent_commit_count');
	protected $datefields = array('datetime_last_discovered');

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
		self::$app->db_write->query('INSERT INTO components SET {module_name}, {is_origami}, {origami_group}, {git_repo_url}, {host_type}, {recent_commit_count}, {datetime_last_discovered|date} ON DUPLICATE KEY UPDATE {git_repo_url}, {is_origami}, {origami_group}, {host_type}, {recent_commit_count}, {datetime_last_discovered|date}', $this->data);
		if (!$this->id) {
			$this->id = self::$app->db_write->querySingle('SELECT id FROM components WHERE {module_name}', $this->data);
		}
	}


	public function discoverVersions() {
		if (!$this->id) throw new \Exception('Component must be saved first');
		self::$app->logger->debug('Searching for new versions', array('component'=>$this->module_name));
		$apiclass = '\\GitAPIClients\\'.$this->host_type;
		$api = new $apiclass(self::$app->logger);
		$newversions = $api->discoverVersions($this->git_repo_url, array_keys($this->versions));

		foreach ($newversions as $version) {
			$versionobj = ComponentVersion::findOrCreate($this, $version['tag_name']);

			if (!$versionobj->id) {
				$versionobj->datetime_created = $version['datetime_created'];
				$versionobj->save();
				$this->data['versions'][$version['tag_name']] = $versionobj;

				self::$app->logger->info('Discovered new version', array(
					'module_name' => $this->module_name,
					'tag' => $version['tag_name'],
				));
			}
		}

		$this->_initialiseVersions();
	}


	public function buildVersions($unbuiltonly = true, $depth = 5) {
		$versions = $this->versions;
		if (empty($versions)) return;

		$version = $latest = end($versions);
		while ($version && ($version->is_valid === null or !$unbuiltonly) && $depth) {
			$isnew = ($version->is_valid === null);
			try {
				$version->build();

				$version->save();
				$this->data['is_origami'] = $latest->is_valid;
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
		$results = self::$app->db_read->queryAllRows('SELECT c.*, MAX(cv.datetime_created) as latest_version_release_date FROM components c LEFT JOIN componentversions cv ON c.id=cv.component_id WHERE '.$sql.' GROUP BY c.id ORDER BY latest_version_release_date DESC', $data);
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
