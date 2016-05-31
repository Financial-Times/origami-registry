<?php

namespace Controllers\Errors;

class Error405 extends \Controllers\BaseController {

	public function all() {
		$allowlist = strtoupper(join(', ', $this->routeargs['allowedmethods']));

		$this->resp->setStatus(405);
		$this->resp->setHeader('Allow', $allowlist);

		$this->addViewData(array(
			'method' => $this->req->getMethod(),
			'allowedmethods' => $allowlist
		));
		$this->renderView('errors/405');
	}

}
