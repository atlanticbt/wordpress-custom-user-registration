<?php
class WP_Options_Page {

/**
 * namespace, option identifier
 */
private $GUID;
/**
 * Additional settings, such as whether or not to make this an option page or not
 * @var array
 */
private $settings;
/**
 * page title
 */
private $title;
/**
 * sidebar title
 */
private $short;

/**
 * What page this appears on?
 * @var string
 */
public $page, $slug;

/**
 * The grouped value of the settings option
 * @var array
 */
private $value;

/**
 * Simple constructor - don't know usage yet, so just take an ID
 * @param $GUID
 */
function __construct($GUID) {
	$this->GUID = $GUID;
	$this->value = get_option($this->GUID);	//get value for later
}//--	fn	__construct




#region ======================= RETRIEVE VALUES ============================

/**
 * Get Option of for given key, section
 * @param unknown_type $key
 */
public function option($key, $section = FALSE) {
	if( FALSE === $section ){
		return abt_v($this->value[$key]);
	}
	
	return abt_v($this->value[$section][$key]);
}//--	fn	option

#endregion ======================= RETRIEVE VALUES ============================




/**
 * Get one of the admin page properties, like style, etc
 */
private function setting($key, $value = null){
	if( ! isset($this->settings[$key]) ){
		wp_die(__CLASS__ . ' setting [' . $key . '] not available');
	}
	
	if( null === $value ) return $this->settings[$key];
	
	else $this->settings[$key] = $value;
}//--	fn	setting




/**
 * Set up new option page + settings
 * @param $title
 * @param $short
 * @param mixed $settings
 */
function register($title, $short, $settings = array()) {
	$this->title = $title;
	$this->short = $short;
	$this->slug = $this->GUID.'_config';
	
	$defaults = array(
		'style' => 'admin'						//where this page is located (general option, its own section, etc)
		,'capabilities' => 'manage_options'		//who can access this page
		#, 'icon' => 'icon.png'					// menu icon
	);
	$this->settings = wp_parse_args($settings, $defaults);
	
	add_action('init', array(&$this, 'on_start'));
	add_action('admin_init', array(&$this, 'init') );
	add_action('admin_menu', array(&$this, 'add_page') );
	
	//don't allow the rest if we're not on the expected page
	//	must allow 'options.php' for option saving???
	global $pagenow;
	###pbug( __FILE__, __LINE__, $pagenow, abt_v($_GET['page']), (isset($_GET['page']) ? $_GET['page'] : 'NOTSET'), $this->GUID);

	///TODO: reeeaaaally weird permissions error triggered by abt_v() - no idea why this happens, confirm by uncommenting above pbug
	if( /* $this->setting('style').'.php' === $pagenow && */ 'options.php' === $pagenow || $this->slug === ( isset($_GET['page']) ? $_GET['page'] : NULL )) {	}
	else { return false; }
	
	$this->sections = array();
	
	return true;
}//--	fn	__construct

public function on_start(){
	if (!session_id()) session_start();	//protect session
}//--	fn	on_start

// Init plugin options to white list our options
public function init(){
	register_setting( $this->GUID, $this->GUID /* actually given as slightly different: ozh_sample vs. ozh_sampleoptions */, array(&$this, 'sanitize') );
}




#region ====================== PAGE and PAGE Styles =========================

// Add menu page
public function add_page() {
	$style = $this->setting('style');
	if( is_array($style) && 'submenu' === $style[0] ):
		$this->page = add_submenu_page($style[1], $this->title, $this->short, $this->setting('capabilities'), $this->slug, array(&$this, 'render'));
	elseif( 'options' === $style || 'options-general' === $style ):
		$this->page = add_options_page($this->title, $this->short, $this->setting('capabilities'), $this->slug, array(&$this, 'render'));
	else:
		$this->page = add_menu_page($this->title, $this->short, $this->setting('capabilities'), $this->slug, array(&$this, 'render'), $this->setting('icon'));
	endif;
	
	### pbug(__FUNCTION__, __LINE__, $style, $this->page);
	
}//--	fn	add_page

/**
 * Add styles to the option page
 * @param mixed $method callback function name ( like "array(&$this, 'add_admin_headers')" )
 */
public function add_styles($method){
	//add admin stylesheet
	add_action('admin_print_styles-' . $this->page, $method);
	return $this;
}//--	fn	add_styles
/**
 * Add scripts to the option page
 * @param mixed $method callback function name ( like "array(&$this, 'add_admin_headers')" )
 */
public function add_scripts($method){
	//add admin scripts
	add_action('admin_print_scripts-' . $this->page, $method);
	return $this;
}//--	fn	add_scripts


