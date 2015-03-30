<?php

if (!defined('ABSPATH')) die('Access denied.');

global $current_user, $simba_two_factor_authentication, $wpdb;

if (is_multisite() && (!is_super_admin() || !is_object($wpdb) || !isset($wpdb->blogid) || 1 != $wpdb->blogid)) {
	die("How did you get here?");
	return;
}

if(!empty($_POST['tfa_enable_tfa']) && !empty($_GET['settings-updated']) == 'true') {
	$tfa->changeEnableTFA($current_user->ID, $_POST['tfa_enable_tfa']);
} elseif (!empty($_POST['tfa_algorithm_type']) && !empty($_GET['settings-updated']) == 'true') {
	$old_algorithm = $tfa->getUserAlgorithm($current_user->ID);
	
	if($old_algorithm != $_POST['tfa_algorithm_type'])
		$tfa->changeUserAlgorithmTo($current_user->ID, $_POST['tfa_algorithm_type']);
}
if(isset($_GET['warning_button_clicked']) && $_GET['warning_button_clicked'] == 1) {
	delete_user_meta($current_user->ID, 'tfa_hotp_off_sync');
}
?>
<style>
	#icon-tfa-plugin {
    	background: transparent url('<?php print plugin_dir_url(__FILE__); ?>img/tfa_admin_icon_32x32.png' ) no-repeat;
	}
	.inside > h3, .normal {
		cursor: default;
		margin-top: 20px;
	}
</style>
<div class="wrap">

	<?php screen_icon('tfa-plugin'); ?>
	<h2><?php echo apply_filters('tfa_white_label', 'Two Factor Authentication'); ?> <?php _e('Settings', SIMBA_TFA_TEXT_DOMAIN); ?></h2>

	<?php $simba_two_factor_authentication->settings_intro_notices(); ?>
	
	<!-- New Radios to enable/disable tfa -->
	<form method="post" action="<?php print esc_attr(add_query_arg('settings-updated', 'true', $_SERVER['REQUEST_URI'])); ?>">
		<h2><?php _e('Activate two factor authentication', SIMBA_TFA_TEXT_DOMAIN); ?></h2>
		<p>
			<?php
				$date_now = get_date_from_gmt(gmdate('Y-m-d H:i:s'), 'Y-m-d H:i:s');
				echo sprintf(__('N.B. It is important for both the server and your TFA device to have accurate time. The current UTC time when this page loaded: %s', SIMBA_TFA_TEXT_DOMAIN), htmlspecialchars($date_now));
			?>
		</p>
		<p>
		<?php
			$simba_two_factor_authentication->tfaListEnableRadios($current_user->ID);
		?></p>
		<?php submit_button(); ?>
	</form>
	<?php
	
		$simba_two_factor_authentication->current_codes_box();

		$simba_two_factor_authentication->advanced_settings_box();

	?>

</div>