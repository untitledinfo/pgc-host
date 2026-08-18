<?php
if ( ! defined( 'ABSPATH' ) ) exit;
$currency = get_option( 'ptero_currency', 'PKR' );
$logged_in = is_user_logged_in();
?>
<div class="ptero-order-form" id="ptero-order-form">
	<h2 class="ptero-title"><?php echo esc_html( $atts['title'] ); ?></h2>

	<?php if ( ! $logged_in ) : ?>
		<div class="ptero-notice">
			<?php printf( wp_kses_post( __( 'Please <a href="%s">log in</a> or <a href="%s">register</a> to place an order.', 'ptero-host' ) ), esc_url( wp_login_url( get_permalink() ) ), esc_url( wp_registration_url() ) ); ?>
		</div>
	<?php endif; ?>

	<div class="ptero-grid">
		<div class="ptero-field">
			<label><?php _e( 'Server Name', 'ptero-host' ); ?></label>
			<input type="text" id="ptero-server-name" placeholder="My Awesome Server" maxlength="60">
		</div>

		<div class="ptero-field">
			<label><?php _e( 'Game / Egg', 'ptero-host' ); ?></label>
			<select id="ptero-nest">
				<option value=""><?php _e( '— choose a game category —', 'ptero-host' ); ?></option>
				<?php foreach ( $nests as $nest ) : ?>
					<option value="<?php echo esc_attr( $nest['attributes']['id'] ); ?>"><?php echo esc_html( $nest['attributes']['name'] ); ?></option>
				<?php endforeach; ?>
			</select>
			<select id="ptero-egg" style="margin-top:6px;" disabled>
				<option value=""><?php _e( 'Select a game category first', 'ptero-host' ); ?></option>
			</select>
		</div>

		<?php if ( get_option( 'ptero_auto_show_locations', '1' ) === '1' ) : ?>
		<div class="ptero-field">
			<label><?php _e( 'Location', 'ptero-host' ); ?></label>
			<select id="ptero-location">
				<option value=""><?php _e( '— choose a location —', 'ptero-host' ); ?></option>
				<?php foreach ( $locations as $loc ) : ?>
					<option value="<?php echo esc_attr( $loc['attributes']['id'] ); ?>">
						<?php echo esc_html( $loc['attributes']['long'] ?: $loc['attributes']['short'] ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>
		<?php else : ?>
			<input type="hidden" id="ptero-location" value="">
		<?php endif; ?>

		<div class="ptero-field">
			<label><?php _e( 'RAM (MB)', 'ptero-host' ); ?> — <span id="ptero-ram-val"><?php echo esc_html( $atts['min_ram'] ); ?></span> MB</label>
			<input type="range" id="ptero-ram" min="<?php echo esc_attr( $atts['min_ram'] ); ?>" max="<?php echo esc_attr( $atts['max_ram'] ); ?>" step="256" value="<?php echo esc_attr( $atts['min_ram'] ); ?>">
		</div>

		<div class="ptero-field">
			<label><?php _e( 'CPU (%)', 'ptero-host' ); ?> — <span id="ptero-cpu-val"><?php echo esc_html( $atts['min_cpu'] ); ?></span>%</label>
			<input type="range" id="ptero-cpu" min="<?php echo esc_attr( $atts['min_cpu'] ); ?>" max="<?php echo esc_attr( $atts['max_cpu'] ); ?>" step="25" value="<?php echo esc_attr( $atts['min_cpu'] ); ?>">
		</div>

		<div class="ptero-field">
			<label><?php _e( 'Disk (MB)', 'ptero-host' ); ?> — <span id="ptero-disk-val">2048</span> MB</label>
			<input type="range" id="ptero-disk" min="1024" max="102400" step="1024" value="2048">
		</div>

		<div class="ptero-field">
			<label><?php _e( 'Backups', 'ptero-host' ); ?></label>
			<input type="number" id="ptero-backups" min="0" max="10" value="0">
		</div>

		<div class="ptero-field">
			<label><?php _e( 'Databases', 'ptero-host' ); ?></label>
			<input type="number" id="ptero-databases" min="0" max="10" value="1">
		</div>

		<div class="ptero-field ptero-checkbox">
			<label><input type="checkbox" id="ptero-dedicated-ip"> <?php _e( 'Dedicated IP address', 'ptero-host' ); ?></label>
		</div>

		<div class="ptero-field">
			<label><?php _e( 'Billing Cycle', 'ptero-host' ); ?></label>
			<select id="ptero-billing-cycle">
				<option value="monthly"><?php _e( 'Monthly', 'ptero-host' ); ?></option>
				<option value="quarterly"><?php _e( 'Quarterly (5% off)', 'ptero-host' ); ?></option>
				<option value="yearly"><?php _e( 'Yearly (15% off)', 'ptero-host' ); ?></option>
			</select>
		</div>

		<div class="ptero-field">
			<label><?php _e( 'Coupon Code', 'ptero-host' ); ?></label>
			<input type="text" id="ptero-coupon" placeholder="<?php esc_attr_e( 'Optional', 'ptero-host' ); ?>">
		</div>
	</div>

	<?php if ( get_option( 'ptero_recaptcha_site_key' ) ) : ?>
		<div class="g-recaptcha" data-sitekey="<?php echo esc_attr( get_option( 'ptero_recaptcha_site_key' ) ); ?>"></div>
		<script src="https://www.google.com/recaptcha/api.js" async defer></script>
	<?php endif; ?>

	<div class="ptero-cost-box">
		<span><?php _e( 'Estimated Cost:', 'ptero-host' ); ?></span>
		<strong id="ptero-total-price">— <?php echo esc_html( $currency ); ?></strong>
		<span class="ptero-cost-sub">/ <span id="ptero-cost-cycle"><?php _e( 'monthly', 'ptero-host' ); ?></span></span>
	</div>

	<button id="ptero-submit-order" class="ptero-btn" <?php disabled( ! $logged_in ); ?>><?php _e( 'Place Order', 'ptero-host' ); ?></button>
	<div id="ptero-order-result"></div>
</div>
