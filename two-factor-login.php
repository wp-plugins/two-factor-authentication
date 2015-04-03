<?php
/*
Plugin Name: Two Factor Authentication
Plugin URI: 
Description: Secure your WordPress login forms with two factor authentication - including WooCommerce login forms
Author: David Nutbourne + David Anderson, original plugin by Oskar Hane
Author URI: https://www.simbahosting.co.uk
Version: 1.1.2
License: GPLv2 or later
*/

define('SIMBA_TFA_TEXT_DOMAIN', 'two-factor-authentication');
define('SIMBA_TFA_PLUGIN_DIR', dirname( __FILE__ ));
define('SIMBA_TFA_PLUGIN_URL', plugins_url('', __FILE__));

class Simba_Two_Factor_Authentication {

	public $version = '1.1.2';
	private $php_required = '5.3';

	private $frontend;

	public function __construct() {

		if (file_exists(SIMBA_TFA_PLUGIN_DIR.'/premium.php')) include_once(SIMBA_TFA_PLUGIN_DIR.'/premium.php');

		if (version_compare(PHP_VERSION, $this->php_required, '<' )) {
			add_action('all_admin_notices', array($this, 'admin_notice_insufficient_php'));
			$abort = true;
		}

		if (!function_exists('mcrypt_get_iv_size')) {
			add_action('all_admin_notices', array($this, 'admin_notice_missing_mcrypt'));
			$abort = true;
		}

		if (!empty($abort)) return;

		add_action('wp_ajax_nopriv_simbatfa-init-otp', array($this, 'tfaInitLogin'));

		add_action('wp_ajax_simbatfa_shared_ajax', array($this, 'shared_ajax'));

		add_action('woocommerce_before_customer_login_form', array($this, 'woocommerce_before_customer_login_form'));

		if (is_admin()) {
			//Save settings
			add_action('admin_init', array($this, 'check_possible_reset'));
			
			//Add to Settings menu on sites
			add_action('admin_menu', array($this, 'menu_entry_for_admin'));

			//Add settings link in plugin list
			$plugin = plugin_basename(__FILE__); 
			add_filter("plugin_action_links_".$plugin, array($this, 'addPluginSettingsLink' ));
			add_filter('network_admin_plugin_action_links_'.$plugin, array($this, 'addPluginSettingsLink' ));

			// Entry that everybody gets
			add_action('network_admin_menu', array($this, 'admin_menu'));
			add_action('admin_menu', array($this, 'admin_menu'));

		} else {
			add_action('init', array($this, 'check_possible_reset'));
		}

		add_action('plugins_loaded', array($this, 'plugins_loaded'));

		//Show off sync message for hotp
		add_action('admin_notices', array($this, 'tfaShowHOTPOffSyncMessage'));
		add_action('login_enqueue_scripts', array($this, 'login_enqueue_scripts'));

		
		add_filter('authenticate', array($this, 'tfaVerifyCodeAndUser'), 99999999999, 3);
	}

	public function admin_notice_insufficient_php() {
		$this->show_admin_warning('<strong>'.__('Higher PHP version required', 'updraftplus').'</strong><br> '.sprintf(__('The Two Factor Authentication plugin requires PHP version %s or higher - your current version is only %s.', SIMBA_TFA_TEXT_DOMAIN), $this->php_required, PHP_VERSION), 'error');
	}

	public function admin_notice_missing_mcrypt() {
		$this->show_admin_warning('<strong>'.__('PHP Mcrypt module required', 'updraftplus').'</strong><br> '.__('The Two Factor Authentication plugin requires the PHP mcrypt module to be installed. Please ask your web hosting company to install it.', SIMBA_TFA_TEXT_DOMAIN), 'error');
	}

	private function show_admin_warning($message, $class = "updated") {
		echo '<div class="updraftmessage '.$class.'">'."<p>$message</p></div>";
	}

