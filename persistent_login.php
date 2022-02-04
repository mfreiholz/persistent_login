<?php
/**
 * Plugin which provides a persistent login functionality.
 * Also known as "remember me" or "stay logged in" function.
 *
 * @version @package_version@
 * @author insaneFactory, Manuel Freiholz
 * @website http://www.insanefactory.com/
*/
class persistent_login extends rcube_plugin
{
	// registered tasks for this plugin.
	public $task = 'login|logout|mail|addressbook|settings';

	// name of the persistent authentication token cookie.
	private $cookie_name;

	// expire time of cookie/token (in milliseconds).
	private $cookie_expire_time;

	// indicates whether the token based authentication should be used.
	private $use_auth_tokens;

	// name of the database table for the token based authentication.
	private $db_table_auth_tokens;

	// show oauth login only - hide username/password prompts
	private $oauth_login_redirect;

	// temporary variable to hold the login information from "authenticate()" method
	// to use it in a later called method (login_after())
	private $authenticate_args = array();

	// temporary variable to hold the original _action parameter when trying to log in
	// using data from persisten cookie
	private $original_action;

	function init()
	{
		$rcmail = rcmail::get_instance();

		// check whether the "global_config" plugin is available,
		// otherwise load the config manually.
		$plugins = $rcmail->config->get('plugins');
		$plugins = array_flip($plugins);
		if (!isset($plugins['global_config'])) {
			$this->load_config();
		}

		// ip check
		// only allow plugin for users of a configured ip net mask
		$netmaskwl = $rcmail->config->get('ifpl_netmask_whitelist', array());
		if (!empty($netmaskwl)) {
			$user_ip = $this->remote_addr();
			$found = false;
			for ($i = 0; $i < count($netmaskwl) && !$found; $i++) {
				if ($this->ip_in_range($user_ip, $netmaskwl[$i])) {
					$found = true;
					break;
				}
			}
			// abort plugin initialization
			if (!$found) {
				return;
			}
		}

		// load plugin configuration.
		$this->cookie_expire_time = $rcmail->config->get('ifpl_login_expire', 259200);
		$this->cookie_name = $rcmail->config->get('ifpl_cookie_name', '_pt');
		$this->use_auth_tokens = $rcmail->config->get('ifpl_use_auth_tokens', false);
		$this->db_table_auth_tokens = $rcmail->config->get('db_table_auth_tokens', 'auth_tokens');
		$this->oauth_login_redirect = $rcmail->config->get('ifpl_oauth_login_redirect', false);

		// login form modification hook.
		$this->add_hook('template_object_loginform', array($this,'persistent_login_loginform'));

		// register hooks.
		$this->add_hook('startup', array($this, 'startup'));
		$this->add_hook('authenticate', array($this, 'authenticate'));
		$this->add_hook('login_after', array($this, 'login_after'));
		$this->add_hook('logout_after', array($this, 'logout_after'));
		$this->add_hook('login_failed', array($this, 'login_failed'));
		$this->add_hook('oauth_refresh_token', array($this, 'oauth_refresh_token'));
	}

	function startup($args)
	{
		// don't redirect anything but GET requests allowing roundcube
		// to handle redirecting to 'login' normally. most notably,
		// this may occur when 'session_lifetime' has expired during a
		// client refresh POST.
		if ($_SERVER["REQUEST_METHOD"] != "GET") return $args;

		// if the persistent token is available, we have to redirect to login-authentication.
		if (self::is_persistent_cookie_available()) {
			// store the original _action parameter, so we can redirect to where the user
			// wanted after successful login
			if (isset($args['action'])) {
				$this->original_action = $args['action'];
			}
			$args['action'] = 'login';
		}
		return $args;
	}



