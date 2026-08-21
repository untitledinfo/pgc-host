<?php
/**
 * Keeps an existing Pterodactyl panel password in sync with WordPress
 * whenever the customer changes their WordPress password — via the
 * standard "Forgot password" (lost password) email flow, or by changing
 * it on their WordPress profile screen while logged in.
 *
 * This is the sequel to PHM_Password_Bridge: that class hands the panel a
 * matching password at the MOMENT a new account is first created. This
 * class keeps that promise true afterwards — if the customer resets or
 * changes their WordPress password six months later, their game panel
 * login changes with it, so "one login for both" stays accurate instead
 * of silently drifting apart the first time they use "Forgot password."
 *
 * Only acts on WordPress users who already have a linked panel account
 * (found via PHM_DB::get_ptero_user_id_for_wp_user()) — it never creates
 * an account and is a complete no-op for visitors who never ordered.
 *
 * @package Pterodactyl_Hosting
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PHM_Password_Sync {

	public static function init() {
		// "Forgot password" / lost-password reset — core fires this with the
		// plaintext new password right after it has already been saved.
		add_action( 'password_reset', [ __CLASS__, 'on_password_reset' ], 10, 2 );

		// Changing your own password from your WordPress profile screen.
		add_action( 'personal_options_update', [ __CLASS__, 'on_profile_update' ] );

		// An administrator changing a customer's password from wp-admin.
		add_action( 'edit_user_profile_update', [ __CLASS__, 'on_profile_update' ] );
	}

	/**
	 * @param WP_User $user
	 * @param string   $new_pass Plaintext — this is the one moment core
	 *                            gives it to us for this flow.
	 */
	public static function on_password_reset( $user, $new_pass ) {
		if ( ! ( $user instanceof WP_User ) || '' === $new_pass ) {
			return;
		}
		self::sync( (int) $user->ID, $new_pass, __( 'forgot-password reset', 'pterodactyl-hosting' ) );
	}

	/**
	 * @param int $user_id
	 */
	public static function on_profile_update( $user_id ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- core has already verified its own profile-update nonce before firing this hook; we only read a value core itself is about to save.
		$pass1 = isset( $_POST['pass1'] ) ? (string) wp_unslash( $_POST['pass1'] ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$pass2 = isset( $_POST['pass2'] ) ? (string) wp_unslash( $_POST['pass2'] ) : '';

		if ( '' === $pass1 || $pass1 !== $pass2 ) {
			return; // empty (not changing it) or the two fields didn't match — core itself will reject this, don't act on it.
		}
		self::sync( (int) $user_id, $pass1, __( 'profile password change', 'pterodactyl-hosting' ) );
	}

	/**
	 * Push $password onto the panel account linked to $wp_user_id, if any.
	 */
	private static function sync( $wp_user_id, $password, $reason ) {
		if ( ! PHM_Settings::is_configured() ) {
			return;
		}

		$ptero_user_id = PHM_DB::get_ptero_user_id_for_wp_user( $wp_user_id );
		if ( ! $ptero_user_id ) {
			return; // this WordPress user has no linked panel account yet.
		}

		$result = PHM_API::update_user_password( $ptero_user_id, $password );

		if ( is_wp_error( $result ) ) {
			PHM_DB::log(
				'error',
				sprintf(
					/* translators: 1: WP user ID, 2: panel user ID, 3: reason, 4: error message */
					__( 'Panel password sync failed for WP user #%1$d (panel user #%2$d) after %3$s: %4$s', 'pterodactyl-hosting' ),
					$wp_user_id,
					$ptero_user_id,
					$reason,
					$result->get_error_message()
				)
			);
			return;
		}

		PHM_DB::log(
			'success',
			sprintf(
				/* translators: 1: WP user ID, 2: panel user ID, 3: reason */
				__( 'Panel password synced for WP user #%1$d (panel user #%2$d) after %3$s.', 'pterodactyl-hosting' ),
				$wp_user_id,
				$ptero_user_id,
				$reason
			)
		);

		/**
		 * Fires after a customer's panel password has been synced to match
		 * a changed WordPress password.
		 *
		 * @param int $wp_user_id
		 * @param int $ptero_user_id
		 */
		do_action( 'phm_panel_password_synced', $wp_user_id, $ptero_user_id );
	}
}
