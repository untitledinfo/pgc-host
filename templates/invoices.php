<?php if ( ! defined( 'ABSPATH' ) ) exit;
$client = Ptero_Client_Auth::instance()->current_client();
if ( ! $client ) { echo '<p>' . esc_html__( 'Please log in to view your invoices.', 'ptero-host' ) . '</p>'; return; }

$single_id = isset( $_GET['invoice'] ) ? (int) $_GET['invoice'] : 0;
$billing = Ptero_Billing::instance();

if ( $single_id ) {
	$invoice = $billing->get_invoice( $single_id );
	if ( ! $invoice || (int) $invoice->client_id !== (int) $client->id ) { echo '<p>' . esc_html__( 'Invoice not found.', 'ptero-host' ) . '</p>'; return; }
	$items = $billing->get_invoice_items( $single_id );
	?>
	<div class="ptero-invoice">
		<h3><?php echo esc_html( $invoice->invoice_number ); ?> — <?php echo esc_html( ucfirst( $invoice->status ) ); ?></h3>
		<div class="ptero-form-msg" style="display:none;"></div>
		<table style="width:100%;border-collapse:collapse;">
			<thead><tr><th><?php _e( 'Description', 'ptero-host' ); ?></th><th><?php _e( 'Total', 'ptero-host' ); ?></th></tr></thead>
			<tbody>
			<?php foreach ( $items as $it ) : ?>
				<tr><td><?php echo esc_html( $it->description ); ?></td><td><?php echo esc_html( $invoice->currency . ' ' . number_format( (float) $it->total, 2 ) ); ?></td></tr>
			<?php endforeach; ?>
			</tbody>
			<tfoot><tr><td><strong><?php _e( 'Total Due', 'ptero-host' ); ?></strong></td><td><strong><?php echo esc_html( $invoice->currency . ' ' . number_format( (float) $invoice->total, 2 ) ); ?></strong></td></tr></tfoot>
		</table>
		<?php if ( $invoice->status !== 'paid' ) : ?>
			<p>
				<button class="ptero-btn ptero-pay-invoice" data-invoice-id="<?php echo (int) $invoice->id; ?>" data-method="wallet"><?php _e( 'Pay from Wallet', 'ptero-host' ); ?></button>
				<button class="ptero-btn ptero-pay-invoice" data-invoice-id="<?php echo (int) $invoice->id; ?>" data-method="manual"><?php _e( 'Pay Manually / Bank Transfer', 'ptero-host' ); ?></button>
			</p>
			<div id="ptero-pay-instructions"></div>
		<?php endif; ?>
	</div>
	<script>
	jQuery(function($){
		$('.ptero-pay-invoice').on('click', function(){
			var id = $(this).data('invoice-id'), method = $(this).data('method');
			var $msg = $('.ptero-form-msg');
			$.post(PteroHost.ajax_url, { action: 'ptero_pay_invoice', nonce: PteroHost.nonce, invoice_id: id, method: method }, function(res){
				$msg.show().text(res.data.message).css('color', res.success ? 'green' : 'red');
				if (res.data.instructions) $('#ptero-pay-instructions').html(res.data.instructions);
			});
		});
	});
	</script>
	<?php
	return;
}

$invoices = $billing->get_client_invoices( $client->id );
?>
<div class="ptero-invoices-list">
	<h3><?php _e( 'Your Invoices', 'ptero-host' ); ?> — <?php _e( 'Wallet Balance', 'ptero-host' ); ?>: <?php echo esc_html( $client->currency . ' ' . number_format( (float) $client->balance, 2 ) ); ?></h3>
	<?php if ( ! $invoices ) : ?>
		<p><?php _e( 'No invoices yet.', 'ptero-host' ); ?></p>
	<?php else : ?>
		<table style="width:100%;border-collapse:collapse;">
			<thead><tr><th>#</th><th><?php _e( 'Total', 'ptero-host' ); ?></th><th><?php _e( 'Status', 'ptero-host' ); ?></th><th></th></tr></thead>
			<tbody>
			<?php foreach ( $invoices as $inv ) : ?>
				<tr>
					<td><?php echo esc_html( $inv->invoice_number ); ?></td>
					<td><?php echo esc_html( $inv->currency . ' ' . number_format( (float) $inv->total, 2 ) ); ?></td>
					<td><?php echo esc_html( ucfirst( $inv->status ) ); ?></td>
					<td><a href="<?php echo esc_url( add_query_arg( 'invoice', $inv->id ) ); ?>"><?php _e( 'View', 'ptero-host' ); ?></a></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