	function authenticate($args)
	{
		$this->authenticate_args = $args;

		// check for auth_token cookie.
		if (!self::is_persistent_cookie_available()) {
			return $args;
		}

		$rcmail = rcmail::get_instance();

		$auth = $this->auth_from_cookie();
		if ($auth == null) {
			self::unset_persistent_cookie();
			return $args;
		}

		$this->authenticate_args['host'] = $auth['host'];
		$this->authenticate_args['user'] = $auth['user_name'];

		$authenticate_success = false;
		
		if ($auth['auth_type'] == 'PLAIN') {
			$authenticate_success = $this->authenticate_plain(
				$args,
				$auth
			);
		}
		else if ($auth['auth_type'] == 'OAUTH') {
			$authenticate_success = $this->authenticate_oauth(
				$args,
				$auth
			);
		}
		
		if (! $authenticate_success) {
			self::unset_persistent_cookie();
			$args['valid'] = false;
		}
		return $args;
	}

	/**
	 * Helper function to authenticate via oauth. The process exits if
	 * login was successful. Returns false if authentication failed.
	 * @return bool
	 */
	function authenticate_oauth(&$args, $auth) {
		$rcmail = rcmail::get_instance();

		// refresh the access token
		// 'oauth_refresh_token' hook will get called if refreshed
		$_SESSION['oauth_token'] = $auth['auth_data'];
		$rcmail->oauth->refresh([]);

		$data = $_SESSION['oauth_token'];
		$authorization = sprintf(
			'%s %s',
			$data['token_type'],
			$data['access_token']
		);
	
		// check XOAUTH2 authorization against the IMAP server
		$rcmail->config->set('imap_auth_type', 'XOAUTH2');
		$rcmail->config->set('login_password_maxlen', strlen($authorization));
		$host = empty($auth['host']) ? $rcmail->autoselect_host() : $auth['host'];
  		if ($rcmail->login($auth['user_name'], $authorization, $host, true)) {

			// log successful login
			$rcmail->log_login();

			// update our cookie
			self::set_persistent_cookie();

			// update roundcube's sesssion cookies
			$rcmail->session->regenerate_id(false);
			$rcmail->session->set_auth_cookie();

			// success - redirect to mail
			header('Location: ' . $rcmail->url(['task' => 'mail'], true, false));
			exit;
		}
		return false;
	}

	/**
	 * Helper function to authenticate via username/password
	 * @return bool
	 */
	function authenticate_plain(&$args, $auth) {
		// set login data.
		$args['user'] = $auth['user_name'];
		$args['pass'] = $auth['user_pass'];
		$args['host'] = $auth['host'];
		$args['cookiecheck'] = false;
		$args['valid'] = true;
		$args['abort'] = false;
		return true;
	}

	/**
	 * roundcube handler for 'oauth_refresh_token' hook, which is
	 * called after an oauth aceess_token is refreshed. Registered
	 * roundcube tasks "mail|addressbook|settings" must be set or
	 * this function won't be called during refreshes when the user is
	 * on one of those pages.
	 */
	function oauth_refresh_token($args) {
		if (self::is_persistent_cookie_available()) {
			$rcmail = rcmail::get_instance();
			
			// remove old token when using token mechanic to identify user.
			if ($this->use_auth_tokens) {				
				// 0 - user-id
				// 1 - auth-token
				$token_parts = explode('|', self::get_persistent_cookie());

				// abort: invalid cookie format.
				if (empty($token_parts) || !is_array($token_parts)
					|| count($token_parts) != 2
				) {
					return $args;
				}

				// remove old token
				$this->db_remove_token(
					$rcmail,
					$token_parts[1], // token
					$token_parts[0]  // user_id
				);
			}

			// update the existing cookie if the user is logged
			// in. they won't be logged in if a refresh occurred due
			// to our automatically re-authenticating them
			if (! empty($rcmail->user->ID)) {
				self::set_persistent_cookie();
			}
		}
		return $args;
	}

