<?php
/** Support tickets admin list. @package Pterodactyl_Hosting */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
PHM_Admin::render_msg();

$filter  = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
$tickets = PHM_DB::get_tickets( $filter );
$counts  = PHM_DB::ticket_counts();
?>
<div class="wrap phm-admin">
	<h1><?php esc_html_e( 'Support Tickets', 'pterodactyl-hosting' ); ?></h1>

	<ul class="subsubsub">
		<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=phm-tickets' ) ); ?>" class="<?php echo $filter ? '' : 'current'; ?>"><?php esc_html_e( 'All', 'pterodactyl-hosting' ); ?></a> |</li>
		<?php foreach ( PHM_Tickets::STATUSES as $st ) : ?>
			<li>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=phm-tickets&status=' . $st ) ); ?>" class="<?php echo $filter === $st ? 'current' : ''; ?>">
					<?php echo esc_html( PHM_Tickets::admin_status_label( $st ) ); ?> (<?php echo (int) $counts[ $st ]; ?>)
				</a><?php echo 'closed' === $st ? '' : ' |'; ?>
			</li>
		<?php endforeach; ?>
	</ul>

	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Ticket', 'pterodactyl-hosting' ); ?></th>
				<th><?php esc_html_e( 'Customer', 'pterodactyl-hosting' ); ?></th>
				<th><?php esc_html_e( 'Server', 'pterodactyl-hosting' ); ?></th>
				<th><?php esc_html_e( 'Priority', 'pterodactyl-hosting' ); ?></th>
				<th><?php esc_html_e( 'Status', 'pterodactyl-hosting' ); ?></th>
				<th><?php esc_html_e( 'Updated', 'pterodactyl-hosting' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'pterodactyl-hosting' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php if ( ! $tickets ) : ?>
			<tr><td colspan="7"><?php esc_html_e( 'No tickets here.', 'pterodactyl-hosting' ); ?></td></tr>
		<?php else : foreach ( $tickets as $t ) :
			$customer = get_userdata( $t->wp_user_id );
			$order    = $t->order_id ? PHM_DB::get_order( $t->order_id ) : null;
			$view_url = admin_url( 'admin.php?page=phm-tickets&view=' . (int) $t->id );
			?>
			<tr>
				<td>
					<a href="<?php echo esc_url( $view_url ); ?>"><strong><?php echo esc_html( $t->subject ); ?></strong></a><br>
					<small><?php echo esc_html( $t->ticket_number ); ?><?php if ( ! empty( $t->department ) ) : ?> · <span style="background:#f1f5f9; padding: 1px 5px; border-radius: 3px; font-weight: 600;"><?php echo esc_html( $t->department ); ?></span><?php endif; ?></small>
				</td>
				<td><?php echo $customer ? esc_html( $customer->display_name ) . '<br><small>' . esc_html( $customer->user_email ) . '</small>' : '—'; ?></td>
				<td><?php echo $order ? esc_html( $order->server_label ? $order->server_label : $order->plan_name ) : '—'; ?></td>
				<td><?php echo esc_html( PHM_Tickets::priority_label( $t->priority ) ); ?></td>
				<td><span class="phm-status phm-status-<?php echo esc_attr( PHM_Tickets::status_class( $t->status ) ); ?>"><?php echo esc_html( PHM_Tickets::admin_status_label( $t->status ) ); ?></span></td>
				<td><?php echo esc_html( $t->updated_at ); ?></td>
				<td class="phm-row-actions">
					<a class="button button-small button-primary" href="<?php echo esc_url( $view_url ); ?>"><?php esc_html_e( 'View / reply', 'pterodactyl-hosting' ); ?></a>
				</td>
			</tr>
		<?php endforeach; endif; ?>
		</tbody>
	</table>
</div>
<?php
