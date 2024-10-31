<?php
if (!class_exists('UtilsLib')) {
	class UtilsLib {
		public static function get($key, $value = null) {
			return self::array($_GET, $key, $value);
		}
		public static function post($key, $value = null) {
			return self::array($_POST, $key, $value);
		}
		public static function request($key, $value = null) {
			return self::array($_REQUEST, $key, $value);
		}
		public static function server($key, $value = null) {
			return self::array($_SERVER, $key, $value);
		}
		public static function array($array, $key, $value = null) {
			return $array && isset($array[$key]) ? $array[$key] : $value;
		}
		public static function prop($obj, $key, $value = null) {
			return $obj && property_exists($obj, $key) ? $obj->$key : $value;
		}
		public static function starts_with($haystack, $needle) {
			//https://stackoverflow.com/questions/834303/startswith-and-endswith-functions-in-php
			return substr_compare($haystack, $needle, 0, strlen($needle)) === 0 || $haystack == $needle;
		}
		public static function ends_with($haystack, $needle) {
			return substr_compare($haystack, $needle, -strlen($needle)) === 0 || $haystack == $needle;
		}
		public static function path() {
			return parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
		}
		public static function excel($timestamp) {
			return 25569 + ($timestamp / 86400);
		}
		public static function timestamp($exceldate) {
			return ($exceldate - 25569) * 86400;
		}
	}
}