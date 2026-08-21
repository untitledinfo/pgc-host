<?php
/**
 * Standalone Support Ticket Creation Template [phm_ticket_create].
 *
 * @package Pterodactyl_Hosting
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! is_user_logged_in() ) {
	?>
	<div class="phm-dash-panel phm-login-gate" style="text-align: center; padding: 40px 20px;">
		<h3><?php esc_html_e( 'Please log in to open a support ticket', 'pterodactyl-hosting' ); ?></h3>
		<p><?php esc_html_e( 'You must have an active account to submit and track support requests.', 'pterodactyl-hosting' ); ?></p>
		<p>
			<a class="phm-btn phm-btn-primary" href="<?php echo esc_url( wp_login_url( home_url( add_query_arg( null, null ) ) ) ); ?>"><?php esc_html_e( 'Log In', 'pterodactyl-hosting' ); ?></a>
		</p>
	</div>
	<?php
	return;
}

$user_orders      = PHM_DB::get_orders_for_wp_user( get_current_user_id() );
$preselect_order  = isset( $_GET['order_id'] ) ? (int) $_GET['order_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification
$dash_url         = PHM_Store::page_url( 'phm_dashboard' );
$action_url       = admin_url( 'admin-post.php' );
?>
<div class="phm-ticket-create-wrap phm-dash-panel">
	<div class="phm-ticket-header">
		<h2><?php esc_html_e( 'Open a Support Ticket', 'pterodactyl-hosting' ); ?></h2>
		<p class="phm-hint"><?php esc_html_e( 'Our dedicated support team is available 24/7 to assist with server setup, technical questions, and billing.', 'pterodactyl-hosting' ); ?></p>
	</div>

	<form method="post" action="<?php echo esc_url( $action_url ); ?>" class="phm-form phm-ticket-form">
		<input type="hidden" name="action" value="phm_ticket_create">
		<?php wp_nonce_field( 'phm_ticket_create' ); ?>

		<div class="phm-form-row phm-form-grid-2">
			<div class="phm-field-group">
				<label for="ticket_department"><?php esc_html_e( 'Department', 'pterodactyl-hosting' ); ?></label>
				<select name="department" id="ticket_department" required>
					<option value="Technical"><?php esc_html_e( 'Technical Support / Server Issue', 'pterodactyl-hosting' ); ?></option>
					<option value="Billing"><?php esc_html_e( 'Billing & Invoices', 'pterodactyl-hosting' ); ?></option>
					<option value="Setup"><?php esc_html_e( 'Plugin / Modpack / Egg Setup', 'pterodactyl-hosting' ); ?></option>
					<option value="General"><?php esc_html_e( 'Sales & General Inquiries', 'pterodactyl-hosting' ); ?></option>
				</select>
			</div>

			<div class="phm-field-group">
				<label for="ticket_priority"><?php esc_html_e( 'Priority', 'pterodactyl-hosting' ); ?></label>
				<select name="priority" id="ticket_priority" required>
					<option value="low"><?php esc_html_e( 'Low — General Question', 'pterodactyl-hosting' ); ?></option>
					<option value="normal" selected><?php esc_html_e( 'Normal — Standard Request', 'pterodactyl-hosting' ); ?></option>
					<option value="high"><?php esc_html_e( 'High — Server Outage / Critical', 'pterodactyl-hosting' ); ?></option>
				</select>
			</div>
		</div>

		<?php if ( ! empty( $user_orders ) ) : ?>
		<div class="phm-field-group">
			<label for="ticket_order"><?php esc_html_e( 'Related Server (Optional)', 'pterodactyl-hosting' ); ?></label>
			<select name="order_id" id="ticket_order">
				<option value="0"><?php esc_html_e( '— None (General Account/Billing Question) —', 'pterodactyl-hosting' ); ?></option>
				<?php foreach ( $user_orders as $o ) : ?>
					<option value="<?php echo (int) $o->id; ?>" <?php selected( $preselect_order === (int) $o->id ); ?>>
						<?php echo esc_html( $o->server_label ? $o->server_label : $o->plan_name ); ?> (<?php echo esc_html( $o->order_number ); ?>)
					</option>
				<?php endforeach; ?>
			</select>
		</div>
		<?php endif; ?>

		<div class="phm-field-group">
			<label for="ticket_subject"><?php esc_html_e( 'Subject', 'pterodactyl-hosting' ); ?> *</label>
			<input type="text" name="subject" id="ticket_subject" required placeholder="<?php esc_attr_e( 'Brief description of your request…', 'pterodactyl-hosting' ); ?>" maxlength="190">
		</div>

		<div class="phm-field-group">
			<label for="ticket_message"><?php esc_html_e( 'Message Details', 'pterodactyl-hosting' ); ?> *</label>
			<textarea name="message" id="ticket_message" rows="6" required placeholder="<?php esc_attr_e( 'Please describe your issue in detail. Include any error messages, logs or steps to reproduce…', 'pterodactyl-hosting' ); ?>"></textarea>
		</div>

		<div class="phm-ticket-actions">
			<button type="submit" class="phm-btn phm-btn-primary phm-btn-lg"><?php esc_html_e( 'Submit Ticket →', 'pterodactyl-hosting' ); ?></button>
			<?php if ( $dash_url ) : ?>
				<a href="<?php echo esc_url( add_query_arg( 'phm_tab', 'tickets', $dash_url ) ); ?>" class="phm-btn phm-btn-muted"><?php esc_html_e( 'View My Tickets', 'pterodactyl-hosting' ); ?></a>
			<?php endif; ?>
		</div>
	</form>
</div>
