<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Ptero_Client_Dashboard {

	private static $instance = null;
	public static function instance() {
		if ( self::$instance === null ) self::$instance = new self();
		return self::$instance;
	}

	private function __construct() {
		add_shortcode( 'ptero_dashboard', array( $this, 'render' ) );
		add_action( 'wp_ajax_ptero_power_action', array( $this, 'ajax_power_action' ) );
		add_action( 'wp_ajax_ptero_get_usage', array( $this, 'ajax_get_usage' ) );
	}

	public function render() {
		if ( ! is_user_logged_in() ) {
			return '<p>' . sprintf( wp_kses_post( __( 'Please <a href="%s">log in</a> to view your servers.', 'ptero-host' ) ), esc_url( wp_login_url( get_permalink() ) ) ) . '</p>';
		}

		wp_enqueue_style( 'ptero-host' );
		wp_enqueue_script( 'ptero-host-dashboard' );

		global $wpdb;
		$table = $wpdb->prefix . 'ptero_servers';
		$servers = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d ORDER BY created_at DESC", get_current_user_id() ) );

		ob_start();
		include PTEROHOST_PATH . 'templates/dashboard.php';
		return ob_get_clean();
	}

	public function ajax_power_action() {
		check_ajax_referer( 'ptero_host_nonce', 'nonce' );
		if ( ! is_user_logged_in() ) wp_send_json_error( 'Not logged in', 401 );

		global $wpdb;
		$table = $wpdb->prefix . 'ptero_servers';
		$order_id = intval( $_POST['order_id'] ?? 0 );
		$signal   = sanitize_key( $_POST['signal'] ?? '' );

		$server = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d AND user_id = %d", $order_id, get_current_user_id() ) );
		if ( ! $server || ! $server->ptero_identifier ) wp_send_json_error( 'Server not found', 404 );
		if ( $server->status !== 'active' ) wp_send_json_error( 'Server is suspended. Contact support.', 403 );

		$api = new Ptero_API();
		$res = $api->send_power_action( $server->ptero_identifier, $signal );
		if ( is_wp_error( $res ) ) wp_send_json_error( $res->get_error_message(), 400 );

		wp_send_json_success();
	}

	public function ajax_get_usage() {
		check_ajax_referer( 'ptero_host_nonce', 'nonce' );
		if ( ! is_user_logged_in() ) wp_send_json_error( 'Not logged in', 401 );

		global $wpdb;
		$table = $wpdb->prefix . 'ptero_servers';
		$order_id = intval( $_POST['order_id'] ?? 0 );
		$server = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d AND user_id = %d", $order_id, get_current_user_id() ) );
		if ( ! $server || ! $server->ptero_identifier ) wp_send_json_error( 'Server not found', 404 );

		$api = new Ptero_API();
		$res = $api->get_resource_usage( $server->ptero_identifier );
		if ( is_wp_error( $res ) ) wp_send_json_error( $res->get_error_message(), 400 );

		wp_send_json_success( $res['attributes'] ?? array() );
	}
}
