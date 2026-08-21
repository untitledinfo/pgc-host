<?php
/**
 * "Support Tickets" tab (list & create view).
 * Variables from templates/dashboard.php: $tickets, $orders.
 *
 * @package Pterodactyl_Hosting
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$preselect_order = isset( $_GET['phm_new_order_id'] ) ? (int) $_GET['phm_new_order_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification
?>
<div class="phm-dash-panel phm-dash-grid">

	<div class="phm-block phm-ticket-form-card">
		<h3><span>+</span> <?php esc_html_e( 'Open a Support Ticket', 'pterodactyl-hosting' ); ?></h3>
		<form method="post" class="phm-ticket-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="phm_ticket_create">
			<?php wp_nonce_field( 'phm_ticket_create' ); ?>

			<div class="phm-field-group">
				<label for="dash_ticket_subject"><?php esc_html_e( 'Subject', 'pterodactyl-hosting' ); ?> *</label>
				<input type="text" id="dash_ticket_subject" name="subject" maxlength="190" placeholder="<?php esc_attr_e( 'Brief description of your issue…', 'pterodactyl-hosting' ); ?>" required>
			</div>

			<div class="phm-form-grid-2">
				<div class="phm-field-group">
					<label for="dash_ticket_dept"><?php esc_html_e( 'Department', 'pterodactyl-hosting' ); ?></label>
					<select name="department" id="dash_ticket_dept">
						<option value="Technical"><?php esc_html_e( 'Technical Support', 'pterodactyl-hosting' ); ?></option>
						<option value="Billing"><?php esc_html_e( 'Billing & Invoices', 'pterodactyl-hosting' ); ?></option>
						<option value="Setup"><?php esc_html_e( 'Server / Modpack Setup', 'pterodactyl-hosting' ); ?></option>
						<option value="General"><?php esc_html_e( 'Sales & General', 'pterodactyl-hosting' ); ?></option>
					</select>
				</div>

				<div class="phm-field-group">
					<label for="dash_ticket_prio"><?php esc_html_e( 'Priority', 'pterodactyl-hosting' ); ?></label>
					<select name="priority" id="dash_ticket_prio">
						<option value="low"><?php esc_html_e( 'Low', 'pterodactyl-hosting' ); ?></option>
						<option value="normal" selected><?php esc_html_e( 'Normal', 'pterodactyl-hosting' ); ?></option>
						<option value="high"><?php esc_html_e( 'High (Urgent)', 'pterodactyl-hosting' ); ?></option>
					</select>
				</div>
			</div>

			<?php if ( ! empty( $orders ) ) : ?>
				<div class="phm-field-group">
					<label for="dash_ticket_order"><?php esc_html_e( 'Related Server', 'pterodactyl-hosting' ); ?></label>
					<select name="order_id" id="dash_ticket_order">
						<option value="0"><?php esc_html_e( '— None (General Inquiry) —', 'pterodactyl-hosting' ); ?></option>
						<?php foreach ( $orders as $o ) : ?>
							<option value="<?php echo esc_attr( $o->id ); ?>" <?php selected( $preselect_order, $o->id ); ?>>
								<?php echo esc_html( ( $o->server_label ? $o->server_label : $o->plan_name ) . ' (' . $o->order_number . ')' ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
			<?php endif; ?>

			<div class="phm-field-group">
				<label for="dash_ticket_msg"><?php esc_html_e( 'Message Details', 'pterodactyl-hosting' ); ?> *</label>
				<textarea id="dash_ticket_msg" name="message" rows="5" placeholder="<?php esc_attr_e( 'Please describe your request in detail…', 'pterodactyl-hosting' ); ?>" required></textarea>
			</div>

			<button type="submit" class="phm-btn phm-btn-primary phm-btn-block"><?php esc_html_e( 'Submit Ticket →', 'pterodactyl-hosting' ); ?></button>
		</form>
	</div>

	<div class="phm-block phm-ticket-list-card">
		<h3><span>#</span> <?php esc_html_e( 'Your Support Tickets', 'pterodactyl-hosting' ); ?></h3>
		<?php if ( empty( $tickets ) ) : ?>
			<div class="phm-empty-tickets">
				<p><?php esc_html_e( "You haven't opened any support tickets yet.", 'pterodactyl-hosting' ); ?></p>
				<small><?php esc_html_e( 'Need help with your server? Use the form on the left to reach our support team.', 'pterodactyl-hosting' ); ?></small>
			</div>
		<?php else : ?>
			<ul class="phm-ticket-list">
				<?php foreach ( $tickets as $t ) : ?>
					<li class="phm-ticket-item">
						<a href="<?php echo esc_url( add_query_arg( [ 'phm_tab' => 'tickets', 'phm_ticket' => $t->id ], remove_query_arg( [ 'phm_msg', 'phm_new_order_id' ] ) ) ); ?>">
							<div class="phm-ticket-item-top">
								<strong class="phm-ticket-title"><?php echo esc_html( $t->subject ); ?></strong>
								<span class="phm-status phm-status-<?php echo esc_attr( PHM_Tickets::status_class( $t->status ) ); ?>">
									<?php echo esc_html( PHM_Tickets::status_label( $t->status ) ); ?>
								</span>
							</div>
							<div class="phm-ticket-meta">
								<code><?php echo esc_html( $t->ticket_number ); ?></code>
								<?php if ( ! empty( $t->department ) ) : ?>
									<span class="phm-dept-tag"><?php echo esc_html( $t->department ); ?></span>
								<?php endif; ?>
								<span><?php echo esc_html( gmdate( 'M d, H:i', strtotime( $t->updated_at ) ) ); ?></span>
							</div>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>

</div>
