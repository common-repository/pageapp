<?php

class RestLib {
	public $apikeys;
	public $cache = 60;
	public $active = false;
	public $prefix;
	public $route = 'wordpress';
	public $is_wordpress = false;

	/* Constructor */
	function __construct($apikeys = null, $cache = 60, $prefix = null, $route = 'wordpress') {
		$this->apikeys = $apikeys ? (is_array($apikeys) ? $apikeys : array($apikeys)) : array();
		$this->cache = $cache !== null ? $cache : $this->cache;
		$this->prefix = $prefix !== null ? $prefix : get_class($this);
		$this->route = $route !== null ? $route : $this->route;
	}

	/* Common methods */
	public function get_param($key, $default = null) {
		$value = $_REQUEST[$key];
		if (isset($value) && $value != null && $value != '') {
			return ($this->is_wordpress ? stripslashes($_REQUEST[$key]) : $_REQUEST[$key]);
		 } else {
			return $default;
		 } 
	}
	public function assert_param($key, $description = null) {
		$value = $this->get_param($key);
		if (isset($value) && $value != null && $value != '') {
			return $value;
		} else {
			throw new Exception(($description?$description:$key).' is required.');
		}
	}
	public function assert_apikey() {
		$apikey = $this->assert_param('_apikey');
		if (in_array($apikey, $this->apikeys)) {
			return $apikey;
		} else {
			throw new Exception('Missing Api Key');
		}
	}
	public function assert_login() {
		if ($this->loggedin()) {
			return true;
		} else {
			throw new Exception('Not logged in');
		}
	}
	public function error($e, $code = 1) {
		$this->output(array(
			'error' => $e->getMessage(),
			'code' => $code,
			'type' => get_class($e)
		));
	}
	public function header_sent($header) {
		$headers = headers_list();
		foreach ($headers as $header) {
			if (strpos($header, $header.': ') !== false) {
				return true;
			}
		}
		return false;
	}
	public function header($header, $value) {
		if (!$this->header_sent($header)) {
			header($header.': '.$value);
		}
	}
	public function cache_control() {
		if ($_SERVER['REQUEST_METHOD'] == 'GET' && !$this->header_sent('Cache-Control')) {
			//Check if header found so parent functions can set ant not override it
			$function = $this->function();
			if ($function) {
				//https://developers.cloudflare.com/cache/about/cache-control/
				if (!$function->isPrivate()) {
					$this->header('Cache-Control', 'public, max-age='.$this->cache);
				} else {
					$this->header('Cache-Control', 'no-store');
				}
			}
		}
	}
	public function result($result) {
		$this->cache_control();
		$json = array();
		if (is_array($result)) {
			if ($this->is_assoc($result)) {
				$json = $result;
			} else {
				$json = array('results' => $result);
			}
		} else {
			$json = array('result' => $result);
		}
		$this->output($json);
	}
	public function output($json) {
		//TODO: xml?
		//TODO: csv?
		$format = $this->get_param('_format', 'json');
		$callback = $this->get_param('callback');
		if ($callback) {
			$this->header('Content-Type' ,'text/javascript');
			echo $callback.'('.json_encode($json).');';
		} else {
			$this->header('Content-Type' ,'application/json');
			echo json_encode($json);
		}
		exit();
	}
	public function is_assoc($arr) {
		//array 
		if (array() === $arr) return false;
		return array_keys($arr) !== range(0, count($arr) - 1);
	}

	/* Override methods */
	public function loggedin() {
		if ($this->is_wordpress) {
			/*if (class_exists('WO_Server')) {
				if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
					$auth = $_SERVER['HTTP_AUTHORIZATION'];
					$bearer = 'Bearer ';
					if (strpos($auth, $bearer) === 0) {
						$auth = str_replace($bearer, '', $auth);
						//$oauth = WO_Server::instance();
						//$prop = ReflectionProperty::setAccessible($oauth, '');
						include_once dirname(WPOAUTH_FILE).'/library/WPOAuth2/Autoloader.php';
						WPOAuth2\Autoloader::register();
						$db = new WPOAuth2\Storage\Wordpressdb();
						$token = $db->getAccessToken($auth);
						if ($token != null) {
							$now = time();
							if ($token->expires > $now) {
								$user = get_user_by('id', $token->user_id);
								//return $user != null;
								return $user;
							}
						}
					}
				}
			}*/
			return is_user_logged_in(); //This works with oauth server
		} else {
			return false;
		}
	}

