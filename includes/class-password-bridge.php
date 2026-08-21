<?php
/**
 * Captures a customer's plaintext WordPress password at the moment they log
 * in (or register) so that when we auto-create their Pterodactyl panel
 * account we can set the SAME password — no second password for them to
 * remember, no separate "here's your panel login" email.
 *
 * WordPress never stores or exposes a user's plaintext password anywhere
 * (only a bcrypt/phpass hash) — the only place the raw value ever exists is
 * in the login/registration POST request itself, for the few milliseconds
 * it's being verified. This class hooks that exact moment, keeps the value
 * ONLY in a short-lived transient tied to the user's ID, and deletes it the
 * instant it's consumed by provisioning (or after 15 minutes, whichever
 * comes first).
 *
 * SECURITY NOTE (please read before enabling this in production): a
 * WordPress transient is stored in `wp_options` (or your object cache) —
 * it is NOT specially encrypted. Anything with server/DB access during that
 * 15-minute window could read it. This trades a small window of exposure
 * for the UX of one shared password. If that trade-off isn't acceptable for
 * your site, use PHM_API::create_user()'s auto-generated random password
 * instead (the pre-existing default) and email it to the customer.
 *
 * @package Pterodactyl_Hosting
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PHM_Password_Bridge {

	const TTL = 15 * MINUTE_IN_SECONDS;

	public static function init() {
		// Successful login — core calls this filter with the plaintext
		// password right after wp_check_password() succeeds.
		add_filter( 'wp_authenticate_user', [ __CLASS__, 'capture_on_login' ], 20, 2 );

		// New account created through the default WP registration form —
		// the plaintext password is still in $_POST at this point.
		add_action( 'user_register', [ __CLASS__, 'capture_on_register' ] );
	}

	/**
	 * @param WP_User|WP_Error $user
	 * @param string            $password Plaintext password just verified.
	 * @return WP_User|WP_Error Unchanged — we only observe.
	 */
	public static function capture_on_login( $user, $password ) {
		if ( $user instanceof WP_User && '' !== $password ) {
			self::store( $user->ID, $password );
		}
		return $user;
	}

	public static function capture_on_register( $user_id ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- core registration form has no action-specific nonce to check here; we only read, never act on this value beyond storing it for the same user's own later use.
		$password = isset( $_POST['pass1'] ) ? (string) wp_unslash( $_POST['pass1'] ) : '';
		if ( '' !== $password ) {
			self::store( (int) $user_id, $password );
		}
	}

	private static function store( $user_id, $password ) {
		set_transient( self::key( $user_id ), $password, self::TTL );
	}

	/**
	 * Consume (read + immediately delete) the captured password for a user.
	 * Returns '' if nothing was captured (e.g. the customer's session
	 * predates this feature, or the 15-minute window lapsed) — callers
	 * should fall back to an auto-generated password in that case.
	 */
	public static function consume( $user_id ) {
		$key = self::key( $user_id );
		$password = get_transient( $key );
		delete_transient( $key );
		return $password ? (string) $password : '';
	}

	private static function key( $user_id ) {
		return 'phm_pw_' . (int) $user_id;
	}
}
