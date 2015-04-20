<?php

if (!defined('ABSPATH')) die('Access denied.');

if (!is_admin() || !current_user_can('manage_options')) exit;

global $wp_roles;
global $simba_two_factor_authentication;

$tfa->setUserHMACTypes();

?><div class="wrap">

	<div>

	<?php screen_icon(); ?>
	<h2><?php echo sprintf(__('Two Factor Authentication (Version: %s) - Admin Settings', SIMBA_TFA_TEXT_DOMAIN), $simba_two_factor_authentication->version); ?> </h2>

	<?php
		if (!class_exists('Simba_Two_Factor_Authentication_Premium')) {
			?>
			<a href="https://www.simbahosting.co.uk/s3/product/two-factor-authentication/"><?php _e("Premium version", SIMBA_TFA_TEXT_DOMAIN);?></a> | 
			<?php
		}
	?>
	<a href="<?php echo apply_filters('simba_tfa_support_url', 'https://wordpress.org/support/plugin/two-factor-authentication/');?>"><?php _e("Support", SIMBA_TFA_TEXT_DOMAIN);?></a> | 
	<a href="https://profiles.wordpress.org/davidanderson#content-plugins"><?php _e('More free plugins', SIMBA_TFA_TEXT_DOMAIN);?></a> | 
	<a href="http://updraftplus.com">UpdraftPlus - <?php _e('WordPress backups', SIMBA_TFA_TEXT_DOMAIN); ?></a> | 
		<a href="https://www.simbahosting.co.uk/s3/shop/"><?php _e('More premium plugins', SIMBA_TFA_TEXT_DOMAIN);?></a>  | 
		<a href="https://twitter.com/updraftplus"><?php _e('Twitter', SIMBA_TFA_TEXT_DOMAIN);?></a> | 
		
		<a href="http://david.dw-perspective.org.uk"><?php _e("Lead developer's homepage", SIMBA_TFA_TEXT_DOMAIN);?></a> 
		<br>

	</div>
	<div style="max-width:800px;">

	<?php
		if (is_multisite()) {
			global $wpdb;
			if (is_super_admin() && is_object($wpdb) && isset($wpdb->blogid) && 1 == $wpdb->blogid) {
				?>
				<p style="font-size: 120%; font-weight: bold;">
				<?php _e('N.B. These two-factor settings apply to your entire WordPress network. (i.e. They are not localised to one particular site).', SIMBA_TFA_TEXT_DOMAIN);?>
				</p>
				<?php
			} else {
				// Should not be possible to reach this; but an extra check does not hurt.
				die('Security check');
			}
		}
	?>

	<form method="post" action="options.php" style="margin-top: 12px">
	<?php
		settings_fields('tfa_user_roles_group');
	?>
		<h2><?php _e('User roles', SIMBA_TFA_TEXT_DOMAIN); ?></h2>
		<?php _e('Choose which user roles will have two factor authentication enabled.', SIMBA_TFA_TEXT_DOMAIN); ?>
		<p>
	<?php
		$simba_two_factor_authentication->tfaListUserRolesCheckboxes();
	?></p>
	<?php submit_button(); ?>
	</form>
	
	<div>
		<hr>
		<form method="post" action="options.php" style="margin-top: 40px">
		<?php
			settings_fields('tfa_xmlrpc_status_group');
		?>
			<h2><?php _e('XMLRPC requests', SIMBA_TFA_TEXT_DOMAIN); ?></h2>
			<?php 

			echo '<p>';
			echo __("XMLRPC is a feature within WordPress allowing other computers to talk to your WordPress install. For example, it could be used by an app on your tablet that allows you to blog directly from the app (instead of needing the WordPress dashboard).");

			echo '<p></p>';

			echo __("Unfortunately, XMLRPC also provides a way for attackers to perform actions on your WordPress site, using only a password (i.e. without a two-factor password). More unfortunately, authors of legitimate programmes using XMLRPC have not yet added two-factor support to their code.", SIMBA_TFA_TEXT_DOMAIN);

			echo '<p></p>';

			echo __(" i.e. XMLRPC requests coming in to WordPress (whether from a legitimate app, or from an attacker) can only be verified using the password - not with a two-factor code. As a result, there not be an ideal option to pick below. You may have to choose between the convenience of using your apps, or the security of two factor authentication.", SIMBA_TFA_TEXT_DOMAIN);

			echo '</p>';
			?>
			<p>
			<?php
				$simba_two_factor_authentication->tfaListXMLRPCStatusRadios();
			?></p>
			<?php submit_button(); ?>
		</form>
	</div>
	
	<hr>
	<form method="post" action="options.php" style="margin-top: 40px">
	<?php
		settings_fields('simba_tfa_default_hmac_group');
	?>
		<h2><?php _e('Default algorithm', SIMBA_TFA_TEXT_DOMAIN); ?></h2>
		<?php _e('Your users can change this in their own settings if they want.', SIMBA_TFA_TEXT_DOMAIN); ?>
		<p>
		<?php
			$simba_two_factor_authentication->tfaListDefaultHMACRadios();
		?></p>
		<?php submit_button(); ?>
	</form>
	<hr>
	<br><br>
	<h2><?php _e("Users' settings", SIMBA_TFA_TEXT_DOMAIN); ?></h2>
	<p>

		<?php
			if (!class_exists('Simba_Two_Factor_Authentication_Premium')) { ?>

				<a href="https://www.simbahosting.co.uk/s3/product/two-factor-authentication/"><?php _e("The Premium version of this plugin allows you to see and reset the TFA settings of other users.", SIMBA_TFA_TEXT_DOMAIN); ?></a>

				<a href="https://wordpress.org/plugins/user-switching/"><?php _e('Another way to do that is by using a user-switching plugin like this one.', SIMBA_TFA_TEXT_DOMAIN); ?></a>

			<?php } ?>
		
		<?php do_action('simba_tfa_users_settings'); ?>

		<?php
		
		// Disabled
		if (1==0) {
		//List users and type of tfa
		foreach($wp_roles->role_names as $id => $name)
		{	
			$setting = $simba_two_factor_authentication->get_option('tfa_'.$id);
			$setting = $setting === false || $setting ? 1 : 0;
			if(!$setting)
				continue;
			
			$users_q = new WP_User_Query( array(
			  'role' => $name
			));
			$users = $users_q->get_results();
			
			if(!$users)
				continue;
			
			print '<h3>'.$name.'s</h3>';
			
			foreach( $users as $user )
			{
				$userdata = get_userdata( $user->ID );
				$tfa_type = get_user_meta($user->ID, 'simbatfa_delivery_type', true);
				print '<span style="font-size: 1.2em">'.esc_attr( $userdata->user_nicename ).'</span>';
				if(!$tfa_type)
					print ' - '.__('Default', SIMBA_TFA_TEXT_DOMAIN);
				else
					print ' - <a class="button" href="'.esc_url(add_query_arg(array('tfa_change_to_email' => 1, 'tfa_user_id' => $user->ID))).'">'.__('Change to email', SIMBA_TFA_TEXT_DOMAIN).'</a>';
				print '<br>';
			}
		}
		}

		?>
	<hr>
	<?php if (!class_exists('Simba_Two_Factor_Authentication_Premium')) { ?>
	<h2><?php _e('Premium version', SIMBA_TFA_TEXT_DOMAIN); ?></h2>
	<p>
		<a href="https://www.simbahosting.co.uk/s3/product/two-factor-authentication/"><?php _e("If you want to say 'thank you' or help this plugin's development, or get extra features, then please take a look at the premium version of this plugin.", SIMBA_TFA_TEXT_DOMAIN); ?></a> <?php _e('It comes with these extra features:', SIMBA_TFA_TEXT_DOMAIN);?><br>
	</p>
	<p>
		<ul style="list-style: disc inside;">
			<li><strong><?php _e('Emergency codes', SIMBA_TFA_TEXT_DOMAIN);?></strong> - <?php _e('provide your users with one-time codes to use in case they lose their device.', SIMBA_TFA_TEXT_DOMAIN);?></li>
			<li><strong><?php _e('Manage all users centrally', SIMBA_TFA_TEXT_DOMAIN);?></strong> - <?php _e('enable, disable or see TFA codes for all your users from one central location.', SIMBA_TFA_TEXT_DOMAIN);?></li>
			<li><strong><?php _e('More shortcodes', SIMBA_TFA_TEXT_DOMAIN);?></strong> - <?php _e('flexible shortcodes allowing you to design your front-end settings page for your users exactly as you wish.', SIMBA_TFA_TEXT_DOMAIN);?></li>
			<li><strong><?php _e('Personal support', SIMBA_TFA_TEXT_DOMAIN);?></strong> - <?php _e('access to our personal support desk for 12 months.', SIMBA_TFA_TEXT_DOMAIN);?></li>
		</ul>
	</p>
	<hr>
	<?php } ?>

	<h2><?php _e('Translations', SIMBA_TFA_TEXT_DOMAIN); ?></h2>
	<p>
		<?php _e("If you translate this plugin, please send the translations .po-file to us so we can include it in future releases - paste a link in the plugin's support forum.", SIMBA_TFA_TEXT_DOMAIN); ?>
		<br>
	</p>

</div>
</div>