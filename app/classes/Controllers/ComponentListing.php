<?php
/**
 * List all available components
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All rights reserved]
 */

namespace Controllers;

use \Origami\Component;

class ComponentListing extends BaseController {

	public function get() {

		$this->addViewData('components', Component::getOrigamiComponentsByCategory());
		$this->addViewData('title', 'Components');
		$this->addViewData('body_class', 'o-registry-page--component-listing');

		$cron_interval = 60*60*4;
		$this->addViewData('cron_last_complete', $this->app->db_read->querySingle("SELECT FROM_UNIXTIME(meta_value) FROM meta WHERE meta_key=%s", 'cron_last_complete'));

		$this->resp->setCacheTTL(300);

		$this->app->metrics->increment($this->app->metrics_prefix . 'serve.ComponentListing');
		$this->renderView('component-listing');
	}
}
