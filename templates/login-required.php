<?php
/**
 * Shown in place of the checkout form for logged-out visitors — ordering
 * requires a WordPress account so the panel login can reuse it.
 *
 * @package Pterodactyl_Hosting
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$here = home_url( add_query_arg( null, null ) );
?>
<div class="phm-checkout-wrap">
	<div class="phm-login-gate">
		<h3><?php esc_html_e( 'Please log in', 'pterodactyl-hosting' ); ?></h3>
		<p><?php esc_html_e( 'Your services are tied to your account. Log in (or create one) to continue — the same login is used for the website and the game panel.', 'pterodactyl-hosting' ); ?></p>
		<p>
			<a class="phm-btn phm-btn-primary" href="<?php echo esc_url( wp_login_url( $here ) ); ?>"><?php esc_html_e( 'Log in', 'pterodactyl-hosting' ); ?></a>
			<?php if ( get_option( 'users_can_register' ) ) : ?>
				<a class="phm-btn phm-btn-muted" href="<?php echo esc_url( wp_registration_url() ); ?>"><?php esc_html_e( 'Create an account', 'pterodactyl-hosting' ); ?></a>
			<?php endif; ?>
		</p>
	</div>
</div>