	public function getTFA()
	{
		if (!class_exists('HOTP')) require_once(SIMBA_TFA_PLUGIN_DIR.'/hotp-php-master/hotp.php');
		if (!class_exists('Base32')) require_once(SIMBA_TFA_PLUGIN_DIR.'/Base32/Base32.php');
		if (!class_exists('Simba_TFA')) require_once(SIMBA_TFA_PLUGIN_DIR.'/includes/class.TFA.php');
		
		$tfa = new Simba_TFA(new Base32(), new HOTP());
		
		return $tfa;
	}

	// "Shared" - i.e. could be called from either front-end or back-end
	public function shared_ajax() {
		if (empty($_POST['subaction']) || empty($_POST['nonce']) || !is_user_logged_in() || !wp_verify_nonce($_POST['nonce'], 'tfa_shared_nonce')) die('Security check.');

		if ($_POST['subaction'] == 'refreshotp') {

			global $current_user;

			$tfa_priv_key_64 = get_user_meta($current_user->ID, 'tfa_priv_key_64', true);

			if (!$tfa_priv_key_64) {
				echo json_encode(array('code' => ''));
				die;
			}

			echo json_encode(array('code' => $this->getTFA()->generateOTP($current_user->ID, $tfa_priv_key_64)));
			exit;
		}

	}

	public function tfaInitLogin() {

		if (empty($_POST['user']) || empty($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'simba_tfa_loginform_nonce')) die('Security check.');

		$tfa = $this->getTFA();
		$res = $tfa->preAuth(array('log' => $_POST['user']));

		echo json_encode(array('status' => $res));
		exit;
	}
	

	// Here's where the login action happens
	public function tfaVerifyCodeAndUser($user, $username, $password)
	{

		$tfa = $this->getTFA();
		
		if (is_wp_error($user)) return $user;

		$params = $_POST;
		$params['log'] = $username;
		$params['caller'] = $_SERVER['PHP_SELF'] ? $_SERVER['PHP_SELF'] : $_SERVER['REQUEST_URI'];
		
		$code_ok = $tfa->authUserFromLogin($params);

		if(!$code_ok)
			return new WP_Error('authentication_failed', '<strong>'.__('Error:', SIMBA_TFA_TEXT_DOMAIN).'</strong> '.__('The one-time password (TFA code) you entered was incorrect.', SIMBA_TFA_TEXT_DOMAIN));
		
		if($user)
			return $user;
			
		return wp_authenticate_username_password(null, $username, $password);
	}

	public function tfaRegisterTwoFactorAuthSettings()
	{
		global $wp_roles;
		if (!isset($wp_roles))
			$wp_roles = new WP_Roles();
		
		foreach($wp_roles->role_names as $id => $name)
		{
			register_setting('tfa_user_roles_group', 'tfa_'.$id);
		}
		
		register_setting('simba_tfa_default_hmac_group', 'tfa_default_hmac');
		register_setting('tfa_xmlrpc_status_group', 'tfa_xmlrpc_on');
	}

	public function tfaListEnableRadios($user_id, $long_label = false)
	{
		if(!$user_id)
			return;
			
		$setting = get_user_meta($user_id, 'tfa_enable_tfa', true);
		$setting = !$setting ? false : $setting;
		
		$tfa_enabled_label = ($long_label) ? __('Enable two-factor authentication', SIMBA_TFA_TEXT_DOMAIN) : __('Enabled', SIMBA_TFA_TEXT_DOMAIN);
		$tfa_disabled_label = ($long_label) ? __('Disable two-factor authentication', SIMBA_TFA_TEXT_DOMAIN) : __('Disabled', SIMBA_TFA_TEXT_DOMAIN);

		print '<input type="radio" id="tfa_enable_tfa_true" name="tfa_enable_tfa" value="true" '.($setting == true ? 'checked="checked"' :'').'> <label for="tfa_enable_tfa_true">'.apply_filters('simbatfa_radiolabel_enabled', $tfa_enabled_label, $long_label).'</label> <br>';
		print '<input type="radio" id="tfa_enable_tfa_false" name="tfa_enable_tfa" value="false" '.($setting == false ? 'checked="checked"' :'').'> <label for="tfa_enable_tfa_false">'.apply_filters('simbatfa_radiolabel_disabled', $tfa_disabled_label, $long_label).'</label> <br>';
	}
		

