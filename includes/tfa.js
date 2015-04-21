jQuery(document).ready(function($) {
	
	// Return value: whether to submit the form or not
	function runGenerateOTPCall() {
		
		var username = $('#user_login').val() || $('[name="log"]').val();
		
		if(!username.length) return false;
		
		// If this is a "lost password" form, then exit
		if ($('#user_login').parents('#lostpasswordform').length) return false;

		if (simba_tfasettings.hasOwnProperty('spinnerimg')) {
			var styling = 'float:right; margin:6px 12px;';
			if ($('#theme-my-login #wp-submit').length >0) {
				var styling = 'margin-left: 4px; position: relative; top: 4px; border:0px; box-shadow:none;';
			}
			$('#wp-submit').after('<img class="simbaotp_spinner" src="'+simba_tfasettings.spinnerimg+'" style="'+styling+'">');
		}
		
		$.ajax({
			url: simba_tfasettings.ajaxurl,
			type: 'POST',
			data: {
				action: 'simbatfa-init-otp',
				nonce: simba_tfasettings.nonce,
				user: username
			},
			dataType: 'text',
			success: function(resp) {
				try {
					var json_begins = resp.search('{"jsonstarter":"justhere"');
					if (json_begins > -1) {
						if (json_begins > 0) {
							console.log("Expected JSON marker found at position: "+json_begins);
							resp = resp.substring(json_begins);
						}
					} else {
						console.log("Expected JSON marker not found");
					}
					response = $.parseJSON(resp);
					if (response.hasOwnProperty('php_output')) {
						console.log("PHP output was returned (follows)");
						console.log(response.php_output);
					}
					if (response.hasOwnProperty('extra_output')) {
						console.log("Extra output was returned (follows)");
						console.log(response.extra_output);
					}
					if (response.status === true) {
						// Don't bother to remove the spinner if the form is being submitted.
						$('.simbaotp_spinner').remove();
						console.log("Simba TFA: User has OTP enabled: showing OTP field");
						tfaShowOTPField();
					} else {
						console.log("Simba TFA: User does not have OTP enabled: submitting form");
						$('#wp-submit').parents('form:first').submit();
					}
				} catch(err) {
					$('#login').html(resp);
					console.log("Simba TFA: Error when processing response");
					console.log(err);
					console.log(resp);
				}
			},
			error: function(jq_xhr, text_status, error_thrown) {
				console.log("Simba TFA: AJAX error: "+error_thrown+": "+text_status);
				console.log(jq_xhr);
				if (jq_xhr.hasOwnProperty('responseText')) { console.log(jq_xhr.responseText);}
			}
		});
		return true;
	}
	
	function tfaShowOTPField() {
		//Hide all elements in sa browser safe way
		$('#wp-submit').parents('form:first').find('p').each(function(i) {
			$(this).css('visibility','hidden').css('position', 'absolute');
		});
		$('#wp-submit').attr('disabled', 'disabled');
		
		//Add new field and controls
		var html = '';
		html += '<label for="simba_two_factor_auth">' + simba_tfasettings.otp + '<br><input type="text" name="two_factor_code" id="simba_two_factor_auth" autocomplete="off"></label>';
		html += '<p class="forgetmenot" style="font-size:small; max-width: 60%">' + simba_tfasettings.otp_login_help + '</p>';
		html += '<p class="submit"><input id="tfa_login_btn" class="button button-primary button-large" type="submit" value="' + $('#wp-submit').val() + '"></p>';
		
		$('#wp-submit').parents('form:first').prepend(html);
		$('#simba_two_factor_auth').focus();
	}
	
	var tfa_cb = function(e) {
		console.log("Simba TFA: form submit request");
		
		var res = runGenerateOTPCall();
		
		$('#wp-submit').parents('form:first').off();
		
		if(!res) return true;

		e.preventDefault();
		return false;
	};
	
	
	
	$('#wp-submit').parents('form:first').on('submit', tfa_cb);
});
