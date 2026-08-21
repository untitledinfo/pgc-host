<?php
/**
 * Order tracking template. Variables: $order (object|null), $error (string).
 *
 * @package Pterodactyl_Hosting
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="phm-track-wrap">
	<form method="post" class="phm-form phm-track-form">
		<?php wp_nonce_field( 'phm_track', 'phm_track_nonce' ); ?>
		<h3><?php esc_html_e( 'Track your order', 'pterodactyl-hosting' ); ?></h3>
		<input type="text" name="order_number" placeholder="<?php esc_attr_e( 'Order number (PHM-2026-000001)', 'pterodactyl-hosting' ); ?>" required>
		<input type="email" name="email" placeholder="<?php esc_attr_e( 'Email used at checkout', 'pterodactyl-hosting' ); ?>" required>
		<button class="phm-btn phm-btn-primary" type="submit"><?php esc_html_e( 'Check status', 'pterodactyl-hosting' ); ?></button>
	</form>

	<?php if ( $error ) : ?>
		<p class="phm-alert phm-alert-error"><?php echo esc_html( $error ); ?></p>
	<?php elseif ( $order ) : ?>
		<div class="phm-result phm-track-result">
			<h3><?php echo esc_html( $order->order_number ); ?> — <?php echo esc_html( PHM_Orders::status_label( $order->status ) ); ?></h3>
			<ul>
				<li><strong><?php esc_html_e( 'Plan:', 'pterodactyl-hosting' ); ?></strong> <?php echo esc_html( $order->plan_name ); ?> (<?php echo esc_html( $order->egg_name ); ?>)</li>
				<li><strong><?php esc_html_e( 'Amount:', 'pterodactyl-hosting' ); ?></strong> <?php echo esc_html( PHM_Plans::format_price( $order->amount, $order->currency ) ); ?></li>
				<?php if ( $order->fqdn ) : ?><li><strong><?php esc_html_e( 'Subdomain:', 'pterodactyl-hosting' ); ?></strong> <code><?php echo esc_html( $order->fqdn ); ?></code></li><?php endif; ?>
				<?php if ( 'active' === $order->status ) :
					// FIX: this used to always link to the bare panel homepage.
					// Passing the order's server_identifier deep-links straight
					// to THIS server's console instead.
					$panel_login_url = ( is_user_logged_in() && (int) $order->wp_user_id === get_current_user_id() )
						? PHM_Cookie_Login::url_for_current_user( $order->server_identifier )
						: '';
					?>
					<li><strong><?php esc_html_e( 'Panel:', 'pterodactyl-hosting' ); ?></strong>
						<?php if ( $panel_login_url ) : ?>
							<a class="phm-btn phm-btn-primary" href="<?php echo esc_url( $panel_login_url ); ?>"><?php esc_html_e( 'Go to Server', 'pterodactyl-hosting' ); ?></a>
						<?php else : ?>
							<a href="<?php echo esc_url( PHM_Settings::panel_url() ); ?>" target="_blank" rel="noopener"><?php echo esc_html( PHM_Settings::panel_url() ); ?></a>
						<?php endif; ?>
					</li>
					<?php $public_address = PHM_Frontend::public_address( $order ); ?>
					<?php if ( $public_address ) : ?>
					<li><strong><?php esc_html_e( 'Hostname:', 'pterodactyl-hosting' ); ?></strong>
						<code><?php echo esc_html( $public_address ); ?></code></li>
					<?php else : ?>
					<li><strong><?php esc_html_e( 'Connection:', 'pterodactyl-hosting' ); ?></strong>
						<?php esc_html_e( 'Connect through the Game Panel — the node IP is private.', 'pterodactyl-hosting' ); ?></li>
					<?php endif; ?>
				<?php elseif ( 'failed' === $order->status && $order->error_message ) : ?>
					<li class="phm-alert-error"><?php echo esc_html( $order->error_message ); ?></li>
				<?php endif; ?>
				<li><strong><?php esc_html_e( 'Updated:', 'pterodactyl-hosting' ); ?></strong> <?php echo esc_html( $order->updated_at ); ?></li>
			</ul>
		</div>
	<?php endif; ?>
</div>
<?php
