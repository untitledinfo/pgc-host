<?php
/**
 * Single ticket thread, customer side. Variables from templates/dashboard.php:
 * $ticket, $ticket_replies.
 *
 * @package Pterodactyl_Hosting
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$list_url = add_query_arg( [ 'phm_tab' => 'tickets' ], remove_query_arg( [ 'phm_ticket', 'phm_msg' ] ) );
?>
<div class="phm-dash-panel phm-ticket-view-panel">
	<div class="phm-ticket-nav">
		<a class="phm-back-link" href="<?php echo esc_url( $list_url ); ?>">&larr; <?php esc_html_e( 'Back to All Tickets', 'pterodactyl-hosting' ); ?></a>
	</div>

	<div class="phm-block phm-ticket-thread-block">
		<div class="phm-ticket-thread-head">
			<div class="phm-ticket-head-left">
				<div class="phm-ticket-badge-row">
					<span class="phm-ticket-code"><?php echo esc_html( $ticket->ticket_number ); ?></span>
					<?php if ( ! empty( $ticket->department ) ) : ?>
						<span class="phm-dept-tag"><?php echo esc_html( $ticket->department ); ?></span>
					<?php endif; ?>
					<span class="phm-prio-tag phm-prio-<?php echo esc_attr( $ticket->priority ); ?>">
						<?php echo esc_html( PHM_Tickets::priority_label( $ticket->priority ) ); ?>
					</span>
				</div>
				<h2 class="phm-ticket-subject"><?php echo esc_html( $ticket->subject ); ?></h2>
			</div>
			<div class="phm-ticket-head-right">
				<span class="phm-status phm-status-<?php echo esc_attr( PHM_Tickets::status_class( $ticket->status ) ); ?>">
					<span class="phm-status-dot"></span>
					<?php echo esc_html( PHM_Tickets::status_label( $ticket->status ) ); ?>
				</span>
			</div>
		</div>

		<div class="phm-ticket-thread">
			<?php foreach ( $ticket_replies as $r ) : ?>
				<div class="phm-ticket-msg <?php echo $r->is_staff ? 'is-staff' : 'is-customer'; ?>">
					<div class="phm-msg-avatar">
						<?php echo $r->is_staff ? '🛡️' : '👤'; ?>
					</div>
					<div class="phm-msg-body">
						<div class="phm-ticket-msg-head">
							<strong>
								<?php echo esc_html( $r->is_staff ? ( $r->author_name ? $r->author_name : __( 'Support Staff', 'pterodactyl-hosting' ) ) : $r->author_name ); ?>
								<?php if ( $r->is_staff ) : ?>
									<span class="phm-staff-badge"><?php esc_html_e( 'STAFF', 'pterodactyl-hosting' ); ?></span>
								<?php endif; ?>
							</strong>
							<span class="phm-msg-time"><?php echo esc_html( gmdate( 'M d, Y - H:i', strtotime( $r->created_at ) ) ); ?></span>
						</div>
						<div class="phm-msg-content">
							<?php echo nl2br( esc_html( $r->message ) ); ?>
						</div>
					</div>
				</div>
			<?php endforeach; ?>
		</div>

		<?php if ( 'closed' === $ticket->status ) : ?>
			<div class="phm-ticket-closed-notice">
				<p>🔒 <?php esc_html_e( 'This ticket is currently closed. If you still need help, please open a new ticket.', 'pterodactyl-hosting' ); ?></p>
			</div>
		<?php else : ?>
			<div class="phm-ticket-reply-box">
				<h4><?php esc_html_e( 'Send a Reply', 'pterodactyl-hosting' ); ?></h4>
				<form method="post" class="phm-ticket-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="phm_ticket_reply">
					<input type="hidden" name="ticket_id" value="<?php echo esc_attr( $ticket->id ); ?>">
					<?php wp_nonce_field( 'phm_ticket_reply_' . $ticket->id ); ?>
					<textarea name="message" rows="4" placeholder="<?php esc_attr_e( 'Type your reply here…', 'pterodactyl-hosting' ); ?>" required></textarea>
					<button type="submit" class="phm-btn phm-btn-primary"><?php esc_html_e( 'Post Reply →', 'pterodactyl-hosting' ); ?></button>
				</form>
			</div>
		<?php endif; ?>
	</div>
</div>
