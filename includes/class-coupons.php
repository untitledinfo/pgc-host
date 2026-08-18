<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Ptero_Coupons {

	private static $instance = null;
	public static function instance() {
		if ( self::$instance === null ) self::$instance = new self();
		return self::$instance;
	}

	private function __construct() {
		// Reserved for future hooks (e.g. usage tracking on successful order).
		add_action( 'ptero_host_order_completed', array( $this, 'increment_usage' ), 10, 1 );
	}

	public static function apply( $code, $total ) {
		if ( empty( $code ) ) return $total;

		global $wpdb;
		$table = $wpdb->prefix . 'ptero_coupons';
		$coupon = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE code = %s", strtoupper( (string) sanitize_text_field( $code ) ) ) );

		if ( ! $coupon ) return $total;
		if ( $coupon->expires_at && strtotime( $coupon->expires_at ) < time() ) return $total;
		if ( $coupon->max_uses !== null && $coupon->used >= $coupon->max_uses ) return $total;

		if ( $coupon->type === 'percent' ) {
			$total -= $total * ( $coupon->amount / 100 );
		} else {
			$total -= $coupon->amount;
		}

		return max( 0, $total );
	}

	public function increment_usage( $code ) {
		if ( empty( $code ) ) return;
		global $wpdb;
		$table = $wpdb->prefix . 'ptero_coupons';
		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET used = used + 1 WHERE code = %s", strtoupper( (string) $code ) ) );
	}
}
