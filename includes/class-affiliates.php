<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Lightweight referral system: ?ref=USERID sets a cookie; when the referred
 * user places their first order, the referrer is credited (stored as user meta
 * so it can be paid out / discounted manually, or wired into a wallet system).
 */
class Ptero_Affiliates {

	private static $instance = null;
	public static function instance() {
		if ( self::$instance === null ) self::$instance = new self();
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( $this, 'capture_ref' ) );
		add_action( 'ptero_host_order_completed_full', array( $this, 'credit_referrer' ), 10, 2 );
	}

	public function capture_ref() {
		if ( isset( $_GET['ref'] ) && is_user_logged_in() === false ) {
			$ref = intval( $_GET['ref'] );
			if ( $ref && ! isset( $_COOKIE['ptero_ref'] ) ) {
				setcookie( 'ptero_ref', $ref, time() + ( 30 * DAY_IN_SECONDS ), COOKIEPATH ?: '/' );
			}
		}
	}

	public function credit_referrer( $order_id, $user_id ) {
		if ( empty( $_COOKIE['ptero_ref'] ) ) return;
		$ref_id = intval( $_COOKIE['ptero_ref'] );
		if ( $ref_id === $user_id ) return;

		$already = get_user_meta( $user_id, '_ptero_referred_by', true );
		if ( $already ) return;

		update_user_meta( $user_id, '_ptero_referred_by', $ref_id );
		$credits = intval( get_user_meta( $ref_id, '_ptero_referral_credits', true ) );
		update_user_meta( $ref_id, '_ptero_referral_credits', $credits + 1 );
	}
}
