<?php

if (!defined('ABSPATH')) die('Access denied.');

class Simba_TFA  {

	private $salt_prefix;
	private $pw_prefix;

	public function __construct($base32_encoder, $otp_helper)
	{
		$this->base32_encoder = $base32_encoder;
		$this->otp_helper = $otp_helper;
		$this->time_window_size = 30;
		$this->check_back_time_windows = 2;
		$this->check_forward_counter_window = 20;
		$this->otp_length = 6;
		$this->emergency_codes_length = 8;
		$this->salt_prefix = AUTH_SALT;
		$this->pw_prefix = AUTH_KEY;
		$this->default_hmac = 'totp';
	}

	public function generateOTP($user_ID, $key_b64, $length = 6, $counter = false)
	{
		
		$length = $length ? (int)$length : 6;
		
		$key = $this->decryptString($key_b64, $user_ID);
		$alg = $this->getUserAlgorithm($user_ID);
		
		if($alg == 'hotp')
		{
			$db_counter = $this->getUserCounter($user_ID);
			
			$counter = $counter ? $counter : $db_counter;
			$otp_res = $this->otp_helper->generateByCounter($key, $counter);
		}
		else
		{
			//time() is supposed to be UTC
			$time = $counter ? $counter : time();
			$otp_res = $this->otp_helper->generateByTime($key, $this->time_window_size, $time);
		}
		$code = $otp_res->toHotp($length);
		
		return $code;
	}

	public function generateOTPsForLoginCheck($user_ID, $key_b64)
	{
		$key = trim($this->decryptString($key_b64, $user_ID));
		$alg = $this->getUserAlgorithm($user_ID);
		
		if($alg == 'totp')
			$otp_res = $this->otp_helper->generateByTimeWindow($key, $this->time_window_size, -1*$this->check_back_time_windows, 0);
		elseif($alg == 'hotp')
		{
			$counter = $this->getUserCounter($user_ID);
			
			$otp_res = array();
			for($i = 0; $i < $this->check_forward_counter_window; $i++)
				$otp_res[] = $this->otp_helper->generateByCounter($key, ($counter+$i));
		}
		return $otp_res;
	}
	

	public function addPrivateKey($user_ID, $key = false)
	{
		//Generate a private key for the user. 
		//To work with Google Authenticator it has to be 10 bytes = 16 chars in base32
		$code = $key ? $key : strtoupper($this->randString(10));

		//Lets encrypt the key
		$code = $this->encryptString($code, $user_ID);
		
		//Add private key to users meta
		update_user_meta($user_ID, 'tfa_priv_key_64', $code);
		
		$alg = $this->getUserAlgorithm($user_ID);
		
		do_action('simba_tfa_adding_private_key', $alg, $user_ID, $code, $this);
		
		$this->changeUserAlgorithmTo($user_ID, $alg);
		
		return $code;
	}


	public function getPrivateKeyPlain($enc, $user_ID)
	{
		$dec = $this->decryptString($enc, $user_ID);
		return $dec;
	}


	public function getPanicCodesString($arr, $user_ID)
	{
		if(!is_array($arr)) return '<em>'.__('No emergency codes left. Sorry.', SIMBA_TFA_TEXT_DOMAIN).'</em>';
		
		$emergency_str = '';
		
		foreach($arr as $p_code) {
			$emergency_str .= $this->decryptString($p_code, $user_ID).', ';
		}

		$emergency_str = rtrim($emergency_str, ', ');
		
		$emergency_str = $emergency_str ? $emergency_str : '<em>'.__('No emergency codes left. Sorry.', SIMBA_TFA_TEXT_DOMAIN).'</em>';
		return $emergency_str;
	}
	
	public function preAuth($params)
	{
		global $wpdb;
		$field = filter_var($params['log'], FILTER_VALIDATE_EMAIL) ? 'user_email' : 'user_login';
		$query = $wpdb->prepare("SELECT ID, user_email from ".$wpdb->users." WHERE ".$field."=%s", $params['log']);
		$user = $wpdb->get_row($query);
		$is_activated_for_user = true;
		$is_activated_by_user = false;
		
		if($user)
		{
			$tfa_priv_key = get_user_meta($user->ID, 'tfa_priv_key_64', true);
			$is_activated_for_user = $this->isActivatedForUser($user->ID);
			$is_activated_by_user = $this->isActivatedByUser($user->ID);
			
			if($is_activated_for_user && $is_activated_by_user)
			{
				$delivery_type = get_user_meta($user->ID, 'simbatfa_delivery_type', true);
				
				//Default is email
				if(!$delivery_type)
				{
					//No private key yet, generate one.
					//This is safe to do since the code is emailed to the user.
					//Not safe to do if the user has disabled email.
					if(!$tfa_priv_key)
						$tfa_priv_key = $this->addPrivateKey($user->ID);
					
					$code = $this->generateOTP($user->ID, $tfa_priv_key);
				}
				return true;//Set to true
			}
			return false;
		}
		return true;
	}
	
