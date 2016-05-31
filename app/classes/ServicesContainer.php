<?php

use FTLabs\MySqlConnection;
use \Psr\Log\LogLevel;

class ServicesContainer extends Pimple {

	function __construct() {

		parent::__construct();

		// Capture errors and exceptions and relay them to sentry along with log events
		$globallogger = \FTLabs\Logger::init();
		$errorhandlers = array(
			'stderr' => array(
				'handler' => new StdErrLogHandler(),
				'min_severity' => getenv('IS_DEV') ? LogLevel::DEBUG : LogLevel::INFO,
			),
			'session' => array(
				'handler' => new \FTLabs\SessionIdHackHandler(),
				'min_severity' => LogLevel::WARNING,
			)
		);

		if (isset($_SERVER['SENTRY_DSN'])) {
			$version = @file_get_contents($_SERVER['DOCUMENT_ROOT'].'/../appversion');
			if (!$version) $version = 'unknown';
			$errorhandlers['sentry'] = array(
				'handler' => new \FTLabs\SentryReportLogHandler(new \Raven_Client($_SERVER['SENTRY_DSN'], array(
					'release' => $version
				))),
				'min_severity' => LogLevel::ERROR,
			);
		}
		if (!empty($_SERVER['IS_DEV'])) {

			// This isn't ideal.  The default behaviour of Logger would switch template
			// based on whether process is delivering a page over HTTP. This hard codes
			// it to the HTML page.
			$formatter = new \FTLabs\HtmlLogFormatter('template_dev_html');
			$errorhandlers['stop'] = array(
				'handler'=> new \FTLabs\DisplayErrorLogHandler($formatter),
				'min_severity' => LogLevel::NOTICE,
			);
		}
		$globallogger->setLogHandlers($errorhandlers);


		$this['db_config'] = function($c) {
			$mysqlDatabaseUrl = getenv("DATABASE_URL");
			$parsedUrl = parse_url($mysqlDatabaseUrl);
			return array(
				MySqlConnection::PARAM_SERVER => $parsedUrl["host"],
				MySqlConnection::PARAM_DBNAME => substr($parsedUrl["path"], 1),
				MySqlConnection::PARAM_USERNAME => $parsedUrl["user"],
				MySqlConnection::PARAM_PASSWORD => $parsedUrl["pass"]
			);
		};

		$this['db_write'] = function($c) {
            $conn = new MySqlConnection($c->db_config);
			$conn->setMaxConnectionFailures(10);
			return $conn;
		};

		$this['db_read'] = function($c) {
			$conn = new MySqlConnection($c->db_config);
			$conn->setMaxConnectionFailures(10);
			return $conn;
		};

		$this['view'] = function($c) {
			$loader = new Twig_Loader_Filesystem(realpath(__DIR__.'/../../app/views'));
			$twig = new Twig_Environment($loader, array(
				'cache' => realpath(__DIR__.'/../../'.getenv('VIEW_CACHE_PATH')),
				'debug' => true
			));
			$twig->addFilter(new Twig_SimpleFilter('slugify', function ($string) {
				return strtolower(str_replace(' ', '-', $string));
			}));
			$twig->addFilter(new Twig_SimpleFilter('unslugify', function ($string) {
				return ucfirst(str_replace('-', ' ', $string));
			}));
			$twig->addFilter(new Twig_SimpleFilter('markdown', function ($string) {
				return Parsedown::instance()->parse($string);
			}));
			$twig->addFilter(new Twig_SimpleFilter('timediff', function ($date, $precision=2) {
				$now = new DateTime();
				$interval = $now->diff($date);
				$datecomponents = array('y' => 'year', 'm' => 'month', 'd' => 'day', 'h' => 'hour', 'i' => 'minute', 's' => 'second');
				$output = array();
				$dateComponentCount = 0;
				foreach ($datecomponents as $dateComponentIdentifier => $dateComponent) {
					if ($interval->$dateComponentIdentifier > 0) {
						$output[] = $interval->$dateComponentIdentifier . ' ' . $dateComponent . ($interval->$dateComponentIdentifier == 1 ? '' : 's');
						if (++$dateComponentCount >= $precision) break;
					}
				}
				return implode(', ', $output);
			}));
			$twig->addFilter(new Twig_SimpleFilter('timepast', function ($date, $precision=2) {
				$now = new DateTime();
				$diff = $now->diff($date);
				$intervals = array(
					array(0, 'Yz', 'Today'),
					array('1D', 'Yz', 'Yesterday'),
					array(0, 'YW', 'Earlier this week'),
					array('1W', 'YW', 'Last week'),
					array(0, 'Ym', 'Earlier this month'),
					array('1M', 'Ym', 'Last month'),
					array(0, 'Y', '%m months ago'),
					array('1Y', 'Y', 'Last year'),
				);
				foreach ($intervals as $int) {
					$tmp = clone $now;
					if ($int[0]) $tmp->sub(new DateInterval('P'.$int[0]));
					if ($tmp->format($int[1]) == $date->format($int[1])) return $diff->format($int[2]);
				}
				return "Long ago";
			}));

			$twig->addFilter(new Twig_SimpleFilter('linkify', function($string) {
				$anchorMarkup = "<a href=\"%s\" target=\"_blank\" >%s</a>";
				$regexp_url = "/(?<linkstart><a.*?>)?(?<url>https?:\/\/(\w+\.)+\w+(\/[\w\-\%\.\?\=\&]+)*)(?<linkend><\/a.*?>)?/i";
				$regexp_email = "/(?<linkstart><a.*?>)?(?<addr>\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,4}\b)(?<linkend><\/a.*?>)?/i";

				preg_match_all($regexp_url, $string, $matches, \PREG_SET_ORDER);
				foreach ($matches as $match) {
					if (empty($match['linkstart']) && empty($match['linkend'])) {
						$replace = sprintf($anchorMarkup, $match['url'], $match['url']);
						$string = str_replace($match[0], $replace, $string);
					}
				}
				preg_match_all($regexp_email, $string, $matches, \PREG_SET_ORDER);
				foreach ($matches as $match) {
					if (empty($match['linkstart']) && empty($match['linkend'])) {
						$replace = sprintf($anchorMarkup, 'mailto:'.$match['addr'], $match['addr']);
						$string = str_replace($match[0], $replace, $string);
					}
				}

				return $string;
			}));

			$twig->addFilter(new Twig_SimpleFilter('bytes', function($num) {
				$kb = 1024;
				$mb = $kb * 1024;
				$gb = $mb * 1024;
				if ($num < (5*$kb)) return number_format($num)."b";
				elseif ($num < (2*$mb)) return number_format($num/$kb, 2)."KB";
				elseif ($num < (2*$gb)) return number_format($num/$mb, 2)."MB";
				else return number_format($num/$gb, 2)."GB";
			}));

			$twig->addGlobal('SERVER', $_SERVER);
			$twig->addExtension(new Twig_Extension_Debug());
			return $twig;
		};

		$this['logger'] = function($c) use ($globallogger) {
			return $globallogger;
		};

		$this['metrics'] = function() {
			if( getenv('GRAPHITE_HOST') ) {
				$collector = \Beberlei\Metrics\Factory::create('graphite', array(
					'host' => getenv('GRAPHITE_HOST'),
				));
			} else {
				$collector = \Beberlei\Metrics\Factory::create('null');
			}

			return $collector;
		};

		$this['metrics_prefix'] = function() {
			$prefix = 'origami.registry';

			$prefix.= getenv('IS_DEV') ? '.dev.' : '.prod.';

			return $prefix;
		};

	}
}
