<?php
require_once 'inc/jsonlib.php';
class PageAppJson extends JsonLib {
	function __construct() {
		parent::__construct('pageapp/v1', PageApp::api_keys());
	}
	
	/* Helper Functions */
	private static $cookie = null;
	private static function filter_user($user, $cookie = null) {
		$result = array(
			'ID' => $user->ID,
			'user_login' => $user->user_login,
			'user_email' => $user->user_email,
			'display_name' => $user->display_name,
			'cookie' => $cookie
		);
		return apply_filters('pageapp_user', $result);
	}
	public static function set_logged_in_cookie($logged_in_cookie, $expire, $expiration, $user_id, $scheme, $token) {
		self::$cookie = $logged_in_cookie;
	}
	public static function auth_cookie_expiration($length, $user_id, $remember) {
		$length = apply_filters('pageapp_cookie_expiration', $length, $user_id, $remember);
		if ($length != $length) {
			return $length;
		} else {
			return $remember ? YEAR_IN_SECONDS : $length; //1 Year - should it be more? 
		}
	}

	/* Login Functions */
	protected function user_exists() {
		$email = self::assert_param('user_login');
		return username_exists($email) || email_exists($email);
	}
	protected function signup() {
		$email = strtolower(self::assert_param('user_login'));
		$password = self::assert_param('user_password');
		$create = true;
		$create = apply_filters('pageapp_pre_signup', $email, $password, $create);
		if (is_wp_error($create)) {
			return $create;
		} else {
			$user_id = wp_create_user($email, $password, $email);
			if (is_wp_error($user_id)) {
				return $user_id;
			} elseif ($user_id) {
				do_action('pageapp_post_signup', $email, $password, $user_id);
				$credentials = array(
					'user_login' => $email,
					'user_password' => $password,
					'remember' => true
				);
				return self::login();
			} else {
				return self::error('User ID is null');
			}
		}
	}
	protected function login() {
		//https://developer.wordpress.org/reference/functions/wp_signon/
		$email = strtolower(self::assert_param('user_login'));
		$password = self::assert_param('user_password');
		//https://developer.wordpress.org/reference/functions/wp_set_auth_cookie/
		add_filter('auth_cookie_expiration', array(static::class, 'auth_cookie_expiration'), 10, 3);
		add_action('set_logged_in_cookie', array(static::class, 'set_logged_in_cookie'), 10, 6);
		$user = wp_signon(array('user_login' => $email, 'user_password' => $password, 'remember' => true), true);
		remove_filter('auth_cookie_expiration', array(static::class, 'auth_cookie_expiration'));
		remove_action('set_logged_in_cookie', array(static::class, 'set_logged_in_cookie'));
		$cookie = self::$cookie;
		self::$cookie = null;
		return $cookie == null || $cookie == '' ? self::error('Invalid username or password') : self::filter_user($user, $cookie);
	}
	protected function authenticate() {
		$cookie = self::assert_param('cookie');
		$user_id = wp_validate_auth_cookie($cookie, 'logged_in');
		if ($user_id) {
			return self::filter_user(get_userdata($user_id), $cookie);
		}
	}

	/* Fire/Roku Functions */
	protected function roku() {
		//https://developer.roku.com/docs/specs/direct-publisher-feed-specs/json-dp-spec.md
		$name = get_option('pageapp_vimeo_name');
		$genre = get_option('pageapp_vimeo_genre');
		$types = array(
			'movies' => get_option('pageapp_vimeo_movies'),
			'shortFormVideos' => get_option('pageapp_vimeo_short'),
			'tvSpecials' => get_option('pageapp_vimeo_specials')
		);
		$series = preg_split("/[\s,]+/", get_option('pageapp_vimeo_series'));
		//$updated = new DateTime();
		$updated = new DateTime('1970-01-01');
		$result = array(
			'providerName' => $name ? $name : 'PageApp',
			'lastUpdated' => '',
			'language' => 'en-US',
			'movies' => array(),
			'shortFormVideos' => array(),
			'tvSpecials' => array(),
			'series' => array()
		);
		foreach ($types as $id => $url) {
			if ($url) {
				$json = PageApp::cache_json($url);
				$last = new DateTime($json['lastUpdated']);
				$updated = $last > $updated ? $last : $updated;
				$result[$id] = $json['shortFormVideos'];
			}
		}
		foreach($series as $url) {
			if ($url) {
				$json = PageApp::cache_json($url);
				$last = new DateTime($json['lastUpdated']);
				$updated = $last > $updated ? $last : $updated;
				$name = $json['providerName'];
				$videos = isset($json['shortFormVideos']) && count($json['shortFormVideos']) > 0 ? $json['shortFormVideos'] : $json['movies'];
				$index = 1;
				$count = $videos ? count($videos) : 0;
				if ($count > 0) {
					for ($i = $count-1; $i>=0; $i--) {
						$videos[$i]['episodeNumber'] = $index;
						//TODO: not sure if these gets pulled from vimeo
						//$videos[$i]['genres'] = array($genre);
						//$videos[$i]['tags'] = array('No Category');
						$index++;
					}
					$result['series'][] = array(
						'id' => md5($name),
						'title' => $name,
						'genres' => array($genre),
						'releaseDate' => $videos[$count-1]['releaseDate'],
						//TODO: Not sure if false thumbnail will make Roku take it automatically
						'thumbnail' => count($videos) > 0 ? $videos[0]['thumbnail'] : false,
						'shortDescription' => $name,
						'longDescription' => $name,
						'tags' => [$name],
						'episodes' => $videos
					);
				}
				
			}
		}
		$result['lastUpdated'] = $updated->format(DateTime::ATOM);
		return $result;
	}