	function login_after($args)
	{
		// update the already existing cookie (because of expiration time).
		if (self::is_persistent_cookie_available()) {
			self::set_persistent_cookie();
		}
		// user just logged in by form and wants a cookie now.
		else if (rcube_utils::get_input_value('_ifpl', rcube_utils::INPUT_POST)) {
			self::set_persistent_cookie();
		}
		// user just logged in using oauth and wants a cookie now
		else if (rcube_utils::get_input_value('_ifpl', rcube_utils::INPUT_COOKIE) == '1') {
			self::set_persistent_cookie();
		}
		self::remove_cookie('_ifpl');

		// restore the user requested action unless it's an action
		// that's not compatible with the 'mail' task, which is always
		// set by roundcube after login
		if (isset($this->original_action) && $this->original_action != 'keep-alive') {
			$args['action'] = $this->original_action;
		}
		return $args;
	}

	function logout_after($args)
	{
		$rcmail = rcmail::get_instance();
		if ($this->use_auth_tokens) {
			// get user-id and token from cookie.
			$cookie_data = self::get_persistent_cookie();
			$token_parts = explode('|', $cookie_data);

			if (!empty($token_parts) && is_array($token_parts)
				&& count($token_parts) == 2
			) {
				// remove token from db.
				$this->db_remove_token(
					$rcmail,
					$token_parts[1], // token
					$token_parts[0]  // user_id
				);
			}
		}

		// delete the persistent token cookie.
		self::unset_persistent_cookie();

		return $args;
	}

	/**
	 * roundcube handler to clean up if login failed
	 */
	function login_failed($args)
	{
		self::unset_persistent_cookie();
		return $args;
	}

	///////////////////////////////////////////////////////////////////////////
	// template callback functions
	///////////////////////////////////////////////////////////////////////////

	function persistent_login_loginform($content)
	{
		// load localizations.
		$this->add_texts('localization', true);

		// import CSS styles.
		$this->include_stylesheet('persistent_login.css');

		// import javascript client code.
		// the javascript code adds the <input type="checkbox"...> to the login form.
		$this->include_script('persistent_login.js');

		// set variable
		self::remove_cookie('_ifpl');
		rcmail::get_instance()
			->output
			->set_env('hide_login_form', $this->oauth_login_redirect && rcmail::get_instance()->oauth->is_enabled());

		return $content;
	}

	///////////////////////////////////////////////////////////////////////////
	// private functions to handle tokens
	///////////////////////////////////////////////////////////////////////////

	/**
	 * gets the data which is stored in cookie.
	 *
	 * @return string the cookie data.
	 */
	function get_persistent_cookie()
	{
		return rcmail::get_instance()->decrypt($_COOKIE[$this->cookie_name]);
	}

