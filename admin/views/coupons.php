<?php
/**
 * Admin view: Coupons / Promo codes.
 *
 * @package Pterodactyl_Hosting
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$action   = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
$edit_id  = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification
$coupon   = $edit_id ? PHM_DB::get_coupon( $edit_id ) : null;
$coupons  = PHM_DB::get_coupons();
$products = PHM_DB::get_products();
?>
<div class="wrap phm-admin-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Coupons & Promo Codes', 'pterodactyl-hosting' ); ?></h1>
	<?php if ( 'new' !== $action && ! $coupon ) : ?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=phm-coupons&action=new' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add New Coupon', 'pterodactyl-hosting' ); ?></a>
	<?php endif; ?>
	<hr class="wp-header-end">

	<?php PHM_Admin::render_msg(); ?>

	<?php if ( 'new' === $action || $coupon ) :
		$selected_products = $coupon && ! empty( $coupon->product_ids ) ? array_filter( array_map( 'intval', explode( ',', $coupon->product_ids ) ) ) : [];
		$expiry_date       = $coupon && ! empty( $coupon->expires_at ) && '0000-00-00 00:00:00' !== $coupon->expires_at ? gmdate( 'Y-m-d', strtotime( $coupon->expires_at ) ) : '';
		?>
		<div class="phm-card" style="max-width: 700px; margin-top: 20px;">
			<h2><?php echo $coupon ? esc_html__( 'Edit Coupon', 'pterodactyl-hosting' ) : esc_html__( 'Create New Coupon', 'pterodactyl-hosting' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="phm_save_coupon">
				<input type="hidden" name="id" value="<?php echo $coupon ? (int) $coupon->id : 0; ?>">
				<?php wp_nonce_field( 'phm_save_coupon' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="coupon_code"><?php esc_html_e( 'Coupon Code', 'pterodactyl-hosting' ); ?> *</label></th>
						<td>
							<input name="code" type="text" id="coupon_code" value="<?php echo $coupon ? esc_attr( $coupon->code ) : ''; ?>" class="regular-text" style="text-transform: uppercase; font-weight: bold; letter-spacing: 1px;" required placeholder="e.g. SUMMER50">
							<p class="description"><?php esc_html_e( 'Case-insensitive code the customer enters at checkout.', 'pterodactyl-hosting' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="discount_type"><?php esc_html_e( 'Discount Type', 'pterodactyl-hosting' ); ?></label></th>
						<td>
							<select name="discount_type" id="discount_type">
								<option value="percent" <?php selected( ! $coupon || 'percent' === $coupon->discount_type ); ?>><?php esc_html_e( 'Percentage Discount (%)', 'pterodactyl-hosting' ); ?></option>
								<option value="fixed" <?php selected( $coupon && 'fixed' === $coupon->discount_type ); ?>><?php esc_html_e( 'Fixed Amount Discount ($/EUR)', 'pterodactyl-hosting' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="discount_amount"><?php esc_html_e( 'Discount Value', 'pterodactyl-hosting' ); ?> *</label></th>
						<td>
							<input name="discount_amount" type="number" step="0.01" min="0" id="discount_amount" value="<?php echo $coupon ? esc_attr( $coupon->discount_amount ) : '10.00'; ?>" class="small-text" required>
							<p class="description"><?php esc_html_e( 'e.g. 20 for 20% off, or 5.00 for $5.00 off.', 'pterodactyl-hosting' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="min_spend"><?php esc_html_e( 'Minimum Spend', 'pterodactyl-hosting' ); ?></label></th>
						<td>
							<input name="min_spend" type="number" step="0.01" min="0" id="min_spend" value="<?php echo $coupon ? esc_attr( $coupon->min_spend ) : '0.00'; ?>" class="small-text">
							<p class="description"><?php esc_html_e( 'Minimum order total required to use this coupon. 0 for no minimum.', 'pterodactyl-hosting' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="max_uses"><?php esc_html_e( 'Usage Limit', 'pterodactyl-hosting' ); ?></label></th>
						<td>
							<input name="max_uses" type="number" step="1" id="max_uses" value="<?php echo $coupon ? (int) $coupon->max_uses : -1; ?>" class="small-text">
							<p class="description"><?php esc_html_e( 'Maximum total uses allowed. Enter -1 for unlimited uses.', 'pterodactyl-hosting' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="expires_at"><?php esc_html_e( 'Expiry Date', 'pterodactyl-hosting' ); ?></label></th>
						<td>
							<input name="expires_at" type="date" id="expires_at" value="<?php echo esc_attr( $expiry_date ); ?>">
							<p class="description"><?php esc_html_e( 'Leave blank for no expiration date.', 'pterodactyl-hosting' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Restrict to Plans', 'pterodactyl-hosting' ); ?></th>
						<td>
							<fieldset>
								<legend class="screen-reader-text"><?php esc_html_e( 'Restrict to Plans', 'pterodactyl-hosting' ); ?></legend>
								<?php if ( empty( $products ) ) : ?>
									<p class="description"><?php esc_html_e( 'No plans created yet.', 'pterodactyl-hosting' ); ?></p>
								<?php else : ?>
									<p class="description"><?php esc_html_e( 'Select plans valid for this coupon (leave all unchecked to allow on ALL plans):', 'pterodactyl-hosting' ); ?></p>
									<div style="max-height: 140px; overflow-y: auto; padding: 6px; border: 1px solid #ddd; background: #fff; border-radius: 4px; margin-top: 6px;">
										<?php foreach ( $products as $p ) : ?>
											<label style="display: block; margin-bottom: 4px;">
												<input type="checkbox" name="product_ids[]" value="<?php echo (int) $p->id; ?>" <?php checked( in_array( (int) $p->id, $selected_products, true ) ); ?>>
												<?php echo esc_html( $p->name ); ?> (<?php echo esc_html( $p->price . ' ' . $p->currency ); ?>)
											</label>
										<?php endforeach; ?>
									</div>
								<?php endif; ?>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Status', 'pterodactyl-hosting' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="active" value="1" <?php checked( ! $coupon || $coupon->active ); ?>>
								<?php esc_html_e( 'Active (customers can apply this code)', 'pterodactyl-hosting' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<p class="submit">
					<button type="submit" class="button button-primary"><?php echo $coupon ? esc_html__( 'Update Coupon', 'pterodactyl-hosting' ) : esc_html__( 'Create Coupon', 'pterodactyl-hosting' ); ?></button>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=phm-coupons' ) ); ?>" class="button"><?php esc_html_e( 'Cancel', 'pterodactyl-hosting' ); ?></a>
				</p>
			</form>
		</div>
	<?php else : ?>

		<div class="phm-card" style="margin-top: 20px;">
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Code', 'pterodactyl-hosting' ); ?></th>
						<th><?php esc_html_e( 'Discount', 'pterodactyl-hosting' ); ?></th>
						<th><?php esc_html_e( 'Usage / Limit', 'pterodactyl-hosting' ); ?></th>
						<th><?php esc_html_e( 'Min Spend', 'pterodactyl-hosting' ); ?></th>
						<th><?php esc_html_e( 'Expiry', 'pterodactyl-hosting' ); ?></th>
						<th><?php esc_html_e( 'Status', 'pterodactyl-hosting' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'pterodactyl-hosting' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $coupons ) ) : ?>
						<tr>
							<td colspan="7" style="text-align: center; padding: 20px; color: #888;">
								<?php esc_html_e( 'No coupons created yet.', 'pterodactyl-hosting' ); ?>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=phm-coupons&action=new' ) ); ?>"><?php esc_html_e( 'Create your first coupon →', 'pterodactyl-hosting' ); ?></a>
							</td>
						</tr>
					<?php else : ?>
						<?php foreach ( $coupons as $c ) :
							$is_expired = ! empty( $c->expires_at ) && '0000-00-00 00:00:00' !== $c->expires_at && strtotime( $c->expires_at ) < current_time( 'timestamp' );
							?>
							<tr>
								<td>
									<strong><code><?php echo esc_html( $c->code ); ?></code></strong>
								</td>
								<td>
									<?php
									if ( 'percent' === $c->discount_type ) {
										echo esc_html( $c->discount_amount . '%' );
									} else {
										echo esc_html( '$' . number_format( (float) $c->discount_amount, 2 ) );
									}
									?>
								</td>
								<td>
									<?php echo (int) $c->uses_count; ?> / <?php echo (int) $c->max_uses > 0 ? (int) $c->max_uses : '∞'; ?>
								</td>
								<td>
									<?php echo (float) $c->min_spend > 0 ? esc_html( '$' . number_format( (float) $c->min_spend, 2 ) ) : '—'; ?>
								</td>
								<td>
									<?php
									if ( ! empty( $c->expires_at ) && '0000-00-00 00:00:00' !== $c->expires_at ) {
										echo esc_html( gmdate( 'Y-m-d', strtotime( $c->expires_at ) ) );
										if ( $is_expired ) {
											echo ' <span style="color: #d63638; font-weight: 600;">(' . esc_html__( 'Expired', 'pterodactyl-hosting' ) . ')</span>';
										}
									} else {
										echo '—';
									}
									?>
								</td>
								<td>
									<?php if ( $c->active && ! $is_expired ) : ?>
										<span class="phm-badge phm-badge-active"><?php esc_html_e( 'Active', 'pterodactyl-hosting' ); ?></span>
									<?php else : ?>
										<span class="phm-badge phm-badge-inactive"><?php esc_html_e( 'Inactive', 'pterodactyl-hosting' ); ?></span>
									<?php endif; ?>
								</td>
								<td>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=phm-coupons&action=edit&id=' . (int) $c->id ) ); ?>" class="button button-small"><?php esc_html_e( 'Edit', 'pterodactyl-hosting' ); ?></a>
									<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=phm_toggle_coupon&id=' . (int) $c->id ), 'phm_toggle_coupon_' . $c->id ) ); ?>" class="button button-small"><?php echo $c->active ? esc_html__( 'Disable', 'pterodactyl-hosting' ) : esc_html__( 'Enable', 'pterodactyl-hosting' ); ?></a>
									<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=phm_delete_coupon&id=' . (int) $c->id ), 'phm_delete_coupon_' . $c->id ) ); ?>" class="button button-small button-link-delete" onclick="return confirm('<?php esc_attr_e( 'Delete this coupon permanently?', 'pterodactyl-hosting' ); ?>')"><?php esc_html_e( 'Delete', 'pterodactyl-hosting' ); ?></a>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>

	<?php endif; ?>
</div>