	#region ------------ Arbitrary Menu Items ---------------
	
	/**
	 * Add an arbitrary item to the FRONTEND ADMIN BAR menu, complete with link
	 * Must hook with: add_action( 'wp_before_admin_bar_render', 'mytheme_admin_bar_render' );, calling this function in mytheme_admin_bar_render method
	 *
	 * @param $title link title
	 * @param $href {default: index.php} where to go, such as name of file -- can use external links, if no http in title will use admin_url
	 * @param $id {optional} slug identifier, defaults to sanitized title value
	 * @param $meta array of any of the following options: array( 'parent' => {ID of parent menu, omit for root}, 'html' => '', 'class' => '', 'onclick' => '', target => '', title => '' );
	 *
	 * @source http://www.wprecipes.com/add-custom-links-to-wordpress-admin-bar
	 */
	static function arbitrary_admin_bar_item($title, $href = 'index.php', $id = false, $meta = false ) {
		
		global $wp_admin_bar;
		
		//basic args
		$args = array('title'=>$title, 'href'=>$href, 'parent' => abt_v($meta['parent'], false), 'meta' => $meta);
		
		//use admin_url if not extern
		if( 0 !== strpos($href, 'http:') ){
			$args['href'] = admin_url($href);
		}
		
		if( false !== $id ) $args['id'] = $id;
		
		$wp_admin_bar->add_menu( $args );
		
		/*   ------ EXAMPLE ----
		$wp_admin_bar->add_menu( array(
			'parent' => 'new-content', // use 'false' for a root menu, or pass the ID of the parent menu
			'id' => 'new_media', // link ID, defaults to a sanitized title value
			'title' => __('Media'), // link title
			'href' => admin_url( 'media-new.php'), // name of file
			'meta' => false // array of any of the following options: array( 'html' => '', 'class' => '', 'onclick' => '', target => '', title => '' );
		));
		*/
	}//--	fn	_arbitrary_admin_menu_item
	
	/**
	 * Add an arbitrary item to the admin menu, complete with link
	 * Must hook with: add_action( 'admin_menu', 'mytheme_admin_bar_render' );, calling this function in mytheme_admin_bar_render method
	 *
	 * @param $title link title
	 * @param $href {default: index.php} where to go, such as name of file -- can use external links, if no http in title will use admin_url
	 * @param $id {optional} slug identifier, defaults to sanitized title value
	 * @param $meta array of any of the following options: array( 'parent' => {ID of parent menu, omit for root}, 'position' => {numerical index of where to place it, will replace existing indices}, 'capabilities' => {permissions string} );
	 *
	 * @source http://wordpress.stackexchange.com/questions/1039/adding-an-arbitrary-link-to-the-admin-menu/3831#3831
	 */
	static function arbitrary_admin_item($title, $href = 'index.php', $meta = false ) {
		global $submenu;
		
		//debug helper
		if( isset( $meta['debug'] ) ){ pbug(__FUNCTION__, $submenu); }
		
		//default adding to root
		$parent = abt_v( $meta['parent'], 'index.php' );
		//default adding it to end of parent
		$position = abt_v( $meta['position'], ( isset( $submenu[$parent] ) ? count( $submenu[$parent] ) + 100 : 1 ));
		//permissions
		$capabilities = abt_v( $meta['capabilities'], 'manage_options' );
		//use admin_url if not extern
		if( 0 !== strpos($href, 'http:') ){
			$href = admin_url($href);
		}
		
		//inject in menu
		$submenu[$parent][$position] = array( $title, $capabilities , $href );
	}//--	fn	_arbitrary_admin_menu_item
	
	
	#region ------------ Arbitrary Menu Items ---------------


#endregion ====================== PAGE and PAGE Styles =========================





#region ======================= SECTIONS AND FIELDS ============================

