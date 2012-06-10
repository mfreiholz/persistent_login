<?php
/**
 * Plugin which provides a persistent login functionality.
 * Also known as "remembery me" or "stay logged in" function.
 *
 * @author insaneFactory, Manuel Freiholz
 * @website http://manuel.insanefactory.com/
*/
class persistent_login extends rcube_plugin
{
	public $task = 'login|logout';
	
	// name of the persistent authentication token cookie.
	private $cookie_name;
	
	// expire time of cookie/token (in milliseconds).
	private $cookie_expire_time;
	
	private $use_auth_tokens;
	private $db_table_auth_tokens;
	
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
		
		// load plugin configuration.
		$this->cookie_expire_time = $rcmail->config->get('ifpl_login_expire', 259200);
		$this->cookie_name = $rcmail->config->get('ifpl_cookie_name', '_pt');
		$this->use_auth_tokens = $rcmail->config->get('ifpl_use_auth_tokens', false);
		$this->db_table_auth_tokens = $rcmail->config->get('db_table_auth_tokens', 'auth_tokens');
		
		// login form modification hook.
		$this->add_hook('template_object_loginform', array($this,'persistent_login_loginform'));

		// register hooks.
		$this->add_hook('startup', array($this, 'startup'));
		$this->add_hook('authenticate', array($this, 'authenticate'));
		$this->add_hook('login_after', array($this, 'login_after'));
		$this->add_hook('logout_after', array($this, 'logout_after'));
	}
	
	function startup($args)
	{
		// if the persistent token is available, we have to redirect to login-authentication.
		if (self::is_persistent_cookie_available()) {
			$args['action'] = 'login';
		}
		return $args;
	}
	
	function authenticate($args)
	{
		// check for auth_token cookie.
		if (!self::is_persistent_cookie_available()) {
			return $args;
		}
		
		// --- identify user by cookie. ------------------------------------ //
		
		$rcmail = rcmail::get_instance();
		
		// use token mechanic to identify user.
		if ($this->use_auth_tokens) {
		
			// remove all expired tokens from database.
			$rcmail->get_dbh()->query("DELETE FROM " . get_table_name($this->db_table_auth_tokens) . " WHERE `expires` < NOW()");
		
			// 0 - user-id
			// 1 - auth-token
			$token_parts = explode(':', self::get_persistent_cookie());
			
			// abort: invalid cookie format.
			if (empty($token_parts) || !is_array($token_parts)
				|| count($token_parts) != 2
			) {
				self::unset_persistent_cookie();
				return $args;
			}
			
			// get auth_token data from db.
			$sql = "SELECT * FROM " . get_table_name($this->db_table_auth_tokens) . " WHERE `token`=? AND `user_id`=?";
			$res = $rcmail->get_dbh()->query($sql, $token_parts[1], $token_parts[0]);
			
			if ($data = $rcmail->get_dbh()->fetch_assoc($res)) {
				// has the token been expired?
				/*if (false) {
					self::unset_persistent_cookie();
					$rcmail->get_dbh()->query("delete from " . get_table_name('auth_tokens') . " where `token`=? and `user_id`=?", $token_parts[1], $token_parts[0]);
					error_log('persistent-login expired, of user ' . $token_parts[0]);
					return $args;
				}*/
				
				// set login data.
				$args['user'] = $data['user_name'];
				$args['pass'] = $rcmail->decrypt($data['user_pass']);
				$args['cookiecheck'] = false;
				$args['valid'] = true;
				
				// remove token from db.
				$rcmail->get_dbh()->query("DELETE FROM " . get_table_name($this->db_table_auth_tokens) . " WHERE `token`=? AND `user_id`=?", $token_parts[1], $token_parts[0]);
			}
			else {
				// seems like the token is invalid.
				// this case can only happen if the token is used a 2nd time -> got hacked?!
				// for security reason we invalidate all persistent-auth cookies of the user and log the wrong users IP!
				self::unset_persistent_cookie();
				$rcmail->get_dbh()->query("DELETE FROM " . get_table_name($this->db_table_auth_tokens) . " WHERE `user_id`=?", $token_parts[0]);
				//error_log('seems like a persistent login cookie has been stolen. invalidated all auth-tokens of user ' . $token_parts[0]);
			}
			
		}
		// use only-cookie mechanic to identify the user.
		else {
		
			// extract user data from auth_token.
			// 0 -> user-id
			// 1 -> username
			// 2 -> password (encrypted)
			// 3 -> expire timestamp
			$plain_token = $rcmail->decrypt($_COOKIE[$this->cookie_name]);
			$token_parts = explode(':', $plain_token);
			
			if (!empty($token_parts) && is_array($token_parts)
				&& count($token_parts) == 4
			) {
				// cookie/token expired. (should never occur, because the browser shall delete the cookie)
				if (time() > $token_parts[3]) {
					// @todo LOG: persistent login expired!
					self::unset_persistent_cookie();
				}
				// set login data.
				else {
					$args['user'] = $token_parts[1];
					$args['pass'] = $rcmail->decrypt($token_parts[2]);
					$args['cookiecheck'] = false;
					$args['valid'] = true;
				}
			}
			else {
				// invalid token.
				self::unset_persistent_cookie();
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
		else if (get_input_value('_ifpl', RCUBE_INPUT_POST)) {
			self::set_persistent_cookie();
		}
		return $args;
	}
	
	function logout_after($args)
	{
		if ($this->use_auth_tokens) {
			// get user-id and token from cookie.
			$cookie_data = self::get_persistent_cookie();
			$token_parts = explode(':', $cookie_data);
			
			if (!empty($token_parts) && is_array($token_parts)
				&& count($token_parts) == 2
			) {
				// remove token from db.
				rcmail::get_instance()->get_dbh()->query("DELETE FROM " . get_table_name($this->db_table_auth_tokens) . " WHERE `token`=? AND `user_id`=?", $token_parts[1], $token_parts[0]);
			}
		}
		
		// delete the persistent token cookie.
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
		
		// add additional stylesheet for larry theme.
		if (rcmail::get_instance()->config->get('skin', 'default') == 'larry') {
			$this->include_stylesheet('persistent_login_larry.css');
		}
		
		// import javascript client code.
		// the javascript code adds the <input type="checkbox"...> to the login form.
		$this->include_script('persistent_login.js');
		
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
		$rcmail = rcmail::get_instance();
		
		if ($this->use_auth_tokens) {
			// generate new token in database and set it to user as cookie...
			$auth_token = time() . "-" . self::generate_random_token();
			$plain_token = $rcmail->user->ID . ':' . $auth_token;
			$crypt_token = $rcmail->encrypt($plain_token);
			
			// calculate expire date for database.
			$ts_expires = time() + $this->cookie_expire_time;
			$sql_expires = date("Y-m-d H:i:s", $ts_expires);
			
			// insert token to database.
			$sql = 'INSERT INTO '.get_table_name($this->db_table_auth_tokens).' (`token`, `expires`, `user_id`, `user_name`, `user_pass`) VALUES (?, ?, ?, ?, ?)';
			$rcmail->get_dbh()->query($sql, $auth_token, $sql_expires, $rcmail->user->ID, $rcmail->user->data['username'], $_SESSION['password']);

			// set token as cookie.
			if (!self::set_cookie($this->cookie_name, $crypt_token, time() + $this->cookie_expire_time)) {
				error_log('unable to set persistent login cookie for user "'.$rcmail->user->data['username'].'"');
			}
		}
		else {
			// create encrypted auth_token to store in cookie.
			// e.g.: "<user_id>:<username>:<ecrypted_password>:<token_creation_timestamp>"
			$plain_token = $rcmail->user->ID . ':' . $rcmail->user->data['username'] . ':' . $_SESSION['password'] . ':' . (time() + $this->cookie_expire_time);
			$crypt_token = $rcmail->encrypt($plain_token);
			
			// set token as cookie.
			if (!self::set_cookie($this->cookie_name, $crypt_token, time() + $this->cookie_expire_time)) {
				error_log('unable to set persistent login cookie for user "'.$rcmail->user->data['username'].'"');
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
	 * @todo for some reason using rcmail::setcookie() doesn't work.
	 *
	 * @param $name
	 * @param $value
	 * @param $exp
	 * @return bool
	 */
	function set_cookie($name, $value, $exp = 0)
	{
		if (headers_sent()) {
			return false;
		}
		return setcookie($name, $value, $exp);
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
		return setcookie($name, "", time() - 60);
	}
}
