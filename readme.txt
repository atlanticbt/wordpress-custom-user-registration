=== Custom User Registration ===
Contributors: atlanticbt, zaus, heyoka, tnblueswirl
Donate link: http://atlanticbt.com
Tags: login, user registration
Requires at least: 3.0
Tested up to: 3.3.1
Stable tag: trunk
License: GPLv2 or later

Customize the user registration page with additional validated fields.  Hooks right into existing parts of the registration process.

== Description ==

Customize the user registration page with additional validated fields.  Hooks right into existing parts of the registration process.

Provides a number of hooks to allow further customization: fields, validation, email header/message/template, custom signup url (if used with other plugins like BuddyPress).

Works with anything using the regular WP register hooks, like [BuddyPress][] and [Prospress][].

[BuddyPress]: http://wordpress.org/extend/plugins/buddypress/ "BuddyPress forum login"
[Prospress]: http://wordpress.org/extend/plugins/prospress/ "Prospress Auction Plugin"

Parts of the functionality in this plugin are based on [Ozh' "Sample Options"][].

[Ozh' "Sample Options"]: http://planetozh.com/blog/2009/05/handling-plugins-options-in-wordpress-28-with-register_setting/ "Ozh' Sample Options"

== Installation ==

1. Upload plugin folder `custom-user-registration` to your plugins directory (`/wp-content/plugins/`)
2. Activate plugin
3. Go to new admin page _User Login - ABT_ and change the registration url, if needed.

Please note that this includes an instance of `Singleton` and `WP_Options_page`, both taken from the [WP-Dev-Library][] plugin, so if you are also using that plugin please be aware of potential conflicts.  This plugin checks for the existance of those classes before including files, so if you experience any issues you can remove those lines.

[WP-Dev-Library]: http://wordpress.org/extend/plugins/wp-dev-library/ "Wordpress Developer Library Plugin"

== Hooks ==

1. `abt_custom_login_nometa`
    determine which fields are not treated as usermeta, but instead directly on user table
    format: pipe-separated, default = `'|user_url|display_name|'`
2. `abt_custom_login_fields`
    add or remove additional login fields
3. `abt_custom_login_extra_validation`
    apply extra validation, return whether it has errors or not - uses $has_errors, $key, $attr, $post
4. `abt_custom_login_has_errors`
    do something with the errors instead of saving the field
5. `abt_custom_login_email_templates`
    adjust default template names
6. `abt_custom_login_email_headers`
    change default email headers
7. `abt_custom_login_email_message`
    change email message before it's sent to user
8. `abt_custom_register_url`
    change where the form redirects to on error; not completely working, so please rely on the admin option instead.
9. `abt_custom_register_admin_settings`
    add more admin settings (using `WP_Options_Page` class)

== Frequently Asked Questions ==

NOTE: All hooks should be placed In your theme's functions.php file.

= How do I add extra fields? =

* Use the hook `abt_custom_register_fields`.  Append or replace items in the `$fields` array with an array of attributes.
* Specify validation with `data-validation`.  See plugin file for examples of password and name fields.
* Make sure that, if you're providing default WP fields, that the field names are correct.

<pre>
function YOUR_register_fields($fields){
	$fields []= array('name'=>'user_url', 'type'=>'text', 'class'=>'input url', 'size'=>20, 'label'=>'Your Website', 'data-validation'=>'url');
	$fields []= array('name'=>'aim', 'type'=>'text', 'class'=>'input social-client', 'size'=>20, 'label'=>'AIM', 'data-validation'=>'alphanumeric');
	
	// set name required
	$fields[3]['data-validation'] = array('required', 'string');
	
	return $fields;
}
add_filter('abt_custom_register_fields', 'YOUR_register_fields');
</pre>

= How do I change the email? =

** Headers **:
<pre>
function YOUR_register_email_headers($headers){
	$headers []= 'Bcc:youremail@domain.com';
	return $headers;
}
add_filter('abt_custom_register_email_headers', 'YOUR_register_email_headers');
</pre>

** Template **:
Just copy `email-signup.tpl.php` from the plugin folder to your theme folder.  Or use the hook `abt_custom_login_email_templates`.

= How do I customize my thank-you message? =

On your custom thank-you page, add something like the following:

        // check if we had a successful signup - indicated by a notification in session
        	$referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : NULL;
        	if( false !== strpos($referer, 'action=register')
        		|| (
        			true === ABT_Custom_User_Access::flash_var('notification')
        		)){
        			?>
        			<p>Thank you for registering!  Please check your email for a confirmation message.</p>
        			<?php
        			// clear the flash message
        			ABT_Custom_User_Access::flash_var(false);
        	}

== Screenshots ==

1. Customized login page (via [BuddyPress][] forums)
2. Simple admin settings.
3. Customized thank-you page

[BuddyPress]: http://wordpress.org/extend/plugins/buddypress/ "BuddyPress forum login"

== Changelog ==

= 0.4 =
Fixed some conflicts between Custom User Registration and WP-Library plugins

= 0.3 =
Changed function names to avoid conflicts (kv() and v() are now abt_kv() and abt_v() respectively)

= 0.2 =
Cleaned up for registration-agnostic page

= 0.1 =
Pulled from wp-auction plugin.

== Upgrade Notice ==

None

== About AtlanticBT ==

From [About AtlanticBT][].

= Our Story =

> Atlantic Business Technologies, Inc. has been in existence since the relative infancy of the Internet.  Since March of 1998, Atlantic BT has become one of the largest and fastest growing web development companies in Raleigh, NC.  While our original business goal was to develop new software and systems for the medical and pharmaceutical industries, we quickly expanded into a business that provides fully customized, functional websites and Internet solutions to small, medium and larger national businesses.

> Our President, Jon Jordan, founded Atlantic BT on the philosophy that Internet solutions should be customized individually for each client’s specialized needs.  Today we have expanded his vision to provide unique custom solutions to a growing account base of more than 600 clients.  We offer end-to-end solutions for all clients including professional business website design, e-commerce and programming solutions, business grade web hosting, web strategy and all facets of internet marketing.

= Who We Are =

> The Atlantic BT Team is made up of friendly and knowledgeable professionals in every department who, with their own unique talents, share a wealth of industry experience.  Because of this, Atlantic BT always has a specialist on hand to address each client’s individual needs.  Due to the fact that the industry is constantly changing, all of our specialists continuously study the latest trends in all aspects of internet technology.   Thanks to our ongoing research in the web designing, programming, hosting and internet marketing fields, we are able to offer our clients the most recent and relevant ideas, suggestions and services.

[About AtlanticBT]: http://www.atlanticbt.com/company "The Company Atlantic BT"
[WP-Dev-Library]: http://wordpress.org/extend/plugins/wp-dev-library/ "Wordpress Developer Library Plugin"
[BuddyPress]: http://wordpress.org/extend/plugins/buddypress/ "BuddyPress forum login"
[Prospress]: http://wordpress.org/extend/plugins/prospress/ "Prospress Auction Plugin"
