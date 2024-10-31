<?php
/*
Plugin Name: PageApp
Plugin URI: https://wordpress.org/plugins/pageapp/
Description: Extensions to Wordpress wp-json for the PageApp API and mobile framework
Version: 1.4.3
Author: PageApp
Author URI: https://www.pageapp.com
License: © 2023 Thirteen32 Pty Ltd
*/
class PageApp {
	public static $name = self::class;
	public static $prefix = 'pageapp';
	public static $maxdefault = 100;
	public static $ValueCache = null;

	/* Require Functions */
	public static function require_cache() {
		require_once 'inc/cachelib.php';
	}
	public static function require_http() {
		require_once 'inc/httplib.php';
	}
	public static function require_json() {
		require_once 'inc/jsonlib.php';
	}
	public static function require_plugin() {
		require_once 'inc/pluginlib.php';
	}
	public static function require_rest() {
		require_once 'inc/restlib.php';
	}
	public static function require_settings() {
		require_once 'inc/settingslib.php';
	}
	public static function require_utils() {
		require_once 'inc/utilslib.php';
	}

	/* Helper Functions */
	public static function plugin() {
		return plugins_url(null,__FILE__);
	}

	/* Hooks */
	public static function add_hooks() {
		add_action('init', array(self::class, 'init'));
		add_action('rest_api_init', array(self::class, 'rest_api_init'), 20);
		add_action('plugins_loaded', array(static::class, 'plugins_loaded'));

		//Registration hooks
		//https://wordpress.stackexchange.com/questions/52302/is-it-possible-to-remove-username-field-from-the-registration-page-if-so-how
		add_action('login_head', array(static::class, 'login_head'));
		add_action('register_form', array(static::class, 'register_form'));
		add_action('registration_errors', array(static::class, 'registration_errors'), 11, 3);
		add_action('login_form_register', array(static::class, 'login_form_register'));
		add_action('user_register', array(static::class, 'user_register'));
		add_filter('register_url', array(static::class, 'redirect_to'));
		add_filter('login_url', array(static::class, 'redirect_to'), 10, 3);
		add_filter('lostpassword_redirect', array(static::class, 'lostpassword_redirect'));
		add_filter('rest_pre_dispatch', array(static::class, 'rest_pre_dispatch'), 10, 3);
	}
	public static function init() {
		self::require_settings();
		add_action('admin_init', array(self::class, 'admin_init'));
		add_action('admin_enqueue_scripts', array(self::class, 'include_cssjs'));
		
		$main = new SettingsLib(array(
			array('id'=>'pageapp_apioptions', 'type'=>'title', 'title'=>'WP JSON Meta'),
			array('id'=>'pageapp_relevanssi', 'type'=>'boolean', 'title'=>'Enable Relevanssi'),
			array('id'=>'pageapp_whitelist', 'type'=>'boolean', 'title'=>'Whitelist Post Meta'),
			array('id'=>'pageapp_addimages', 'type'=>'boolean', 'title'=>'Add Images to Posts'),
			array('id'=>'pageapp_categories', 'type'=>'boolean', 'title'=>'Include Category Details'),
			array('id'=>'pageapp_customposts', 'type'=>'boolean', 'title'=>'Include Custom Post Types'),
			array('id'=>'pageapp_apisettings', 'type'=>'title', 'title'=>'API Settings'),
			array('id'=>'pageapp_restkey', 'type'=>'boolean', 'title'=>'WP JSON Key', 'description'=>'Require apikey on WP JSON API'),
			array('id'=>'pageapp_apikey', 'type'=>'text', 'title'=>'API Keys', 'default'=>md5(wp_salt().time()), 'description'=>'One per line'),
			array('id'=>'pageapp_maxresults', 'type'=>'integer', 'title'=>'Max Results', 'default'=>100, 'description'=>'Maximum results returned over WP JSON API'),
			array('id'=>'pageapp_authentication', 'type'=>'boolean', 'title'=>'Enable Authentication API', 'description'=>'(Deprecated in favour of WP OAuth Server plugin)'),
			array('id'=>'pageapp_registration', 'type'=>'title', 'title'=>'User Registration'),
			array('id'=>'pageapp_username', 'type'=>'boolean', 'title'=>'Hide username field in registration form'),
			array('id'=>'pageapp_password', 'type'=>'boolean', 'title'=>'Enable password field in registration form'),
			array('id'=>'pageapp_login', 'type'=>'boolean', 'title'=>'Login and redirect after user registration'),
		), self::$name, self::$prefix, true, 'manage_options', plugin_dir_url(__FILE__).'/images/pageapp20.png', 3);
		
		add_action('admin_menu', array(self::class, 'admin_menu'));

		$genres = array(
			'action', 'adventure', 'animals', 'animated', 'anime', 'children', 'comedy', 'crime',
			'documentary', 'drama', 'educational', 'fantasy', 'faith', 'food', 'fashion', 'gaming',
			'health', 'history', 'horror', 'miniseries', 'mystery', 'nature', 'news', 'reality',
			'romance', 'science', 'science fiction', 'sitcom', 'special', 'sports', 'thriller', 'technology'
		);
		$vimeo = new SettingsLib(array(
			array('id'=>'pageapp_vimeo_name', 'type'=>'string', 'title'=>'Provider Name', 'description'=>'Roku provider name'),
			array('id'=>'pageapp_vimeo_genre', 'type'=>'select', 'title'=>'Genre', 'description'=>'Roku genre', 'values'=>$genres),
			array('id'=>'pageapp_vimeo_movies', 'type'=>'url', 'title'=>'Movies', 'description'=>'Movies Vimeo Roku json feed'),
			array('id'=>'pageapp_vimeo_short', 'type'=>'url', 'title'=>'Short Form', 'description'=>'Short Form Vimeo Roku json feed'),
			array('id'=>'pageapp_vimeo_specials', 'type'=>'url', 'title'=>'TV Specials', 'description'=>'TV Specials Vimeo Roku json feed'),
			array('id'=>'pageapp_vimeo_series', 'type'=>'text', 'title'=>'Series', 'description'=>'Series Vimeo Roku json feeds (one per line)')
		), 'Roku', self::$prefix);

		$firetv = new SettingsLib(array(
			array('id'=>'pageapp_firetv_name', 'type'=>'string', 'title'=>'Owner Name', 'description'=>'(Defaults to first item'),
			array('id'=>'pageapp_firetv_link', 'type'=>'string', 'title'=>'Owner Link', 'description'=>'(Defaults to first item'),
			array('id'=>'pageapp_firetv_description', 'type'=>'string', 'title'=>'Description', 'description'=>'(Defaults to first item'),
			array('id'=>'pageapp_firetv_editor', 'type'=>'string', 'title'=>'Editor', 'description'=>'(Defaults to first item'),
			array('id'=>'pageapp_firetv_image', 'type'=>'string', 'title'=>'Image', 'description'=>'(Defaults to first item'),
			array('id'=>'pageapp_firetv_feeds', 'type'=>'text', 'title'=>'MRSS Feeds', 'description'=>'Feeds to join (one per line)')
		), 'Fire TV', self::$prefix);
	}
	public static function rest_api_init() {
		if (get_option('pageapp_relevanssi') == '1') {
			add_filter('rest_dispatch_request', array(self::class, 'rest_dispatch_request'), 10, 4);
		}
		require_once 'pageapp-json.php';
		self::add_api_fields();
		self::register_meta();
		if (self::max_results() != self::$maxdefault) {
			$post_types = self::post_types();
			foreach ($post_types as $post_type) {
				add_filter('rest_'.$post_type.'_collection_params', array(self::class, 'rest_collection_params'), 99, 2);
				$taxonomies = get_object_taxonomies($post_type, 'objects');
				foreach ($taxonomies as $name => $tax) {
					add_filter('rest_'.$name.'_collection_params', array(self::class, 'rest_collection_params'), 99, 2);
				}
			}
		}
	}
	public static function plugins_loaded() {
		self::require_cache();
		self::$ValueCache = new ValueCache(__FILE__, 'pa');
	}
	public static function admin_init() {
		self::register_cssjs();
	}
	public static function register_cssjs() {
		wp_register_style('pageapp-admin-style', self::plugin().'/css/admin.css');
		wp_register_script('pageapp-admin-script', self::plugin().'/js/admin.js');
	}
	public static function include_cssjs() {
		if (isset($_GET['page']) && strpos($_GET['page'], self::$prefix) === 0) {
			wp_enqueue_script('jquery');
			wp_enqueue_script('jquery-ui-dialog');
			wp_enqueue_script('jquery-ui-sortable');
			//wp_enqueue_style('jquery-style', '//ajax.googleapis.com/ajax/libs/jqueryui/1.8.2/themes/smoothness/jquery-ui.css');
			wp_enqueue_style('pageapp-admin-style');
			wp_enqueue_script('pageapp-admin-script');
		}
	}

