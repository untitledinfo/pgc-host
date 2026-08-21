<?php
/**
 * Modern High-Performance Order / Checkout Template ("Subdomain Cart").
 * Variables: $products, $product (pre-selected or null), $nests, $methods, $wp_user.
 *
 * @package Pterodactyl_Hosting
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$settings     = PHM_Settings::get();
$subdomain_on = PHM_Cloudflare::enabled();
$base         = PHM_Cloudflare::base_domain();
$gateways     = PHM_Gateways::get_active_methods();
$is_logged_in = is_user_logged_in();
$current_user = wp_get_current_user();
?>
<div class="phm-checkout-wrap">
	<?php if ( ! $products ) : ?>
		<p class="phm-empty">
			<?php esc_html_e( 'No plans are available right now — please check back soon.', 'pterodactyl-hosting' ); ?>
			<?php if ( current_user_can( 'manage_options' ) ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=phm-products&action=new' ) ); ?>"><?php esc_html_e( 'Add your first plan →', 'pterodactyl-hosting' ); ?></a>
			<?php endif; ?>
		</p>
	<?php else : ?>
	<form id="phm-order-form" class="phm-form">
		<div class="phm-form-col">

			<!-- Step 1: Plan Selection -->
			<section class="phm-block">
				<h3><span>1</span> <?php esc_html_e( 'Choose Your Server Plan', 'pterodactyl-hosting' ); ?></h3>
				<select name="product_id" id="phm-product" required>
					<?php foreach ( $products as $p ) : ?>
						<option
							value="<?php echo (int) $p->id; ?>"
							data-nest="<?php echo (int) $p->nest_id; ?>"
							data-egg="<?php echo (int) $p->egg_id; ?>"
							data-amount="<?php echo esc_attr( (float) $p->price + (float) $p->setup_fee ); ?>"
							data-price="<?php echo esc_attr( PHM_Plans::format_price( (float) $p->price + (float) $p->setup_fee, $p->currency ) ); ?>"
							<?php selected( $product && (int) $product->id === (int) $p->id ); ?> >
							<?php echo esc_html( $p->name ); ?> — <?php echo esc_html( PHM_Plans::format_price( $p->price, $p->currency ) ); ?>/mo
							(<?php echo esc_html( PHM_Plans::format_memory( $p->memory ) ); ?> RAM, <?php echo esc_html( PHM_Plans::format_memory( $p->disk ) ); ?> NVMe)
						</option>
					<?php endforeach; ?>
				</select>
			</section>

			<!-- Step 2: Server Type / Egg Selection -->
			<section class="phm-block">
				<h3><span>2</span> <?php esc_html_e( 'Server Software / Game Version', 'pterodactyl-hosting' ); ?></h3>
				<select name="egg_id" id="phm-egg" required></select>
				<p class="phm-hint" id="phm-egg-desc"></p>
			</section>

			<!-- Step 3: Subdomain Selection -->
			<?php if ( $subdomain_on ) : ?>
			<section class="phm-block">
				<h3><span>3</span> <?php esc_html_e( 'Choose Your Free Subdomain', 'pterodactyl-hosting' ); ?></h3>
				<div class="phm-subdomain-row">
					<input type="text" name="subdomain" id="phm-subdomain" maxlength="32" placeholder="myserver" autocomplete="off" <?php echo ! empty( $settings['cf_subdomain_required'] ) ? 'required' : ''; ?>>
					<span class="phm-domain-suffix">.<?php echo esc_html( $base ); ?></span>
				</div>
				<p class="phm-hint" id="phm-subdomain-status" aria-live="polite"></p>
			</section>
			<?php endif; ?>

			<!-- Step: Server Name -->
			<section class="phm-block">
				<h3><span><?php echo $subdomain_on ? '4' : '3'; ?></span> <?php esc_html_e( 'Server Name & Details', 'pterodactyl-hosting' ); ?></h3>
				<input type="text" name="server_label" id="phm-server-label" maxlength="60" placeholder="<?php esc_attr_e( 'Server Display Name (e.g. My Survival SMP)', 'pterodactyl-hosting' ); ?>">
				<input type="text" name="discord" placeholder="<?php esc_attr_e( 'Discord Username (optional for support notifications)', 'pterodactyl-hosting' ); ?>">
			</section>
		</div>

		<div class="phm-form-col">
			<!-- Step: Account Setup -->
			<section class="phm-block phm-account-block">
				<h3><span><?php echo $subdomain_on ? '5' : '4'; ?></span> <?php esc_html_e( 'Account Details', 'pterodactyl-hosting' ); ?></h3>
				<?php if ( $is_logged_in ) : ?>
					<div class="phm-account-info">
						<div class="phm-avatar-icon">👤</div>
						<div class="phm-account-text">
							<strong><?php echo esc_html( $current_user->display_name ); ?></strong>
							<span><?php echo esc_html( $current_user->user_email ); ?></span>
						</div>
						<a href="<?php echo esc_url( wp_logout_url( add_query_arg( null, null ) ) ); ?>" class="phm-logout-link"><?php esc_html_e( 'Change', 'pterodactyl-hosting' ); ?></a>
					</div>
					<p class="phm-hint"><?php esc_html_e( 'Your server panel login will be linked to this account.', 'pterodactyl-hosting' ); ?></p>
				<?php else : ?>
					<p class="phm-hint"><?php esc_html_e( 'Create your account to access your server panel and manage your services.', 'pterodactyl-hosting' ); ?></p>
					<div class="phm-guest-inputs">
						<input type="email" name="email" id="phm-email" placeholder="<?php esc_attr_e( 'Your Email Address *', 'pterodactyl-hosting' ); ?>" required autocomplete="email">
						<input type="text" name="username" id="phm-username" placeholder="<?php esc_attr_e( 'Username (optional)', 'pterodactyl-hosting' ); ?>" autocomplete="username">
						<input type="password" name="password" id="phm-password" placeholder="<?php esc_attr_e( 'Choose Password *', 'pterodactyl-hosting' ); ?>" required autocomplete="new-password">
					</div>
					<p class="phm-hint" style="margin-top: 8px;">
						<?php esc_html_e( 'Already have an account?', 'pterodactyl-hosting' ); ?>
						<a href="<?php echo esc_url( wp_login_url( home_url( add_query_arg( null, null ) ) ) ); ?>" style="color: #6366f1; text-decoration: underline;"><?php esc_html_e( 'Log in here', 'pterodactyl-hosting' ); ?></a>
					</p>
				<?php endif; ?>
			</section>

			<!-- Step: Promo / Coupon Code -->
			<section class="phm-block phm-coupon-block">
				<h3><span><?php echo $subdomain_on ? '6' : '5'; ?></span> <?php esc_html_e( 'Promo / Coupon Code', 'pterodactyl-hosting' ); ?></h3>
				<div class="phm-coupon-input-wrap">
					<input type="text" name="coupon_code" id="phm-coupon-code" placeholder="<?php esc_attr_e( 'Promo Code (e.g. DISCOUNT50)', 'pterodactyl-hosting' ); ?>" style="text-transform: uppercase;">
					<button type="button" id="phm-apply-coupon" class="phm-btn phm-btn-secondary"><?php esc_html_e( 'Apply', 'pterodactyl-hosting' ); ?></button>
				</div>
				<p class="phm-hint" id="phm-coupon-status" aria-live="polite"></p>
			</section>

			<!-- Step: Payment Gateway -->
			<section class="phm-block" id="phm-payment-section">
				<h3><span><?php echo $subdomain_on ? '7' : '6'; ?></span> <?php esc_html_e( 'Payment Gateway', 'pterodactyl-hosting' ); ?></h3>
				<div class="phm-gateways-list">
					<?php
					$g_index = 0;
					foreach ( $gateways as $key => $m ) :
						$g_index++;
						?>
						<label class="phm-pay-row">
							<input type="radio" name="payment_method" value="<?php echo esc_attr( $key ); ?>" <?php checked( 1 === $g_index ); ?> required>
							<div class="phm-pay-content">
								<span class="phm-pay-title"><?php echo esc_html( $m['label'] ); ?></span>
								<?php if ( ! empty( $m['details'] ) ) : ?>
									<small class="phm-pay-desc"><?php echo esc_html( wp_strip_all_tags( $m['details'] ) ); ?></small>
								<?php endif; ?>
							</div>
						</label>
					<?php endforeach; ?>
				</div>
			</section>

			<p class="phm-hint phm-free-badge" id="phm-free-note" hidden>🎉 <?php esc_html_e( 'This plan is 100% Free! Server will deploy instantly upon placing order.', 'pterodactyl-hosting' ); ?></p>

			<!-- Total Summary -->
			<div class="phm-summary-card">
				<div class="phm-summary-row">
					<span><?php esc_html_e( 'Subtotal', 'pterodactyl-hosting' ); ?></span>
					<span id="phm-subtotal">—</span>
				</div>
				<div class="phm-summary-row phm-discount-row" id="phm-discount-line" style="display: none;">
					<span><?php esc_html_e( 'Coupon Discount', 'pterodactyl-hosting' ); ?></span>
					<span id="phm-discount-amount" style="color: #10b981;">—</span>
				</div>
				<div class="phm-total-row">
					<span><?php esc_html_e( 'Total Today', 'pterodactyl-hosting' ); ?></span>
					<strong id="phm-total">—</strong>
				</div>
			</div>

			<button type="submit" class="phm-btn phm-btn-primary phm-btn-lg phm-submit-btn" id="phm-submit">
				<?php esc_html_e( 'Complete Order & Deploy Server →', 'pterodactyl-hosting' ); ?>
			</button>
			<p class="phm-hint phm-security-note">🔒 <?php esc_html_e( 'Instant setup. 256-bit encrypted checkout with 99.9% uptime guarantee.', 'pterodactyl-hosting' ); ?></p>
		</div>
	</form>
	<?php endif; ?>

	<div id="phm-order-result" class="phm-result" hidden></div>
</div>
