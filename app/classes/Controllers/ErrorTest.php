<?php
/**
 * Embed demo
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All rights reserved]
 */

namespace Controllers;

class ErrorTest extends BaseController {

	public function get() {

		throw new \Exception('Test exception');
	}
}