	#region ------------------ SECTIONS ------------------

	private $sections;
	
	function add_section($section_id, $label, $description) {
		$section = $section_id;
		$this->sections[$section_id] = array(
			'label' => $label,
			'description'	=> $description,
			'fields' => array()
		);
		add_settings_section( $section_id, $label, array( &$this, 'section_'.$section_id ), $this->page);
		return $this;
	}//--	fn	add_section
	
	/**
	 * used by magic call to customize each section
	 * @param $section_id
	 */
	function section_router($section_id) {
		echo '<p>'.$this->sections[$section_id]['description'].'</p>';
	}
	
	/**
	 * special routing assistance for section_XYZ
	 */
	function __call($method,$args) {
		#pdump('magic router', $args);
		if(0 === strpos($method, 'section_')){
			$this->section_router($args[0]['id']);
		}
	}
	
	#endregion ------------------ FIELDS ------------------
	
	
	
	
	
	#region ------------------ FIELDS ------------------

	/**
	 * args can contain sanitize details
	 * @param $section_id
	 * @param $field_id
	 * @param $label
	 * @param $args
	 */
	function add_field($section_id, $field_id, $label, $args=array()) {
		$value = abt_v($this->value[$section_id][$field_id]);
		
		$default_args = array(
			'type'    	=> 'text',
			'id'		=> $field_id,
			'std'		=> '',
			'value'		=> $value,
			//allow user override corrections later:  'label_for'	=> $this->GUID.'-'.$section_id.'-'.$field_id,
			'class'		=> '',
			'section'	=> $section_id
			,'label'	=> $label
		);
		
		$args = wp_parse_args( $args, $default_args );
		$args['label_for'] = $this->GUID.'-'.$section_id.'-'.$field_id;
		
		$this->sections[$section_id]['fields'][$field_id] = $args;
		
		add_settings_field( $field_id, $label, array( &$this, 'render_field'), $this->page, $section_id, $args);
		return $this;
	}
	
