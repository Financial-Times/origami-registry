<?php
/**
 * Returns the JavaScript and CSS needed to embed registry demos in other websites
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All rights reserved]
 */

namespace Controllers;

class EmbedApi extends BaseController {

	public function get() {
		$this->resp->setCacheTTL(3600*24*7);
		if ($this->req->getPath() == '/embedapi.css') {
			$this->resp->setHeader('Content-Type', 'text/css; charset=UTF-8');
			$this->renderView('embedapi-css');
		} else {
			$this->resp->setHeader('Content-Type', 'text/javascript; charset=UTF-8');
			$this->addViewData('autoload', $this->req->getQuery('autoload'));
			$this->renderView('embedapi-js');
		}
	}

}
