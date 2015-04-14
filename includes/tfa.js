jQuery(document).ready(function() {
	
	// Return value: whether to submit the form or not
	function runGenerateOTPCall() {
		var username = jQuery('#user_login').val() || jQuery('[name="log"]').val();
		
		if(!username.length)
			return false;
		
		jQuery.ajax(
			{
				url: simba_tfasettings.ajaxurl,
				type: 'POST',
				data: {
					action: 'simbatfa-init-otp',
					nonce: simba_tfasettings.nonce,
					user: username
				},
				dataType: 'json',
				success: function(response) {
					if(response.status === true)
						tfaShowOTPField();
					else
						jQuery('#wp-submit').parents('form:first').submit();
				}
			});
		return true;
	}
	
	function tfaShowOTPField() {
		//Hide all elements in sa browser safe way
		jQuery('#wp-submit').parents('form:first').find('p').each(function(i) {
			jQuery(this).css('visibility','hidden').css('position', 'absolute');
		});
		jQuery('#wp-submit').attr('disabled', 'disabled');
		
		//Add new field and controls
		var html = '';
		html += '<label for="simba_two_factor_auth">' + simba_tfasettings.otp + '<br><input type="text" name="two_factor_code" id="simba_two_factor_auth" autocomplete="off"></label>';
		html += '<p class="forgetmenot" style="font-size:small; max-width: 60%">' + simba_tfasettings.otp_login_help + '</p>';
		html += '<p class="submit"><input id="tfa_login_btn" class="button button-primary button-large" type="submit" value="' + jQuery('#wp-submit').val() + '"></p>';
		
		jQuery('#wp-submit').parents('form:first').prepend(html);
		jQuery('#simba_two_factor_auth').focus();
	}
	
	var tfa_cb = function(e)
	{
		console.log("TFA: form submit request");
		
		e.preventDefault();
		var res = runGenerateOTPCall();
		
		jQuery('#wp-submit').parents('form:first').off();
		
		if(!res)
			return true;
		
		return false;
	};
	jQuery('#wp-submit').parents('form:first').on('submit', tfa_cb);
});