	/**
	 * Sets a new or updates the current persistent cookie to be used on the
	 * next auto-login.
	 */
	function set_persistent_cookie()
	{
		// prepare data for login via cookie
		$rcmail = rcmail::get_instance();

		// host connect url
		$host = '';
		if (isset($this->authenticate_args['host']) && !empty($this->authenticate_args['host'])) {
			$host = $this->authenticate_args['host'];
		}
		else {
			// fallback (should never happen!)
			// note: can not use this way, if the main.inc.php config defines the host without port,
			// but the cookie holds the url with port, the cookie authentication won't work!
			error_log('using fallback mechanism for "host" connect url.');

			if (isset($_SESSION['storage_ssl']) && !empty($_SESSION['storage_ssl'])) {
				$host.= $_SESSION['storage_ssl'] . '://';
			}
			$host.= $_SESSION['storage_host'];
			if (isset($_SESSION['storage_port']) && !empty($_SESSION['storage_port']) && $_SESSION['storage_port'] != 0) {
				$host.= ':' . $_SESSION['storage_port'];
			}
		}

		// user id
		$user_id = $rcmail->user->ID;

		// user name
		$user_name = $rcmail->user->data['username'];

		// user password
		$user_password = isset($_SESSION['password']) ? $_SESSION['password'] : '';

		// auth data
		$oauth_data = isset($_SESSION['oauth_token']) ? json_encode($_SESSION['oauth_token']) : null;

		if (! empty($oauth_data)) {
			$auth_type = 'OAUTH';
			$user_password = '';
		}
		else {
			$auth_type = 'PLAIN';
			$oauth_data = null;
		}

		if ($this->use_auth_tokens) {
			// generate new token in database and set it to user as cookie...
			$auth_token = time() . "-" . self::generate_random_token();
			$plain_token = $user_id . '|' . $auth_token;
			$crypt_token = $rcmail->encrypt($plain_token);

			// calculate expire date for database.
			$ts_expires = time() + $this->cookie_expire_time;
			$sql_expires = date("Y-m-d H:i:s", $ts_expires);

			// insert token to database.
			$rcmail->get_dbh()->query(
				"INSERT INTO ".$rcmail->db->table_name($this->db_table_auth_tokens)
				." (auth_type, token, expires, user_id, user_name, user_pass, host, auth_data)"
				." VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
				$auth_type, $auth_token, $sql_expires, $user_id, $user_name, $user_password, $host, $oauth_data);

			// set token as cookie.
			if (!self::set_cookie($this->cookie_name, $crypt_token, $ts_expires)) {
				error_log('unable to set persistent login cookie for user "'.$rcmail->user->data['username'].'"');
			}
		}
		else {
			// create encrypted auth_token to store in cookie.
			// e.g.: "<user_id>|<username>|<ecrypted_password>|<token_creation_timestamp>"
			if ($auth_type == 'OAUTH') {
				$plain_token = 'OAUTH' . '|' . $user_id . '|' . $user_name . '|' . $host . '|' . (time() + $this->cookie_expire_time) . '|' . $oauth_data;
			}
			else {
				$plain_token = 'PLAIN' . '|' . $user_id . '|' . $user_name . '|' . $user_password . '|' . $host . '|' . (time() + $this->cookie_expire_time);
			}
			
			$crypt_token = $rcmail->encrypt($plain_token);

			//error_log('set plain token to cookie = '.$plain_token);

			// set token as cookie.
			if (!self::set_cookie($this->cookie_name, $crypt_token, time() + $this->cookie_expire_time)) {
				error_log('unable to set persistent login cookie for user "'.$user_name.'"');
			}
		}
	}

	/**
	 * removes the persistent cookie.
	 */
	function unset_persistent_cookie()
	{
		// remove the cookie.
		self::remove_cookie($this->cookie_name);
	}

	/**
	 * checks whether the user has a persistent cookie.
	 *
	 * @return bool
	 */
	function is_persistent_cookie_available()
	{
		if (empty($_COOKIE[$this->cookie_name])) {
			return false;
		}
		else {
			return true;
		}
	}