	public function tfaListAlgorithmRadios($user_id)
	{
		if(!$user_id) return;
				
		$types = array('totp' => __('TOTP (time based - most common algorithm; used by Google Authenticator)', SIMBA_TFA_TEXT_DOMAIN), 'hotp' => __('HOTP (event based)', SIMBA_TFA_TEXT_DOMAIN)); 
		
		$setting = get_user_meta($user_id, 'tfa_algorithm_type', true);
		$setting = $setting === false || !$setting ? 'totp' : $setting;

		foreach($types as $id => $name) {
			print '<input type="radio" id="tfa_algorithm_type_'.esc_attr($id).'" name="tfa_algorithm_type" value="'.$id.'" '.($setting == $id ? 'checked="checked"' :'').'> <label for="tfa_algorithm_type_'.esc_attr($id).'">'.$name."</label><br>\n";
		}
	}

	public function get_option($key) {
		if (!is_multisite()) return get_option($key);
		switch_to_blog(1);
		$v = get_option($key);
		restore_current_blog();
		return $v;
	}

	public function tfaListUserRolesCheckboxes()
	{

		if (is_multisite()) {
			// Not a real WP role; needs separate handling
			$id = '_super_admin';
			$name = __('Multisite Super Admin', SIMBA_TFA_TEXT_DOMAIN);
			$setting = $this->get_option('tfa_'.$id);
			$setting = $setting === false || $setting ? 1 : 0;
			
			print '<input type="checkbox" id="tfa_'.$id.'" name="tfa_'.$id.'" value="1" '.($setting ? 'checked="checked"' :'').'> <label for="tfa_'.$id.'">'.htmlspecialchars($name)."</label><br>\n";
		}

		global $wp_roles;
		if (!isset($wp_roles)) $wp_roles = new WP_Roles();
		
		foreach($wp_roles->role_names as $id => $name)
		{	
			$setting = $this->get_option('tfa_'.$id);
			$setting = $setting === false || $setting ? 1 : 0;
			
			print '<input type="checkbox" id="tfa_'.$id.'" name="tfa_'.$id.'" value="1" '.($setting ? 'checked="checked"' :'').'> <label for="tfa_'.$id.'">'.htmlspecialchars($name)."</label><br>\n";
		}
		
	}

	public function tfaListDefaultHMACRadios()
	{
		$tfa = $this->getTFA();
		$setting = $this->get_option('tfa_default_hmac');
		$setting = $setting === false || !$setting ? $tfa->default_hmac : $setting;
		
		$types = array('totp' => __('TOTP (time based - most common algorithm; used by Google Authenticator)', SIMBA_TFA_TEXT_DOMAIN), 'hotp' => __('HOTP (event based)', SIMBA_TFA_TEXT_DOMAIN));
		
		foreach($types as $id => $name)
			print '<input type="radio" id="tfa_default_hmac_'.esc_attr($id).'" name="tfa_default_hmac" value="'.$id.'" '.($setting == $id ? 'checked="checked"' :'').'> '.'<label for="tfa_default_hmac_'.esc_attr($id).'">'."$name</label><br>\n";
	}

