<?php if ( ! defined( 'ABSPATH' ) ) exit;
$items = Ptero_Cart::instance()->items();
$total = 0;
?>
<div class="ptero-cart">
	<h3><?php _e( 'Your Cart', 'ptero-host' ); ?></h3>
	<div class="ptero-form-msg" style="display:none;"></div>
	<?php if ( ! $items ) : ?>
		<p><?php _e( 'Your cart is empty.', 'ptero-host' ); ?></p>
	<?php else : ?>
		<table class="ptero-cart-table" style="width:100%;border-collapse:collapse;">
			<thead><tr><th><?php _e( 'Plan', 'ptero-host' ); ?></th><th><?php _e( 'Server Name', 'ptero-host' ); ?></th><th><?php _e( 'Cycle', 'ptero-host' ); ?></th><th><?php _e( 'Price', 'ptero-host' ); ?></th><th></th></tr></thead>
			<tbody>
			<?php foreach ( $items as $item ) : $lt = Ptero_Cart::instance()->line_total( $item ); $total += $lt; ?>
				<tr data-item-id="<?php echo (int) $item->id; ?>">
					<td><?php echo esc_html( $item->plan_name ); ?></td>
					<td><?php echo esc_html( $item->server_name ); ?></td>
					<td><?php echo esc_html( ucfirst( $item->billing_cycle ) ); ?></td>
					<td><?php echo esc_html( $item->currency . ' ' . number_format( $lt, 2 ) ); ?></td>
					<td><a href="#" class="ptero-cart-remove" data-item-id="<?php echo (int) $item->id; ?>"><?php _e( 'Remove', 'ptero-host' ); ?></a></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
			<tfoot><tr><td colspan="3"><strong><?php _e( 'Total', 'ptero-host' ); ?></strong></td><td colspan="2"><strong><?php echo esc_html( ( $items[0]->currency ?? '' ) . ' ' . number_format( $total, 2 ) ); ?></strong></td></tr></tfoot>
		</table>
		<p><a class="ptero-btn" href="<?php echo esc_url( get_option( 'ptero_checkout_page_url', '' ) ); ?>"><?php _e( 'Proceed to Checkout', 'ptero-host' ); ?></a></p>
	<?php endif; ?>
</div>
<script>
jQuery(function($){
	$('.ptero-cart-remove').on('click', function(e){
		e.preventDefault();
		var id = $(this).data('item-id'), $row = $(this).closest('tr');
		$.post(PteroHost.ajax_url, { action: 'ptero_cart_remove', nonce: PteroHost.nonce, item_id: id }, function(res){
			if (res.success) $row.remove();
		});
	});
});
</script>
