<?php
/**
 * Repository discovery service
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All rights reserved]
 */

namespace Origami;
use \FTLabs\HTTPRequest;

final class RepositoryDiscovery {

	const NON_ORIGAMI_CHECK_INTERVAL_HOURS = 48;

	public static function search($data, $app) {

		$metrics = $app->metrics;
		$metrics_prefix = $app->metrics_prefix;
		$app->logger->info('Beginning Origami component discovery');

		// Discover repos
		foreach ($data->sources as $source) {
			$api_query_start = microtime(true);

			$className = '\\GitAPIClients\\' . $source->api->type;
			$users = isset($source->api->users) ? $source->api->users : null;
			$apiClient = new $className($app->logger);
			$foundcount = 0;

			foreach ($apiClient->discoverRepos($source->api->host, $users) as $repository) {

				// Use only the first occurrence of each module name, because module-sources is in priority order
				if (!isset($components[$repository['name']])) {
					$app->logger->debug('Discovered repo', $repository);
					$component = Component::findOrCreate($repository['name']);
					$component->git_repo_url = $repository['url'];
					$component->host_type = $source->api->type;
					$components[$repository['name']] = $component;
					$foundcount++;
					$metrics->increment($metrics_prefix . 'discovery.found');
				}
			}

			$api_query_diff = microtime(true) - $api_query_start;

			$metrics->timing($metrics_prefix . 'discovery. ' .$source->api->type. '.apiRequest', $api_query_diff);
			$app->logger->info('Searched '.$source->name.', found repositories: '.$foundcount);
		}

		// Discover versions and build status
		$app->logger->info('Finding component versions');
		$newlist = array();
		$ageboundary = (new \DateTime())->sub(new \DateInterval('PT'.self::NON_ORIGAMI_CHECK_INTERVAL_HOURS.'H'));
		foreach ($components as $component) {

			// Check new components and Origami components on every scan, everything else only occasionally
			if ($component->is_origami or !$component->id or $component->datetime_last_discovered < $ageboundary) {
				$component->datetime_last_discovered = new \DateTime();
				$component->save();
				$component->discoverVersions();
				$metrics->increment($metrics_prefix . 'discovery.origami');
				if ($component->latest_version and $component->latest_version->is_valid === null) {
					$component->buildVersions();
					if ($component->latest_version->is_valid) {
						$newlist[$component->module_name] = $component;
						$metrics->increment($metrics_prefix . 'discovery.new_component');
					}
				}
			}
		}

		// If new versions have been discovered, announce them in Slack
		if ($newlist && getenv("SLACK_WEBHOOK")) {
			$attachments = array();
			foreach ($newlist as $component) {
				$link = "http://registry.origami.ft.com/components/".$component->module_name."@".$component->latest_version->tag_name;
				$attachments[] = array(
					'fallback' => "  *".$component->module_name."* version `".$component->latest_version->tag_name."` ".$link,
					'title' => $component->module_name." ".$component->latest_version->tag_name,
					'title_link' => $link
				);
			}
			$room = getenv("SLACK_CHANNEL");
			$http = new HTTPRequest(getenv("SLACK_WEBHOOK"));
			$http->setMethod('POST');
			$http->setRequestBody(json_encode(array(
				'channel' => '#'.$room,
				'text' => "New releases available in the Origami registry",
				'attachments' => $attachments
			)));
			try {
				$app->logger->info('Notifying Slack with '.$http->getCliEquiv());
				$http->send();
			} catch(\Exception $e) {}
		}

		// Remove components that have not been seen for a while
		$app->db_write->query('SET foreign_key_checks = 0;');
		$app->db_write->query('DELETE c, cv, d, cd FROM components c LEFT JOIN componentversions cv ON c.id=cv.component_id LEFT JOIN demos d ON c.id=d.componentversion_id LEFT JOIN componentdependencies cd ON cv.id=cd.parent_version_id WHERE c.datetime_last_discovered < (NOW() - INTERVAL 3 DAY);');
		$app->db_write->query('SET foreign_key_checks = 1;');

		$app->logger->info('Scan complete');
	}
}

