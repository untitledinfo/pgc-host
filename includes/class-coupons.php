<?php
/**
 * Coupon and Promo Code discount system.
 *
 * @package Pterodactyl_Hosting
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PHM_Coupons {

	public static function init() {
		add_action( 'admin_post_phm_save_coupon', [ __CLASS__, 'handle_save' ] );
		add_action( 'admin_post_phm_delete_coupon', [ __CLASS__, 'handle_delete' ] );
		add_action( 'admin_post_phm_toggle_coupon', [ __CLASS__, 'handle_toggle' ] );
	}

	/**
	 * Validate a coupon code against a product and amount.
	 *
	 * @param string $code
	 * @param int $product_id
	 * @param float $amount
	 * @return array|WP_Error Array with discount details or WP_Error.
	 */
	public static function validate( $code, $product_id = 0, $amount = 0.0 ) {
		$code = strtoupper( trim( (string) $code ) );
		if ( '' === $code ) {
			return new WP_Error( 'invalid_code', __( 'Please enter a coupon code.', 'pterodactyl-hosting' ) );
		}

		$coupon = PHM_DB::get_coupon_by_code( $code );
		if ( ! $coupon ) {
			return new WP_Error( 'not_found', __( 'Coupon code does not exist.', 'pterodactyl-hosting' ) );
		}

		if ( empty( $coupon->active ) ) {
			return new WP_Error( 'inactive', __( 'This coupon is currently inactive.', 'pterodactyl-hosting' ) );
		}

		if ( ! empty( $coupon->expires_at ) && '0000-00-00 00:00:00' !== $coupon->expires_at ) {
			if ( strtotime( $coupon->expires_at ) < current_time( 'timestamp' ) ) {
				return new WP_Error( 'expired', __( 'This coupon has expired.', 'pterodactyl-hosting' ) );
			}
		}

		if ( (int) $coupon->max_uses > 0 && (int) $coupon->uses_count >= (int) $coupon->max_uses ) {
			return new WP_Error( 'limit_reached', __( 'This coupon has reached its maximum usage limit.', 'pterodactyl-hosting' ) );
		}

		$amount = (float) $amount;
		if ( (float) $coupon->min_spend > 0 && $amount < (float) $coupon->min_spend ) {
			return new WP_Error(
				'min_spend',
				sprintf(
					/* translators: %s: minimum spend amount */
					__( 'Minimum spend for this coupon is %s.', 'pterodactyl-hosting' ),
					(float) $coupon->min_spend
				)
			);
		}

		// Product restriction check.
		if ( ! empty( $coupon->product_ids ) ) {
			$allowed = array_filter( array_map( 'intval', explode( ',', (string) $coupon->product_ids ) ) );
			if ( $allowed && ! in_array( (int) $product_id, $allowed, true ) ) {
				return new WP_Error( 'product_restricted', __( 'This coupon is not valid for the selected plan.', 'pterodactyl-hosting' ) );
			}
		}

		// Calculate discount.
		$discount = 0.0;
		if ( 'percent' === $coupon->discount_type ) {
			$percent  = max( 0, min( 100, (float) $coupon->discount_amount ) );
			$discount = round( ( $amount * $percent ) / 100, 2 );
		} else {
			$discount = min( $amount, (float) $coupon->discount_amount );
		}

		$final_total = max( 0.0, round( $amount - $discount, 2 ) );

		return [
			'valid'           => true,
			'code'            => $coupon->code,
			'discount_type'   => $coupon->discount_type,
			'discount_rate'   => (float) $coupon->discount_amount,
			'discount_amount' => $discount,
			'original_amount' => $amount,
			'final_total'     => $final_total,
			'message'         => sprintf(
				/* translators: 1: coupon code, 2: discount amount */
				__( 'Coupon %1$s applied! Saved %2$s', 'pterodactyl-hosting' ),
				$coupon->code,
				'percent' === $coupon->discount_type ? $coupon->discount_amount . '%' : number_format( $discount, 2 )
			),
		];
	}

	public static function handle_save() {
		if ( ! current_user_can( PHM_Admin::capability() ) ) {
			wp_die( esc_html__( 'Permission denied.', 'pterodactyl-hosting' ) );
		}
		check_admin_referer( 'phm_save_coupon' );

		$id              = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$code            = isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '';
		$discount_type   = isset( $_POST['discount_type'] ) && 'fixed' === $_POST['discount_type'] ? 'fixed' : 'percent';
		$discount_amount = isset( $_POST['discount_amount'] ) ? (float) $_POST['discount_amount'] : 0.0;
		$min_spend       = isset( $_POST['min_spend'] ) ? (float) $_POST['min_spend'] : 0.0;
		$max_uses        = isset( $_POST['max_uses'] ) && '' !== trim( (string) $_POST['max_uses'] ) ? (int) $_POST['max_uses'] : -1;
		$expires_at      = ! empty( $_POST['expires_at'] ) ? sanitize_text_field( wp_unslash( $_POST['expires_at'] ) ) . ' 23:59:59' : null;
		$active          = isset( $_POST['active'] ) ? 1 : 0;
		$products        = isset( $_POST['product_ids'] ) && is_array( $_POST['product_ids'] )
			? implode( ',', array_map( 'intval', $_POST['product_ids'] ) )
			: '';

		if ( '' === $code ) {
			wp_safe_redirect( admin_url( 'admin.php?page=phm-coupons&phm_msg=code_required' ) );
			exit;
		}

		$data = [
			'code'            => strtoupper( $code ),
			'discount_type'   => $discount_type,
			'discount_amount' => $discount_amount,
			'min_spend'       => $min_spend,
			'max_uses'        => $max_uses,
			'product_ids'     => $products,
			'expires_at'      => $expires_at,
			'active'          => $active,
		];

		$saved = PHM_DB::save_coupon( $data, $id );
		if ( is_wp_error( $saved ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=phm-coupons&phm_msg=save_failed' ) );
			exit;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=phm-coupons&phm_msg=saved' ) );
		exit;
	}

	public static function handle_delete() {
		if ( ! current_user_can( PHM_Admin::capability() ) ) {
			wp_die( esc_html__( 'Permission denied.', 'pterodactyl-hosting' ) );
		}
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		check_admin_referer( 'phm_delete_coupon_' . $id );

		PHM_DB::delete_coupon( $id );
		wp_safe_redirect( admin_url( 'admin.php?page=phm-coupons&phm_msg=deleted' ) );
		exit;
	}

	public static function handle_toggle() {
		if ( ! current_user_can( PHM_Admin::capability() ) ) {
			wp_die( esc_html__( 'Permission denied.', 'pterodactyl-hosting' ) );
		}
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		check_admin_referer( 'phm_toggle_coupon_' . $id );

		$coupon = PHM_DB::get_coupon( $id );
		if ( $coupon ) {
			PHM_DB::save_coupon( [ 'active' => $coupon->active ? 0 : 1 ], $id );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=phm-coupons&phm_msg=saved' ) );
		exit;
	}
}
