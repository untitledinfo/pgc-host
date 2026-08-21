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
		<h3><?php esc_html_e( 'Please log in to order', 'pterodactyl-hosting' ); ?></h3>
		<p><?php esc_html_e( 'Your server plan is tied to your account — log in (or create one) and your name, email, and password carry straight over to your game panel login, no second password to remember.', 'pterodactyl-hosting' ); ?></p>
		<p>
			<a class="phm-btn phm-btn-primary" href="<?php echo esc_url( wp_login_url( $here ) ); ?>"><?php esc_html_e( 'Log in', 'pterodactyl-hosting' ); ?></a>
			<?php if ( get_option( 'users_can_register' ) ) : ?>
				<a class="phm-btn phm-btn-muted" href="<?php echo esc_url( wp_registration_url() ); ?>"><?php esc_html_e( 'Create an account', 'pterodactyl-hosting' ); ?></a>
			<?php endif; ?>
		</p>
	</div>
</div>
