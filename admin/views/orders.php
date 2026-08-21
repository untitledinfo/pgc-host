<?php
/** Orders admin list. @package Pterodactyl_Hosting */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
PHM_Admin::render_msg();

$filter = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
$orders = PHM_DB::get_orders( $filter );
$methods = PHM_Settings::payment_methods();
?>
<div class="wrap phm-admin">
	<h1><?php esc_html_e( 'Orders', 'pterodactyl-hosting' ); ?></h1>

	<ul class="subsubsub">
		<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=phm-orders' ) ); ?>" class="<?php echo $filter ? '' : 'current'; ?>"><?php esc_html_e( 'All', 'pterodactyl-hosting' ); ?></a> |</li>
		<?php foreach ( PHM_Orders::STATUSES as $st ) : ?>
			<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=phm-orders&status=' . $st ) ); ?>" class="<?php echo $filter === $st ? 'current' : ''; ?>"><?php echo esc_html( PHM_Orders::status_label( $st ) ); ?></a><?php echo 'cancelled' === $st ? '' : ' |'; ?></li>
		<?php endforeach; ?>
	</ul>

	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Order', 'pterodactyl-hosting' ); ?></th>
				<th><?php esc_html_e( 'Customer', 'pterodactyl-hosting' ); ?></th>
				<th><?php esc_html_e( 'Plan / egg', 'pterodactyl-hosting' ); ?></th>
				<th><?php esc_html_e( 'Subdomain', 'pterodactyl-hosting' ); ?></th>
				<th><?php esc_html_e( 'Payment', 'pterodactyl-hosting' ); ?></th>
				<th><?php esc_html_e( 'Next due', 'pterodactyl-hosting' ); ?></th>
				<th><?php esc_html_e( 'Status', 'pterodactyl-hosting' ); ?></th>
				<th><?php esc_html_e( 'Server', 'pterodactyl-hosting' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'pterodactyl-hosting' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php if ( ! $orders ) : ?>
			<tr><td colspan="9"><?php esc_html_e( 'No orders here.', 'pterodactyl-hosting' ); ?></td></tr>
		<?php else : foreach ( $orders as $o ) :
			$base = admin_url( 'admin-post.php?action=phm_order_action&id=' . (int) $o->id );
			?>
			<tr>
				<td><strong><?php echo esc_html( $o->order_number ); ?></strong><br><small><?php echo esc_html( $o->created_at ); ?></small></td>
				<td><?php echo esc_html( $o->customer_name ); ?><br><small><?php echo esc_html( $o->email ); ?><?php echo $o->discord ? ' · ' . esc_html( $o->discord ) : ''; ?></small></td>
				<td><?php echo esc_html( $o->plan_name ); ?><br><small><?php echo esc_html( $o->egg_name ); ?></small></td>
				<td><?php echo $o->fqdn ? '<code>' . esc_html( $o->fqdn ) . '</code>' : '—'; ?></td>
				<td><?php echo esc_html( PHM_Plans::format_price( $o->amount, $o->currency ) ); ?><br>
					<small><?php echo esc_html( isset( $methods[ $o->payment_method ]['label'] ) ? $methods[ $o->payment_method ]['label'] : $o->payment_method ); ?><?php echo $o->payment_ref ? ' (' . esc_html( $o->payment_ref ) . ')' : ''; ?></small>
					<?php if ( ! empty( $o->coupon_code ) ) : ?>
						<br><span style="background: #e0f2fe; color: #0369a1; padding: 2px 6px; border-radius: 4px; font-size: 11px; font-weight: bold;"><?php echo esc_html( $o->coupon_code ); ?> (-$<?php echo esc_html( number_format( (float) $o->discount_amount, 2 ) ); ?>)</span>
					<?php endif; ?>
				</td>
				<td>
					<?php if ( ! empty( $o->next_due_at ) ) : ?>
						<?php
						$due_ts  = strtotime( $o->next_due_at );
						$overdue = $due_ts < time() && 'active' === $o->status;
						?>
						<span class="<?php echo $overdue ? 'phm-bad' : ''; ?>"><?php echo esc_html( gmdate( 'Y-m-d', $due_ts ) ); ?></span>
						<?php if ( $overdue ) : ?><br><small class="phm-bad"><?php esc_html_e( 'overdue', 'pterodactyl-hosting' ); ?></small><?php endif; ?>
					<?php else : ?>—<?php endif; ?>
				</td>
				<td>
					<span class="phm-status phm-status-<?php echo esc_attr( PHM_Orders::status_class( $o->status ) ); ?>"><?php echo esc_html( PHM_Orders::status_label( $o->status ) ); ?></span>
					<?php if ( 'provisioning' === $o->status && $o->stage ) : $stages = PHM_Provisioning::stages(); ?>
						<?php if ( isset( $stages[ $o->stage ] ) ) : ?><br><small><?php echo esc_html( $stages[ $o->stage ][0] ); ?> (<?php echo (int) $stages[ $o->stage ][1]; ?>%)</small><?php endif; ?>
					<?php endif; ?>
					<?php if ( $o->error_message ) : ?><br><small class="phm-bad"><?php echo esc_html( $o->error_message ); ?></small><?php endif; ?>
				</td>
				<td>
					<?php if ( $o->server_id ) : ?>
						<a href="<?php echo esc_url( PHM_Settings::panel_url() . '/admin/servers/view/' . (int) $o->server_id ); ?>" target="_blank" rel="noopener">#<?php echo (int) $o->server_id; ?></a><br>
						<small><?php echo esc_html( trim( $o->server_ip . ( $o->server_port ? ':' . $o->server_port : '' ), ':' ) ); ?></small>
					<?php else : ?>—<?php endif; ?>
				</td>
				<td class="phm-row-actions">
					<?php if ( 'pending' === $o->status ) : ?>
						<a class="button button-small button-primary" href="<?php echo esc_url( wp_nonce_url( $base . '&do=mark_paid', 'phm_order_action' ) ); ?>"><?php esc_html_e( 'Mark paid + deploy', 'pterodactyl-hosting' ); ?></a>
					<?php endif; ?>
					<?php if ( in_array( $o->status, [ 'paid', 'failed' ], true ) ) : ?>
						<a class="button button-small button-primary" href="<?php echo esc_url( wp_nonce_url( $base . '&do=retry', 'phm_order_action' ) ); ?>"><?php esc_html_e( 'Deploy / retry', 'pterodactyl-hosting' ); ?></a>
					<?php endif; ?>
					<?php if ( in_array( $o->status, [ 'active', 'suspended' ], true ) ) : ?>
						<a class="button button-small" href="<?php echo esc_url( wp_nonce_url( $base . '&do=renew', 'phm_order_action' ) ); ?>" title="<?php esc_attr_e( 'Extend renewal by one billing period; unsuspends if suspended', 'pterodactyl-hosting' ); ?>"><?php esc_html_e( 'Renew +1 period', 'pterodactyl-hosting' ); ?></a>
					<?php endif; ?>
					<?php if ( 'active' === $o->status ) : ?>
						<a class="button button-small" href="<?php echo esc_url( wp_nonce_url( $base . '&do=suspend', 'phm_order_action' ) ); ?>" onclick="return confirm('<?php esc_attr_e( 'Suspend the server on the panel?', 'pterodactyl-hosting' ); ?>')"><?php esc_html_e( 'Suspend', 'pterodactyl-hosting' ); ?></a>
						<a class="button button-small button-link-delete" href="<?php echo esc_url( wp_nonce_url( $base . '&do=delete', 'phm_order_action' ) ); ?>" onclick="return confirm('<?php esc_attr_e( 'DELETE the server from the panel + remove DNS?', 'pterodactyl-hosting' ); ?>')"><?php esc_html_e( 'Delete', 'pterodactyl-hosting' ); ?></a>
					<?php endif; ?>
					<?php if ( 'suspended' === $o->status ) : ?>
						<a class="button button-small button-primary" href="<?php echo esc_url( wp_nonce_url( $base . '&do=unsuspend', 'phm_order_action' ) ); ?>"><?php esc_html_e( 'Unsuspend', 'pterodactyl-hosting' ); ?></a>
						<a class="button button-small button-link-delete" href="<?php echo esc_url( wp_nonce_url( $base . '&do=delete', 'phm_order_action' ) ); ?>" onclick="return confirm('<?php esc_attr_e( 'DELETE the server from the panel + remove DNS?', 'pterodactyl-hosting' ); ?>')"><?php esc_html_e( 'Delete', 'pterodactyl-hosting' ); ?></a>
					<?php endif; ?>
					<?php if ( in_array( $o->status, [ 'pending', 'failed' ], true ) ) : ?>
						<a class="button button-small" href="<?php echo esc_url( wp_nonce_url( $base . '&do=cancel', 'phm_order_action' ) ); ?>"><?php esc_html_e( 'Cancel', 'pterodactyl-hosting' ); ?></a>
					<?php endif; ?>
				</td>
			</tr>
		<?php endforeach; endif; ?>
		</tbody>
	</table>
</div>
<?php