	public function function($method = null) {
		$method = $method ? $method : $this->get_param('_method');
		if ($method) {
			//$classname = get_called_class(); //Seems to work too
			$classname = get_class($this); //Current extantiated class
			$reflection = new ReflectionClass($classname);
			$parent = new ReflectionClass('RestLib');
			/* Only call non static methods of this class, not parent */
			if ($method == 'getMethods' || ($reflection->hasMethod($method) && !$parent->hasMethod($method))) {
				$function = $reflection->getMethod($method);
				if (!$function->isStatic() && !$function->isAbstract()) {
					return $function;
				}
			}
		}
	}

	public function method() {
		$method = $this->assert_param('_method');
		$unknown = 'Unknown Method: '.$method;
		if ($method == 'getMethods') {
			return $this->getMethods();
		} elseif ($method != '__construct') {
			$function = $this->function($method);
			if ($function) {
				if ($function->isPrivate()) {
					$apikey = $this->assert_login();
					//https://stackoverflow.com/questions/3475601/accessing-a-protected-member-variable-outside-a-class
					//$prop = ReflectionProperty::setAccessible($this, $method);
					//return $this->_call($method);
					$reflectionClass = new ReflectionClass($this);
					$reflectionMethod = $reflectionClass->getMethod($method);
					$reflectionMethod->setAccessible(true);
					return $reflectionMethod->invoke($this);
				} elseif ($function->isProtected()) {
					$apikey = $this->assert_apikey();
					return $this->$method();
				} elseif ($function->isPublic()) {
					return $this->$method();
				} else {
					throw new Exception($unknown);
				}
			} else {
				throw new Exception($unknown);
			}
		} else {
			throw new Exception($unknown);
		}
	}

	public function getMethods() {
		$classname = get_class($this); //Current extantiated class
		$reflection = new ReflectionClass($classname);
		$parent = new ReflectionClass('RestLib');
		$methods = $reflection->getMethods();
		$results = array();
		foreach ($methods as $function) {
			$name = $function->name;
			//Make sure parent doesn't have method so we can override helpers in child classes
			if ($name == 'getMethods' || (!$parent->hasMethod($name) && $function->getDeclaringClass()->name == $classname && $name != '__construct')) {
				if (!$function->isStatic() && !$function->isAbstract()) {
					$results[] = $name;
				}
			}
		}
		return $results;
	}

	/* General rest implementations */
	public function api() {
		$this->active = true;
		try {
			$this->result($this->method());
		} catch (Exception $e) {
			$this->error($e);
		}
		$this->active = false;
	}

	/* For wordpress implementations */
	public function wordpress() {
		$result = null;
		$this->active = true;
		try {
			$result = $this->method();
		} catch (Exception $e) {
			$result = new WP_Error('error', $e->getMessage());
		}
		$this->active = false;
		return $result;
	}

	public function wordpress_uri() {
		return '/'.$this->prefix.'/v1/api';
	}

	public function register_rest_route() {
		register_rest_route($this->prefix.'/v1', 'api', array(
			'methods' => WP_REST_Server::ALLMETHODS,
			'callback' => array($this, $this->route),
			'args' => array()
		));
	}
	public function rest_pre_dispatch($result, $server, $request) {
		if ($this->wordpress_uri() == $request->get_route()) {
			$request->set_method(isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']) ? $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] : $_SERVER['REQUEST_METHOD']);
		}
		return $result;
	}

	public function add_hooks() {
		$this->is_wordpress = true;
		//add_filter('rest_url_prefix', function () { return 'api'; }); //Doing in htaccess since it lets us have plain /api/
		add_action('rest_api_init', array($this, 'register_rest_route'));
		add_filter('rest_pre_dispatch', array($this, 'rest_pre_dispatch'), 10, 3);
	}
}