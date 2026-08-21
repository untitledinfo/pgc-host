<?php
/**
 * Admin view: Payment Gateways & API System (250+ APIs).
 *
 * @package Pterodactyl_Hosting
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$action    = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
$edit_id   = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification
$gateways  = PHM_DB::get_gateways();
$edit_item = null;
if ( $edit_id ) {
	foreach ( $gateways as $g ) {
		if ( (int) $g->id === $edit_id ) {
			$edit_item = $g;
			break;
		}
	}
}
$directory   = PHM_Gateways::gateway_directory();
$webhook_url = rest_url( 'phm/v1/payment-ipn/' ) . ( $edit_item ? $edit_item->gateway_id : 'universal_api' );
?>
<div class="wrap phm-admin-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Payment Gateways & API Hub', 'pterodactyl-hosting' ); ?></h1>
	<p class="description"><?php esc_html_e( 'Configure Crypto (Cryptomus, NOWPayments, CoinPayments), Credit Cards (Stripe, PayPal, Razorpay, Paystack), and Universal Webhooks for 250+ payment providers.', 'pterodactyl-hosting' ); ?></p>
	<hr class="wp-header-end">

	<?php PHM_Admin::render_msg(); ?>

	<?php if ( 'edit' === $action && $edit_item ) :
		$meta = isset( $directory[ $edit_item->gateway_id ] ) ? $directory[ $edit_item->gateway_id ] : null;
		?>
		<div class="phm-card" style="max-width: 800px; margin-top: 20px;">
			<h2><?php echo sprintf( esc_html__( 'Configure: %s', 'pterodactyl-hosting' ), esc_html( $edit_item->name ) ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="phm_save_gateway">
				<input type="hidden" name="id" value="<?php echo (int) $edit_item->id; ?>">
				<input type="hidden" name="gateway_id" value="<?php echo esc_attr( $edit_item->gateway_id ); ?>">
				<?php wp_nonce_field( 'phm_save_gateway' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="gateway_name"><?php esc_html_e( 'Display Name', 'pterodactyl-hosting' ); ?> *</label></th>
						<td>
							<input name="name" type="text" id="gateway_name" value="<?php echo esc_attr( $edit_item->name ); ?>" class="regular-text" required>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="gateway_type"><?php esc_html_e( 'Type', 'pterodactyl-hosting' ); ?></label></th>
						<td>
							<select name="type" id="gateway_type">
								<option value="crypto" <?php selected( 'crypto' === $edit_item->type ); ?>><?php esc_html_e( 'Cryptocurrency (Instant Auto-Confirm)', 'pterodactyl-hosting' ); ?></option>
								<option value="fiat" <?php selected( 'fiat' === $edit_item->type ); ?>><?php esc_html_e( 'Credit Cards / Fiat / Wallets', 'pterodactyl-hosting' ); ?></option>
								<option value="manual" <?php selected( 'manual' === $edit_item->type ); ?>><?php esc_html_e( 'Manual Bank / UPI / QR', 'pterodactyl-hosting' ); ?></option>
								<option value="api" <?php selected( 'api' === $edit_item->type ); ?>><?php esc_html_e( 'Universal Payment API Webhook', 'pterodactyl-hosting' ); ?></option>
							</select>
						</td>
					</tr>

					<?php if ( 'manual' !== $edit_item->gateway_id ) : ?>
					<tr>
						<th scope="row"><label for="merchant_id"><?php esc_html_e( 'Merchant ID / Client ID', 'pterodactyl-hosting' ); ?></label></th>
						<td>
							<input name="merchant_id" type="text" id="merchant_id" value="<?php echo esc_attr( $edit_item->merchant_id ); ?>" class="regular-text" placeholder="e.g. your merchant UUID or client ID">
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="api_key"><?php esc_html_e( 'API Key / Public Key', 'pterodactyl-hosting' ); ?></label></th>
						<td>
							<input name="api_key" type="password" id="api_key" value="<?php echo esc_attr( $edit_item->api_key ); ?>" class="regular-text" autocomplete="new-password">
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="api_secret"><?php esc_html_e( 'API Secret / Private Key', 'pterodactyl-hosting' ); ?></label></th>
						<td>
							<input name="api_secret" type="password" id="api_secret" value="<?php echo esc_attr( $edit_item->api_secret ); ?>" class="regular-text" autocomplete="new-password">
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="webhook_secret"><?php esc_html_e( 'Webhook Secret / IPN Secret', 'pterodactyl-hosting' ); ?></label></th>
						<td>
							<input name="webhook_secret" type="password" id="webhook_secret" value="<?php echo esc_attr( $edit_item->webhook_secret ); ?>" class="regular-text">
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Your Webhook / IPN URL', 'pterodactyl-hosting' ); ?></th>
						<td>
							<code><?php echo esc_html( $webhook_url ); ?></code>
							<p class="description"><?php esc_html_e( 'Paste this Webhook / IPN URL in your payment gateway dashboard to receive instant payment notifications and automatically deploy servers.', 'pterodactyl-hosting' ); ?></p>
						</td>
					</tr>
					<?php endif; ?>

					<tr>
						<th scope="row"><label for="instructions"><?php esc_html_e( 'Customer Payment Instructions', 'pterodactyl-hosting' ); ?></label></th>
						<td>
							<textarea name="instructions" id="instructions" rows="4" class="large-text" placeholder="<?php esc_attr_e( 'Instructions or note shown to customer during checkout…', 'pterodactyl-hosting' ); ?>"><?php echo esc_textarea( $edit_item->instructions ); ?></textarea>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Test Mode (Sandbox)', 'pterodactyl-hosting' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="test_mode" value="1" <?php checked( ! empty( $edit_item->test_mode ) ); ?>>
								<?php esc_html_e( 'Enable Sandbox / Test Mode for testing transactions', 'pterodactyl-hosting' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Status', 'pterodactyl-hosting' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="active" value="1" <?php checked( ! empty( $edit_item->active ) ); ?>>
								<?php esc_html_e( 'Active (show in checkout method list)', 'pterodactyl-hosting' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<p class="submit">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Gateway Settings', 'pterodactyl-hosting' ); ?></button>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=phm-gateways' ) ); ?>" class="button"><?php esc_html_e( 'Cancel', 'pterodactyl-hosting' ); ?></a>
				</p>
			</form>
		</div>
	<?php else : ?>

		<div class="phm-card" style="margin-top: 20px;">
			<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
				<h2 style="margin: 0;"><?php esc_html_e( 'Configured Gateways & APIs', 'pterodactyl-hosting' ); ?></h2>
				<span class="phm-badge phm-badge-info" style="font-size: 13px; padding: 6px 12px;"><?php esc_html_e( 'Universal Webhook API Ready (250+ Providers)', 'pterodactyl-hosting' ); ?></span>
			</div>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th style="width: 250px;"><?php esc_html_e( 'Gateway Provider', 'pterodactyl-hosting' ); ?></th>
						<th><?php esc_html_e( 'Type', 'pterodactyl-hosting' ); ?></th>
						<th><?php esc_html_e( 'Webhook Endpoint', 'pterodactyl-hosting' ); ?></th>
						<th><?php esc_html_e( 'Mode', 'pterodactyl-hosting' ); ?></th>
						<th><?php esc_html_e( 'Status', 'pterodactyl-hosting' ); ?></th>
						<th><?php esc_html_e( 'Action', 'pterodactyl-hosting' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $gateways as $g ) :
						$url = rest_url( 'phm/v1/payment-ipn/' . $g->gateway_id );
						?>
						<tr>
							<td>
								<strong><?php echo esc_html( $g->name ); ?></strong>
								<br><small style="color: #888;">ID: <code><?php echo esc_html( $g->gateway_id ); ?></code></small>
							</td>
							<td>
								<?php
								if ( 'crypto' === $g->type ) {
									echo '<span class="dashicons dashicons-money-alt" style="color: #f7931a; vertical-align: middle;"></span> ' . esc_html__( 'Crypto', 'pterodactyl-hosting' );
								} elseif ( 'fiat' === $g->type ) {
									echo '<span class="dashicons dashicons-cart" style="color: #2271b1; vertical-align: middle;"></span> ' . esc_html__( 'Card / Fiat', 'pterodactyl-hosting' );
								} elseif ( 'api' === $g->type ) {
									echo '<span class="dashicons dashicons-rest-api" style="color: #46b450; vertical-align: middle;"></span> ' . esc_html__( 'Universal API', 'pterodactyl-hosting' );
								} else {
									echo '<span class="dashicons dashicons-bank" style="color: #888; vertical-align: middle;"></span> ' . esc_html__( 'Manual / UPI', 'pterodactyl-hosting' );
								}
								?>
							</td>
							<td>
								<code><?php echo esc_html( $url ); ?></code>
							</td>
							<td>
								<?php echo ! empty( $g->test_mode ) ? '<span style="color: #d63638; font-weight: 600;">' . esc_html__( 'Sandbox', 'pterodactyl-hosting' ) . '</span>' : esc_html__( 'Live', 'pterodactyl-hosting' ); ?>
							</td>
							<td>
								<?php if ( $g->active ) : ?>
									<span class="phm-badge phm-badge-active"><?php esc_html_e( 'Active', 'pterodactyl-hosting' ); ?></span>
								<?php else : ?>
									<span class="phm-badge phm-badge-inactive"><?php esc_html_e( 'Disabled', 'pterodactyl-hosting' ); ?></span>
								<?php endif; ?>
							</td>
							<td>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=phm-gateways&action=edit&id=' . (int) $g->id ) ); ?>" class="button button-small button-primary"><?php esc_html_e( 'Configure', 'pterodactyl-hosting' ); ?></a>
								<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=phm_toggle_gateway&id=' . (int) $g->id ), 'phm_toggle_gateway_' . $g->id ) ); ?>" class="button button-small"><?php echo $g->active ? esc_html__( 'Disable', 'pterodactyl-hosting' ) : esc_html__( 'Enable', 'pterodactyl-hosting' ); ?></a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

	<?php endif; ?>
</div>
