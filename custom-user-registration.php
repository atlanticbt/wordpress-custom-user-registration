<?php
/**
Plugin Name: Custom User Registration
Plugin URI: http://www.atlanticbt.com/blog/?p=4446
Description: Creates a customized login/register/reset page
Version: 0.4
Author: atlanticbt, zaus, heyoka
Author URI: http://www.atlanticbt.com

    Copyright 2011 Atlantic BT

*/

// use session to pass stuff between redirects
if( !session_id() ) session_start();

		
if( !class_exists('Singleton') ) {
	include('Singleton.class.php');
}
if( !class_exists('WP_Options_Page') ) {
	include('wp_options_page.class.php');
}
// helper function for options page
if( !function_exists('abt_v')) :
	/**
	 * Safely escape value checking
	 */
	function abt_v(&$value, $default = NULL){
		return isset($value) ? $value : $default;
	}
endif;
if( !function_exists('abt_kv')) :
	/**
	 * Safely escape value checking
	 */
	function abt_kv($value, $key, $default = NULL){
		return isset($value[$key]) ? $value[$key] : $default;
	}
endif;


if ( !function_exists('wp_new_user_notification') ) :
/**
 * Override new user notification function to prevent email being sent by default
 * THIS IS A DUPLICATE OF THE FUNCTION IN /wp-includes/pluggable.php
 * @ref http://wordpress.org/support/topic/disable-email-password-confirmation-to-new-users?replies=10#post-1615214
 *
 * @since 2.0
 *
 * @param int $user_id User ID
 * @param string $plaintext_pass Optional. The user's plaintext password
 */
function wp_new_user_notification($user_id, $plaintext_pass = '') {
	ABT_Custom_User_Access::wp_new_user_notification($user_id, $plaintext_pass);
}
endif;



register_activation_hook(__FILE__, array('ABT_Custom_User_Access', 'activate'));
register_uninstall_hook(__FILE__, array('ABT_Custom_User_Access', 'uninstall'));

/**
 * ABT_Custom_User_Access
 *
 * This class adds new features the ProsPress's Auction_Bid_System Class.
 *
 * @package Snapsite AAF Auctions
 * @since 1
 */
class ABT_Custom_User_Access extends Singleton {

	var $name = 'abt_cua';
	const N = __CLASS__;

	/**
	 * Hooking and defaults as required
	 */
	public function init(){
		// hook to init or wp_loaded to make available to themes, etc - http://codex.wordpress.org/Plugin_API/Action_Reference
		add_action('wp_loaded', array(&$this, 'init_register_reroute'));	//stuff for login/register page rerouting
		$this->options_page = new WP_Options_Page(self::N);
		if( $this->options_page
			->register(
				'Custom User Access Settings'
				, 'User Login - ABT'
				, array(
					'icon'=>plugins_url('i_plugin.png', __FILE__)
					)
				)) {
			add_action('admin_init', array(&$this, 'admin_init'));
			
		} // if options page registered
	}
	
	public static function activate(){
		// default options
		add_option(self::N, array(
			'general' => array('signup_url' => '/wp-login.php?action=register')
		));
	}
	public static function uninstall(){
		delete_option(self::N);
	}
	