	// TODO: create options
	public function sanitize($input){
		### _log( __CLASS__ . '::' . __FUNCTION__, $input );
		/* Function to sanitize setting */
		
		//loop sections - from returned response
		foreach($input as $section_id => &$fields){
			//loop fields within returned response section
			foreach($fields as $field_id=>&$f){
				
				//get the field properties
				$field = $this->sections[$section_id]['fields'][$field_id];
				
				//skip non-sanitized fields
				if( ! isset( $field['sanitize'] )) continue;
				
				foreach($field['sanitize'] as $style){
					//if array, extract key as style, and value as additional arguments
					if( is_array($style) ){
						$sanitize_args = current( array_values($style) );
						$style = current( array_keys($style) );
					}
					
					unset($pattern);	//unset for looping
					$fail = false;
					
					switch ($style) {
						case 'required':
							if( is_null($f) || '' == $f ) {
								add_settings_error( $this->GUID, $section_id.'-'.$field_id, "{$field['label']} is required.", 'error');
								$fail = true;
							}
							break;
						case 'url':
							if( false === filter_var($f, FILTER_VALIDATE_URL) ) {
								add_settings_error( $this->GUID, $section_id.'-'.$field_id, "{$field['label']} is not a valid URL.", 'error');
								$fail = true;
							}
							break;
						case 'text':
							/*sanitize*/
							$f = wp_filter_nohtml_kses($f);
							break;
						case 'alpha':
							$pattern = '/^([a-zA-Z])*$/i';
							break;
						case 'alpha_numeric':
							$pattern = '/^([a-zA-Z0-9])*$/i';
							break;
						case 'boolean':
							if( ! ( 1 == $f || 0 == $f ) ) {
								add_settings_error( $this->GUID, $section_id.'-'.$field_id, "{$field['label']} can only be \"Yes/No\".", 'error');
								$fail = true;
							}
							$f = ( 1 == $f ? 1 : 0 );
							break;
						case 'numeric':
							if( ! is_numeric($f) ){
								add_settings_error( $this->GUID, $section_id.'-'.$field_id, "{$field['label']} must be a valid number.", 'error');
								$fail = true;
							}
							break;
						case 'email':
							$pattern = '/^([A-Za-z0-9_\-\.\+])+\@([A-Za-z0-9_\-\.])+\.([A-Za-z]{2,4})$/';
							break;
						case 'min':
							if( $sanitize_args > $f ) {
								add_settings_error( $this->GUID, $section_id.'-'.$field_id, "{$field['label']} must be greater than $sanitize_args.", 'error');
								$fail = true;
							}
							break;
						case 'max':
							if( $sanitize_args < $f ) {
								add_settings_error( $this->GUID, $section_id.'-'.$field_id, "{$field['label']} must be less than $sanitize_args.", 'error');
								$fail = true;
							}
							break;
						case 'password':
							$pattern = '/^.*(?=.{6,})(?=.*[a-zA-Z])(?=.*[0-9])(?=.*[!@#$%^&*+=])[a-zA-Z0-9!@#$%^&*+=]+$/';
							if( 6 > strlen($f) || 0 === preg_match($pattern, $f) ){
								add_settings_error( $this->GUID, $section_id.'-'.$field_id, "{$field['label']} must be at least 8 characters, and contain at least 1 letter, number, and symbol.", 'error');
								$fail = true;
							}
							unset($pattern);
							break;
						case 'value':
							if( $sanitize_args !== $f ) {
								add_settings_error( $this->GUID, $section_id.'-'.$field_id, "{$field['label']} does not match expected value.", 'error');
								$fail = true;
							}
							break;
						default:
							/*check min/max, etc */
							if( 'checkbox' === $f ):
								$f = ( 1 === $f ? 1 : 0 );
							endif;
							
							break;
					}//	switch(style)
					
					//if any of the checks set a pattern, run it here
					if( isset($pattern) ){
						if( 0 === preg_match($pattern, $f, $matches) ){
							add_settings_error( $this->GUID, $section_id.'-'.$field_id, "{$field['label']} did not pass $style validation.", 'error');
							$fail = true;
						}
					}
					
					//reset to "original" value if fail
					if( true === $fail ){
						$f = $field['value'];
					}
					
				}//	foreach sanitize
			}//	foreach fields
		}// foreach $input
		
		$this->session('post', $input);
		return $input;
	}//--	fn	sanitize
	
