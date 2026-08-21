<?php
/**
 * Support ticket system.
 *
 * Frontend: customers open + reply to tickets from the "Support Tickets"
 * tab of [phm_dashboard] (see PHM_Dashboard). Admin: staff reply / close /
 * reopen from wp-admin → PGC Hosting → Support Tickets (see admin/views/
 * tickets.php and ticket-view.php).
 *
 * Lifecycle: open -> answered (staff replied) -> customer-reply (customer
 * replied back) -> ... -> closed. "last_reply_by" is what each dashboard
 * uses to show "waiting on you" vs "waiting on them".
 *
 * @package Pterodactyl_Hosting
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PHM_Tickets {

	const STATUSES  = [ 'open', 'customer-reply', 'answered', 'closed' ];
	const PRIORITIES = [ 'low', 'normal', 'high' ];

	public static function init() {
		add_action( 'admin_post_phm_ticket_create', [ __CLASS__, 'handle_create' ] );
		add_action( 'admin_post_phm_ticket_reply', [ __CLASS__, 'handle_reply' ] );
		add_action( 'admin_post_phm_ticket_admin_reply', [ __CLASS__, 'handle_admin_reply' ] );
		add_action( 'admin_post_phm_ticket_admin_status', [ __CLASS__, 'handle_admin_status' ] );

		add_shortcode( 'phm_ticket_create', [ __CLASS__, 'shortcode_create' ] );
		add_shortcode( 'phm_tickets', [ __CLASS__, 'shortcode_tickets' ] );
	}

	public static function shortcode_create() {
		PHM_Frontend::no_cache();
		PHM_Frontend::enqueue_and_localize();
		ob_start();
		require PHM_PATH . 'templates/ticket-create.php';
		return ob_get_clean();
	}

	public static function shortcode_tickets() {
		return PHM_Dashboard::shortcode( [ 'tab' => 'tickets' ] );
	}

	/* ---------------------------------------------------------------------
	 * Labels
	 * ------------------------------------------------------------------- */

	public static function status_label( $status ) {
		$labels = [
			'open'           => __( 'Open', 'pterodactyl-hosting' ),
			'customer-reply' => __( 'Awaiting staff reply', 'pterodactyl-hosting' ),
			'answered'       => __( 'Answered — awaiting your reply', 'pterodactyl-hosting' ),
			'closed'         => __( 'Closed', 'pterodactyl-hosting' ),
		];
		return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
	}

	public static function admin_status_label( $status ) {
		$labels = [
			'open'           => __( 'Open — new', 'pterodactyl-hosting' ),
			'customer-reply' => __( 'Customer replied', 'pterodactyl-hosting' ),
			'answered'       => __( 'Answered — awaiting customer', 'pterodactyl-hosting' ),
			'closed'         => __( 'Closed', 'pterodactyl-hosting' ),
		];
		return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
	}

	public static function status_class( $status ) {
		$map = [
			'open'           => 'warning',
			'customer-reply' => 'warning',
			'answered'       => 'info',
			'closed'         => 'muted',
		];
		return isset( $map[ $status ] ) ? $map[ $status ] : 'muted';
	}

	public static function priority_label( $priority ) {
		$labels = [
			'low'    => __( 'Low', 'pterodactyl-hosting' ),
			'normal' => __( 'Normal', 'pterodactyl-hosting' ),
			'high'   => __( 'High', 'pterodactyl-hosting' ),
		];
		return isset( $labels[ $priority ] ) ? $labels[ $priority ] : $priority;
	}

	private static function dashboard_url() {
		$url = PHM_Store::page_url( 'phm_dashboard' );
		return $url ? $url : home_url( '/' );
	}

	/* ---------------------------------------------------------------------
	 * Customer: open a new ticket
	 * ------------------------------------------------------------------- */

	public static function handle_create() {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Please log in first.', 'pterodactyl-hosting' ) );
		}
		check_admin_referer( 'phm_ticket_create' );

		$wp_user  = wp_get_current_user();
		$subject  = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
		$message  = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
		$priority = isset( $_POST['priority'] ) ? sanitize_key( wp_unslash( $_POST['priority'] ) ) : 'normal';
		if ( ! in_array( $priority, self::PRIORITIES, true ) ) {
			$priority = 'normal';
		}

		// Optional: link the ticket to one of the customer's own servers.
		// Silently drop it if it doesn't belong to them, rather than error —
		// this is a convenience field, not a security boundary.
		$order_id = isset( $_POST['order_id'] ) ? (int) $_POST['order_id'] : 0;
		if ( $order_id ) {
			$order = PHM_DB::get_order( $order_id );
			if ( ! $order || (int) $order->wp_user_id !== get_current_user_id() ) {
				$order_id = 0;
			}
		}

		if ( '' === $subject || '' === $message ) {
			wp_safe_redirect( add_query_arg( [ 'phm_tab' => 'tickets', 'phm_msg' => 'ticket_missing' ], self::dashboard_url() ) );
			exit;
		}

		$department = isset( $_POST['department'] ) ? sanitize_text_field( wp_unslash( $_POST['department'] ) ) : 'Technical';
		$ticket_id  = PHM_DB::create_ticket( [
			'wp_user_id' => get_current_user_id(),
			'order_id'   => $order_id,
			'department' => $department,
			'subject'    => $subject,
			'priority'   => $priority,
		] );

		PHM_DB::add_ticket_reply( $ticket_id, [
			'wp_user_id'  => get_current_user_id(),
			'author_name' => $wp_user->display_name,
			'is_staff'    => 0,
			'message'     => $message,
		] );

		$ticket = PHM_DB::get_ticket( $ticket_id );
		PHM_Notifications::ticket_created( $ticket, $wp_user, $message );

		wp_safe_redirect( add_query_arg(
			[ 'phm_tab' => 'tickets', 'phm_ticket' => $ticket_id, 'phm_msg' => 'ticket_created' ],
			self::dashboard_url()
		) );
		exit;
	}

	/* ---------------------------------------------------------------------
	 * Customer: reply on their own ticket
	 * ------------------------------------------------------------------- */

	public static function handle_reply() {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Please log in first.', 'pterodactyl-hosting' ) );
		}
		$id = isset( $_POST['ticket_id'] ) ? (int) $_POST['ticket_id'] : 0;
		check_admin_referer( 'phm_ticket_reply_' . $id );

		// Ownership check happens INSIDE the query — a ticket ID that isn't
		// this WP user's simply returns null, same as "not found".
		$ticket = PHM_DB::get_ticket_for_wp_user( $id, get_current_user_id() );
		if ( ! $ticket ) {
			wp_die( esc_html__( 'Ticket not found.', 'pterodactyl-hosting' ) );
		}
		if ( 'closed' === $ticket->status ) {
			wp_safe_redirect( add_query_arg( [ 'phm_tab' => 'tickets', 'phm_ticket' => $id, 'phm_msg' => 'ticket_closed' ], self::dashboard_url() ) );
			exit;
		}

		$message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
		if ( '' === $message ) {
			wp_safe_redirect( add_query_arg( [ 'phm_tab' => 'tickets', 'phm_ticket' => $id, 'phm_msg' => 'ticket_missing' ], self::dashboard_url() ) );
			exit;
		}

		$wp_user = wp_get_current_user();
		PHM_DB::add_ticket_reply( $id, [
			'wp_user_id'  => get_current_user_id(),
			'author_name' => $wp_user->display_name,
			'is_staff'    => 0,
			'message'     => $message,
		] );
		PHM_DB::update_ticket( $id, [ 'status' => 'customer-reply', 'last_reply_by' => 'customer' ] );

		PHM_Notifications::ticket_customer_reply( $ticket, $wp_user, $message );

		wp_safe_redirect( add_query_arg( [ 'phm_tab' => 'tickets', 'phm_ticket' => $id, 'phm_msg' => 'ticket_replied' ], self::dashboard_url() ) );
		exit;
	}

	/* ---------------------------------------------------------------------
	 * Staff: reply (optionally closing the ticket in the same action)
	 * ------------------------------------------------------------------- */

	public static function handle_admin_reply() {
		if ( ! current_user_can( PHM_Admin::capability() ) ) {
			wp_die( esc_html__( 'Permission denied.', 'pterodactyl-hosting' ) );
		}
		$id = isset( $_POST['ticket_id'] ) ? (int) $_POST['ticket_id'] : 0;
		check_admin_referer( 'phm_ticket_admin_reply_' . $id );

		$ticket = PHM_DB::get_ticket( $id );
		if ( ! $ticket ) {
			wp_safe_redirect( admin_url( 'admin.php?page=phm-tickets&phm_msg=missing' ) );
			exit;
		}

		$message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
		$close   = ! empty( $_POST['close'] );

		if ( '' !== $message ) {
			$admin_user = wp_get_current_user();
			PHM_DB::add_ticket_reply( $id, [
				'wp_user_id'  => get_current_user_id(),
				'author_name' => $admin_user->display_name ? $admin_user->display_name : __( 'Support', 'pterodactyl-hosting' ),
				'is_staff'    => 1,
				'message'     => $message,
			] );
			PHM_Notifications::ticket_staff_reply( $ticket, $message );
		}

		PHM_DB::update_ticket( $id, [
			'status'        => $close ? 'closed' : 'answered',
			'last_reply_by' => 'staff',
		] );

		wp_safe_redirect( admin_url( 'admin.php?page=phm-tickets&view=' . $id . '&phm_msg=ticket_replied' ) );
		exit;
	}

	/* ---------------------------------------------------------------------
	 * Staff: change status only (reopen/close without leaving a message)
	 * ------------------------------------------------------------------- */

	public static function handle_admin_status() {
		if ( ! current_user_can( PHM_Admin::capability() ) ) {
			wp_die( esc_html__( 'Permission denied.', 'pterodactyl-hosting' ) );
		}
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		check_admin_referer( 'phm_ticket_admin_status_' . $id );

		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		if ( in_array( $status, self::STATUSES, true ) ) {
			PHM_DB::update_ticket( $id, [ 'status' => $status ] );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=phm-tickets&view=' . $id . '&phm_msg=saved' ) );
		exit;
	}
}
