<?php

namespace Controllers;
use \Origami\Component;

abstract class BaseController {

	protected $app, $req, $resp, $routeargs;
	protected $viewdata = array();

	protected $component, $version;

	final public function __construct($di, $req, $resp, $routeargs=array()) {
		$this->app = $di;
		$this->req = $req;
		$this->resp = $resp;
		$this->routeargs = $routeargs;
	}

	final public function dispatch($method) {

		if (isset($this->routeargs['component'])) {
			$this->component = Component::find($this->routeargs['component']);
		}

		if (isset($this->routeargs['version']) and isset($this->component->versions[$this->routeargs['version']])) {
			$this->version = $this->component->versions[$this->routeargs['version']];
		} elseif (!isset($this->routeargs['version']) and $this->component) {
			$this->version = $this->component->latest_stable_version;
		}

		if (method_exists($this, 'validate')) {
			if ($this->validate() === false) throw new \FTLabs\Routing\RouteRejectedException;
		}
		if (method_exists($this, $method)) {
			$this->$method();
		} elseif (method_exists($this, 'all')) {
			$this->all();
		}
	}




	protected function addViewData($a, $val=null) {
		if (is_scalar($a)) {
			$this->viewdata[$a] = $val;
		} else if (is_array($a) and $val === null) {
			$this->viewdata = array_merge($this->viewdata, $a);
		} else {
			throw new \Exception('Invalid function overloading');
		}
	}

	protected function renderView($templ) {
		$headers = $this->resp->getHeaders();
		if (!isset($headers['Content-Type'])) {
			$this->resp->setHeader('Content-Type', 'text/html; charset=UTF-8');
		}
		$this->resp->setContent(
			$this->app->view->render($templ.'.html', $this->viewdata)
		);
	}



	final public static function getSupportedMethods() {
		return get_class_methods(get_called_class());
	}
}