	function render_field( $args=array() ){
		extract( $args );
		### pbug('args from '.__FUNCTION__, $args);
		
		$name = $this->GUID.'['.$section.']['.$id.']';
		$display_id = $label_for;
		
		$field = '<div class="field '.$class.'">';
		
			if( isset($prefix) ){
			$field .= $prefix;
		}
		
		switch ( $type ) {
			case 'heading':
				$field = '<h4>' . $description . '</h4>';
				break;
			case 'p':
				$field = '<p id="' . $display_id . '">' . $text . '</p>';
				break;

			case 'checkbox':
				$field = '<input class="checkbox" type="checkbox" id="' . $display_id . '" name="'. $name .'" value="1" ' . checked( $value, 1, false ) . ' /> <label for="' . $display_id . '">' . $description . '</label>';
				break;

			case 'select':
				$field = '<select class="select" id="'. $display_id .'" name="'. $name .'">';

				foreach ( $choices as $val => $label )
					$field .= "\n" . '<option value="' . esc_attr( $val ) . '"' . selected( $value, $val, false ) . '>' . $label . '</option>';

				$field .= "\n</select>";

				break;
				
			case 'multiselect':
				$field = '<select multiple="multiple" class="multiple" id="'. $display_id .'" name="'. $name .'[]" >';

				foreach ( $choices as $val => $label ){
					$selected = (in_array($val, (array)$value)) ? ' selected="selected"' : '';
					$field .= "\n" . '<option value="' . esc_attr( $val ) . '"' . $selected . '>' . $label . '</option>';
				}
				$field .= "\n</select>";

				break;

			case 'radio':
				$i = 0;
				$field = '';
				foreach ( $choices as $val => $label ) {
					$field .= '<input class="radio" type="radio" name="'. $name .'" id="' . $display_id . '" value="' . esc_attr( $val ) . '" ' . checked( $value, $std, false ) . '> <label for="' . $display_id . '">' . $label . '</label>';
					if ( $i < count( $options ) - 1 )
						$field .= '<br />';
					$i++;
				}

				break;

			case 'textarea':
				$field = '<textarea class="regular-text" id="' . $display_id . '" name="'. $name .'" placeholder="' . esc_attr( $std ) . '" rows="5" cols="30">' . wp_htmledit_pre( $value ) . '</textarea>';

				
				break;

			case 'password':
				$field = '<input class="regular-text" type="password" id="' . $display_id . '" name="'. $name .'" value="' . esc_attr( $value ) . '" />';

				break;
			case 'text':
			default:
				$field = '<input id="' . $display_id . '" name="'. $name .'" class="regular-text" type="'.$type.'" placeholder="' . esc_attr( $std ) . '" value="' . esc_attr( $value ) . '" />';
				break;
				
			
		}
		
		if( isset($suffix) ){
			$field .= $suffix;
		}
		
		if( isset($description) && ($type != 'heading') )
			$field .= '<br /><em class="description">' . $description . '</em>';
		
			///TODO: how to display settings errors next to field???
		$field .= '</div>';
			
		//@hookpoint
		$field = apply_filters('wp_options_page_field', $field, $args);
		$field = apply_filters('wp_options_page_field-'.$this->GUID, $field, $args);
		### $field = apply_filters('wp_options_page_field-'.$type, $field, $args);	//redundant?, since type is part of args
		echo $field;
	}
	
	#endregion ------------------ FIELDS ------------------
	
	
#endregion ======================= SECTIONS AND FIELDS ============================

/**
 * Draw the menu page itself - with hookpoints and filters
 */
function render() {
	### $post = $this->session('post');
	### pdump( 'posted vars', $post );
	?>
	<div class="wrap ">
		<h2><?php echo $this->title ?></h2>
		<form method="post" action="<?php
		//@hookpoint
		$form_action = apply_filters('render_action', 'options.php');
		$form_action = apply_filters('render_action-'.$this->GUID, $form_action);
		echo $form_action; ?>">
		<?php
		settings_errors();	//for displaying update and fail messages
		settings_fields($this->GUID);
		do_settings_sections( $this->page );
		do_action('render_after-'.$this->GUID, $this->session('post'));
		?>
			<p class="submit">
			<input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
			</p>
		</form>
	</div>
	<?php
	$this->session_clear('post');
}//--	fn	render


/**
 * SET/GET an associated SESSION value
 * @param string $key identifier of what to save
 * @param mixd $value {optional} if given, what to save; if not given, returns the current value
 * @return if $value not given (null), returns the current session value for key
 */
private function session($key, $value = null) {
	if( null === $value) return abt_kv($_SESSION, $this->GUID, $key);
	
	if( !isset( $_SESSION[$this->GUID] )) $_SESSION[$this->GUID] = array();
	
	return $_SESSION[ $this->GUID ][ $key ] = $value;
}//--	fn	session
/**
 * Empty the session
 * @param $key
 */
private function session_clear($key){
	if( isset( $_SESSION[$this->GUID] )) unset($_SESSION[$this->GUID]);
}//--	fn	session_clear

}//--	class	WP_Options_Page