<?php
if (!class_exists('PageAppPlugin')) {
	class PageAppPlugin {
		var $css;
		var $js;
		var $admincss;
		var $adminjs;
		var $version;
		var $plugin;

		function __construct() {
			$this->js = array();
			$this->css = array();
			$this->adminjs = array();
			$this->admincss = array();
			$this->add_hooks();
		}
		public function add_hooks() {
			add_action('wp_enqueue_scripts', array($this, 'include_cssjs'));
			add_action('admin_enqueue_scripts', array($this, 'include_admin_cssjs'));
		}
		public function include_cssjs() {
			foreach ($this->css as $css) {
				wp_enqueue_style($css);
			}
			foreach ($this->js as $js) {
				wp_enqueue_script($js);
			}
		}
		public function include_admin_cssjs() {
			foreach ($this->admincss as $css) {
				wp_enqueue_style($css);
			}
			foreach ($this->adminjs as $js) {
				wp_enqueue_script($js);
			}
		}
		public function plugin($file = null) {
			if ($this->plugin == null) {
				$file = $file ? $file : $this->parent();
				$this->plugin = plugins_url(null, $file);
			}
			return $this->plugin;
		}
		public function parent() {
			$trace = debug_backtrace();
			$class = static::class;
			foreach ($trace as $function) {
				if ($function['class'] != $class) {
					return $function['file'];
				}
			}
		}
		public function version($file = null) {
			if ($this->version == null) {
				$file = $file ? $file : $this->parent();
				if ($file) {
					$data = get_plugin_data($file);
					$this->version = $data['Version'];
				} else {
					$this->version = '';
				}
			}
			return $this->version;
		}
		public function register_js($name, $file, $version = null) {
			$version = $version ? $version : $this->version();
			$this->js[] = $name;
			wp_register_script($name, $file, array(), $version);
		}
		public function register_css($name, $file, $version = null) {
			$version = $version ? $version : $this->version();
			$this->css[] = $name;
			wp_register_style($name, $file, array(), $version);
		}
		public function register_admin_js($name, $file, $version = null) {
			$version = $version ? $version : $this->version();
			$this->adminjs[] = $name;
			wp_register_script($name, $file, array(), $version);
		}
		public function register_admin_css($name, $file, $version = null) {
			$version = $version ? $version : $this->version();
			$this->admincss[] = $name;
			wp_register_style($name, $file, array(), $version);
		}

		//Oauth Server
		var $grant_types; //e.g. array('refresh_token', 'password')
		var $access_token;
		var $refresh_token;
		public function oauth($grant_types = 'refresh_token', $access_token = 86400, $refresh_token = 2592000) {
			if (class_exists('WO_Server')) {
				$this->grant_types = is_array($grant_types) ? $grant_types : array($grant_types);
				$this->access_token = $access_token;
				$this->refresh_token = $refresh_token;
				add_filter('get_post_metadata', array($this, 'get_post_metadata'), 10, 5);
				add_filter('option_wo_options', array($this, 'option_wo_options'), 10, 2);
				//I think modifying wo_options is enough, but just incase, we may need to modify this in the future:
				//$oauth = WO_Server::instance();
				//$oauth->default_settings
			}
		}
		public function get_post_metadata($check, $object_id, $meta_key, $single, $meta_type) {
			if ($meta_key == 'grant_types') {
				$grant_types = array_merge(array('authorization_code', 'implicit'), $this->grant_types);
				return array($grant_types); //Always wrap as parent function handles $single
			}
			return $check;
		}
		public function option_wo_options($value, $option) {
			if (is_array($value)) {
				$value['access_token_lifetime'] = $this->access_token;
				$value['refresh_token_lifetime'] = $this->refresh_token;
			}
			return $value;
		}
	}
}