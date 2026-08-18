<?php if ( ! defined( 'ABSPATH' ) ) exit;
$client = Ptero_Client_Auth::instance()->current_client();
$items = Ptero_Cart::instance()->items();
$total = 0;
foreach ( $items as $item ) $total += Ptero_Cart::instance()->line_total( $item );
?>
<div class="ptero-checkout">
	<h3><?php _e( 'Checkout', 'ptero-host' ); ?></h3>
	<div class="ptero-form-msg" style="display:none;"></div>
	<?php if ( ! $client ) : ?>
		<p><?php _e( 'Please log in or create an account to complete checkout.', 'ptero-host' ); ?></p>
	<?php elseif ( ! $items ) : ?>
		<p><?php _e( 'Your cart is empty.', 'ptero-host' ); ?></p>
	<?php else : ?>
		<ul>
			<?php foreach ( $items as $item ) : ?>
				<li><?php echo esc_html( $item->plan_name . ' — ' . $item->server_name . ' (' . $item->billing_cycle . '): ' . $item->currency . ' ' . number_format( Ptero_Cart::instance()->line_total( $item ), 2 ) ); ?></li>
			<?php endforeach; ?>
		</ul>
		<p><strong><?php echo esc_html( __( 'Total: ', 'ptero-host' ) . ( $items[0]->currency ?? '' ) . ' ' . number_format( $total, 2 ) ); ?></strong></p>
		<button id="ptero-place-order" class="ptero-btn"><?php _e( 'Place Order', 'ptero-host' ); ?></button>
	<?php endif; ?>
</div>
<script>
jQuery(function($){
	$('#ptero-place-order').on('click', function(){
		var $msg = $('.ptero-form-msg');
		$.post(PteroHost.ajax_url, { action: 'ptero_cart_checkout', nonce: PteroHost.nonce }, function(res){
			$msg.show().text(res.data.message).css('color', res.success ? 'green' : 'red');
			if (res.success) { setTimeout(function(){ window.location.href = res.data.redirect || window.location.href; }, 800); }
		});
	});
});
</script>