	public function authUserFromLogin($params)
	{
		
		global $wpdb;
		
		if(!$this->isCallerActive($params))
			return true;
		
		$field = filter_var($params['log'], FILTER_VALIDATE_EMAIL) ? 'user_email' : 'user_login';
		$query = $wpdb->prepare("SELECT ID from ".$wpdb->users." WHERE ".$field."=%s", $params['log']);
		$user_ID = $wpdb->get_var($query);
		$user_code = trim(@$params['two_factor_code']);
		
		if(!$user_ID)
			return true;
		
		if(!$this->isActivatedForUser($user_ID))
			return true;
			
		if(!$this->isActivatedByUser($user_ID))
			return true;
			
		$tfa_priv_key = get_user_meta($user_ID, 'tfa_priv_key_64', true);
		$tfa_last_login = get_user_meta($user_ID, 'tfa_last_login', true);
		$tfa_last_pws_arr = get_user_meta($user_ID, 'tfa_last_pws', true);
		$tfa_last_pws = @$tfa_last_pws_arr ? $tfa_last_pws_arr : array();
		$alg = $this->getUserAlgorithm($user_ID);
		
		$current_time_window = intval(time()/30);
		
		//Give the user 1,5 minutes time span to enter/retrieve the code
		//Or check $this->check_forward_counter_window number of events if hotp
		$codes = $this->generateOTPsForLoginCheck($user_ID, $tfa_priv_key);
	
		//A recently used code was entered.
		//Not ok
		if(in_array($this->hash($user_code, $user_ID), $tfa_last_pws))
			return false;
	
		$match = false;
		foreach($codes as $index => $code)
		{
			if(trim($code->toHotp(6)) == trim($user_code))
			{
				$match = true;
				$found_index = $index;
				break;
			}
		}
		
		//Check emergency codes
		if(!$match)
		{
			$emergency_codes = get_user_meta($user_ID, 'simba_tfa_emergency_codes_64', true);
			
			if(!@$emergency_codes)
				return $match;
			
			$dec = array();
			foreach($emergency_codes as $emergency_code)
				$dec[] = trim($this->decryptString(trim($emergency_code), $user_ID));

			$in_array = array_search($user_code, $dec);
			$match = $in_array !== false;
			
			if($match)//Remove emergency code
			{
				array_splice($emergency_codes, $in_array, 1);
				update_user_meta($user_ID, 'simba_tfa_emergency_codes_64', $emergency_codes);
				do_action('simba_tfa_emergency_code_used', $user_ID, $emergency_codes);
			}
			
		} else {
			//Add the used code as well so it cant be used again
			//Keep the two last codes
			$tfa_last_pws[] = $this->hash($user_code, $user_ID);
			$nr_of_old_to_save = $alg == 'hotp' ? $this->check_forward_counter_window : $this->check_back_time_windows;
			
			if(count($tfa_last_pws) > $nr_of_old_to_save)
				array_splice($tfa_last_pws, 0, 1);
				
			update_user_meta($user_ID, 'tfa_last_pws', $tfa_last_pws);
		}
		
		if($match)
		{
			//Save the time window when the last successful login took place
			update_user_meta($user_ID, 'tfa_last_login', $current_time_window);
			
			//Update the counter if HOTP was used
			if($alg == 'hotp')
			{
				$counter = $this->getUserCounter($user_ID);
				
				$enc_new_counter = $this->encryptString($counter+1, $user_ID);
				update_user_meta($user_ID, 'tfa_hotp_counter', $enc_new_counter);
				
				if($found_index > 10)
					update_user_meta($user_ID, 'tfa_hotp_off_sync', 1);
			}
		}
		
		return $match;
		
	}

	public function getUserCounter($user_ID)
	{
		$enc_counter = get_user_meta($user_ID, 'tfa_hotp_counter', true);
		
		if($enc_counter)
			$counter = $this->decryptString(trim($enc_counter), $user_ID);
		else
			return '';
			
		return trim($counter);
	}
	
	public function changeUserAlgorithmTo($user_id, $new_algorithm)
	{
		update_user_meta($user_id, 'tfa_algorithm_type', $new_algorithm);
		delete_user_meta($user_id, 'tfa_hotp_off_sync');
		
		$counter_start = rand(13, 999999999);
		$enc_counter_start = $this->encryptString($counter_start, $user_id);
		
		if($new_algorithm == 'hotp')
			update_user_meta($user_id, 'tfa_hotp_counter', $enc_counter_start);
		else
			delete_user_meta($user_id, 'tfa_hotp_counter');
	}
	
