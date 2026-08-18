<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Standalone client account system (its own email + password, own table),
 * independent of WordPress users — with an optional setting to also mirror
 * the account into a real WP user (ptero_sync_wp_user) so it shows up under
 * Users → All Users too, and WordPress logins keep working.
 */
class Ptero_Client_Auth {

	private static $instance = null;
	const COOKIE_NAME = 'ptero_client_token';

	public static function instance() {
		if ( self::$instance === null ) self::$instance = new self();
		return self::$instance;
	}

	private function __construct() {
		add_shortcode( 'ptero_login', array( $this, 'render_login' ) );
		add_shortcode( 'ptero_register', array( $this, 'render_register' ) );
		add_shortcode( 'ptero_logout', array( $this, 'do_logout_shortcode' ) );

		add_action( 'wp_ajax_nopriv_ptero_client_register', array( $this, 'ajax_register' ) );
		add_action( 'wp_ajax_nopriv_ptero_client_login', array( $this, 'ajax_login' ) );
		add_action( 'wp_ajax_ptero_client_register', array( $this, 'ajax_register' ) );
		add_action( 'wp_ajax_ptero_client_login', array( $this, 'ajax_login' ) );
		add_action( 'wp_ajax_ptero_client_logout', array( $this, 'ajax_logout' ) );
		add_action( 'wp_ajax_nopriv_ptero_client_logout', array( $this, 'ajax_logout' ) );
	}

	// ------------------------------------------------------------- Helpers

	private function table() {
		global $wpdb;
		return $wpdb->prefix . 'ptero_clients';
	}

	public function current_client() {
		static $cached = 'unset';
		if ( $cached !== 'unset' ) return $cached;

		$token = $_COOKIE[ self::COOKIE_NAME ] ?? '';
		if ( ! $token ) { $cached = null; return null; }

		global $wpdb;
		$client = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$this->table()} WHERE auth_token = %s AND token_expires > NOW()", $token
		) );
		$cached = $client ?: null;
		return $cached;
	}

	private function issue_token( $client_id ) {
		global $wpdb;
		$token = wp_generate_password( 40, false );
		$wpdb->update( $this->table(), array(
			'auth_token'    => $token,
			'token_expires' => date( 'Y-m-d H:i:s', time() + 30 * DAY_IN_SECONDS ),
		), array( 'id' => $client_id ) );
		return $token;
	}

	private function set_cookie( $token ) {
		setcookie( self::COOKIE_NAME, $token, time() + 30 * DAY_IN_SECONDS, COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), true );
	}

	/** Optionally mirrors a client record into a real WordPress user (email + password kept in sync). */
	private function maybe_sync_wp_user( $name, $email, $plain_password, $existing_wp_user_id = 0 ) {
		if ( get_option( 'ptero_sync_wp_user', '0' ) !== '1' ) return $existing_wp_user_id;

		if ( $existing_wp_user_id && get_user_by( 'id', $existing_wp_user_id ) ) {
			wp_update_user( array( 'ID' => $existing_wp_user_id, 'user_pass' => $plain_password, 'user_email' => $email ) );
			return $existing_wp_user_id;
		}
		$existing = get_user_by( 'email', $email );
		if ( $existing ) return $existing->ID;

		$user_id = wp_create_user( sanitize_user( current( explode( '@', $email ) ) . '_' . wp_rand( 100, 999 ) ), $plain_password, $email );
		if ( ! is_wp_error( $user_id ) ) {
			wp_update_user( array( 'ID' => $user_id, 'display_name' => $name ) );
			return $user_id;
		}
		return 0;
	}

	// -------------------------------------------------------------- AJAX

	public function ajax_register() {
		check_ajax_referer( 'ptero_host_nonce', 'nonce' );
		global $wpdb;

		$name  = sanitize_text_field( $_POST['name'] ?? '' );
		$email = sanitize_email( $_POST['email'] ?? '' );
		$pass  = (string) ( $_POST['password'] ?? '' );

		if ( ! $name || ! is_email( $email ) || strlen( $pass ) < 6 ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid name, email, and a password of at least 6 characters.', 'ptero-host' ) ) );
		}

		$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$this->table()} WHERE email = %s", $email ) );
		if ( $exists ) {
			wp_send_json_error( array( 'message' => __( 'An account with that email already exists.', 'ptero-host' ) ) );
		}

		$wp_user_id = $this->maybe_sync_wp_user( $name, $email, $pass );

		$wpdb->insert( $this->table(), array(
			'wp_user_id'    => $wp_user_id ?: null,
			'name'          => $name,
			'email'         => $email,
			'password_hash' => wp_hash_password( $pass ),
			'currency'      => get_option( 'ptero_currency', 'PKR' ),
			'status'        => 'active',
			'verified'      => 1,
		) );
		$client_id = $wpdb->insert_id;

		do_action( 'ptero_client_registered', $client_id, $email, $name );

		$token = $this->issue_token( $client_id );
		$this->set_cookie( $token );
		wp_send_json_success( array( 'message' => __( 'Account created!', 'ptero-host' ), 'redirect' => get_option( 'ptero_dashboard_page_url', '' ) ) );
	}

	public function ajax_login() {
		check_ajax_referer( 'ptero_host_nonce', 'nonce' );
		global $wpdb;

		$email = sanitize_email( $_POST['email'] ?? '' );
		$pass  = (string) ( $_POST['password'] ?? '' );

		$client = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table()} WHERE email = %s", $email ) );
		if ( ! $client || ! wp_check_password( $pass, $client->password_hash ) ) {
			wp_send_json_error( array( 'message' => __( 'Incorrect email or password.', 'ptero-host' ) ) );
		}
		if ( $client->status !== 'active' ) {
			wp_send_json_error( array( 'message' => __( 'This account is suspended.', 'ptero-host' ) ) );
		}

		$token = $this->issue_token( $client->id );
		$this->set_cookie( $token );
		wp_send_json_success( array( 'message' => __( 'Welcome back!', 'ptero-host' ), 'redirect' => get_option( 'ptero_dashboard_page_url', '' ) ) );
	}

	public function ajax_logout() {
		setcookie( self::COOKIE_NAME, '', time() - HOUR_IN_SECONDS, COOKIEPATH ?: '/', COOKIE_DOMAIN );
		wp_send_json_success();
	}

	public function do_logout_shortcode() {
		if ( isset( $_GET['ptero_logout'] ) ) {
			setcookie( self::COOKIE_NAME, '', time() - HOUR_IN_SECONDS, COOKIEPATH ?: '/', COOKIE_DOMAIN );
		}
		return '';
	}

	// ------------------------------------------------------------ Render

	public function render_login( $atts ) {
		if ( $this->current_client() ) {
			return '<p>' . esc_html__( 'You are already logged in.', 'ptero-host' ) . '</p>';
		}
		ob_start();
		include PTEROHOST_PATH . 'templates/client-login.php';
		return ob_get_clean();
	}

	public function render_register( $atts ) {
		if ( $this->current_client() ) {
			return '<p>' . esc_html__( 'You are already logged in.', 'ptero-host' ) . '</p>';
		}
		ob_start();
		include PTEROHOST_PATH . 'templates/client-register.php';
		return ob_get_clean();
	}
}