	protected function roku_links() {
		$movies = get_option('pageapp_vimeo_movies');
		$shortFormVideos = get_option('pageapp_vimeo_short');
		$tvSpecials = get_option('pageapp_vimeo_specials');
		$list = preg_split("/[\s,]+/", get_option('pageapp_vimeo_series'));
		if ($movies) {
			$list[] = $movies;
		}
		if ($shortFormVideos) {
			$list[] = $shortFormVideos;
		}
		if ($tvSpecials) {
			$list[] = $tvSpecials;
		}
		foreach ($list as $url) {
			echo $url."\n"; 
		}
		exit();
	}

	protected function roku_cache() {
		$url = self::assert_param('url');
		echo json_encode(PageApp::cache_json($url));
		exit;
	}

	protected function fire_links() {
		$list = preg_split("/[\s,]+/", get_option('pageapp_firetv_feeds'));
		foreach ($list as $url) {
			echo $url."\n"; 
		}
		exit();
	}

	protected function fire_cache() {
		$url = self::assert_param('url');
		echo PageApp::cache_xml($url)->asXML();
		exit;
	}

	protected function firetv() {
		$name = get_option('pageapp_firetv_name');
		$link = get_option('pageapp_firetv_link');
		$description = get_option('pageapp_firetv_description');
		$editor = get_option('pageapp_firetv_editor');
		$image = get_option('pageapp_firetv_image');

		$feeds = preg_split("/[\s,]+/", get_option('pageapp_firetv_feeds'));
		$result = null;
		foreach ($feeds as $url) {
			$xml = PageApp::cache_xml($url);
			if ($result) {
				$title = $xml->channel->title;
				$append = '';
				for ($i=0; $i<count($xml->channel->item); $i++) {
					$item = $xml->channel->item[$i];
					$append .= str_replace('<media:category>All</media:category>', '<media:category>'.esc_html($title).'</media:category>', $item->asXML())."\n";
				}
				$result = str_replace('</channel>', $append.'</channel>', $result);
				/*for ($i=0; $i<count($xml->channel->item); $i++) {
					//TODO: Replace media:catgeory with title
					//TODO: append elment not really working
					//https://stackoverflow.com/questions/3418019/simplexml-append-one-tree-to-another/22099078
					//$result->channel->addChild('item', (string)$xml->channel->item[$i]->item->asXML());
					// Create new DOMElements from the two SimpleXMLElements
					$channel = dom_import_simplexml($result->channel);
					$item  = dom_import_simplexml($xml->channel->item[$i]);
					// Import the <cat> into the dictionary document
					$item  = $channel->ownerDocument->importNode($item, true);
					// Append the <cat> to <c> in the dictionary
					$channel->appendChild($item);
				}*/
			} else {
				$result = $xml;
				$title = (string) $xml->channel->title;
				if ($name) {
					$xml->channel->title = $name;
					$xml->channel->image->title = $name;
				}
				if ($link) {
					$xml->channel->link = $link;
					$xml->channel->image->link = $link;
				}
				if ($description) {
					$xml->channel->description = $description;
					$xml->channel->image->description = $description;
				}
				if ($editor) {
					$xml->channel->managingEditor = $editor;
				}
				if ($image) {
					$xml->channel->image->url = $image;
				}
				$result = $xml->asXML();
				$result = str_replace('<media:category>All</media:category>', '<media:category>'.esc_html($title).'</media:category>', $result);
			}
		}
		if ($result) {
			//TODO: content header
			echo $result;
			exit;
		} else {
			return false;
		}
	}
}
$pageappjson = new PageAppJson();