<?php
/**
 * GitHub
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All rights reserved]
 */

namespace GitAPIClients;

class GitHub extends GitAPIClient {

    protected $basequery = array(
		'per_page' => 100
	);

	protected $scheme = 'https';
	protected $hostmap = array(
		'github.com' => 'api.github.com'
	);

	protected $headers = array(
        'Accept' => 'application/vnd.github.v3+json',
	);

	private $owner, $repo;

    public function __construct($logger) {
        parent::__construct($logger);
        $this->headers['Authorization'] = 'Basic ' . base64_encode(getenv('GITHUB_CREDENTIALS'));
    }

	public function discoverRepos($host, $users=null) {
		$this->host = $host;
		if (!$users) {
			$users = array();
			foreach ($this->_doRequest('/users') as $user) {
				if ($user['type'] == 'Organization') {
					$users[] = $user['login'];
				}
			}
		}
		$repos = array();
		foreach ($users as $user) {
			foreach ($this->_doRequest('/orgs/'.rawurlencode($user).'/repos') as $repo) {
				$repos[] = array(
					'name'=>$repo['name'],
					'url'=>$repo['clone_url']
				);
			}
		}
		return $repos;
	}

	public function discoverVersions($repourl, $existing=array()) {
		$this->host = parse_url($repourl, PHP_URL_HOST);
		preg_match('/^\/([^\/]+)\/([^\/]+?)(.git)?(\/.*)?$/', parse_url($repourl, PHP_URL_PATH), $m);
		$owner = $m[1];
		$repo = $m[2];
		$op = array();
		foreach($this->_doRequest('/repos/'.$owner.'/'.$repo.'/tags') as $tag) {
			if (!preg_match('/^v?(\d+\.\d+\.\d+(\-(beta|rc)\.\d+)?)$/', $tag['name'], $m)) continue;
			if ($existing and in_array($m[1], $existing)) continue;
			$commit = $this->_doRequest('/repos/'.$owner.'/'.$repo.'/git/commits/'.$tag['commit']['sha']);
			if ($commit) {
				$op[$m[1]] = array(
					'tag_name' => $m[1],
					'datetime_created' => new \DateTime($commit['committer']['date'])
				);
			}
		}
		return $op;
	}

	public function getRecentCommitCount($repourl) {
		$this->host = parse_url($repourl, PHP_URL_HOST);
		preg_match('/^\/([^\/]+)\/([^\/]+?)(.git)?(\/.*)?$/', parse_url($repourl, PHP_URL_PATH), $m);
		$owner = $m[1];
		$repo = $m[2];
		$since = new \DateTime('30 days ago');
		$commits = $this->_doRequest('/repos/'.$owner.'/'.$repo.'/commits', array('since'=>$since->format('c')));
		return count($commits);
	}

}