	//Added
	public function changeEnableTFA($user_id, $setting)
	{
		$setting = ($setting === 'true');
		
		update_user_meta($user_id, 'tfa_enable_tfa', $setting);
	}
	
	public function getUserAlgorithm($user_id)
	{
		global $simba_two_factor_authentication;
		$setting = get_user_meta($user_id, 'tfa_algorithm_type', true);
		$default_hmac = $simba_two_factor_authentication->get_option('tfa_default_hmac');
		$default_hmac = $default_hmac ? $default_hmac : $this->default_hmac;
		
		$setting = $setting === false || !$setting ? $default_hmac : $setting;
		return $setting;
	}
	
	public function isActivatedForUser($user_id)
	{

		if (empty($user_id)) return false;

		global $simba_two_factor_authentication;

		// Super admin is not a role (they are admins with an extra attribute); needs separate handling
		if (is_multisite() && is_super_admin($user_id)) {
			// This is always a final decision - we don't want it to drop through to the 'admin' role's setting
			$role = '_super_admin';
			$db_val = $simba_two_factor_authentication->get_option('tfa_'.$role);
			$db_val = $db_val === false || $db_val ? 1 : 0; //Nothing saved or > 0 returns 1;
			
			return ($db_val) ? true : false;
		}

		$user = new WP_User($user_id);

		foreach($user->roles as $role)
		{
			$db_val = $simba_two_factor_authentication->get_option('tfa_'.$role);
			$db_val = $db_val === false || $db_val ? 1 : 0; //Nothing saved or > 0 returns 1;
			
			if($db_val)
				return true;
		}
		
		return false;
		
	}
	
	//Added
	public function isActivatedByUser($user_id){
		$enabled = get_user_meta($user_id, 'tfa_enable_tfa', true);
		$enabled = $enabled === '' ? false : $enabled; //If there is an empty string returned - has not been set
		
		return $enabled;
	}

	// Disabled: unused
// 	public function saveCallerStatus($caller_id, $status)
// 	{
// 		global $simba_two_factor_authentication;
// 		if($caller_id == 'xmlrpc')
// 			$simba_two_factor_authentication->set_option('tfa_xmlrpc_on', $status);
// 	}

	private function isCallerActive($params)
	{

		return true;

		if(!preg_match('/(\/xmlrpc\.php)$/', trim($params['caller'])))
			return true;

		global $simba_two_factor_authentication;
		$saved_data = $simba_two_factor_authentication->get_option('tfa_xmlrpc_on');
		
		if($saved_data)
			return true;
		
		return false;
	}

	public function encryptString($string, $salt_suffix)
	{
		$key = $this->hashAndBin($this->pw_prefix.$salt_suffix, $this->salt_prefix.$salt_suffix);
		
		$iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_CBC);
		$iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
		
		$enc = mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $key, $string, MCRYPT_MODE_CBC, $iv);
		
		$enc = $iv.$enc;
		$enc_b64 = base64_encode($enc);
		return $enc_b64;
	}
	
	private function decryptString($enc_b64, $salt_suffix)
	{
		$key = $this->hashAndBin($this->pw_prefix.$salt_suffix, $this->salt_prefix.$salt_suffix);
		
		$iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_CBC);
		$enc_conc = base64_decode($enc_b64);
		
		$iv = substr($enc_conc, 0, $iv_size);
		$enc = substr($enc_conc, $iv_size);
		
		$string = mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $key, $enc, MCRYPT_MODE_CBC, $iv);

		// Remove zeroed bytes
		return rtrim($string);
	}

	private function hashAndBin($pw, $salt)
	{
		$key = $this->hash($pw, $salt);
		$key = pack('H*', $key);
	}

	private function hash($pw, $salt)
	{
		//$hash = hash_pbkdf2('sha256', $pw, $salt, 10);
		//$hash = crypt($pw, '$5$'.$salt.'$');
		$hash = md5($salt.$pw);
		return $hash;
	}

	private function randString($len = 6)
	{
		$chars = '23456789QWERTYUPASDFGHJKLZXCVBNM';
		$chars = str_split($chars);
		shuffle($chars);
		$code = implode('', array_splice($chars, 0, $len));
		
		return $code;
	}
	
	public function setUserHMACTypes()
	{
		//We need this because we dont want to change third party apps users algorithm
		$users = get_users(array('meta_key' => 'simbatfa_delivery_type', 'meta_value' => 'third-party-apps'));
		if(!empty($users))
		{
			foreach($users as $user)
			{
				$tfa_algorithm_type = get_user_meta($user->ID, 'tfa_algorithm_type', true);
				if($tfa_algorithm_type)
					continue;
				
				update_user_meta($user->ID, 'tfa_algorithm_type', $this->getUserAlgorithm($user->ID));
			}
		}
	}
	
}


?>