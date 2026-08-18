<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Ptero_REST_API {

	private static $instance = null;
	public static function instance() {
		if ( self::$instance === null ) self::$instance = new self();
		return self::$instance;
	}

	private function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route( 'ptero-host/v1', '/servers', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_my_servers' ),
			'permission_callback' => function () { return is_user_logged_in(); },
		) );

		register_rest_route( 'ptero-host/v1', '/locations', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_locations' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( 'ptero-host/v1', '/estimate', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'estimate' ),
			'permission_callback' => '__return_true',
		) );
	}

	public function get_my_servers( $request ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ptero_servers';
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d", get_current_user_id() ) );
		return rest_ensure_response( $rows );
	}

	public function get_locations( $request ) {
		$api = new Ptero_API();
		$locations = $api->get_locations();
		if ( is_wp_error( $locations ) ) {
			return new WP_Error( 'ptero_error', $locations->get_error_message(), array( 'status' => 400 ) );
		}
		return rest_ensure_response( $locations );
	}

	public function estimate( $request ) {
		$body = $request->get_json_params();
		$price = Ptero_Pricing::calculate( array(
			'ram'           => intval( $body['ram'] ?? 0 ),
			'cpu'           => intval( $body['cpu'] ?? 0 ),
			'disk'          => intval( $body['disk'] ?? 0 ),
			'dedicated_ip'  => ! empty( $body['dedicated_ip'] ),
			'backups'       => intval( $body['backups'] ?? 0 ),
			'databases'     => intval( $body['databases'] ?? 0 ),
			'billing_cycle' => sanitize_key( $body['billing_cycle'] ?? 'monthly' ),
		) );
		return rest_ensure_response( array( 'price' => $price, 'currency' => get_option( 'ptero_currency', 'PKR' ) ) );
	}
}
