<?php
/** Support ticket admin detail view. @package Pterodactyl_Hosting */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
PHM_Admin::render_msg();

$id     = isset( $_GET['view'] ) ? (int) $_GET['view'] : 0; // phpcs:ignore WordPress.Security.NonceVerification
$ticket = PHM_DB::get_ticket( $id );

if ( ! $ticket ) {
	echo '<div class="wrap phm-admin"><h1>' . esc_html__( 'Support Tickets', 'pterodactyl-hosting' ) . '</h1><p>' . esc_html__( 'Ticket not found.', 'pterodactyl-hosting' ) . '</p></div>';
	return;
}

$replies  = PHM_DB::get_ticket_replies( $ticket->id );
$customer = get_userdata( $ticket->wp_user_id );
$order    = $ticket->order_id ? PHM_DB::get_order( $ticket->order_id ) : null;
$back_url = admin_url( 'admin.php?page=phm-tickets' );
?>
<div class="wrap phm-admin">
	<h1>
		<?php echo esc_html( $ticket->subject ); ?>
		<a href="<?php echo esc_url( $back_url ); ?>" class="page-title-action"><?php esc_html_e( 'Back to all tickets', 'pterodactyl-hosting' ); ?></a>
	</h1>

	<div class="phm-grid-2">
		<div class="phm-panel">
			<h2><?php esc_html_e( 'Conversation', 'pterodactyl-hosting' ); ?></h2>

			<p>
				<span class="phm-status phm-status-<?php echo esc_attr( PHM_Tickets::status_class( $ticket->status ) ); ?>"><?php echo esc_html( PHM_Tickets::admin_status_label( $ticket->status ) ); ?></span>
				&nbsp;<?php echo esc_html( PHM_Tickets::priority_label( $ticket->priority ) ); ?> <?php esc_html_e( 'priority', 'pterodactyl-hosting' ); ?>
				&nbsp;·&nbsp; <?php echo esc_html( $ticket->ticket_number ); ?>
			</p>

			<ul class="phm-log">
				<?php foreach ( $replies as $r ) : ?>
					<li class="<?php echo $r->is_staff ? 'phm-log-success' : ''; ?>">
						<strong><?php echo esc_html( $r->author_name ? $r->author_name : ( $r->is_staff ? __( 'Support', 'pterodactyl-hosting' ) : __( 'Customer', 'pterodactyl-hosting' ) ) ); ?></strong>
						<small><?php echo esc_html( $r->created_at ); ?></small><br>
						<?php echo nl2br( esc_html( $r->message ) ); ?>
					</li>
				<?php endforeach; ?>
			</ul>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="phm_ticket_admin_reply">
				<input type="hidden" name="ticket_id" value="<?php echo esc_attr( $ticket->id ); ?>">
				<?php wp_nonce_field( 'phm_ticket_admin_reply_' . $ticket->id ); ?>
				<p>
					<textarea name="message" rows="5" class="large-text" placeholder="<?php esc_attr_e( 'Write a reply…', 'pterodactyl-hosting' ); ?>"></textarea>
				</p>
				<p class="phm-actions-row">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Send reply', 'pterodactyl-hosting' ); ?></button>
					<button type="submit" name="close" value="1" class="button"><?php esc_html_e( 'Reply + close ticket', 'pterodactyl-hosting' ); ?></button>
				</p>
			</form>

			<?php if ( 'closed' === $ticket->status ) : ?>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=phm_ticket_admin_status&id=' . (int) $ticket->id . '&status=open' ), 'phm_ticket_admin_status_' . $ticket->id ) ); ?>"><?php esc_html_e( 'Reopen ticket', 'pterodactyl-hosting' ); ?></a>
			<?php else : ?>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=phm_ticket_admin_status&id=' . (int) $ticket->id . '&status=closed' ), 'phm_ticket_admin_status_' . $ticket->id ) ); ?>" onclick="return confirm('<?php esc_attr_e( 'Close this ticket without replying?', 'pterodactyl-hosting' ); ?>')"><?php esc_html_e( 'Close without reply', 'pterodactyl-hosting' ); ?></a>
			<?php endif; ?>
		</div>

		<div class="phm-panel">
			<h2><?php esc_html_e( 'Details', 'pterodactyl-hosting' ); ?></h2>
			<table class="form-table">
				<tr>
					<th><?php esc_html_e( 'Customer', 'pterodactyl-hosting' ); ?></th>
					<td><?php echo $customer ? esc_html( $customer->display_name ) . '<br>' . esc_html( $customer->user_email ) : '—'; ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Server', 'pterodactyl-hosting' ); ?></th>
					<td>
						<?php if ( $order ) : ?>
							<?php echo esc_html( $order->server_label ? $order->server_label : $order->plan_name ); ?><br>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=phm-orders' ) ); ?>"><?php echo esc_html( $order->order_number ); ?></a>
							<?php if ( $order->server_id ) : ?>
								<br><a href="<?php echo esc_url( PHM_Settings::panel_url() . '/admin/servers/view/' . (int) $order->server_id ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View on panel', 'pterodactyl-hosting' ); ?></a>
							<?php endif; ?>
						<?php else : ?>—<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Opened', 'pterodactyl-hosting' ); ?></th>
					<td><?php echo esc_html( $ticket->created_at ); ?></td>
				</tr>
			</table>
		</div>
	</div>
</div>
<?php
