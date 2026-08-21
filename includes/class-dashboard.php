<?php
/**
 * Customer "My Account" dashboard: [phm_dashboard].
 *
 * Two tabs:
 *  - My Servers   — every order/server tied to the logged-in WP account,
 *                    with a "Go to Server" button that deep-links straight
 *                    to that server's console (see PHM_Cookie_Login).
 *  - Support      — open a new ticket / view + reply to existing ones.
 *
 * @package Pterodactyl_Hosting
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PHM_Dashboard {

	public static function init() {
		add_shortcode( 'phm_dashboard', [ __CLASS__, 'shortcode' ] );
	}

	public static function shortcode() {
		PHM_Frontend::no_cache();
		PHM_Frontend::enqueue_and_localize();

		if ( ! is_user_logged_in() ) {
			ob_start();
			require PHM_PATH . 'templates/login-required.php';
			return ob_get_clean();
		}

		$user_id = get_current_user_id();
		$tab     = isset( $_GET['phm_tab'] ) && 'tickets' === $_GET['phm_tab'] ? 'tickets' : 'servers'; // phpcs:ignore WordPress.Security.NonceVerification

		$orders  = PHM_DB::get_orders_for_wp_user( $user_id );
		$tickets = PHM_DB::get_tickets_for_wp_user( $user_id ); // small per-user list — always fetched, used for the nav badge too.

		$awaiting_count = 0;
		foreach ( $tickets as $t ) {
			if ( 'answered' === $t->status ) {
				$awaiting_count++;
			}
		}

		$ticket         = null;
		$ticket_replies = [];
		if ( 'tickets' === $tab ) {
			$ticket_id = isset( $_GET['phm_ticket'] ) ? (int) $_GET['phm_ticket'] : 0; // phpcs:ignore WordPress.Security.NonceVerification
			if ( $ticket_id ) {
				$ticket = PHM_DB::get_ticket_for_wp_user( $ticket_id, $user_id );
				if ( $ticket ) {
					$ticket_replies = PHM_DB::get_ticket_replies( $ticket->id );
				}
			}
		}

		$msg = isset( $_GET['phm_msg'] ) ? sanitize_key( wp_unslash( $_GET['phm_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

		ob_start();
		require PHM_PATH . 'templates/dashboard.php';
		return ob_get_clean();
	}

	/**
	 * Small banner-message map, mirrors the style of PHM_Admin::render_msg()
	 * but for the customer-facing side.
	 */
	public static function message( $code ) {
		$map = [
			'ticket_created' => [ 'good', __( 'Your ticket was submitted — we\'ll reply here soon.', 'pterodactyl-hosting' ) ],
			'ticket_replied' => [ 'good', __( 'Your reply was sent.', 'pterodactyl-hosting' ) ],
			'ticket_missing' => [ 'bad', __( 'Please fill in both a subject and a message.', 'pterodactyl-hosting' ) ],
			'ticket_closed'  => [ 'bad', __( 'This ticket is closed. Open a new ticket if you still need help.', 'pterodactyl-hosting' ) ],
		];
		return isset( $map[ $code ] ) ? $map[ $code ] : null;
	}
}