	/**
	 * Do stuff after admin starts
	 */
	public function admin_init(){
			$this->options_page
				->add_section('general', 'General Settings', 'Plugin-wide settings.')
					->add_field('general', 'signup_url', 'Signup Page Url', array(
							'type'			=> 'text',
							'description'	=> 'Relative link to the signup page',
							'std'			=> '/wp-login.php?action=register',
							#'validation'	=> 'required',
							'sanitize'		=> array('required')
						))
					/*
					->add_field('general', 'email', 'Email', array(
							'type'			=> 'text',
							'description'	=> 'Email address where all alerts/receipts will be sent',
							'std'			=> 'email@address.com',
							'validation'	=> 'required',
							'sanitize'		=> 'required'
						))
					->add_field('general', 'checkout_style', 'Checkout Style', array(
							'type'			=> 'select',
							'choices'		=> array(
									'direct'	=> 'Direct',
									'multiple'	=> 'Multiple'
								),
							'description'	=> 'Allow direct checkout, or multiple items in cart.  If direct, use checkout form in place of checkout link for shortcode.',
							'std'			=> 'direct',
							'validation'	=> 'required',
						))
					*/
				;
			
			// add more admin settings
			do_action('abt_custom_register_admin_settings', $this->options_page);
	}//--	fn	admin_init
	
	
	#region ------------------ LOGIN / REGISTER -------------------------
	/**
	 * Change bid redirect-for-login to point to custom login page instead
	 * @param string $redirect the full url with redirect parameters
	 * @see pp-market-system.class, function controller
	 */
	public function login_reroute($redirect){
		//send to custom login page
		$redirect = str_replace('/wp-login.php', apply_filters('abt_custom_register_url', $this->options_page->option('signup_url', 'general')), $redirect);
		#//remove subsequent redirect?
		#$redirect = substr($redirect, 0, strpos($redirect, 'bid_redirect'));
		//remove ajax restriction
		#$redirect = str_replace(array('&bid_submit=ajax', '&amp;bid_submit=ajax', urlencode('&bid_submit=ajax')), '', $redirect);
		return $redirect;
	}//--	fn	login_reroute

	#endregion ------------------ LOGIN -------------------------
	
	
	#region ------------------ REGISTER -------------------------
	
	
	public function on_register_failure(){
		$redirect = apply_filters('abt_custom_register_url', $this->options_page->option('signup_url', 'general') ) ;
		### _log(__FUNCTION__, $redirect );
		wp_safe_redirect( $redirect );
		exit();
	}
	
	/**
	 * Hooks and things for modifying the login/register form
	 */
	public function init_register_reroute(){
		// append fields to form
		add_action('register_form', array(&$this, 'hook_register_form'));
		// extra processing for registration
		add_action('user_register', array(&$this, 'hook_user_register'));
		// display extra processing errors
		add_action('registration_errors', array(&$this, 'hook_registration_errors'));
		
		
		// redirect errors back to signup page
		/// TODO: send errors back correctly
		#add_action('login_form_register', array(&$this, 'on_register_failure'));
		
		
		//artefacts of wp-user-registration plugin; may use later
		$this->userdata = array();
		$this->nometa = apply_filters('abt_custom_register_nometa', '|user_url|display_name|');
		
		// set fields here, so they'll be available elsewhere
		$fields = array(
			1=>array('name'=>'user_pw1', 'data-validation'=>'password', 'data-rel'=>'user_pw2', 'type'=>'password', 'class'=>'input', 'size'=>20, 'label'=>'Password', 'description'=>'At least 6 characters.')
			, 2=>array('name'=>'user_pw2', 'type'=>'password', 'class'=>'input', 'size'=>20, 'label'=>'Confirm Password', 'description'=>'Must match the previous entry.')
			, 3=>array('name'=>'first_name', 'data-validation'=>'string', 'type'=>'text', 'class'=>'input name', 'size'=>20, 'label'=>'First Name', 'description'=>'Your first name.'/*, 'value'=> abt_v($this->userdata['first_name'])*/)
			, 4=>array('name'=>'last_name', 'data-validation'=>'string', 'type'=>'text', 'class'=>'input name', 'size'=>20, 'label'=>'Last Name', 'description'=>'Your last name.'/*, 'value'=> abt_v($this->userdata['last_name'])*/)
		);
		
		// hook to add fields
		$this->fields = apply_filters('abt_custom_register_fields', $fields);
		
		$this->field_attributes = apply_filters('abt_custom_register_field_attributes', array(
			'container_tag' => 'p'
			, 'tabindex_increment' => 10
			, 'non-text-types' => array('checkbox', 'radio', 'select')
			, 'static' => array('label', 'description', 'name')
		));
		
		// remember where we were
		#$this->form_referer = apply_filters('abt_custom_register_url', '/' . $_SERVER['REQUEST_URI']);
		
	}//--	fn	login_reroute_init
	