	/**
	 * retrieve the persistent cookie data as an array or null if auth
	 * data is unavailable. The fields of the returned associative
	 * array match those of the database table column names.
	 *
	 * returned array: [
	 *    'auth_type' => 'OAUTH' | 'PLAIN',
	 *    'user_id'   => {number; may be formatted as integer or string},
	 *    'user_name' => {string},
	 *    'user_pass' => {string; '' if auth_type=='OAUTH'},
	 *    'host'      => {string},
	 *    'expires'   => {string; 'YYYY-MM-DD HH:MM:SS' format if use_auth_tokens is truthy, otherwise the number of seconds from the epoch },
	 *    'auth_data' => {array} | null
	 * ]
	 *	
	 * when using auth tokens, the function has the side effect of
	 * removing the correspoding row in the database.
	 *
	 * @return array
	 * @return null
	 */
	function auth_from_cookie() {
		$rcmail = rcmail::get_instance();
		
		// use token mechanic to identify user.
		if ($this->use_auth_tokens) {

			// purge expired records from the database
			$this->clean_db($rcmail);

			// 0 - user-id
			// 1 - auth-token
			$token_parts = explode('|', self::get_persistent_cookie());

			// abort: invalid cookie format.
			if (empty($token_parts) || !is_array($token_parts)
				|| count($token_parts) != 2
			) {
				return null;
			}

			// get auth_token data from db.
			$res = $rcmail->get_dbh()->query(
				"SELECT * FROM " . $rcmail->db->table_name($this->db_table_auth_tokens)
				." WHERE token = ?"
					." AND user_id = ?",
				$token_parts[1],
				$token_parts[0]);

			if (($data = $rcmail->get_dbh()->fetch_assoc($res))) {
				$err_msg = null;
				if ($data['auth_type'] == 'PLAIN') {
					$data['user_pass'] = $rcmail->decrypt($data['user_pass']);
				}
				else if ($data['auth_type'] == 'OAUTH') {
					$data['auth_data'] = json_decode($data['auth_data'], true);
					if ($data['auth_data'] == null) {
						$err_msg = "Database table auth_tokens contains an auth_data field that is not parsable json. token=" . $data['token'];
						$data =null;
					}
				}
				else {
					$err_msg = "Database table auth_tokens contains an invalid auth_type. auth_type=" . $data['auth_type'] . " token=" . $data['token'];
					$data = null;
				}

				$this->db_remove_token(
					$rcmail,
					$token_parts[1], // token
					$token_parts[0]  // user_id
				);

				return $data;
			}
			else {
				// seems like the token is invalid.
				// this case can only happen if the token is used a 2nd time -> got hacked?!
				// for security reason we invalidate all persistent-auth cookies of the user
				// and log the wrong users IP!
				self::unset_persistent_cookie();
				$this->db_remove_all_user_tokens(
					$rcmail,
					$token_parts[0]  // user_id
				);
				//error_log('seems like a persistent login cookie has been stolen. invalidated all auth-tokens of user ' . $token_parts[0]);
				return null;
			}

		} // end: use_auth_tokens


		// use only-cookie mechanic to identify the user.
		else {
			// extract user data from auth_token.
			// 0 -> auth_type
			//
			// for auth_type "PLAIN":
			// 1 -> user-id
			// 2 -> username
			// 3 -> password (encrypted)
			// 4 -> host
			// 5 -> expire timestamp
			//
			// for auth_type "OAUTH":
			// 1 -> user-id
			// 2 -> username
			// 3 -> host
			// 4 -> expire timestamp
			// 5 -> json from oauth server containing authorization
			
			$plain_token = $rcmail->decrypt($_COOKIE[$this->cookie_name]);
			$token_parts = explode('|', $plain_token);

			//error_log('plain token from cookie = '.$plain_token);
			
			if (empty($token_parts) || !is_array($token_parts) || count($token_parts) < 1) {
				// invalid token.
				return null;
			}

			$auth_type = $token_parts[0];

			if ($auth_type == 'PLAIN' && count($token_parts) == 6) {
				// cookie/token expired. (should never occur, because the browser shall delete the cookie)
				if (time() > $token_parts[5]) {
					return null;
				}
				$data = [];
				$data['auth_type'] = $auth_type;
				$data['user_id'] = $token_parts[1];
				$data['user_name'] = $token_parts[2];
				$data['user_pass'] = $rcmail->decrypt($token_parts[3]);
				$data['host'] = $token_parts[4];
				$data['expires'] = $token_parts[5];
				$data['auth_data'] = null;
				return $data;
			}
			else if ($auth_type == 'OAUTH' && count($token_parts) == 6) {
				if (time() > $token_parts[4]) {
					return null;
				}
				$data = [];
				$data['auth_type'] = $auth_type;
				$data['user_id'] = $token_parts[1];
				$data['user_name'] = $token_parts[2];
				$data['user_pass'] = '';
				$data['host'] = $token_parts[3];
				$data['expires'] = $token_parts[4];
				$data['auth_data'] = json_decode($token_parts[5], true);
				if ($data['auth_data'] == null) {
					// json is invalid
					return null;
				}
				return $data;
			}
			else {
				// invalid auth_type or token.
				return null;
			}

			
		} // end: use only-cookie mechanic to identify the user.		
	}

