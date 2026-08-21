<?php
/**
 * REST API Endpoints for Pterodactyl Hosting Manager.
 *
 * Namespace: /wp-json/phm/v1/
 *
 * @package Pterodactyl_Hosting
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PHM_API_Endpoints {

	public static function init() {
		add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
	}

	public static function register_routes() {
		$ns = 'phm/v1';

		// Public plans.
		register_rest_route( $ns, '/plans', [
			'methods'             => 'GET',
			'callback'            => [ __CLASS__, 'get_plans' ],
			'permission_callback' => '__return_true',
		] );

		// Coupon validation.
		register_rest_route( $ns, '/coupon/apply', [
			'methods'             => 'POST',
			'callback'            => [ __CLASS__, 'apply_coupon' ],
			'permission_callback' => '__return_true',
		] );

		// Payment IPN / Webhook (Universal 250+ gateways).
		register_rest_route( $ns, '/payment-ipn/(?P<gateway>[a-zA-Z0-9_-]+)', [
			'methods'             => [ 'GET', 'POST' ],
			'callback'            => [ __CLASS__, 'payment_ipn' ],
			'permission_callback' => '__return_true',
		] );

		// Order status.
		register_rest_route( $ns, '/order/(?P<id>\d+)/status', [
			'methods'             => 'GET',
			'callback'            => [ __CLASS__, 'order_status' ],
			'permission_callback' => [ __CLASS__, 'check_auth' ],
		] );

		// User servers.
		register_rest_route( $ns, '/servers', [
			'methods'             => 'GET',
			'callback'            => [ __CLASS__, 'get_servers' ],
			'permission_callback' => [ __CLASS__, 'check_auth' ],
		] );

		// User tickets.
		register_rest_route( $ns, '/tickets', [
			'methods'             => 'GET',
			'callback'            => [ __CLASS__, 'get_tickets' ],
			'permission_callback' => [ __CLASS__, 'check_auth' ],
		] );

		register_rest_route( $ns, '/tickets/create', [
			'methods'             => 'POST',
			'callback'            => [ __CLASS__, 'create_ticket' ],
			'permission_callback' => [ __CLASS__, 'check_auth' ],
		] );
	}

	public static function check_auth() {
		return is_user_logged_in();
	}

	public static function get_plans( WP_REST_Request $request ) {
		$products = PHM_DB::get_products( true );
		$out      = [];
		foreach ( $products as $p ) {
			$out[] = [
				'id'          => (int) $p->id,
				'name'        => $p->name,
				'price'       => (float) $p->price,
				'currency'    => $p->currency,
				'memory_mb'   => (int) $p->memory,
				'disk_mb'     => (int) $p->disk,
				'cpu_limit'   => (int) $p->cpu,
				'description' => $p->description,
				'stock'       => (int) $p->stock,
			];
		}
		return rest_ensure_response( [ 'success' => true, 'plans' => $out ] );
	}

	public static function apply_coupon( WP_REST_Request $request ) {
		$code       = $request->get_param( 'code' );
		$product_id = (int) $request->get_param( 'product_id' );
		$amount     = (float) $request->get_param( 'amount' );

		$result = PHM_Coupons::validate( $code, $product_id, $amount );
		if ( is_wp_error( $result ) ) {
			return new WP_Error( 'coupon_error', $result->get_error_message(), [ 'status' => 400 ] );
		}

		return rest_ensure_response( [ 'success' => true, 'coupon' => $result ] );
	}

	public static function payment_ipn( WP_REST_Request $request ) {
		$gateway = sanitize_key( $request->get_param( 'gateway' ) );
		$_GET['gateway'] = $gateway;
		PHM_Gateways::handle_webhook();
		exit;
	}

	public static function order_status( WP_REST_Request $request ) {
		$id    = (int) $request->get_param( 'id' );
		$order = PHM_DB::get_order( $id );
		if ( ! $order || (int) $order->wp_user_id !== get_current_user_id() ) {
			return new WP_Error( 'not_found', 'Order not found', [ 'status' => 404 ] );
		}

		$stages  = PHM_Provisioning::stages();
		$stage   = $order->stage && isset( $stages[ $order->stage ] ) ? $order->stage : 'queued';
		$percent = 'failed' === $order->status ? 100 : (int) $stages[ $stage ][1];

		return rest_ensure_response( [
			'id'                => (int) $order->id,
			'number'            => $order->order_number,
			'status'            => $order->status,
			'stage'             => $stage,
			'percent'           => $percent,
			'server_name'       => $order->server_label ? $order->server_label : $order->plan_name,
			'server_ip'         => $order->server_ip,
			'server_port'       => (int) $order->server_port,
			'fqdn'              => $order->fqdn,
			'credential_note'   => $order->credential_note,
			'panel_url'         => PHM_Settings::panel_url(),
			'error'             => $order->error_message,
		] );
	}

	public static function get_servers( WP_REST_Request $request ) {
		$orders = PHM_DB::get_orders_for_wp_user( get_current_user_id() );
		$out    = [];
		foreach ( $orders as $o ) {
			$out[] = [
				'id'                => (int) $o->id,
				'order_number'      => $o->order_number,
				'plan_name'         => $o->plan_name,
				'egg_name'          => $o->egg_name,
				'server_label'      => $o->server_label ? $o->server_label : $o->plan_name,
				'status'            => $o->status,
				'address'           => PHM_Frontend::public_address( $o ),
				'server_identifier' => $o->server_identifier,
				'next_due_at'       => $o->next_due_at,
			];
		}
		return rest_ensure_response( [ 'success' => true, 'servers' => $out ] );
	}

	public static function get_tickets( WP_REST_Request $request ) {
		$tickets = PHM_DB::get_tickets_for_wp_user( get_current_user_id() );
		return rest_ensure_response( [ 'success' => true, 'tickets' => $tickets ] );
	}

	public static function create_ticket( WP_REST_Request $request ) {
		$subject    = sanitize_text_field( (string) $request->get_param( 'subject' ) );
		$message    = sanitize_textarea_field( (string) $request->get_param( 'message' ) );
		$priority   = sanitize_key( (string) $request->get_param( 'priority' ) );
		$department = sanitize_text_field( (string) $request->get_param( 'department' ) );
		$order_id   = (int) $request->get_param( 'order_id' );

		if ( '' === $subject || '' === $message ) {
			return new WP_Error( 'missing_fields', 'Subject and message are required', [ 'status' => 400 ] );
		}

		$ticket_id = PHM_DB::create_ticket( [
			'wp_user_id' => get_current_user_id(),
			'order_id'   => $order_id,
			'department' => $department ? $department : 'Technical',
			'subject'    => $subject,
			'priority'   => $priority ? $priority : 'normal',
		] );

		$user = wp_get_current_user();
		PHM_DB::add_ticket_reply( $ticket_id, [
			'wp_user_id'  => get_current_user_id(),
			'author_name' => $user->display_name,
			'is_staff'    => 0,
			'message'     => $message,
		] );

		return rest_ensure_response( [ 'success' => true, 'ticket_id' => $ticket_id ] );
	}
}
