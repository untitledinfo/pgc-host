<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="ptero-auth-box">
	<h3><?php _e( 'Create Account', 'ptero-host' ); ?></h3>
	<div class="ptero-form-msg" style="display:none;"></div>
	<form id="ptero-register-form">
		<p><label><?php _e( 'Name', 'ptero-host' ); ?></label><input type="text" name="name" required></p>
		<p><label><?php _e( 'Email', 'ptero-host' ); ?></label><input type="email" name="email" required></p>
		<p><label><?php _e( 'Password', 'ptero-host' ); ?></label><input type="password" name="password" minlength="6" required></p>
		<button type="submit" class="ptero-btn"><?php _e( 'Create Account', 'ptero-host' ); ?></button>
	</form>
</div>
<script>
jQuery(function($){
	$('#ptero-register-form').on('submit', function(e){
		e.preventDefault();
		var $f = $(this), $msg = $f.siblings('.ptero-form-msg');
		$.post(PteroHost.ajax_url, {
			action: 'ptero_client_register', nonce: PteroHost.nonce,
			name: $f.find('[name=name]').val(), email: $f.find('[name=email]').val(), password: $f.find('[name=password]').val()
		}, function(res){
			$msg.show().text(res.data.message).css('color', res.success ? 'green' : 'red');
			if (res.success) { setTimeout(function(){ window.location.href = res.data.redirect || window.location.href; }, 800); }
		});
	});
});
</script>
