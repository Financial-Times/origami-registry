<?php
/**
 * Embed demo
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All rights reserved]
 */

namespace Controllers;

use \Origami\Component;
use \OrigamiRegistry;
use \FTLabs\HTTPRequest;

class Embed extends BaseController {

	protected function validate() {
		if (!$this->component or !$this->version) return false;
	}

	public function get() {

		$this->addViewData($this->component->toArray());
		$this->addViewData($this->version->toArray());
		$this->addViewData('demoname', $this->routeargs['demoname']);
		$this->addViewData('repo_home_url', str_replace('.git', '', $this->component->git_repo_url));
		$this->addViewData('latest_version', $this->component->latest_version->tag_name);
		$this->addViewData('support_status', $this->version->supportStatusVersion->support_status);
		$this->addViewData('support_status_version', $this->version->supportStatusVersion->tag_name);

		$this->resp->setCacheTTL(3600);
		$this->renderView('page_embed_demo');
	}
}
