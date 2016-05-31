<?php
/**
 * Stash
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All rights reserved]
 */

namespace GitAPIClients;

class Stash extends GitAPIClient {

	protected $basepath = '/rest/api/1.0';
	protected $basequery = array('limit' => 1000);

	private $project, $repo;

	public function discoverRepos($host, $users=null) {
		$this->host = $host;
		$projdata = $this->_doRequest('/projects');
		$op = array();
		if (!empty($projdata['values'])) {
			foreach ($projdata['values'] as $proj) {
				$data = $this->_doRequest('/projects/' . rawurlencode($proj['key']) . '/repos');
				if (isset($data['values'])) {
					foreach ($data['values'] as $repo) {
						$op[] = array(
							"name" => $repo['slug'],
							"url" => $repo['cloneUrl']
						);
					}
				}
			}
		}
		return $op;
	}

	public function discoverVersions($repourl, $existing=array()) {
		if (parse_url($repourl, PHP_URL_PORT)) {
			$this->host = parse_url($repourl, PHP_URL_HOST).':'.parse_url($repourl, PHP_URL_PORT);
		} else {
			$this->host = parse_url($repourl, PHP_URL_HOST);
		}
		preg_match('/^(?:\/scm)?\/([^\/]+)\/([^\/]+?)(.git)?(\/.*)?$/', parse_url($repourl, PHP_URL_PATH), $m);
		$project = $m[1];
		$repo = $m[2];
		$op = array();
		$tagdata = $this->_doRequest('/projects/'.$project.'/repos/'.$repo.'/tags');
		if (isset($tagdata['values'])) {
			foreach($tagdata['values'] as $tag) {
				if (!preg_match('/^v?(\d+\.\d+\.\d+(\-(beta|rc)\.\d+)?)$/', $tag['displayId'], $m)) continue;
				if ($existing and in_array($m[1], $existing)) continue;
				$commit = $this->_doRequest('/projects/'.$project.'/repos/'.$repo.'/commits/'.$tag['latestChangeset']);
				if ($commit) {
					$op[$m[1]] = array(
						'tag_name' => $m[1],
						'datetime_created' => new \DateTime('@'.floor($commit['authorTimestamp']/1000))
					);
				}
			}
		}
		return $op;
	}

	public function getRecentCommitCount($repourl) {
		if (parse_url($repourl, PHP_URL_PORT)) {
			$this->host = parse_url($repourl, PHP_URL_HOST).':'.parse_url($repourl, PHP_URL_PORT);
		} else {
			$this->host = parse_url($repourl, PHP_URL_HOST);
		}
		preg_match('/^(?:\/scm)?\/([^\/]+)\/([^\/]+?)(.git)?(\/.*)?$/', parse_url($repourl, PHP_URL_PATH), $m);
		$project = $m[1];
		$repo = $m[2];
		$since = new \DateTime('30 days ago');
		$commitdata = $this->_doRequest('/projects/'.$project.'/repos/'.$repo.'/commits');
		$commitcount = 0;
		if (isset($commitdata['values'])) {
			foreach ($commitdata['values'] as $commit) {
				$commitdate = new \DateTime('@'.$commit['authorTimestamp']/1000);
				if ($commitdate < $since) break;
				$commitcount++;
			}
		}
		return $commitcount;
	}
}