	/**
	 * Alter registration form to allow passwords, etc
	 * Not actually needed in our case, but adding here so that the custom validation
	 * for the custom login page won't break regular registration
	 */
	public function hook_register_form(){
		$this->form_fields(NULL, 98);
		// clear "flash" data after display
		self::flash_var(false);
	}//--	fn	register_form_hook
	
	/**
	 * Print given fields for register form
	 */
	public function form_fields($fields = NULL, $tabindex = 20){
		
		$nonTextTypes = $this->field_attributes['non-text-types'];
		$static = $this->field_attributes['static'];
		
		if( NULL === $fields ) { $fields =& $this->fields; }
		
		// get errors from our custom redirect
		$errors = self::flash_var('errors');
		if( $errors ){
			$errors = $errors->errors;
			///TODO: check if WP_Error or array...for old WP
		}
		else {
			$errors = array();
		}
		
		
		foreach($fields as $name => $details){
			//extract non-attribute details
			foreach($static as $field){
				if( isset($details[$field]) ):
					${$field} = $details[$field];
					unset($details[$field]);
				else:
					// don't unset special directive...
					if( 'name' != $field ) ${$field} = null;
				endif;
			}//foreach $static
			
			$nonText = in_array( $details['type'], $nonTextTypes );
			?>
			<<?php echo $this->field_attributes['container_tag'] ?> class="fields<?php if( ! $nonText ) echo ' text'; ?>">
				<?php if( $label ): ?><label for="<?php echo $name?>"><?php _e($label, self::N); ?></label><?php endif; ?>
				<input name="<?php echo $name; ?>"<?php
					foreach($details as $attr => $value): echo " $attr=\"$value\""; endforeach;
					//get post value if not already specified
					if( isset($_REQUEST[$name])) :
						$value = $_REQUEST[$name];
						if( $nonText && isset( $details['value'] ) ){
							if( $value === $details['value'] ){
								echo ' checked="checked" selected="selected"';	//lazy for select, checkbox
							}
						}
						else {
							echo " value=\"$value\"";
						}
					endif; // isset request[$name]
					?> tabindex="<?php echo $tabindex += $this->field_attributes['tabindex_increment']; ?>" />
				<?php if( $description ): ?><em class="description"><?php _e($description, self::N); ?></em><?php endif; ?>
				<?php if( isset($errors[$name]) ): ?><p class="error msg"><?php _e( implode('.  ', $errors[$name]), self::N); ?></p><?php endif; ?>
			</<?php echo $this->field_attributes['container_tag'] ?>>
			<?php
		}//	foreach $fields
		?>
		<?php
	}//--	fn	form_fields
	
	/**
	 * Additional processing of user registration
	 * @param unknown_type $userID
	 */
	public function hook_user_register($userID){
		global $wpdb;
		$user_pw = ''; // for later

		if(!empty($this->user_pw)){
			$sql = <<<USERSQL
UPDATE $wpdb->users
SET
	user_pass = md5(%s)
WHERE ID = %d
USERSQL;
			$wpdb->query($wpdb->prepare($sql, $this->user_pw, $userID));
			$user_pw = $this->user_pw;
			
			// $this->reset_password($user, $this->user_pw); ///???
		}
		$this->user_pw ='';
		unset($this->user_pw);

		//update additional userdata
		if(!empty($this->userdata)){
			foreach($this->userdata as $key => $value){
				if(strpos($this->nometa, '|'.$key.'|') === FALSE){
					update_user_meta($userID, $key, $value);
				}else{
					$wpdb->query($wpdb->prepare("UPDATE $wpdb->users SET $key = %s WHERE ID = %d", $value, $userID));
				}
			}
		}
		
		//disable admin bars
		$metas = array('show_admin_bar_front', 'show_admin_bar_admin');
		foreach($metas as $meta_key){
			update_user_meta($userID, $meta_key, 'false');
		}
		
		//now send notification; moved here so we can access the updated values
		
		//overridden function - see class
		// prevents default email to user being sent
		self::wp_new_user_notification($userID, $user_pw, true, 'wp-user-registration');
			//clear user_pw for safety?
			$user_pw = ''; unset($user_pw);
		
		//suppress regular email notification - return control later by removing this hook
		///@deprecated: where do we get this to work?  doesn't seem to be called
		add_action('phpmailer_init',array(&$this, 'phpmailer_init'), 99999);
		//should hook for phpmailer_init be moved to hook_registration_errors, so that it'll get run before this is called
		
		
		### _log(__FUNCTION__, array('userdata' => $this->userdata, 'pw' => $this->user_pw, 'username' => $this->user_name, 'userid' => $userID) );
		
		
		//force login, http://cleverwp.com/autologin-wordpress-php-script/
		$user_login = $this->user_name;
		wp_set_current_user($userID, $user_login);
		wp_set_auth_cookie($userID);
		do_action('wp_login', $user_login);


		
	}//--	fn	user_register_hook
	
