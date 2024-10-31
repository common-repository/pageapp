<?php
if (!class_exists('ValueCache')) {
class ValueCache {
	var $prefix;
	var $file;
	var $expiry;
	var $enable = true;

	public function __construct($file, $prefix = 'valuecache', $expiry = '1 HOUR') {
		$this->file = $file;
		$this->prefix = $prefix.'_';
		$this->expiry = $expiry;
		$this->create_table();
	}
	private function table() {
		global $wpdb;
		return $wpdb->prefix . $this->prefix . 'cache';
	}
	private function create_table() {
		if (is_admin()) {
			if (!function_exists('get_plugin_data')) {
				require_once(ABSPATH.'wp-admin/includes/plugin.php');
			}
			$plugin = get_plugin_data($this->file);
			$option = $this->prefix.'version';
			$version = get_option($option);
			$expiry = $this->expiry;
			if ($version != $plugin['Version']) {
				global $wpdb;
				$charset_collate = $wpdb->get_charset_collate();
				$table_name = $this->table();
				//Could be up to 1000 in 64 bit vesion of MySQL
				$sql = "CREATE TABLE $table_name (
					name VARCHAR(333) NOT NULL,
					expires datetime DEFAULT DATE_ADD(NOW(), INTERVAL $expiry)  NOT NULL,
					data MEDIUMTEXT NULL,
					PRIMARY KEY (name)
				) $charset_collate;";
				require_once(ABSPATH.'wp-admin/includes/upgrade.php');
				dbDelta($sql);
				update_option($option, $plugin['Version']);
			}
		}
	}
	public function clear() {
		if ($this->enable) {
			global $wpdb;
			$table = $this->table();
			$wpdb->query("DELETE FROM $table;");
		}
	}
	public function expire() {
		if ($this->enable) {
			//TODO: Should we add hook for expires? But maybe not, make the client do it because they can do it on relevant pages, rather than everyone
			global $wpdb;
			$table = $this->table();
			$wpdb->query("DELETE FROM $table WHERE expires < NOW();");
		}
	}
	public function delete($name) {
		if ($this->enable) {
			global $wpdb;
			$table = $this->table();
			$name = esc_sql($name);
			$wpdb->query("DELETE FROM $table WHERE name = '$name';");
		}
	}
	public function get($name) {
		if ($this->enable) {
			global $wpdb;
			$table = $this->table();
			$name = esc_sql($name);
			return $wpdb->get_var("SELECT data FROM $table WHERE name = '$name' AND expires > NOW();");
		}
	}
	public function put($name, $value, $expiry = null) {
		if ($this->enable) {
			global $wpdb;
			$table = $this->table();
			$expiry = $expiry ? $expiry : $this->expiry;
			$name = esc_sql($name);
			$value = esc_sql($value);
			$sql = "INSERT INTO $table (name, expires, data)
					VALUES ('$name', DATE_ADD(NOW(), INTERVAL $expiry), '$value')
					ON DUPLICATE KEY UPDATE data = '$value', expires = DATE_ADD(NOW(), INTERVAL $expiry)
			;";
			$wpdb->query($sql);
		}
		return $value;
	}
}
}