	/**
	 * purge database records with expired tokens
	 */
	function clean_db($rcmail) {
		// remove all expired tokens from database.
		$rcmail->get_dbh()->query(
			"DELETE FROM " . $rcmail->db->table_name($this->db_table_auth_tokens)
			." WHERE expires < ".$rcmail->db->now());		
	}

	/**
	 * remove the database record corresponding to the supplied
	 * roundcube user-id and token
	 */
	function db_remove_token($rcmail, $token, $user_id) {
		// remove token from db.
		$rcmail->get_dbh()->query(
			"DELETE FROM " . $rcmail->db->table_name($this->db_table_auth_tokens)
			." WHERE token = ? "
			." AND user_id = ?",
			$token,
			$user_id);
	}

	/**
	 * remove all database records corresponding to the supplied
	 * roundcube user-id
	 */
	function db_remove_all_user_tokens($rcmail, $user_id) {
		$rcmail->get_dbh()->query(
			"DELETE FROM " . $rcmail->db->table_name($this->db_table_auth_tokens)
			. " WHERE user_id = ?",
			$user_id);
	}
	

	/**
	 * generates a random string of numbers and letters.
	 *
	 * @param $len length of the generated random string
	 * @return string
	 */
	function generate_random_token($len = 28)
	{
		$chars = "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
		$min = 0;
		$max = strlen($chars) - 1;

		$random_string = "";
		for ($i = 0; $i < $len; ++$i) {
			$pos = mt_rand($min, $max);
			$random_string.= substr($chars, $pos, 1);
		}

		return $random_string;
	}

	/**
	 * sets a cookie.
	 *
	 * @param $name
	 * @param $value
	 * @param $exp
	 * @return bool
	 */
	function set_cookie($name, $value, $exp = 0)
	{
		if (class_exists('rcube_utils')) {
			rcube_utils::setcookie($name, $value, $exp);
		} else {
			rcmail::get_instance()->setcookie($name, $value, $exp);
		}
		return true;
	}

	/**
	 * removes/unsets a cookie.
	 *
	 * @param $name the name of the cookie.
	 * @return bool
	 */
	function remove_cookie($name)
	{
		if (headers_sent()) {
			return false;
		}
		if (class_exists('rcube_utils')) {
			rcube_utils::setcookie($name, "", time() - 60);
		} else {
			rcmail::get_instance()->setcookie($name, "", time() - 60);
		}
		return true;
	}

	/**
	* Check if a given ip is in a network
	* @param  string $ip    IP to check in IPV4 format eg. 127.0.0.1
	* @param  string $range IP/CIDR netmask eg. 127.0.0.0/24, also 127.0.0.1 is accepted and /32 assumed
	* @return boolean true if the ip is in this range / false if not.
	* @source https://gist.github.com/tott/7684443
	*/
	function ip_in_range($ip, $range)
	{
		if (strpos($range, '/') === false) {
			$range .= '/32';
		}
		// $range is in IP/CIDR format eg 127.0.0.1/24
		list($range, $netmask) = explode('/', $range, 2);
		$range_decimal = ip2long($range);
		$ip_decimal = ip2long($ip);
		$wildcard_decimal = pow(2, (32 - $netmask)) - 1;
		$netmask_decimal = ~ $wildcard_decimal;
		return (($ip_decimal & $netmask_decimal) == ($range_decimal & $netmask_decimal));
	}

	function remote_addr()
	{
		if (isset($_SERVER["HTTP_X_FORWARDED_FOR"])) {
			return $_SERVER["HTTP_X_FORWARDED_FOR"];
		} else if (isset($_SERVER["REMOTE_ADDR"])) {
			return $_SERVER["REMOTE_ADDR"];
		}
		return "";
	}

}
