<?php
/**
 * Stash
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All rights reserved]
 */

namespace GitAPIClients;

use \Logger;
use \FTLabs\HTTPRequest;

abstract class GitAPIClient {

	protected $logger;
	protected $host;
	protected $scheme = 'http';
	protected $basepath = '';
	protected $basequery = array();
	protected $headers = array();
	protected $hostmap = array();

	public function __construct($logger) {
		$this->logger = $logger;
	}

	abstract public function discoverRepos($host, $users=null);

	abstract public function discoverVersions($repourl, $existing=array());

	protected function _doRequest($url, $query=array()) {

		// In order to be a good citizen of third party APIs, we pause for 500ms before making each request
		// (GitHub can apply automatic rate limits)
		usleep(500000);

		$query = array_merge($this->basequery, $query);
		$query['_cachebust'] = microtime(true);
		$host = isset($this->hostmap[$this->host]) ? $this->hostmap[$this->host] : $this->host;
		$url = $this->scheme . '://' . $host . $this->basepath . $url . '?' . http_build_query($query);
		$data = array();
		try {
			while ($url) {
				$req = new HTTPRequest($url);
				$req->setTimeLimit(10);
				$req->setMaxRetries(2);
				$req->setRetryInterval(5);
				foreach ($this->headers as $key=>$val) $req->setHeader($key, $val);
				$resp = $req->send();
				$this->logger->debug('Fetch: '.$url.' ('.$resp->getResponseTime().'s)');
				if ($resp->getResponseStatusCode() !== 200) {
					throw new \Exception('HTTP '.$resp->getResponseStatusCode().' from '+$this->host);
				}
				if ($resp->getData('json')) {
					$data = array_merge($data, $resp->getData('json'));
				}
				$url = (preg_match('/<([^>]+)>;\s*rel="next"/', $resp->getHeader('Link'), $m)) ? $m[1] : null;
			}
		} catch (\Exception $e) {
			$this->logger->notice('Unable to fetch JSON from git source', array(
				'msg' => $e->getMessage(),
			));
		}
		return $data;
	}
}
