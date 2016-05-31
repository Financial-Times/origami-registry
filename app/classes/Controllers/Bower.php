<?php
/**
 * List all available components
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All rights reserved]
 */

namespace Controllers;

use \Origami\Component;

class Bower extends BaseController {

	public function get() {

		$op = array();

		// Single result: if /packages/<name>, look up that component and return repo URL
		if (isset($this->routeargs['component'])) {
			$component = Component::find($this->routeargs['component']);
			if ($component) {
				$op['name'] = $component->module_name;
				$op['url'] = $component->git_repo_url;
				$this->app->metrics->increment($this->app->metrics_prefix . 'serve.bower.' . $component->module_name);
			} else {
				return $this->resp->setStatus(404);
			}

		// Multiple results
		} else {

			// if /packages/search/<name>, do a search for matching repos
			if (isset($this->routeargs['searchterm'])) {
				$sql = '{module_name|like} AND ';
				$data = array('module_name'=>$this->routeargs['searchterm']);
				$this->app->metrics->increment($this->app->metrics_prefix . 'serve.bower.search.' . $this->routeargs['searchterm']);

			// Otherwise, route assumed to be /packages - display full list
			} else {
				$sql = '';
				$data = array();
				$this->app->metrics->increment($this->app->metrics_prefix . 'serve.bower.packages');
			}
			$components = Component::findAll($sql.'(is_origami = TRUE)', $data);
			foreach ($components as $component) {
				$op[] = array('name' => $component->module_name, 'url' => $component->git_repo_url);
			}
		}
		$this->resp->setCacheTTL(60);
		$this->resp->setJSON($op);
	}

	public function post() {
		$this->resp->setStatus(501);
		$this->resp->setHeader('Content-Type', 'text/plain');
		$this->resp->setContent('The Origami registry does not accept submissions via Bower.  To publish a component to the Origami registry, just push it to a git server that the registry monitors.');
	}
}
