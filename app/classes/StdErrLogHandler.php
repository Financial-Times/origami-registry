<?php
/**
 * Prints log to srderr
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All rights reserved]
 */

use \FTLabs\AbstractLogHandler;
use \FTLabs\LogFormatterInterface;
use \FTLabs\KeyValueLogFormatter;
use \FTLabs\ErrorLog;

class StdErrLogHandler extends AbstractLogHandler {

	function __construct(LogFormatterInterface $formatter = null) {
		$this->formatter = $formatter ? $formatter : new KeyValueLogFormatter();
	}

	public function reinitialise() {
		flush();
	}

	public function handleLogMessage($severity, $message, array $context, ErrorLog $e = NULL) {
		if ($e) {
			list(,$body) = $this->formatter->formattedErrorLog($e);
		} else {
			if (isset($context['eh:timestamp'])) {
				$timestamp = $context['eh:timestamp'];
				unset($context['eh:timestamp']);
			} else {
				$timestamp = null;
			}
			$body = $this->formatter->formattedLogMessage($severity, $message, $context, $timestamp);
		}
		file_put_contents("php://stderr", $body."\n");
	}
}