	public function tfaListXMLRPCStatusRadios()
	{
		$tfa = $this->getTFA();
		$setting = $this->get_option('tfa_xmlrpc_on');
		$setting = $setting === false || !$setting ? 0 : 1;
		
		$types = array(
			'0' => __('Do not require 2FA over XMLRPC (best option if you must use XMLRPC and your client does not support 2FA)', SIMBA_TFA_TEXT_DOMAIN),
			'1' => __('Do require 2FA over XMLRPC (best option if you do not use XMLRPC or are unsure)', SIMBA_TFA_TEXT_DOMAIN)
		);
		
		foreach($types as $id => $name)
			print '<input type="radio" name="tfa_xmlrpc_on" id="tfa_xmlrpc_on_'.$id.'" value="'.$id.'" '.($setting == $id ? 'checked="checked"' :'').'> <label for="tfa_xmlrpc_on_'.$id.'">'.$name."</label><br>\n";
	}

	public function tfaShowAdminSettingsPage()
	{
		$tfa = $this->getTFA();
		require_once(SIMBA_TFA_PLUGIN_DIR.'/includes/admin_settings.php');
	}

	public function tfaShowUserSettingsPage()
	{
		$tfa = $this->getTFA();
		include SIMBA_TFA_PLUGIN_DIR.'/includes/user_settings.php';
	}

	public function admin_menu() 
	{
		$tfa = $this->getTFA();
		
		global $current_user;
		if(!$tfa->isActivatedForUser($current_user->ID)) return;
		add_menu_page(__('Two Factor Authentication', SIMBA_TFA_TEXT_DOMAIN), __('Two Factor Auth', SIMBA_TFA_TEXT_DOMAIN), 'read', 'two-factor-auth-user', array($this, 'tfaShowUserSettingsPage'), SIMBA_TFA_PLUGIN_URL.'/img/tfa_admin_icon_16x16.png', 72);
	}

	public function menu_entry_for_admin() {

		// On multisite, only show the entry on site ID 1 - to ensure options get saved in the right place.
		global $current_site, $wpdb;
		// $current_site is not the right way to do this - it is internal, and could be anything
		if (is_multisite() && (!is_super_admin() || !is_object($wpdb) || !isset($wpdb->blogid) || 1 != $wpdb->blogid)) return;

		add_action( 'admin_init', array($this, 'tfaRegisterTwoFactorAuthSettings' ));

		add_options_page(
			__('Two Factor Authentication', SIMBA_TFA_TEXT_DOMAIN),
			__('Two Factor Authentication', SIMBA_TFA_TEXT_DOMAIN),
			'manage_options',
			'two-factor-auth',
			array($this, 'tfaShowAdminSettingsPage')
		);
	}

	public function addPluginSettingsLink($links)
	{
		if (!is_network_admin()) {
			$link = '<a href="options-general.php?page=two-factor-auth">'.__('Plugin settings', SIMBA_TFA_TEXT_DOMAIN).'</a>';
			array_unshift($links, $link);
		} else {
			switch_to_blog(1);
			$link = '<a href="'.admin_url('options-general.php').'?page=two-factor-auth">'.__('Plugin settings', SIMBA_TFA_TEXT_DOMAIN).'</a>';
			restore_current_blog();
			array_unshift($links, $link);
		}

		$link2 = '<a href="admin.php?page=two-factor-auth-user">'.__('User settings', SIMBA_TFA_TEXT_DOMAIN).'</a>';
		array_unshift($links, $link2);

		return $links;
	}

	public function check_possible_reset() {
		if(!empty($_GET['simbatfa_priv_key_reset']) && !empty($_REQUEST['nonce']) && wp_verify_nonce($_REQUEST['nonce'], 'simbatfa_reset_private_key'))
		{
			$this->reset_private_key_and_emergency_codes();
// 			if (empty($_REQUEST['noredirect'])) exit;
			exit;
		}
		
	}

