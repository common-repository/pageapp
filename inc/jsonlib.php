<?php
if (!class_exists('JsonLib')) {
class JsonLib {
	static $json = false;
	static $apikeys = null;
	static $path = null;

	function __construct($path, $apikey = null) {
		self::$apikeys = $apikey ? (is_array($apikey) ? $apikey : array($apikey)) : array();
		self::add_hooks($path);
	}
	/*
	* Json functions
	*/
	public static function add_hooks($path) {
		register_rest_route($path, 'api', array(
			'methods' => 'GET,POST',
			'callback' => array(static::class, 'json'),
			'args' => array()
		));
	}
	public static function is_json() {
		return self::$json;
	}
	public static function is_admin() {
		return current_user_can('manage_options');
	}
	public static function json() {
		try {
			self::$json = true;
			$method = self::assert_param('method');
			$classname = get_called_class();
			$reflection = new ReflectionClass($classname);
			$function = $reflection->getMethod($method);
			$json = new $classname;
			if ($function && !$function->isStatic()) {
				if ($function->isPrivate()) {
					if (is_user_logged_in() && self::is_admin()) {
						return $json->$method();
					} else {
						return self::error('No permissions');
					}
				} elseif ($function->isProtected()) {
					if (in_array(self::assert_param('apikey'), self::$apikeys)) {
						return $json->$method();
					} else {
						return self::error('Invalid API Key');
					}
				} elseif ($function->isPublic()) {
					return $json->$method();
				} else {
					return self::error('Unknown Method: '.$method);
				}
			} else {
				return self::error('Unknown Method: '.$method);
			}
		} catch (Exception $e) {
			return new WP_Error('error', __($e->getMessage(), 'pageapp'));
		}
	}
	public static function manual($method, $post = null) {
		$params = $params ? $params : array();
		$parts = explode('/?', $method);
		if (count($parts) > 1) {
			$params = explode('&', $parts[1]);
			foreach ($params as $param) {
				$param = explode('=', $param);
				$_GET[$param[0]] = $param[1];
				$_REQUEST[$param[0]] = $param[1];
			}
			$method = $parts[0];
		}
		if ($post) {
			foreach ($post as $key => $value) {
				$_POST[$key] = $value;
				$_REQUEST[$key] = $value;
			}
		}
		self::$json = true;
		$classname = get_called_class();
		$json = new $classname;
		$output = $json->$method();
		self::$json = false;
		return json_encode($output);
	}
	
	/*
		* Request functions
		*/
	public static function strip($value) {
		return strip_tags(str_replace('?>','',str_replace('<?php','',stripslashes($value))));
	}
	public static function get_param($key, $default = null) {
		$value = $_REQUEST[$key];
		return isset($value) ? self::strip($value) : $default;
	}
	public static function assert_param($key, $description = null) {
		$value = $_REQUEST[$key];
		if (isset($value) && $value != '') {
			return self::strip($value);
		} else {
			throw new Exception(($description?$description:$key).' is required.');
		}
	}
	public static function is_widget() {
		$result = $_REQUEST['format'] == 'widget' || self::is_iframe();
		return $result;
	}
	public static function is_iframe() {
		return $_REQUEST['format'] == 'iframe';
	}
	public static function is_csv() {
		return $_REQUEST['format'] == 'csv';
	}
	public static function is_redirect($url = null) {
		$redirect = $_REQUEST['format'] == 'redirect';
		if ($redirect && $url) {
			self::header('Location: '.$url);
			exit;
		} else {
			return $redirect;
		}
	}
	public static function requestunset($key) {
		unset($_REQUEST[$key]);
		unset($_POST[$key]);
		unset($_GET[$key]);
	}
	public static function error($message) {
		return new WP_Error('error', __($message, 'premiere'));
	}
	public static function output($json) {
		//TODO: Add JSONP?
		return $json;
	}
	public static function raw($raw) {
		//Not used with manual calls
		echo $raw;
		wp_die();
	}
	public static function header($value) {
		//Not used with manual calls
		header($value);
	}
	public static function csv($results, $file = 'results.csv') {
		self::header('Content-type: text/csv');
		self::header('Content-Disposition: attachment; filename="'.$file.'"');
		$out = fopen('php://output', 'w');
		foreach ($results as $row) {
			//$line = '';
			$line = array();
			foreach ($row as $key => $value) {
				//$line .= (($line == '') ? '' : ',') . '"' . str_replace('&amp;', '&', $value) . '"';
				$line[] = str_replace('&amp;', '&', $value);
			}
			fputcsv($out, $line);
			//echo $line . "\n";
		}
		fclose($out);
	}
}
}