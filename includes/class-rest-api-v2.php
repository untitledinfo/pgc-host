<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * REST API for external apps / mobile apps / WordPress-to-WordPress
 * integrations: register, login, plans, cart, tickets, invoices.
 * Namespace: /wp-json/ptero-host/v1/...
 * Auth: send the token from /login back as header  X-Ptero-Token
 */
class Ptero_REST_API_V2 {

	private static $instance = null;
	public static function instance() {
		if ( self::$instance === null ) self::$instance = new self();
		return self::$instance;
	}

	private function __construct() {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	public function routes() {
		$ns = 'ptero-host/v1';

		register_rest_route( $ns, '/register', array( 'methods' => 'POST', 'callback' => array( $this, 'register' ), 'permission_callback' => '__return_true' ) );
		register_rest_route( $ns, '/login', array( 'methods' => 'POST', 'callback' => array( $this, 'login' ), 'permission_callback' => '__return_true' ) );
		register_rest_route( $ns, '/me', array( 'methods' => 'GET', 'callback' => array( $this, 'me' ), 'permission_callback' => '__return_true' ) );

		register_rest_route( $ns, '/plans', array( 'methods' => 'GET', 'callback' => array( $this, 'plans' ), 'permission_callback' => '__return_true' ) );
		register_rest_route( $ns, '/locations', array( 'methods' => 'GET', 'callback' => array( $this, 'locations' ), 'permission_callback' => '__return_true' ) );

		register_rest_route( $ns, '/cart', array( 'methods' => 'GET', 'callback' => array( $this, 'cart_get' ), 'permission_callback' => '__return_true' ) );
		register_rest_route( $ns, '/cart/add', array( 'methods' => 'POST', 'callback' => array( $this, 'cart_add' ), 'permission_callback' => '__return_true' ) );
		register_rest_route( $ns, '/checkout', array( 'methods' => 'POST', 'callback' => array( $this, 'checkout' ), 'permission_callback' => '__return_true' ) );

		register_rest_route( $ns, '/invoices', array( 'methods' => 'GET', 'callback' => array( $this, 'invoices' ), 'permission_callback' => '__return_true' ) );
		register_rest_route( $ns, '/tickets', array( 'methods' => 'GET', 'callback' => array( $this, 'tickets_list' ), 'permission_callback' => '__return_true' ) );
		register_rest_route( $ns, '/tickets', array( 'methods' => 'POST', 'callback' => array( $this, 'tickets_create' ), 'permission_callback' => '__return_true' ) );
	}

	private function auth_client( \WP_REST_Request $req ) {
		global $wpdb;
		$token = $req->get_header( 'x-ptero-token' ) ?: $req->get_param( 'token' );
		if ( ! $token ) return null;
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}ptero_clients WHERE auth_token = %s AND token_expires > NOW()", $token
		) );
	}

	public function register( \WP_REST_Request $req ) {
		global $wpdb;
		$name  = sanitize_text_field( $req->get_param( 'name' ) );
		$email = sanitize_email( $req->get_param( 'email' ) );
		$pass  = (string) $req->get_param( 'password' );

		if ( ! $name || ! is_email( $email ) || strlen( $pass ) < 6 ) {
			return new WP_Error( 'invalid_input', 'name, valid email, and password (6+ chars) are required.', array( 'status' => 400 ) );
		}
		$table = $wpdb->prefix . 'ptero_clients';
		if ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE email = %s", $email ) ) ) {
			return new WP_Error( 'exists', 'An account with that email already exists.', array( 'status' => 409 ) );
		}

		$wpdb->insert( $table, array(
			'name' => $name, 'email' => $email, 'password_hash' => wp_hash_password( $pass ),
			'currency' => get_option( 'ptero_currency', 'PKR' ), 'status' => 'active', 'verified' => 1,
		) );
		$client_id = $wpdb->insert_id;
		$token = wp_generate_password( 40, false );
		$wpdb->update( $table, array( 'auth_token' => $token, 'token_expires' => date( 'Y-m-d H:i:s', time() + 30 * DAY_IN_SECONDS ) ), array( 'id' => $client_id ) );

		return array( 'success' => true, 'token' => $token, 'client_id' => $client_id );
	}

	public function login( \WP_REST_Request $req ) {
		global $wpdb;
		$email = sanitize_email( $req->get_param( 'email' ) );
		$pass  = (string) $req->get_param( 'password' );
		$table = $wpdb->prefix . 'ptero_clients';
		$client = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE email = %s", $email ) );
		if ( ! $client || ! wp_check_password( $pass, $client->password_hash ) ) {
			return new WP_Error( 'invalid_login', 'Incorrect email or password.', array( 'status' => 401 ) );
		}
		$token = wp_generate_password( 40, false );
		$wpdb->update( $table, array( 'auth_token' => $token, 'token_expires' => date( 'Y-m-d H:i:s', time() + 30 * DAY_IN_SECONDS ) ), array( 'id' => $client->id ) );
		return array( 'success' => true, 'token' => $token, 'client_id' => $client->id );
	}

	public function me( \WP_REST_Request $req ) {
		$client = $this->auth_client( $req );
		if ( ! $client ) return new WP_Error( 'unauthorized', 'Invalid or expired token.', array( 'status' => 401 ) );
		unset( $client->password_hash, $client->auth_token );
		return $client;
	}

	public function plans( \WP_REST_Request $req ) {
		return array_map( function ( $p ) { unset( $p->egg_id ); return $p; }, Ptero_Plans::get_active( 200 ) );
	}

	public function locations( \WP_REST_Request $req ) {
		if ( ! class_exists( 'Ptero_API' ) ) return array();
		$api = new Ptero_API();
		if ( ! $api->is_configured() ) return array();
		return $api->get_locations() ?: array();
	}

	public function cart_get( \WP_REST_Request $req ) {
		$key = $req->get_param( 'session_key' );
		if ( ! $key ) return new WP_Error( 'missing_session', 'session_key is required.', array( 'status' => 400 ) );
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT c.*, p.name AS plan_name, p.currency FROM {$wpdb->prefix}ptero_cart c JOIN {$wpdb->prefix}ptero_plans p ON p.id = c.plan_id WHERE c.session_key = %s", $key
		) );
	}

	public function cart_add( \WP_REST_Request $req ) {
		global $wpdb;
		$plan_id = (int) $req->get_param( 'plan_id' );
		$cycle   = sanitize_text_field( $req->get_param( 'billing_cycle' ) ?: 'monthly' );
		$key     = sanitize_text_field( $req->get_param( 'session_key' ) ?: wp_generate_password( 32, false ) );
		$plan    = Ptero_Plans::get( $plan_id );
		if ( ! $plan || $plan->status !== 'active' ) return new WP_Error( 'invalid_plan', 'Plan not available.', array( 'status' => 404 ) );

		$client = $this->auth_client( $req );
		$wpdb->insert( $wpdb->prefix . 'ptero_cart', array(
			'session_key' => $key, 'client_id' => $client ? $client->id : null, 'plan_id' => $plan_id,
			'server_name' => sanitize_text_field( $req->get_param( 'server_name' ) ?: ( $plan->name . ' Server' ) ),
			'billing_cycle' => $cycle, 'quantity' => 1,
		) );
		return array( 'success' => true, 'session_key' => $key );
	}

	public function checkout( \WP_REST_Request $req ) {
		$client = $this->auth_client( $req );
		if ( ! $client ) return new WP_Error( 'unauthorized', 'Login required to check out.', array( 'status' => 401 ) );

		global $wpdb;
		$key = sanitize_text_field( $req->get_param( 'session_key' ) );
		$items = $wpdb->get_results( $wpdb->prepare(
			"SELECT c.*, p.name AS plan_name, p.currency FROM {$wpdb->prefix}ptero_cart c JOIN {$wpdb->prefix}ptero_plans p ON p.id = c.plan_id WHERE c.session_key = %s", $key
		) );
		if ( ! $items ) return new WP_Error( 'empty_cart', 'Cart is empty.', array( 'status' => 400 ) );

		$cart_helper = Ptero_Cart::instance();
		$invoice_id = Ptero_Billing::create_invoice_from_cart( $client, $items, $cart_helper );
		$wpdb->delete( $wpdb->prefix . 'ptero_cart', array( 'session_key' => $key ) );

		return array( 'success' => true, 'invoice_id' => $invoice_id );
	}

	public function invoices( \WP_REST_Request $req ) {
		$client = $this->auth_client( $req );
		if ( ! $client ) return new WP_Error( 'unauthorized', 'Invalid or expired token.', array( 'status' => 401 ) );
		return Ptero_Billing::instance()->get_client_invoices( $client->id );
	}

	public function tickets_list( \WP_REST_Request $req ) {
		$client = $this->auth_client( $req );
		if ( ! $client ) return new WP_Error( 'unauthorized', 'Invalid or expired token.', array( 'status' => 401 ) );
		return Ptero_Tickets::instance()->get_client_tickets( $client->id );
	}

	public function tickets_create( \WP_REST_Request $req ) {
		$client = $this->auth_client( $req );
		if ( ! $client ) return new WP_Error( 'unauthorized', 'Invalid or expired token.', array( 'status' => 401 ) );

		global $wpdb;
		$subject = sanitize_text_field( $req->get_param( 'subject' ) );
		$message = wp_kses_post( $req->get_param( 'message' ) );
		if ( ! $subject || ! $message ) return new WP_Error( 'invalid_input', 'subject and message are required.', array( 'status' => 400 ) );

		$wpdb->insert( $wpdb->prefix . 'ptero_tickets', array( 'client_id' => $client->id, 'subject' => $subject, 'department' => sanitize_text_field( $req->get_param( 'department' ) ?: 'general' ) ) );
		$ticket_id = $wpdb->insert_id;
		$wpdb->insert( $wpdb->prefix . 'ptero_ticket_replies', array( 'ticket_id' => $ticket_id, 'sender_type' => 'client', 'sender_name' => $client->name, 'message' => $message ) );

		return array( 'success' => true, 'ticket_id' => $ticket_id );
	}
}