	/**
	 * Supress user notification ??
	 * @deprecated
	 *
	 * @param obj $phpmailer
	 */
	public function phpmailer_init(&$phpmailer){
		//return regular control
		remove_action('phpmailer_init',array(&$this, 'phpmailer_init'), 99999);
		// remove address so send will fail
		$phpmailer->ClearAddresses();
	}

	/**
	 * Notify the blog admin of a new user, normally via email.
	 * THIS IS A DUPLICATE OF THE FUNCTION IN /wp-includes/pluggable.php
	 * @ref http://wordpress.org/support/topic/disable-email-password-confirmation-to-new-users?replies=10#post-1615214
	 *
	 * @since 2.0
	 *
	 * @param int $user_id User ID
	 * @param string $plaintext_pass Optional. The user's plaintext password
	 * @param bool $to_user {false} whether or not to send message to user
	 */
	public static function wp_new_user_notification($user_id, $plaintext_pass = '', $to_user = false) {
		$self = self::instance(__CLASS__);
		
		$user = new WP_User($user_id);
	
		$user_login = stripslashes($user->user_login);
		$user_email = stripslashes($user->user_email);
	
		// The blogname option is escaped with esc_html on the way into the database in sanitize_option
		// we want to reverse this for the plain text arena of emails.
		$site_name = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
		$admin_email = get_option('admin_email');
	
		$message  = sprintf(__('New user registration on your site %s:'), $site_name) . "\r\n\r\n";
		$message .= sprintf(__('Username: %s'), $user_login) . "\r\n\r\n";
		$message .= sprintf(__('E-mail: %s'), $user_email) . "\r\n";
	
		@wp_mail($admin_email, sprintf(__('[%s] New User Registration'), $site_name), $message);
	
		//additional parameter $to_user used to prevent default notification unless explicitly set
		if ( empty($plaintext_pass) || false === $to_user )
			return;
	
		//vars for email
		/// TODO: get URL from admin setting
		$login_url = str_replace('/wp-login.php', apply_filters('abt_custom_register_url', $self->options_page->option('signup_url', 'general')), wp_login_url() );
		$site_url = get_bloginfo('home');
		
		//get email template - look in current theme directory, otherwise use default
		/// TODO: get email template from admin setting
		$template_name = apply_filters('abt_custom_register_email_templates', array('email-signup.tpl.php'), $user_login);
		$template_path = locate_template( $template_name );
		if( !$template_path ) $template_path = 'email-signup.tpl.php';
		
		include($template_path);
		
		//headers
		$headers = array(
					'From: "Registrations" <registrations@' . ltrim(strstr($site_url, '//'), '/') . '>'
					//, 'Bcc: backup@atlanticbt.com'
					, 'Reply-To: ' . $admin_email
					, 'Content-Type: text/html')
					;
		
		$headers = apply_filters('abt_custom_register_email_headers', $headers);
		$output = apply_filters('abt_custom_register_email_message', $output);
		
		wp_mail($user_email, sprintf(__('[%s] Your username and password'), $site_name), $output, $headers);
		
		// save the fact that we sent a notification, so we can return a message on the login page
		self::flash_var('notification', true);
		
	}//--	fn	::wp_new_user_notification

