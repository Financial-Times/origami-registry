<?php
/**
 * Origami component version
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All rights reserved]
 */

namespace Origami;

use \Model;
use \FTLabs\HTTPRequest;
use \FTLabs\MySqlConnection;
use \FTLabs\MySqlQueryException;

final class ComponentVersion extends Model {

	private $component;

	protected $fields = array('id', 'component_id', 'tag_name', 'datetime_last_cached', 'datetime_created', 'is_valid', 'description', 'origami_type', 'origami_category', 'origami_version', 'support', 'service_url', 'readme_gfm', 'support_status', 'ci_status', 'has_js', 'has_css', 'bundlesize_js', 'bundlesize_css', 'image_list', 'design_guidelines');
	protected $datefields = array('datetime_last_cached', 'datetime_created');


	/**
	 * Object constructor
	 *
	 * Note that this constructor is private since objects of this class should only be instantiated using the factory methods createFromTagName() and _createFromDatabaseRow().
	 *
	 * @param Component &$parentComponent     The Origami component of which this is a version.
	 */
	protected function __construct($parentComponent) {
		parent::__construct();
		$this->component = $parentComponent;
		$this->data['component_id'] = $this->component->id;
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

			case 'supportStatusVersion':
				$thismajor = preg_replace('/^(\d+)\..*$/', '$1', $this->tag_name);
				$changedin = $this;
				foreach ($this->component->versions as $version) {
					$thatmajor = preg_replace('/^(\d+)\..*$/', '$1', $version->tag_name);
					if ($thatmajor == $thismajor and version_compare($version->tag_name, $changedin->tag_name) === 1 and $version->support_status !== $changedin->support_status) {
						$changedin = $version;
					}
				}
				$this->data['supportStatusVersion'] = $changedin;
			break;

			case 'dependencies':
				$this->data['dependencies'] = self::$app->db_read->queryAllRows('SELECT components.id, componentdependencies.child_component_name, componentdependencies.child_component_target FROM componentdependencies LEFT OUTER JOIN (components INNER JOIN componentversions ON components.id = componentversions.component_id) ON componentdependencies.child_component_name = components.module_name WHERE componentdependencies.parent_version_id = ' . $this->id . ' GROUP BY componentdependencies.child_component_name ORDER BY componentdependencies.child_component_name ASC');
			break;

			case 'dependents':
				$this->data['dependents'] = self::$app->db_read->queryAllRows('SELECT DISTINCT components.id, components.module_name FROM componentdependencies INNER JOIN (componentversions INNER JOIN components ON componentversions.component_id = components.id) ON componentdependencies.parent_version_id = componentversions.id WHERE componentdependencies.child_component_name = %s ORDER BY components.module_name ASC', $this->component->module_name);
			break;

			case 'demos':
				// Backwards compat: keep 'path' for use in older demos that are
				// not compatible with demo endpoint
				$this->data['demos'] = self::$app->db_read->queryAllRows('SELECT title, name, path, description, hidden, display_html FROM demos WHERE componentversion_id=%d', $this->id);
			break;
		}
		return parent::__get($propertyName);
	}

	public function isStable() {
		return preg_match('/^\d+(\.\d+)+$/', $this->tag_name);
	}


	/**
	 * Insert the current object as a new row in the database
	 *
	 * @param MySqlConnection $dbwrite A write-capable connection to the master database
	 * @return mixed Either the datbase ID (unsigned integer) of the newly-inserted row; or FALSE, if an error occurred
	 */
	public function save() {
		self::$app->logger->debug('Saving component version in DB', array('module_name'=>$this->component->module_name, 'tag_name'=>$this->tag_name));
		$this->data['is_valid'] = isset($this->data['is_valid']) ? (int)$this->data['is_valid'] : null;
		$this->data['has_css'] = isset($this->data['has_css']) ? (int)$this->data['has_css'] : null;
		$this->data['has_js'] = isset($this->data['has_js']) ? (int)$this->data['has_js'] : null;

		self::$app->db_write->query('INSERT INTO componentversions SET {component_id}, {tag_name}, {is_valid}, {description}, {origami_type}, {origami_version}, {support}, {service_url}, {readme_gfm}, {support_status}, {ci_status}, {has_js}, {has_css}, {bundlesize_js}, {bundlesize_css}, {image_list}, {design_guidelines}, {datetime_created|date}, datetime_last_cached=NOW() ON DUPLICATE KEY UPDATE {is_valid}, {description}, {origami_type}, {origami_version}, {support}, {service_url}, {readme_gfm}, {support_status}, {ci_status}, {has_js}, {has_css}, {bundlesize_js}, {bundlesize_css}, {image_list}, {design_guidelines}, {datetime_created|date}, datetime_last_cached=NOW()', $this->data);
		if (!$this->id) {
			$this->id = self::$app->db_write->querySingle('SELECT id FROM componentversions WHERE {component_id} AND {tag_name}', $this->data);
		}
		if ($this->dependencies) {
			foreach ($this->dependencies as $dep) {
				self::$app->db_write->query('REPLACE INTO componentdependencies SET parent_version_id=%d, child_component_name=%s, child_component_target=%s', $this->id, $dep['child_component_name'], $dep['child_component_target']);
			}
		}
		if ($this->demos) {
			foreach ($this->demos as $demo) {
				self::$app->db_write->query('REPLACE INTO demos SET componentversion_id=%d, title=%s, name=%s, path=%s, description=%s, hidden=%d, display_html=%d', $this->id, $demo['title'], $demo['name'], $demo['path'], $demo['description'], (integer)$demo['hidden'], (integer)$demo['display_html']);
			}
		}
	}


	/**
	 * Query the Origami build service's metadata endpoint
	 *
	 * @return mixed Either the build service's response JSON object (decoded as a stdClass object); or FALSE, if an error occurred
	 */
	public function build() {

		$name = rawurlencode($this->component->module_name);
		$url = 'https://' . getenv('BUILD_SERVICE_HOST') . '/v2/modules/' . $name . '@' . $this->tag_name;
		self::$app->logger->debug('Querying build service', array(
			'url' => $url,
		));

		$request = new HTTPRequest($url);
		$request->setTimeLimit(120);
		$request->setMaxRetries(2);
		$request->setRetryInterval(5);

		// Send the request
		try {
			$response = $request->send();
		} catch (Exception $e) {
			self::$app->logger->error('HTTP failure querying build service', $e->getMessage());
			return false;
		}

		// A 200 status code implies that the request was successful, but the response may still indicate an error
		if ($response->getResponseStatusCode() == 200) {

			// Check if the response is well-formed JSON
			if (($responseJson = json_decode($response->getBody())) === null) {
				self::$app->logger->notice('Unable to decode build service response', array(
					'component' => $name,
					'json_error' => json_last_error(),
					'cli' => $request->getCliEquiv(),
					'response' => $response->getBody(),
				));
				return false;

			}

			// Check for reported build service errors
			foreach ($responseJson->build as $buildtype => $info) {
				if (!$info->valid) {

					// Distinguish between temporary and permanent errors (Build service probably ought to do this better)
					if ($buildtype == 'bundler' and isset($info->code) and $info->code === 'ENOSPC') {
						throw new \Exception('Build service out of disk space');
					} elseif ($buildtype == 'bundler' and isset($info->code) and $info->code === 'ENORESTARGET') {
						throw new \Exception('Tag not found');
					} elseif ($buildtype == 'bundler' and isset($info->code) and $info->code === 'EMISSINGORIGAMICONFIG') {
						// Ignore this, it just means the repo is not an Origami component
					} elseif ($buildtype == 'bundler' and isset($info->code) and $info->code === 'ENOTFOUND') {
						$this->readme_gfm = "Error occured during build:\n\n" . $info->error;
					} elseif ($buildtype == 'bundler' and isset($info->code) and $info->code === 'ECONFLICT') {
						$this->readme_gfm = "Conflict occured during build:\n\n" . $info->error;
					} elseif ($buildtype == 'css' and $info->valid == false) {
						$this->readme_gfm = "CSS build failed: " . $info->error;
					} elseif ($buildtype == 'js' and $info->valid == false) {
						$this->readme_gfm = "JavaScript build failed: " . $info->error;
					} else {
						self::$app->logger->notice('Build service reported unrecognised error', array(
							'component' => $name,
							'buildtype' => $buildtype,
							'cli' => $request->getCliEquiv(),
							'info' => json_encode($info),
						));
					}
				}
			}

			$this->is_valid = !empty($responseJson->build->origami->valid);

			if (isset($responseJson->bowerManifest) and isset($responseJson->bowerManifest->dependencies)) {
				$this->data['dependencies'] = array();
				foreach ($responseJson->bowerManifest->dependencies as $name => $target) {
					$this->data['dependencies'][] = array(
						'child_component_name' => $name,
						'child_component_target' => $target
					);
				}
			}

			if (isset($responseJson->build->js->bundleSize)) {
				$this->bundlesize_js = $responseJson->build->js->bundleSize;
			}
			if (isset($responseJson->build->css->bundleSize)) {
				$this->bundlesize_css = $responseJson->build->css->bundleSize;
			}

			if (isset($responseJson->origamiManifest)) {

				// Backwards compat
				if (isset($responseJson->origamiManifest->supportStatus) and strtolower($responseJson->origamiManifest->supportStatus) == 'not implemented') {
					$responseJson->origamiManifest->supportStatus = 'experimental';
				}

				foreach (array(
					'description' => 'description',
					'keywords' => 'keywords',
					'origamiType' => 'origami_type',
					'origamiCategory' => 'origami_category',
					'origamiVersion' => 'origami_version',
					'support' => 'support',
					'supportStatus' => 'support_status',
				) as $responseField => $propertyName) {
					if (isset($responseJson->origamiManifest->$responseField)) {
						$this->$propertyName = $responseJson->origamiManifest->$responseField;
					}
				}
				if ($this->origami_type == 'service' and isset($responseJson->origamiManifest->serviceUrl)) {
					$this->service_url = $responseJson->origamiManifest->serviceUrl;
				}
				if (isset($responseJson->readme)) {
					$readme = $responseJson->readme;

					// Remove Build badges (twice to remove images inside links)
					$readme = preg_replace('/\!?\[[^\[\]]+\]\(https?:\/\/travis-ci.org\/[^\)]+\)/i', '', $readme);
					$readme = preg_replace('/\!?\[[^\[\]]+\]\(https?:\/\/travis-ci.org\/[^\)]+\)/i', '', $readme);

					$this->readme_gfm = $readme;
				}

				if (isset($responseJson->designGuidelines) && $responseJson->designGuidelines !== false) {
					$this->data['design_guidelines'] = $responseJson->designGuidelines;
				} else {
					$this->data['design_guidelines'] = null;
				}

				$this->data['demos'] = array();

				if (isset($responseJson->origamiManifest->demosDefaults)) {
					$demodefaults = (array)$responseJson->origamiManifest->demosDefaults;
				} else {
					$demodefaults = array();
				}

				if (isset($responseJson->origamiManifest->demos)) {
					foreach ($responseJson->origamiManifest->demos as $demo) {
						if (!is_object($demo)) {
							$demo = array_merge($demodefaults, array('name' => $demo));
						} else {
							$demo = array_merge($demodefaults, (array)$demo);
						}

						if (empty($demo['hidden'])) {
							$demo['hidden'] = false;
						}

						if (!isset($demo['display_html'])) {
							$demo['display_html'] = true;
						}

						if (empty($demo['name']) and !empty($demo['path'])) {
							$demo['name'] = basename($demo['path'], '.html');
						} else if (empty($demo['name']) and !empty($demo['title'])) {
							$demo['name'] = $demo['title'];
						}

						if (empty($demo['title']) and !empty($demo['name'])) {
							$demo['title'] = $demo['name'];
						}

						if (empty($demo['description'])) {
							$demo['description'] = null;
						}

						$this->data['demos'][] = $demo;
					}
				}
				// Check CI - if not specified, and repo is GH, try Travis
				if (empty($responseJson->origamiManifest->ci) and preg_match('/github\.com\/([^\/]+)\/([^\/]+)\.git/i', $this->component->git_repo_url, $m)) {
					$responseJson->origamiManifest->ci = (object)array(
						'travis' => 'https://api.travis-ci.org/repos/'.$m[1].'/'.$m[2]
					);
				}
				if (isset($responseJson->origamiManifest->ci->travis)) {
					$url = $responseJson->origamiManifest->ci->travis.'/branches/'.$this->tag_name;
					$trvreq = new HTTPRequest($url);
					try {
						$resp = $trvreq->send();
						$data = $resp->getData('json');
						if (isset($data['branch']['state'])) {
							$this->ci_status = $data['branch']['state'] == 'passed' ? 'pass' : 'fail';
						}
					} catch (\Exception $e) {
						self::$app->logger->notice('Failed to load build data from Travis', array(
							'url' => $url,
							'err' => $e->getMessage()
						));
					}
				}
				if (isset($responseJson->bowerManifest->main)) {
					$mains = $responseJson->bowerManifest->main;
					if (!is_array($mains)) $mains = array($mains);
					foreach ($mains as $main) {
						if (preg_match("/\.s?css$/", $main)) $this->data['has_css'] = true;
						if (preg_match("/\.js$/", $main)) $this->data['has_js'] = true;
					}
				}
			}

			self::$app->logger->info('Build complete', array(
				'component'=>$this->component->module_name,
				'keywords'=>$this->component->keywords,
				'version'=>$this->tag_name,
				'is_valid'=>$this->is_valid
			));

		} else {
			$logdata = array(
				'component' => $name,
				'status_code' => $response->getResponseStatusCode(),
			);
			if (!in_array($response->getResponseStatusCode(), array(404,403))) {
				$logdata['response'] = $response->getBody();
			}
			self::$app->logger->notice('Bad build service HTTP response', $logdata);
			return false;
		}
	}

	public function buildImageList() {
		$name = rawurlencode($this->component->module_name);
		$url = 'https://raw.githubusercontent.com/Financial-Times/' . $name . '/master/imageList.json';

		$request = new HTTPRequest($url);
		$request->setTimeLimit(120);
		$request->setMaxRetries(2);
		$request->setRetryInterval(5);

		self::$app->logger->notice('Query GitHub imageset json');

		// Send the request
		try {
			$response = $request->send();
		} catch (Exception $e) {
			self::$app->logger->error('HTTP failure querying GitHub raw', $e->getMessage());
			return false;
		}

		// A 200 status code implies that the request was successful, but the response may still indicate an error
		if ($response->getResponseStatusCode() == 200) {
			// Check if the response is well-formed JSON
			if (($responseJson = json_decode($response->getBody())) === null) {
				self::$app->logger->notice('Unable to decode GitHub raw response', array(
					'component' => $name,
					'json_error' => json_last_error(),
					'response' => $response->getBody(),
				));
				return false;
			}

			if (isset($responseJson)) {
				$oiu_url = 'https://raw.githubusercontent.com/Financial-Times/origami-imageset-uploader/master/imageset-map.json';

				$oiu_request = new HTTPRequest($oiu_url);
				$oiu_request->setTimeLimit(120);
				$oiu_request->setMaxRetries(2);
				$oiu_request->setRetryInterval(5);

				// Send the request
				try {
					$oiu_response = $oiu_request->send();
				} catch (Exception $e) {
					self::$app->logger->error('HTTP failure querying GitHub raw', $e->getMessage());
					return false;
				}

				if ($oiu_response->getResponseStatusCode() == 200) {
					$oui_json = json_decode($oiu_response->getBody());

					if (property_exists($oui_json, $name)) {
						$imageset_map_data = $oui_json->{$name};

						if ($name === 'fticons') {
							$scheme_version = '-v' . explode('.', $this->tag_name)[0];
							$imageset_map_data->scheme .= $scheme_version;
						}

						$responseJson->imageset_data = $imageset_map_data;
					}
				} else {
					$logdata = array(
						'component' => $name,
						'status_code' => $oiu_response->getResponseStatusCode(),
					);
					if (!in_array($oiu_response->getResponseStatusCode(), array(404,403))) {
						$logdata['response'] = $oiu_response->getBody();
					}
					self::$app->logger->notice('Bad imageset uploader HTTP response', $logdata);
				}

				$image_list = json_encode($responseJson);

				if ($image_list) {
					$this->image_list = $image_list;
				} else {
					$logdata = array(
						'component' => $name,
						'error' => 'unable to build image_list',
						'image_list' => $image_list,
					);
					self::$app->logger->error('Unable to build image_list', $logdata);
				}
			}
		}
	}


	public function delete() {
		self::$app->db_write->query('DELETE FROM demos WHERE componentversion_id=%d', $this->id);
		self::$app->db_write->query('DELETE FROM componentdependencies WHERE parent_version_id=%d', $this->id);
		self::$app->db_write->query('DELETE FROM componentversions WHERE id=%d', $this->id);
		$this->id = null;
	}


	/**
	 * Factory method for creating a new object from a cached database record
	 *
	 * @param array     $databaseRow      The array of key=>value fields
	 * @param Component &$parentComponent The Origami component of which this is a version
	 *
	 * @return ComponentVersion The newly-created instance of this class, with all properties set according to the database record's field values
	 */
	private static function _createFromDatabaseRow(array $databaseRow, Component &$parentComponent) {
		$ret = new self($parentComponent);
		$ret->edit($databaseRow);
		return $ret;
	}

	/**
	 * Factory method for creating a new object from a tag name and its parent Origami component
	 *
	 * @param Component &$parentComponent The Origami component of which this is a version
	 * @param string    $tag_name         The name of the Git tag which the new version should represent
	 * @return ComponentVersion The newly-created instance of this class, with mostly default properties
	 */
	public static function findOrCreate(Component &$parentComponent, $tag_name) {
		$row = self::$app->db_read->queryRow('SELECT *, TRIM(LEADING %s FROM tag_name) AS tag_name FROM componentversions WHERE component_id = %d AND tag_name=%s', 'v', $parentComponent->id, $tag_name);
		if ($row) {
			return self::_createFromDatabaseRow($row, $parentComponent);
		} else {
			$ret = new self($parentComponent);
			$ret->tag_name = $tag_name;
			return $ret;
		}
	}

	/**
	 * Wrapper around factory method for creating an array of new objects from the database cache
	 *
	 * @param MySqlConnection $dbread           A connection to a master or slave database, for reading
	 * @param Component       &$parentComponent The Origami component of which this is a version
	 *
	 * @return array Copy of all cached component versions as instances of ComponentVersion
	 */
	public static function findAllForComponent(Component &$parentComponent) {
		$versions = array();
		foreach (self::$app->db_read->queryAllRows('SELECT *, TRIM(LEADING %s FROM tag_name) AS tag_name FROM componentversions WHERE component_id = ' . $parentComponent->id, 'v') as $row) {
			$version = self::_createFromDatabaseRow($row, $parentComponent);
			$versions[$row['tag_name']] = $version;
		}
		uksort($versions, "version_compare");
		return $versions;
	}
}
