<?php if ( ! defined( 'ABSPATH' ) ) exit;
$client = Ptero_Client_Auth::instance()->current_client();
if ( ! $client ) { echo '<p>' . esc_html__( 'Please log in to add funds.', 'ptero-host' ) . '</p>'; return; }
?>
<div class="ptero-add-funds">
	<h3><?php _e( 'Add Funds', 'ptero-host' ); ?></h3>
	<p><?php echo esc_html( __( 'Current balance: ', 'ptero-host' ) . $client->currency . ' ' . number_format( (float) $client->balance, 2 ) ); ?></p>
	<div class="ptero-form-msg" style="display:none;"></div>
	<form id="ptero-add-funds-form">
		<p><label><?php _e( 'Amount', 'ptero-host' ); ?></label><input type="number" step="0.01" min="1" name="amount" required></p>
		<button type="submit" class="ptero-btn"><?php _e( 'Submit Top-Up Request', 'ptero-host' ); ?></button>
	</form>
</div>
<script>
jQuery(function($){
	$('#ptero-add-funds-form').on('submit', function(e){
		e.preventDefault();
		var $f = $(this), $msg = $f.siblings('.ptero-form-msg');
		$.post(PteroHost.ajax_url, { action: 'ptero_add_funds', nonce: PteroHost.nonce, amount: $f.find('[name=amount]').val() }, function(res){
			$msg.show().text(res.data.message).css('color', res.success ? 'green' : 'red');
		});
	});
});
</script>