	/**
	 * Additional processing of user registration, returns errors
	 * @param $errors an existing list of errors - make sure to properly append using $this->errors()
	 */
	function hook_registration_errors($errors, $sanitized_user_login = NULL, $user_email = NULL){
		if(sanitize_user( $_POST['user_login'] ) != sanitize_user($_POST['user_login'], true)){
			$errors = $this->errors($errors, 'user_name', __('<strong>ERROR</strong>: username is invalid.',self::N));
		}

		
/*
	'first_name'=>array('data-validation'=>'string', 'type'=>'text', 'class'=>'input name', 'size'=>20, 'label'=>'First Name', 'description'=>'Your first name.')
			, 'last_name'=>array('data-validation'=>'string', 'type'=>'text', 'class'=>'input name', 'size'=>20, 'label'=>'Last Name', 'description'=>'Your last name.')
			, 'user_pw1'=>array('data-validation'=>'password', 'data-rel'=>'user_pw2', 'type'=>'password', 'class'=>'input', 'size'=>20, 'label'=>'Password', 'description'=>'At least 6 characters.')
			, 'user_pw2'=>array('type'=>'password', 'class'=>'input', 'size'=>20, 'label'=>'Confirm Password', 'description'=>'Must match the previous entry.')
		);
*/
		//validate userpass
		foreach($this->fields as $key => $attr ){
			// name trick
			if( isset($attr['name']) ) $key = $attr['name'];
			
			$post =& $_POST[$key];
			
			if( isset($attr['data-validation'] ) ) {
				
				$validation = (array)$attr['data-validation'];
				$has_errors = false;
				
				foreach($validation as $rule) :
					switch( $rule ) :
						case 'password':
							if(empty($_POST[ $attr['data-rel'] ]) || $post == ''){
								$errors = $this->errors($errors, $key, __('<strong>ERROR</strong>: Please enter both password fields.',self::N));
								$has_errors = true;
							}elseif(strlen($post)<6){
								$errors = $this->errors($errors, $key, __('<strong>ERROR</strong>: Password require at least 6 characters.',self::N));
								$has_errors = true;
							}elseif( $post !== $_POST[ $attr['data-rel'] ] ){
								$errors = $this->errors($errors, $key, __('<strong>ERROR</strong>: Please type the same password in the two password fields.',self::N));
								$has_errors = true;
							}else{
								// remove duplicate value
								unset( $_POST[ $attr['data-rel'] ] );
								
								// special case - user password and name set separately
								//$this->user_pw = $_POST['user_pw1'];
								//$this->user_name = $_POST['user_login'];
							}
							break;
						case 'string':
							if( 0 !== preg_match('/[^\w\s-\.]/', $post) ) {
								$errors = $this->errors($errors, $key, __("<strong>ERROR</strong>: Please provide a valid {$attr['label']} - no funky characters please.",self::N));
								$has_errors = true;
							}
							break;
						case 'alphanumeric':
							if( 0 !== preg_match('/[^a-zA-Z0-9]/', $post) ) {
								$errors = $this->errors($errors, $key, __("<strong>ERROR</strong>: Please provide a valid {$attr['label']} - letters and numbers only.",self::N));
								$has_errors = true;
							}
							break;
						case 'alpha':
							if( 0 !== preg_match('/[^a-zA-Z]/', $post) ) {
								$errors = $this->errors($errors, $key, __("<strong>ERROR</strong>: Please provide a valid {$attr['label']} - letters only.",self::N));
								$has_errors = true;
							}
							break;
						case 'numbers':
							if( !is_numeric($post) ) {
								$errors = $this->errors($errors, $key, __("<strong>ERROR</strong>: Please provide a valid {$attr['label']} - numbers only.",self::N));
								$has_errors = true;
							}
							break;
						case 'required':
							if( ! isset($post) || empty($post) ) {
								$errors = $this->errors($errors, $key, __("<strong>ERROR</strong>: {$attr['label']} is required.",self::N));
								$has_errors = true;
							}
							break;
					endswitch; // $rule
					
					### _log("tested rule $rule for $key", $post, $has_errors ? 'error' : 'none');
					
				endforeach; // $validation
				
				$has_errors = apply_filters('abt_custom_register_extra_validation', $has_errors, $key, $attr, $post);
				
				if( $has_errors ) {
					// do something?
					do_action('abt_custom_register_has_errors', $key, $errors);
				}
				// and if it's a special field
				elseif( /* 'user_login' == $key || */
						'user_pw1' == $key
						) {
					$this->user_pw = $post;
					$this->user_name = $_POST['user_login'];	// get the other associated field
				}
				// and if it's still not a special field
				elseif( 'user_pw2' != $key ) {
					$this->set_userdata($key, $post);
				}
				
			}// isset data-validation
			else {
				$this->set_userdata($key, $post);
				### _log("no validation requested for $key", $post);
			}
		}// foreach $fields
		
		// adjust errors?
		$errors = apply_filters('abt_custom_register_errors', $errors);
		
		// special case - display name
		if( !isset($this->userdata['display_name']) &&
			isset( $this->userdata['first_name'] ) &&
			isset( $this->userdata['last_name'] )) {
			//update display name
			$this->set_userdata('display_name', $this->userdata['first_name'] . ' ' . $this->userdata['last_name']);
		}
		
		###_log(__FUNCTION__, array('fields' => $this->fields, 'post'=>$_POST, 'userdata'=>$this->userdata, 'errors'=>$errors), $this);
		
		// special case - user password
		//-- handled in hook_user_register from $this->user_pw
		
		self::flash_var('errors', $errors);
		
		return $errors;
	}//--	fn
	
