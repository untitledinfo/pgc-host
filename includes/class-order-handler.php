<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Ptero_Order_Handler {

	private static $instance = null;
	public static function instance() {
		if ( self::$instance === null ) self::$instance = new self();
		return self::$instance;
	}

	private function __construct() {
		add_action( 'wp_ajax_ptero_submit_order', array( $this, 'ajax_submit_order' ) );
		add_action( 'wp_ajax_nopriv_ptero_submit_order', array( $this, 'ajax_require_login' ) );
		add_action( 'wp_ajax_ptero_calculate_price', array( $this, 'ajax_calculate_price' ) );
		add_action( 'wp_ajax_nopriv_ptero_calculate_price', array( $this, 'ajax_calculate_price' ) );
		add_action( 'wp_ajax_ptero_get_eggs', array( $this, 'ajax_get_eggs' ) );
		add_action( 'wp_ajax_nopriv_ptero_get_eggs', array( $this, 'ajax_get_eggs' ) );
	}

	public function ajax_get_eggs() {
		check_ajax_referer( 'ptero_host_nonce', 'nonce' );
		$nest_id = intval( $_POST['nest_id'] ?? 0 );
		if ( ! $nest_id ) wp_send_json_error( 'Missing nest id', 400 );

		$api  = new Ptero_API();
		$eggs = $api->get_eggs( $nest_id );
		if ( is_wp_error( $eggs ) ) wp_send_json_error( $eggs->get_error_message(), 400 );

		$out = array_map( function ( $e ) {
			return array( 'id' => $e['attributes']['id'], 'name' => $e['attributes']['name'] );
		}, $eggs );

		wp_send_json_success( $out );
	}

	public function ajax_require_login() {
		wp_send_json_error( __( 'Please log in to place an order.', 'ptero-host' ), 401 );
	}

	public function ajax_calculate_price() {
		check_ajax_referer( 'ptero_host_nonce', 'nonce' );
		$price = Ptero_Pricing::calculate( array(
			'ram'           => intval( $_POST['ram'] ?? 0 ),
			'cpu'           => intval( $_POST['cpu'] ?? 0 ),
			'disk'          => intval( $_POST['disk'] ?? 0 ),
			'dedicated_ip'  => ! empty( $_POST['dedicated_ip'] ),
			'backups'       => intval( $_POST['backups'] ?? 0 ),
			'databases'     => intval( $_POST['databases'] ?? 0 ),
			'billing_cycle' => sanitize_key( $_POST['billing_cycle'] ?? 'monthly' ),
			'coupon'        => sanitize_text_field( $_POST['coupon'] ?? '' ),
		) );
		wp_send_json_success( array( 'price' => $price, 'currency' => get_option( 'ptero_currency', 'PKR' ) ) );
	}

	public function ajax_submit_order() {
		check_ajax_referer( 'ptero_host_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( __( 'Please log in first.', 'ptero-host' ), 401 );
		}

		// reCAPTCHA
		$secret = get_option( 'ptero_recaptcha_secret_key' );
		if ( $secret ) {
			$token = sanitize_text_field( $_POST['recaptcha_token'] ?? '' );
			$verify = wp_remote_post( 'https://www.google.com/recaptcha/api/siteverify', array(
				'body' => array( 'secret' => $secret, 'response' => $token ),
			) );
			$body = json_decode( wp_remote_retrieve_body( $verify ), true );
			if ( empty( $body['success'] ) ) {
				wp_send_json_error( __( 'reCAPTCHA verification failed.', 'ptero-host' ), 400 );
			}
		}

		global $wpdb;
		$user_id = get_current_user_id();
		$table   = $wpdb->prefix . 'ptero_servers';

		$ram          = max( 512, intval( $_POST['ram'] ?? 1024 ) );
		$cpu          = max( 25, intval( $_POST['cpu'] ?? 100 ) );
		$disk         = max( 512, intval( $_POST['disk'] ?? 2048 ) );
		$location_id  = intval( $_POST['location_id'] ?? 0 );
		$egg_id       = intval( $_POST['egg_id'] ?? 0 );
		$dedicated_ip = ! empty( $_POST['dedicated_ip'] );
		$backups      = intval( $_POST['backups'] ?? 0 );
		$databases    = intval( $_POST['databases'] ?? 1 );
		$billing      = sanitize_key( $_POST['billing_cycle'] ?? 'monthly' );
		$coupon       = sanitize_text_field( $_POST['coupon'] ?? '' );
		$server_name  = sanitize_text_field( $_POST['server_name'] ?? ( 'srv-' . wp_generate_password( 6, false ) ) );

		if ( ! $location_id || ! $egg_id ) {
			wp_send_json_error( __( 'Please choose a location and game type.', 'ptero-host' ), 400 );
		}

		$price = Ptero_Pricing::calculate( compact( 'ram', 'cpu', 'disk', 'dedicated_ip', 'backups', 'databases', 'billing', 'coupon' ) + array( 'billing_cycle' => $billing ) );

		$wpdb->insert( $table, array(
			'user_id'      => $user_id,
			'server_name'  => $server_name,
			'egg_id'       => $egg_id,
			'location_id'  => $location_id,
			'ram'          => $ram,
			'cpu'          => $cpu,
			'disk'         => $disk,
			'backups'      => $backups,
			'databases'    => $databases,
			'dedicated_ip' => $dedicated_ip ? 1 : 0,
			'price'        => $price,
			'currency'     => get_option( 'ptero_currency', 'PKR' ),
			'billing_cycle'=> $billing,
			'status'       => 'pending',
			'created_at'   => current_time( 'mysql' ),
		) );

		$order_id = $wpdb->insert_id;

		if ( get_option( 'ptero_payment_mode', 'manual' ) === 'manual' ) {
			Ptero_Notifications::instance()->send_order_received( $order_id );
			wp_send_json_success( array(
				'order_id' => $order_id,
				'mode'     => 'manual',
				'message'  => get_option( 'ptero_manual_instructions' ),
			) );
		} else {
			// WooCommerce mode: hand off to WC to create a product/order & checkout URL.
			$checkout_url = Ptero_Payments::instance()->create_woocommerce_order( $order_id, $price );
			wp_send_json_success( array(
				'order_id'     => $order_id,
				'mode'         => 'woocommerce',
				'checkout_url' => $checkout_url,
			) );
		}
	}

	/** Actually provisions the server on the panel once payment/approval is confirmed. */
	public function provision( $order_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ptero_servers';
		$order = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $order_id ) );
		if ( ! $order ) return new WP_Error( 'not_found', 'Order not found' );

		$api = new Ptero_API();
		$user = get_user_by( 'id', $order->user_id );
		if ( ! $user ) return new WP_Error( 'no_user', 'User not found' );

		$ptero_user_id = $api->find_or_create_user( $user );
		if ( is_wp_error( $ptero_user_id ) ) return $ptero_user_id;

		// Check node capacity before creating (avoid overselling)
		$nodes = $api->get_nodes( $order->location_id );
		if ( is_wp_error( $nodes ) || empty( $nodes ) ) return new WP_Error( 'no_nodes', 'No nodes available in this location' );

		$chosen_node = null;
		foreach ( $nodes as $node ) {
			$cap = $api->get_node_capacity( $node['attributes']['id'] );
			if ( is_wp_error( $cap ) ) continue;
			$free_ram = $cap['memory_total'] - $cap['memory_allocated'];
			if ( $free_ram >= $order->ram ) {
				$chosen_node = $node['attributes']['id'];
				break;
			}
		}
		if ( ! $chosen_node ) return new WP_Error( 'no_capacity', 'No node currently has capacity for this plan. Please contact support.' );

		$allocation_id = $api->find_free_allocation( $chosen_node, (bool) $order->dedicated_ip );
		if ( is_wp_error( $allocation_id ) ) return $allocation_id;

		// Docker image / startup command / environment variables per egg.
		// Hook into these filters (e.g. in your theme's functions.php) to map
		// each egg ID to its correct Docker image & startup string.
		$environment = apply_filters( 'ptero_host_environment_for_egg', array(), $order->egg_id );

		$created = $api->create_server( array(
			'name'          => $order->server_name,
			'user'          => $ptero_user_id,
			'egg'           => $order->egg_id,
			'docker_image'  => apply_filters( 'ptero_host_docker_image_for_egg', '', $order->egg_id ),
			'startup'       => apply_filters( 'ptero_host_startup_for_egg', '', $order->egg_id ),
			'environment'   => $environment,
			'memory'        => $order->ram,
			'disk'          => $order->disk,
			'cpu'           => $order->cpu,
			'databases'     => $order->databases,
			'backups'       => $order->backups,
			'allocation_id' => $allocation_id,
		) );

		if ( is_wp_error( $created ) ) return $created;

		$attrs = $created['attributes'];
		$expires = self::next_expiry( $order->billing_cycle );

		$wpdb->update( $table, array(
			'ptero_server_id'  => $attrs['id'],
			'ptero_identifier' => $attrs['identifier'],
			'node_id'          => $chosen_node,
			'status'           => 'active',
			'expires_at'       => $expires,
		), array( 'id' => $order_id ) );

		Ptero_Notifications::instance()->send_server_ready( $order_id );

		return true;
	}

	public static function next_expiry( $cycle ) {
		$map = array( 'monthly' => '+1 month', 'quarterly' => '+3 months', 'yearly' => '+1 year' );
		return date( 'Y-m-d H:i:s', strtotime( $map[ $cycle ] ?? '+1 month' ) );
	}

	public function handle_admin_action( $order_id, $action ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ptero_servers';
		$order = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $order_id ) );
		if ( ! $order ) return new WP_Error( 'not_found', 'Order not found' );

		$api = new Ptero_API();

		switch ( $action ) {
			case 'approve':
				return $this->provision( $order_id );

			case 'suspend':
				$res = $api->suspend_server( $order->ptero_server_id );
				if ( is_wp_error( $res ) ) return $res;
				$wpdb->update( $table, array( 'status' => 'suspended' ), array( 'id' => $order_id ) );
				return true;

			case 'unsuspend':
				$res = $api->unsuspend_server( $order->ptero_server_id );
				if ( is_wp_error( $res ) ) return $res;
				$wpdb->update( $table, array( 'status' => 'active' ), array( 'id' => $order_id ) );
				return true;

			case 'delete':
				if ( $order->ptero_server_id ) {
					$res = $api->delete_server( $order->ptero_server_id );
					if ( is_wp_error( $res ) ) return $res;
				}
				$wpdb->delete( $table, array( 'id' => $order_id ) );
				return true;
		}

		return new WP_Error( 'bad_action', 'Unknown action' );
	}
}