	/* Helper Functions */
	public static function sanitize_options($input) {
		return $input;
	}
	public static function api_keys() {
		$keys = get_option('pageapp_apikey');
		$parts = preg_split('/[\s,]+/', $keys);
		return array_filter($parts);
	}

	/* Registration hooks */
	public static function login_head() {
		?>
		<style>
			#registerform > p:first-child {
				display:none !important;
			}
		</style>
		<?php
		/*<script type="text/javascript">
		document.addEventListener('DOMContentLoaded', function() {
			var registerForm = document.getElementById('registerform');
			if (registerForm) {
				var firstParagraph = registerForm.querySelector('p:first-child');
				if (firstParagraph) {
					firstParagraph.style.display = 'none';
				}
			}
		});
		</script>*/
	}
	public static function register_form() {
		if (get_option('pageapp_password') == '1') {
		?>
		<p>
			<label for="password"><?php _e('Password'); ?><br />
				<input type="password" name="password" id="password" class="input" value="<?php echo esc_attr(wp_unslash($_POST['password'])); ?>" size="25" />
			</label>
		</p>
		<?php
		}
	}
	public static function registration_errors($errors, $sanitized_user_login, $user_email) {
		if (get_option('pageapp_password') == '1') {
			if (empty($_POST['password'])) {
				$errors->add('empty_password', __('Please enter a password.'));
			}
		}
		if (get_option('pageapp_username') == '1') {
			if(isset($errors->errors['empty_username'])){
				unset($errors->errors['empty_username']);
			}
			//Wordpress requires unique emails so we dont' need to check unique usernames
			if(isset($errors->errors['username_exists'])){
				unset($errors->errors['username_exists']);
			}
		}
		return $errors;
	}
	public static function login_form_register() {
		if (get_option('pageapp_username') == '1') {
			if(isset($_POST['user_login']) && isset($_POST['user_email']) && !empty($_POST['user_email'])){
				$_POST['user_login'] = $_POST['user_email'];
			}
		}
	}
	public static function user_register($user_id) {
		//Only run on built in registration form
		if (self::is_login_page() && get_option('pageapp_password') == '1') {
			if (!empty($_POST['password'])) {
				update_user_meta($user_id, 'password', $_POST['password']);
				if (get_option('pageapp_login') == '1') {
					self::login_user($user_id);
					add_filter('wp_redirect', array(static::class, 'wp_redirect'), 10, 2);
				}
			}
		}
	}
	public static function wp_redirect($location, $status) {
		return isset($_REQUEST['redirect_to']) && $_REQUEST['redirect_to'] != '' ? $_REQUEST['redirect_to'] : '/';
	}
	public static function redirect_to($url, $redirect = null, $force_reauth = null) {
		if (self::is_login_page() && strpos($url, 'redirect_to') === false && self::has_redirect() && get_option('pageapp_login') == '1') {
			$url = add_query_arg('redirect_to', urlencode($_REQUEST['redirect_to']), $url);
		}
		return $url;
	}
	public static function lostpassword_redirect($url) {
		if (self::is_lost_page() && self::has_redirect() && get_option('pageapp_login') == '1') {
			$url = wp_login_url(); //This auto adds the redirect_to because of above hook
		}
		return $url;
	}

	/* Registration helpers */
	public static function has_redirect() {
		return isset($_REQUEST['redirect_to']);
	}
	public static function is_lost_page() {
		return self::is_login_page() && strpos($_SERVER['REQUEST_URI'], 'action=lostpassword') !== false;
	}
	public static function is_login_page() {
		return strpos($_SERVER['REQUEST_URI'], 'wp-login.php') !== false;
	}
	public static function login_user($user_id) {
		if ($user_id) {
			$user = get_user_by('id', $user_id);
			if ($user) {
				//add_filter('auth_cookie_expiration', array(static::class, 'auth_cookie_expiration'), 10, 3);
				wp_set_current_user($user->ID, $user->user_login);
				wp_set_auth_cookie($user->ID);
				do_action('wp_login', $user->user_login);
			}
		}
	}

	/* Rest/WP-JSON Functions */
	public static function rest_collection_params($params, $post_type) {
		if (isset($params['per_page'])) {
			$params['per_page']['maximum'] = self::max_results();
		}
		return $params;
	}
	public static function post_types() {
		if (get_option('pageapp_customposts') == '1') {
			return array_keys(get_post_types(array('public' => true)));
		} else {
			return array('post', 'page'); //TODO: include anything else, authors, menus?
		}
	}
	public static function add_api_fields() {
		if (get_option('pageapp_addimages') == '1') {
			$post_types = self::post_types();
			foreach ($post_types as $post_type) {
				register_rest_field($post_type, 'featured_image_urls',
					array(
						'get_callback'    => array(self::class, 'post_images'),
						'update_callback' => null,
						'schema'          => null,
					)
				);
				$taxonomies = get_object_taxonomies($post_type, 'objects');
				foreach ($taxonomies as $name => $tax) {
					register_rest_field($name, 'featured_image_urls',
						array(
							'get_callback'    => array(self::class, 'category_images'),
							'update_callback' => null,
							'schema'          => null,
						)
					);
				}
			}
		}
		if (get_option('pageapp_categories') == '1') {
			$post_types = self::post_types();
			foreach ($post_types as $post_type) {
				register_rest_field($post_type, 'terms_details',
					array(
						'get_callback'    => array(self::class, 'terms'),
						'update_callback' => null,
						'schema'          => null,
					)
				);
			}
		}
	}
	public static function category_images($tax) {
		if (isset($tax['images']) && isset($tax['images']['thumbnail']) && strpos($tax['images']['thumbnail'], 'placeholder.png') === false) {
			return (array) $tax['images'];
		} else {
			$details = get_taxonomy($tax['taxonomy']);
			$args = array(
				'numberposts'      => 1,
				'orderby'          => 'menu_order',
				'order'            => 'ASC',
				'include'          => array(),
				'exclude'          => array(),
				'meta_key'         => '',
				'meta_value'       => '',
				'post_type'        => $details->object_type[0],
				'suppress_filters' => true,
				'tax_query' => array(
					array(
						'taxonomy' => $tax['taxonomy'],
						'field' => 'term_id', 
						'terms' => $tax['id'],
						'include_children' => true
					)
				  )
			);
			$posts = get_posts($args);
			if (count($posts) > 0) {
				return self::image_sizes($posts[0]->ID, $posts[0]->post_type);
			}
			return array();
		}
	}
	public static function terms($post) {
		$taxonomies = get_object_taxonomies($post['type'], 'objects');
		$results = array();
		foreach ($taxonomies as $name => $tax) {
			$rest = $tax->rest_base;
			$ids = $post[$rest];
			$row = array();
			if ($ids && count($ids) > 0) {
				foreach ($ids as $id)
				$row[] = get_term($id, $name);
			}
			$results[$rest] = $row;
		}
		return $results;
	}
	public static function post_images($post) {
		if (isset($post['images']) && isset($post['images']['thumbnail'])) {
			return (array) $post['images'];
		} else {
			return self::image_sizes($post['id'], $post['type']);
		}
	}
	public static function image_sizes($post_id, $post_type = null) {
		$featured_id = get_post_thumbnail_id($post_id);
		$sizes = wp_get_attachment_metadata($featured_id);
		$size_data = array();
		if (!empty($sizes['sizes'])) {
			foreach ($sizes['sizes'] as $key => $size) {
				$image_src = wp_get_attachment_image_src($featured_id, $key);
				if (!$image_src) {
					continue;
				}
				$size_data[$key] = $image_src[0];
			}
		}
		if (empty($size_data) && $post_type == 'rvs_video') {
			//Special condition for wpvs videos from wpvs-rest-api-functions.php
			$video_id = $post_id;
            $thumbnail_image = get_post_meta($video_id, 'rvs_thumbnail_image', true);
            if(has_post_thumbnail($video_id)) {
                $featured_id = get_post_thumbnail_id($video_id);
                $featured_image = wp_get_attachment_image_src($featured_id, 'vs-netflix-header', true)[0];
                if(empty($featured_image)) {
                    $featured_image = wp_get_attachment_image_src($featured_id, 'full', true)[0];
                }
            }
            if(empty($featured_image)) {
                $featured_image = get_post_meta($video_id, 'wpvs_featured_image', true);
            }
            $vimeo_thumbnails = array('thumbnail' => null, 'featured' => null);
            if( ! empty($thumbnail_image) ) {
                $vimeo_thumbnails['thumbnail'] = $thumbnail_image;
            }
            if( ! empty($featured_image) ) {
                $vimeo_thumbnails['featured'] = $featured_image;
            }
            $size_data = (array) $vimeo_thumbnails;
		}
		return $size_data;
	}
	public static function register_meta() {
		if (get_option('pageapp_whitelist') == '1') {
			$post_meta = json_decode(get_option('pageapp_postmeta'), true);
			if (is_array($post_meta)) {
				$post_types = self::post_types();
				foreach ($post_types as $type) {
					foreach ($post_meta as $key => $meta) {
						register_meta($type, $key, array(
							'type'         => $meta['type'],
							'description'  => $key,
							'single'       => $meta['single'],
							'show_in_rest' => $meta['restapi'],
						));
					}
				}
			}
		}
	}
	public static function register_options() {
		//Whitelist Meta
		register_setting(self::$prefix, 'pageapp_postmeta');
	}
	public static function max_results() {
		$value = (int) get_option('pageapp_maxresults', self::$maxdefault);
		return $value > 0 ? $value : self::$maxdefault;
	}
	public static function rest_pre_dispatch($result, $server, $request) {
		if (strpos($request->get_route(), '/wp/v2/') === 0 && get_option('pageapp_restkey') == '1') {
			if (!isset($_REQUEST['apikey']) || empty($_REQUEST['apikey'])) {
				return new WP_Error('missing_api_key', 'The apikey is missing from the request.', array('status' => 403));
			} else if (!in_array($_REQUEST['apikey'], self::api_keys())) {
				return new WP_Error('invalid_api_key', 'The apikey is invalid.', array('status' => 403));
			}
		}
		return $result;
	}

	/* Post Meta Admin Functions */
	public static function admin_menu() {
		add_submenu_page(self::$prefix, 'Post Meta', 'Post Meta', 'manage_options', self::$prefix.'-meta', array(self::class, 'post_meta'));
	}
	public static function get_post_meta() {
		global $wpdb;
		$prefix = $wpdb->prefix;
		$sql = "SELECT DISTINCT meta_key FROM {$prefix}postmeta
			WHERE SUBSTRING(meta_key,1,1) != '_' AND SUBSTRING(meta_key,1,6) != 'field_'
			ORDER BY meta_key ASC";
		return $wpdb->get_results($sql);
	}
	public static function meta_checkbox($key, $option, $param) {
		return '<input name="'.$key.'_'.$param.'" type="checkbox"'.($option[$key][$param]?' checked="checked"':'').' />';
	}
	public static function meta_hidden($value) {
		return '<input name="pageapp_metakey[]" type="hidden" value="'.$value.'" />';
	}
	public static function meta_select($key, $option, $param) {
		//https://developer.wordpress.org/reference/functions/register_meta/
		$types = array(
			'string' => 'String',
			'integer' => 'Integer',
			'number' => 'Number',
			'boolean' => 'Boolean',
			'array' => 'Array',
			'object' => 'Object'
		);
		$result = '<select name="'.$key.'_'.$param.'">';
		foreach ($types as $type => $name) {
			$result .= '<option value="'.$type.'"'.($option[$key]['type']==$type?' selected="selected"':'').'>'.$name.'</option>';
		}
		$result .= '</select>';
		return $result;
	}
	public static function post_meta() {
		echo '<h1>Post Meta</h1>';
		$nounce = 'pageapp_postmeta';
		if (SettingsLib::check_nounce($nounce)) {
			$keys = $_REQUEST['pageapp_metakey'];
			$save_meta = array();
			foreach ($keys as $key) {
				if (isset($_REQUEST[$key.'_restapi'])) {
					$save_meta[$key] = array(
						'restapi' => SettingsLib::checkbox_request($key.'_restapi'),
						'single' =>  SettingsLib::checkbox_request($key.'_single'),
						'type' => $_REQUEST[$key.'_type']
					);
				} 
			}
			update_option('pageapp_postmeta', json_encode($save_meta));
			SettingsLib::notice('Settings Saved.');
		}
		$post_meta = self::get_post_meta();
		?>
		<form method="post">
		<?php SettingsLib::echo_nounce($nounce); ?>
		<?php submit_button(); ?>
		<table id="pageapp_postmeta" class="widefat">
		<thead>
			<tr><td>Meta Key</td><td>Rest Api</td><td>Single</td><td>Type</td></tr>
		</thead>
		<tbody>
		<?php
		$option = json_decode(get_option('pageapp_postmeta'), true);
		$allmeta = array();
		foreach ($post_meta as $meta) {
			$allmeta[] = $meta->meta_key;
		}
		//Merge in missing from options in case last post with this meta is deleted, so that we at least save these settings
		if (is_array($option)) {
			foreach ($option as $key => $meta) {
				if (!in_array($key, $allmeta)) {
					$allmeta[] = $key;
				}
			}
		}

		foreach($allmeta as $meta) {
			echo "<tr>
				<td>$meta".self::meta_hidden($meta)."</td>
				<td>".self::meta_checkbox($meta, $option, 'restapi')."</td>
				<td>".self::meta_checkbox($meta, $option, 'single')."</td>
				<td>".self::meta_select($meta, $option, 'type')."</td>
			</tr>";
		}
		?>
		</tbody></table>
		<?php submit_button(); ?>
		</form>
		<?php
	}

	/* Relevanssi Functions */
	public static function relevanssi_installed() {
		return function_exists('relevanssi_do_query');
	}
	public static function rest_dispatch_request($dispatch_result, $request, $route, $handler) {
		$parameters = $request->get_query_params();
		if (strpos($request->get_route(),'/wp/v2') === 0
			&& isset($parameters['search']) && $parameters['search'] != '' && self::relevanssi_installed()) {
			return self::relevanssi_request($request);
		} else {
			return $dispatch_result;
		}
	}
	public static function post_type() {
		$fix = str_replace('/?', '?', $_SERVER['REQUEST_URI']);
		$info = pathinfo($fix);
		$path = explode('?', $info['basename']);
		$path = explode('/', $path[0]);
		$post_type = $path[0];
		if ($post_type == 'posts') {
			return 'post';
		} elseif ($post_type == 'pages') {
			return 'page';
		} else {
			return $post_type;
		}
	}
	public static function relevanssi_request($request) {
		$parameters = $request->get_query_params();
		$args = array(
			'posts_per_page' => $parameters['per_page'] ? intval($parameters['per_page']) : 10,
			'paged' => $parameters['page'] ? intval($parameters['page']) : 0,
			'post_type' => self::post_type(),
			's' => $parameters['search'],
			'cat' => implode(',', $parameters['categories'])
		);
		
		// Run search query
		$search_query = new WP_Query( $args );
		relevanssi_do_query($search_query);
		
		// Create controller to access posts via REST API
		$ctrl = new WP_REST_Posts_Controller($args['post_type']);
		
		// Collect results and preare response
		$posts = array();
		while ($search_query->have_posts()) {
			$search_query->the_post();
			$data = $ctrl->prepare_item_for_response($search_query->post, $request);
			$posts[] = $ctrl->prepare_response_for_collection($data);
		}
		
		$resp = new WP_REST_Response($posts, 200);
		$resp->set_headers(["Access-Control-Allow-Headers"=>"Authorization, Content-Type", "Access-Control-Expose-Headers"=>"X-WP-Total, X-WP-TotalPages, X-WP-Type", "X-WP-Total"=>$search_query->found_posts, "X-WP-TotalPages"=>$search_query->max_num_pages, "X-WP-Type"=>$args['post_type']]);
		//400
		//rest_post_invalid_page_number
		//The page number requested is larger than the number of pages available.
		return $resp;
	}

	/* Cache Functions */
	public static function cache_xml($url) {
		self::require_http();
		$url = trim($url);
		$result = self::$ValueCache->get($url);
		if ($result) {
			return new SimpleXmlElement(trim($result));
		} else {
			$result = trim(get_url($url));
			if (strpos($result, '<?xml') !== false) {
				$rand = rand(24, 48);
				self::$ValueCache->put($url, $result, $rand.' HOUR');
				return new SimpleXmlElement($result);
			}
		}
	}
	public static function cache_json($url) {
		self::require_http();
		$url = trim($url);
		$result = self::$ValueCache->get($url);
		if ($result) {
			return json_decode($result, true);
		} else {
			$result = get_url($url);
			$json = json_decode($result, true);;
			if ($json !== null) {
				$rand = rand(24, 48);
				self::$ValueCache->put($url, $result, $rand.' HOUR');
				return $json;
			}
		}
	}
}
PageApp::add_hooks();