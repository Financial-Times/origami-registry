<?php
/**
 * ComponentDetail
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All rights reserved]
 */

namespace Controllers;

use \Origami\Component;

use vierbergenlars\SemVer\version;
use vierbergenlars\SemVer\expression;

class ComponentDetail extends BaseController {

	protected function validate() {
		if (!$this->component or !$this->version) return false;
	}

	public function get() {

		$component = $this->component->toArray();
		$thisversion = $this->version->toArray();

		// Set values for template placeholders
		$this->addViewData(array_merge($component, $thisversion));

		// Build a version list - only show one unstable version
		$versions = $this->component->versions;
		$versions = array_reverse($versions);
		$versionlist = array();
		$unstabledone = false;

		foreach ($versions as $version) {
			if ($unstabledone and !$version->isStable()) continue;
			$versionlist[] = array_merge($version->toArray(), array(
				'selected' => $version->tag_name == $this->version->tag_name,
			));
			if (!$version->isStable()) $unstabledone = true;
		}

		$this->addViewData('versions', $versionlist);

		// Check dependencies
		$dependencies = array();
		foreach($this->version->dependencies as $dep) {

			// Remove URLs that are part of the target - they cannot be parsed as valid semver expressions (and create noisy output)
			$dep['child_component_target'] = preg_replace('/^.*\#/', '', $dep['child_component_target']);
			if (!empty($dep['id'])) {
				try {
					$expr = new expression($dep['child_component_target']);
					$latest = Component::getbyID($dep['id'])->latest_stable_version;
					$latestver = new version($latest->tag_name);
					$dep['uptodate'] = $latestver->satisfies($expr);
					$dep['latest'] = $latestver;

				// Dependency has unparseable target, probably because it's not an Origami component
				} catch (\Exception $e) {}
			}
			$dependencies[] = $dep;
		}

		$this->addViewData('dependencies', $dependencies);

		$this->addViewData('demos', $this->version->demos);
		$this->addViewData('dependents', $this->version->dependents);

		$this->addViewData('title', $this->component->module_name);
		$this->addViewData('body_class', 'o-registry-page--component-detail');

		$this->addViewData('repo_home_url', str_replace('.git', '', $this->component->git_repo_url));
		$this->addViewData('latest_version', $this->component->latest_version->tag_name);
		$this->addViewData('latest_datetime_created', $this->component->latest_version->datetime_created);
		$this->addViewData('latest_stable_version', $this->component->latest_stable_version->tag_name);
		$this->addViewData('latest_stable_datetime_created', $this->component->latest_stable_version->datetime_created);
		$this->addViewData('is_stable', $this->version->isStable());
		$this->addViewData('support_status', $this->version->supportStatusVersion->support_status);
		$this->addViewData('support_status_version', $this->version->supportStatusVersion->tag_name);

		// Only show the latest version of modules to search engines
		if ($this->version->tag_name !== $this->component->latest_version->tag_name) {
			$this->addViewData('noindex', true);
		}

		// Count documentation types
		$docs_sections = 0;
		if ($this->version->readme_gfm) $docs_sections++;
		if ($this->version->has_js) $docs_sections++;
		if ($this->version->has_css) $docs_sections++;
		$this->addViewData('docs_sections', $docs_sections);

		if ($this->version->image_list) {
			$imageset_images = json_decode($this->version->image_list);
			$this->addViewData('imageset_scheme', $imageset_images->imageset_data->scheme);
			$this->addViewData('imageset_path', $imageset_images->imageset_data->pathToImages);
			// Remove the imageset_data property so the following foreach works
			// regardless of the property name of the images
			unset($imageset_images->imageset_data);

			foreach ($imageset_images as $imgset) {
				$this->addViewData('imageset_list', $imgset);
			}
		}

		// Certain older versions of o-colors cannot be demoed via the /demo endpoint
		if ($this->component->module_name === 'o-colors' && version_compare($this->version->tag_name, '3.3.0', '<=')) {
			$this->addViewData('force_old_demo_url', true);
		}

		// Render templates and return response
		$this->resp->setCacheTTL(isset($this->routeargs['version']) ? 3600 : 0);
		if ($this->routeargs['format'] === 'json') {
			$this->resp->setJSON($this->viewdata);
		} else {
			$this->addViewData('components', Component::getOrigamiComponentsByCategory());
			$this->app->metrics->increment($this->app->metrics_prefix . 'serve.ComponentDetail');
			$this->renderView('component-detail');
		}
	}
}