	/**
	 * Appropriate error wrapper
	 * @param $errors
	 * @param $name
	 * @param $message
	 */
	function errors($errors, $name, $message){
		global $wp_version;

		if($wp_version < 2.5){
			$errors[$name] = $message;
		}else{
			$errors->add($name,$message);
		}
		return $errors;
	}
	
	/**
	 * Get/Set session variable (or clear it)
	 * @param $key the variable key; if given as FALSE will clear the bucket
	 * @param $value {optional} if given, will set the value.  if not given, return the value
	 *
	 * @return the variable, if requested
	 */
	public static function flash_var($key){
		// start the bucket if DNE
		if( !isset($_SESSION['abt_custom_register']) || false === $key )
			$_SESSION['abt_custom_register'] = array();
		
		// just return the value
		if( 1 == func_num_args() ) {
			return isset($_SESSION['abt_custom_register'][$key]) ? $_SESSION['abt_custom_register'][$key] : NULL;
		}
		
		$value = func_get_arg(1);
		$_SESSION['abt_custom_register'][$key] = $value;
	}
	
	/**
	 * Alternate handling of resetting the user's password.
	 *
	 * @uses $wpdb WordPress Database object
	 *
	 * @param string $key Hash to validate sending user's password
	 */
	function reset_password($user, $new_pass) {
		do_action('password_reset', $user, $new_pass);
	
		wp_set_password($new_pass, $user->ID);
	
		wp_password_change_notification($user);
	}//--	fn	reset_password
	
	
		#region ----------- magic methods --------------
		
		private function set_userdata($key, $value){
			$this->userdata[$key] = $value;
		}
			
		#endregion ----------- magic methods --------------
	
	#endregion ------------------ REGISTER -------------------------
	
	
	
	
	
	
	
	#region ---------- magic methods -----------
	
	/**
	 * Lazy implementation of copied variables
	 * @var unknown_type
	 */
	
	private $data = array();
	
	public function & __get($name){
		if(array_key_exists($name, $this->data)){
			return $this->data[$name];
		}
		
		trigger_error('Undefined property ['.$name.']', E_USER_NOTICE);
		return null;
	}//--	fn	__get
	
	public function __set($name, $value) {
		$this->data[$name] = $value;
	}//--	fn	__set
	
	/**  As of PHP 5.1.0  */
	public function __isset($name) {
		return isset($this->data[$name]);
	}

	/**  As of PHP 5.1.0  */
	public function __unset($name) {
		unset($this->data[$name]);
	}
	
	#endregion ---------- magic methods -----------
}

// engage!
$abtcua = ABT_Custom_User_Access::instance('ABT_Custom_User_Access');
$abtcua->init();

?>