	public function reset_private_key_and_emergency_codes() {
		global $current_user;
		delete_user_meta($current_user->ID, 'tfa_priv_key_64');
		delete_user_meta($current_user->ID, 'simba_tfa_emergency_codes_64');
		if (empty($_REQUEST['noredirect'])) {
			wp_safe_redirect( admin_url('admin.php').'?page=two-factor-auth-user&settings-updated=1');
		} else {
			$url = ( is_ssl() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . remove_query_arg(array('simbatfa_priv_key_reset', 'noredirect', 'nonce'));

			wp_redirect($url);
		}
	}

	public function reset_link($admin = true) {

		$url_base = ($admin) ? admin_url('admin.php').'?page=two-factor-auth-user&settings-updated=1' : (( is_ssl() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST']);

		$add_query_args = array(
			'simbatfa_priv_key_reset' => 1,
		);
		if (!$admin) $add_query_args['noredirect'] = 1;

		$url = $url_base.add_query_arg($add_query_args);

		$url = wp_nonce_url($url, 'simbatfa_reset_private_key', 'nonce');

		return '<a href="javascript:if(confirm(\''.__('Warning: if you reset this key you will have to update your apps with the new one. Are you sure you want this?', SIMBA_TFA_TEXT_DOMAIN).'\')){ window.location = \''.esc_js($url).'\'; }">'.__('Reset private key', SIMBA_TFA_TEXT_DOMAIN).'</a>';

	}

	public function footer() {
		?>
		<script>
			jQuery(document).ready(function($) {
				$('.simbaotp_refresh').click(function(e) {
					e.preventDefault();
					$(".simba_current_otp").html('<em><?php echo esc_attr(__('Updating...', SIMBA_TFA_TEXT_DOMAIN));?></em>');
					$.post('<?php echo esc_js(admin_url('admin-ajax.php'));?>', {
						action: "simbatfa_shared_ajax",
						subaction: "refreshotp",
						nonce: "<?php echo esc_js(wp_create_nonce("tfa_shared_nonce"));?>"
					}, function(response) {
						try {
							var resp = $.parseJSON(response);
							$(".simba_current_otp").html(resp.code);
						} catch(err) {
							alert("<?php echo esc_js(__('Response:', 'SIMBA_TFA_TEXT_DOMAIN')); ?> "+response);
							console.log(response);
							console.log(err);
						}
					});
				});
			});
		</script>
		<?php
	}

	public function print_private_keys($admin, $type = 'full') {

		$tfa = $this->getTFA();
		global $current_user;

		$tfa_priv_key_64 = get_user_meta($current_user->ID, 'tfa_priv_key_64', true);
		if(!$tfa_priv_key_64) $tfa_priv_key_64 = $tfa->addPrivateKey($current_user->ID);

		$tfa_priv_key = trim($tfa->getPrivateKeyPlain($tfa_priv_key_64, $current_user->ID));

		$tfa_priv_key_32 = Base32::encode($tfa_priv_key);

		if ('full' == $type) {
			?>
			<strong><?php echo __('Private key (base 32 - used by Google Authenticator and Authy):', SIMBA_TFA_TEXT_DOMAIN);?></strong>
			<?php echo htmlspecialchars($tfa_priv_key_32); ?><br>

			<strong><?php echo __('Private key:', SIMBA_TFA_TEXT_DOMAIN);?></strong>
			<?php echo htmlspecialchars($tfa_priv_key); ?><br>
			<?php
		} elseif ('plain' == $type) {
			echo htmlspecialchars($tfa_priv_key);
		} elseif ('base32' == $type) {
			echo htmlspecialchars($tfa_priv_key_32);
		} elseif ('base64' == $type) {
			echo htmlspecialchars($tfa_priv_key_64);
		}
	}

	public function current_otp_code($tfa) {
		global $current_user;
		$tfa_priv_key_64 = get_user_meta($current_user->ID, 'tfa_priv_key_64', true);
		return '<span class="simba_current_otp">'.$tfa->generateOTP($current_user->ID, $tfa_priv_key_64).'</span>';
	}

	public function add_footer($admin) {
		static $added_footer;
		if (empty($added_footer)) {
			$added_footer = true;
			wp_enqueue_script('jquery');
			add_action( $admin ? 'admin_footer' : 'wp_footer' , array($this, 'footer'));
		}
	}

	public function current_codes_box($admin = true) {

		global $current_user;
		$tfa = $this->getTFA();

		$this->add_footer($admin);

		$url = preg_replace('/^https?:\/\//', '', site_url());
		
		$tfa_priv_key_64 = get_user_meta($current_user->ID, 'tfa_priv_key_64', true);
		
		if(!$tfa_priv_key_64) $tfa_priv_key_64 = $tfa->addPrivateKey($current_user->ID);

		$tfa_priv_key = trim($tfa->getPrivateKeyPlain($tfa_priv_key_64, $current_user->ID));

		$tfa_priv_key_32 = Base32::encode($tfa_priv_key);

		$algorithm_type = $tfa->getUserAlgorithm($current_user->ID);


		if ($admin) {
			echo '<h2>'.__('Current codes', SIMBA_TFA_TEXT_DOMAIN).'</h2>';
		} else {
// 			echo '<h2>'.__('Current one-time password', SIMBA_TFA_TEXT_DOMAIN).' '.$this->reset_current_otp_link().'</h2>';
		}

		?>
		<div class="postbox">

			<?php if ($admin) { ?>
				<h3 style="padding: 10px 6px 0px; margin:4px 0 0; cursor: default;">
					<span style="cursor: default;"><?php echo __('Current one-time password', SIMBA_TFA_TEXT_DOMAIN).' '.$this->reset_current_otp_link(); ?> </span>
					<div class="inside">
						<p><strong style="font-size: 3em;"><?php echo $this->current_otp_code($tfa); ?></strong></p>
					</div>
				</h3>
			<?php } else {
				?>
				<div class="inside">
					<p class="simbatfa-frontend-current-otp" style="font-size: 1.5em; margin-top:6px;">
					<strong>
						<?php echo __('Current one-time password', SIMBA_TFA_TEXT_DOMAIN).' '.$this->reset_current_otp_link(); ?>
					</strong> :

					<span class="simba_current_otp"><?php print $tfa->generateOTP($current_user->ID, $tfa_priv_key_64); ?></span>
			
					</p>
				</div>

			<?php } ?>

			<?php if ($admin) { ?>
			<h3 style="padding-left: 10px; cursor: default;">
				<span style="cursor: default;"><?php _e('QR code', SIMBA_TFA_TEXT_DOMAIN); ?></span>
			</h3>
			<?php } else {
				echo '<h2>'.__('QR code', SIMBA_TFA_TEXT_DOMAIN).'</h2>';
			} ?>
			<div class="inside">
				<p>
					<?php _e('Scan this code with Duo Mobile, Google Authenticator or any other app that supports 6 digit OTPs', SIMBA_TFA_TEXT_DOMAIN); ?>.
					
					<?php _e('You are currently using', SIMBA_TFA_TEXT_DOMAIN); ?> <?php print strtoupper($algorithm_type).', '.($algorithm_type == 'totp' ? __('a time based', SIMBA_TFA_TEXT_DOMAIN) : __('an event based', SIMBA_TFA_TEXT_DOMAIN)); ?> <?php _e('algorithm', SIMBA_TFA_TEXT_DOMAIN); ?>.
				</p>
				<p title="<?php echo sprintf(__("Private key: %s (base 32: %s)", SIMBA_TFA_TEXT_DOMAIN), $tfa_priv_key, $tfa_priv_key_32);?>">
					<?php echo $this->tfa_qr_code_url($algorithm_type, $url, $tfa_priv_key) ?>
				</p>
			</div>

			<div class="inside">

			<h3 class="normal" style="cursor: default"><?php _e('Private key - always to be kept secret', SIMBA_TFA_TEXT_DOMAIN); ?></h3>

				<p>
					<?php
						$this->print_private_keys($admin);
						echo $this->reset_link($admin);
					?>
				</p>
			</div>

			<?php
				if ($admin || apply_filters('simba_tfa_emergency_codes_user_settings', false) !== false) {
			?>
			<div class="inside">

			<h3 class="normal" style="cursor: default"><?php _e('Emergency codes', SIMBA_TFA_TEXT_DOMAIN); ?></h3>

				<p>
					<?php
						$default_text = __('One-time emergency codes are a feature of the Premium version of this plugin.', SIMBA_TFA_TEXT_DOMAIN);
						echo apply_filters('simba_tfa_emergency_codes_user_settings', $default_text);
					?>
				</p>

			</div>

			<?php } ?>

		</div>
		<?php
	}

	public function reset_current_otp_link($admin = true) {
		return '<a href="#" class="simbaotp_refresh">'.__('(update)', SIMBA_TFA_TEXT_DOMAIN).'</a>';
	}

	public function advanced_settings_box($submit_button_callback = false) {
		$tfa = $this->getTFA();

		global $current_user;
		$algorithm_type = $tfa->getUserAlgorithm($current_user->ID);

		?>
		<h2><?php _e('Advanced settings', SIMBA_TFA_TEXT_DOMAIN); ?></h2>

		<div id="tfa_advanced_box" class="tfa_settings_form" style="margin-top: 20px;">

				<?php if (false === $submit_button_callback) { ?><form method="post" action="<?php print add_query_arg('settings-updated', 'true', $_SERVER['REQUEST_URI']); ?>"><?php } ?>

					<?php _e('Choose which algorithm for One Time Passwords you want to use.', SIMBA_TFA_TEXT_DOMAIN); ?>
					<p>
					<?php
						$this->tfaListAlgorithmRadios($current_user->ID);
						if($algorithm_type == 'hotp')
						{
							$counter = $tfa->getUserCounter($current_user->ID);
							print '<br>'.__('Your counter on the server is currently on', SIMBA_TFA_TEXT_DOMAIN).': '.$counter;
						}
					?>
					
					</p>
					<?php if (false === $submit_button_callback) { submit_button(); echo '</form>'; } else { call_user_func($submit_button_callback); } ?>
		</div>
		<?php
	}

	public function login_enqueue_scripts()
	{
		
		if(isset($_GET['action']) && $_GET['action'] != 'logout' && $_GET['action'] != 'login') return;
		
		// Prevent cacheing when in debug mode
		$script_ver = (defined('WP_DEBUG') && WP_DEBUG) ? time() : $wp_version;

		wp_enqueue_script( 'tfa-ajax-request', SIMBA_TFA_PLUGIN_URL . '/includes/tfa.js', array( 'jquery' ), $script_ver );
		wp_localize_script( 'tfa-ajax-request', 'simba_tfasettings', array(
			'ajaxurl' => admin_url('admin-ajax.php'),
			'click_to_enter_otp' => __("Click to enter One Time Password", SIMBA_TFA_TEXT_DOMAIN),
			'enter_username_first' => __('You have to enter a username first.', SIMBA_TFA_TEXT_DOMAIN),
			'otp' => __("One Time Password (i.e. 2FA)", SIMBA_TFA_TEXT_DOMAIN),
			'otp_login_help' => __('(check your OTP app to get this password)', SIMBA_TFA_TEXT_DOMAIN),
			'nonce' => wp_create_nonce("simba_tfa_loginform_nonce")
		));
	}

	public function tfaShowHOTPOffSyncMessage()
	{
		global $current_user;
		$is_off_sync = get_user_meta($current_user->ID, 'tfa_hotp_off_sync', true);
		if(!$is_off_sync)
			return;
		
		?>
		<div class="error">
		<h3><?php _e('Two Factor Authentication re-sync needed', SIMBA_TFA_TEXT_DOMAIN);?></h3>
		<p>
			<?php _e('You need to resync your device for Two Factor Authentication since the OTP you last used is many steps ahead 
			of the server.', SIMBA_TFA_TEXT_DOMAIN); ?>
			<br>
			<?php _e('Please re-sync or you might not be able to log in if you generate more OTPs without logging in.', SIMBA_TFA_TEXT_DOMAIN);?>
			<br><br>
			<a href="admin.php?page=two-factor-auth-user&warning_button_clicked=1" class="button"><?php _e('Click here and re-scan the QR-Code', SIMBA_TFA_TEXT_DOMAIN);?></a>
		</p>
	</div>
		
		<?php
		
	}

	// QR code image
	public function tfa_qr_code_url($algorithm_type, $url, $tfa_priv_key){
		global $current_user;
		$tfa = $this->getTFA();
		
		$encode = 'otpauth://'.$algorithm_type.'/'.$url.':%2520'.$current_user->user_login.'%3Fsecret%3D'.Base32::encode($tfa_priv_key).'%26issuer='.$url.'%26counter='.$tfa->getUserCounter($current_user->ID);

		$ret = '<img src="https://chart.googleapis.com/chart?chs=200x200&chld=M|0&cht=qr&chl='.$encode.'">';

		return $ret;
	}

	public function settings_intro_notices() {
		?>
		<p class="simba_tfa_personal_settings_notice simba_tfa_intro_notice">
			<?php echo __('These are your personal settings.', SIMBA_TFA_TEXT_DOMAIN).' '.__('Nothing you change here will have any effect on other users.', SIMBA_TFA_TEXT_DOMAIN); ?>
		</p>
		<p class="simba_tfa_verify_tfa_notice simba_tfa_intro_notice"><strong>
			<?php _e('If you activate two-factor authentication, then verify with the One Time Password shown on this page before you log out.', SIMBA_TFA_TEXT_DOMAIN); ?></strong>
		</p>
		<?php
	}

	public function plugins_loaded() {
		load_plugin_textdomain(
			SIMBA_TFA_TEXT_DOMAIN,
			false,
			dirname( plugin_basename( __FILE__ ) ) . '/languages/'
		);

		if ((!is_admin() || (defined('DOING_AJAX') && DOING_AJAX)) && is_user_logged_in() && file_exists(SIMBA_TFA_PLUGIN_DIR.'/includes/tfa_frontend.php')) {
			$this->load_frontend();
		} else {
			add_shortcode('twofactor_user_settings', array($this, 'shortcode_when_not_logged_in'));
		}

	}

	public function load_frontend() {
		if (!class_exists('TFA_Frontend')) require_once(SIMBA_TFA_PLUGIN_DIR.'/includes/tfa_frontend.php');
		if (empty($this->frontend)) $this->frontend = new TFA_Frontend($this);
		return $this->frontend;
	}

	public function shortcode_when_not_logged_in() {
		return '';
	}

	// WooCommerce login form
	public function woocommerce_before_customer_login_form() {
			wp_enqueue_script( 'tfa-wc-ajax-request', SIMBA_TFA_PLUGIN_URL.'/includes/wooextend.js', array('jquery'));
			wp_localize_script( 'tfa-wc-ajax-request', 'simbatfa_wc_settings', array(
				'ajaxurl' => admin_url('admin-ajax.php'),
				'click_to_enter_otp' => __("Enter One Time Password (if you have one)", SIMBA_TFA_TEXT_DOMAIN),
				'enter_username_first' => __('You have to enter a username first.', SIMBA_TFA_TEXT_DOMAIN),
				'otp' => __("One Time Password", SIMBA_TFA_TEXT_DOMAIN),
				'nonce' => wp_create_nonce("simba_tfa_loginform_nonce"),
				'otp_login_help' => __('(check your OTP app to get this password)', SIMBA_TFA_TEXT_DOMAIN),
			));
	}

}

$simba_two_factor_authentication = new Simba_Two_Factor_Authentication();