<?php
/**
 * Re-fetch the component from the build service (all versions)
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All rights reserved]
 */

namespace Controllers;

use \Origami\Component;

final class Refresh extends BaseController {

	protected function validate() {
		if (!$this->component) return false;
	}

	public function post() {
		if (isset($this->routeargs['version']) and $this->version) {
			try {
				$this->version->build();
				$this->version->save();

				$allversions = $this->component->versions;
				$latest = end($allversions);
				if ($this->version == $latest and $this->component->is_origami !== $latest->is_valid) {
					$this->component->is_origami = $latest->is_valid;
					$this->component->save();
				}
				$ver = $this->version->tag_name;

			} catch (\Exception $e) {
				if ($e->getMessage() === 'Tag not found') {
					$this->app->metrics->increment($this->app->metrics_prefix . 'component.refresh.delete');
					$this->version->delete();
					$this->resp->setJSON("/components");
					return;
				}
			}
		} else {
			$this->component->discoverVersions();
			$ver = $this->component->latest_version->tag_name;
		}

		$this->app->metrics->increment($this->app->metrics_prefix . 'refresh.' . $this->component->module_name);
		$this->resp->setJSON("/components/".$this->component->module_name."@".$ver."?cb=".time());
	}
}
