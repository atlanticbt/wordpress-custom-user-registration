<?php ob_start();

/*  ------------------------------------- EDIT CONTENT BELOW --------------------------------- */ ?>
<h1>Welcome <?php echo $user->display_name ?>, to <?php echo get_bloginfo('site_name'); ?></h1>

<p>Thank you for joining our site.</p>

<p><strong>Your Username is <code><?php echo $user_login; ?></code> and your Password is <code><?php echo $plaintext_pass?></code>.</strong></p>
<p>You can log-in to your account and manage your settings from the <a href="<? echo $login_url; ?>" title="Visit login page">User Login page</a>

<p>Thanks again for your support.</p>
<div style="text-align:center">
	<strong>Sincerely, </strong>
	<em>The Staff at <?php echo $site_name; ?></em>
</div>

<?php /*  ------------------------------------- EDIT CONTENT ABOVE --------------------------------- */ 

$output = ob_get_clean();

return $output; ?>