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

		$viewdata = array(
				'primitives' => array(
					'title' => 'Primitives',
					'modules' => array(),
				),
				'component' => array(
					'title' => 'Components',
					'modules' => array(),
				),
				'layouts' => array(
					'title' => 'Layouts',
					'modules' => array(),
				),
				'utilities' => array(
					'title' => 'Utilities',
					'modules' => array(),
				),
			);

		foreach (Component::findAll('c.is_origami IS TRUE') as $component) {
			if ($component->latest_stable_version) {
				$viewdata[$component->origami_group]['modules'][] = array_merge(
					$component->toArray(),
					$component->latest_stable_version->toArray()
				);
			} elseif($component->latest_version) {
				$viewdata[$component->origami_group]['modules'][] = array_merge(
					$component->toArray(),
					$component->latest_version->toArray()
				);
			}
		}

		$this->addViewData('components', $viewdata);
		$this->addViewData('title', 'Components');
		$this->addViewData('body_class', 'o-registry-page--component-listing');

		$cron_interval = 60*60*4;
		$this->addViewData('cron_last_complete', $this->app->db_read->querySingle("SELECT FROM_UNIXTIME(meta_value) FROM meta WHERE meta_key=%s", 'cron_last_complete'));

		$this->resp->setCacheTTL(300);

		$this->app->metrics->increment($this->app->metrics_prefix . 'serve.ComponentListing');
		$this->renderView('component_listing');
	}
}
