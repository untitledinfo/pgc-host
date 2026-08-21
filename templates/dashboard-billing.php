<?php
/**
 * Billing / invoices tab for [phm_dashboard].
 *
 * @package Pterodactyl_Hosting
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="phm-dash-panel">
	<div class="phm-services-header">
		<div class="phm-services-summary">
			<h2><?php esc_html_e( 'Billing & invoices', 'pterodactyl-hosting' ); ?></h2>
			<span class="phm-services-counter"><?php esc_html_e( 'Every service on your account', 'pterodactyl-hosting' ); ?></span>
		</div>
	</div>

	<?php if ( empty( $orders ) ) : ?>
		<div class="phm-empty-block">
			<p><?php esc_html_e( 'No invoices yet — order a server and it will show up here.', 'pterodactyl-hosting' ); ?></p>
		</div>
	<?php else : ?>
		<div class="phm-block phm-billing-table-wrap">
			<table class="phm-billing-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Service', 'pterodactyl-hosting' ); ?></th>
						<th><?php esc_html_e( 'Plan', 'pterodactyl-hosting' ); ?></th>
						<th><?php esc_html_e( 'Amount', 'pterodactyl-hosting' ); ?></th>
						<th><?php esc_html_e( 'Status', 'pterodactyl-hosting' ); ?></th>
						<th><?php esc_html_e( 'Next due', 'pterodactyl-hosting' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $orders as $o ) :
						$is_free = (float) $o->amount <= 0;
						?>
						<tr>
							<td>
								<strong><?php echo esc_html( $o->server_label ? $o->server_label : $o->plan_name ); ?></strong><br>
								<code><?php echo esc_html( $o->order_number ); ?></code>
							</td>
							<td><?php echo esc_html( $o->plan_name ); ?></td>
							<td>
								<?php if ( $is_free ) : ?>
									<span class="phm-billing-free"><?php esc_html_e( 'Free', 'pterodactyl-hosting' ); ?></span>
								<?php else : ?>
									<?php echo esc_html( PHM_Plans::format_price( $o->amount, $o->currency ) ); ?>
								<?php endif; ?>
							</td>
							<td>
								<span class="phm-status phm-status-<?php echo esc_attr( PHM_Orders::status_class( $o->status ) ); ?>">
									<span class="phm-status-dot"></span>
									<?php echo esc_html( PHM_Orders::status_label( $o->status ) ); ?>
								</span>
							</td>
							<td>
								<?php
								if ( $is_free ) {
									esc_html_e( 'Never', 'pterodactyl-hosting' );
								} elseif ( ! empty( $o->next_due_at ) ) {
									echo esc_html( gmdate( 'M d, Y', strtotime( $o->next_due_at ) ) );
								} else {
									echo '—';
								}
								?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<p class="phm-hint"><?php esc_html_e( 'Need a receipt or to renew a paid service? Open a billing ticket from Support.', 'pterodactyl-hosting' ); ?></p>
	<?php endif; ?>
</div>
