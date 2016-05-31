<?php
/**
 * Model base class
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All rights reserved]
 */


abstract class Model {

	protected static $app;
	protected $fields = array(), $data = array(), $datefields = array();

	protected function __construct() {
		foreach ($this->fields as $prop) $this->data[$prop] = null;
	}

	public function toArray() {
		return $this->data;
	}

	/**
	 * Magic getter, allows DB properties to be read
	 *
	 * @param string $propertyName Name of the property being accessed
	 * @return mixed
	 */
	public function __get($propertyName) {
		if (isset($this->data[$propertyName])) return $this->data[$propertyName];
		if (isset($this->$propertyName)) return $this->propertyName;
		return null;
	}

	/**
	 * Magic setter, allows DB properties to be edited
	 *
	 * @param string $k Key
	 * @param string $v Value
	 */
	public function __set($k, $v) {
		if (in_array($k, $this->fields)) $this->data[$k] = $v;
	}

	public function edit($a, $value=null) {
		if (is_array($a) and $value === null) {
			foreach ($a as $key=>$val) $this->edit($key, $val);
		} elseif (is_string($a)) {
			if (in_array($a, $this->datefields) and !($value instanceof DateTime)) {
				$value = new DateTime($value, new DateTimeZone('UTC'));
			}
			$this->data[$a] = $value;
		}
	}

	public static function useDI($di) {
		self::$app = $di;
	}

}
