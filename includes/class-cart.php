<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Ptero_Cart {

	private static $instance = null;
	const COOKIE_NAME = 'ptero_cart_session';

	public static function instance() {
		if ( self::$instance === null ) self::$instance = new self();
		return self::$instance;
	}

	private function __construct() {
		add_shortcode( 'ptero_cart', array( $this, 'render_cart' ) );
		add_shortcode( 'ptero_checkout', array( $this, 'render_checkout' ) );

		add_action( 'wp_ajax_ptero_cart_add', array( $this, 'ajax_add' ) );
		add_action( 'wp_ajax_nopriv_ptero_cart_add', array( $this, 'ajax_add' ) );
		add_action( 'wp_ajax_ptero_cart_remove', array( $this, 'ajax_remove' ) );
		add_action( 'wp_ajax_nopriv_ptero_cart_remove', array( $this, 'ajax_remove' ) );
		add_action( 'wp_ajax_ptero_cart_checkout', array( $this, 'ajax_checkout' ) );
		add_action( 'wp_ajax_nopriv_ptero_cart_checkout', array( $this, 'ajax_checkout' ) );
	}

	private function table() {
		global $wpdb;
		return $wpdb->prefix . 'ptero_cart';
	}

	public function session_key() {
		if ( empty( $_COOKIE[ self::COOKIE_NAME ] ) ) {
			$key = wp_generate_password( 32, false );
			setcookie( self::COOKIE_NAME, $key, time() + 7 * DAY_IN_SECONDS, COOKIEPATH ?: '/', COOKIE_DOMAIN );
			$_COOKIE[ self::COOKIE_NAME ] = $key;
			return $key;
		}
		return $_COOKIE[ self::COOKIE_NAME ];
	}

	public function items() {
		global $wpdb;
		$key = $this->session_key();
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT c.*, p.name AS plan_name, p.currency, p.image_url, p.cpu, p.ram, p.disk
			 FROM {$this->table()} c JOIN " . Ptero_Plans::table() . " p ON p.id = c.plan_id
			 WHERE c.session_key = %s ORDER BY c.id ASC", $key
		) );
	}

	public function line_total( $item ) {
		$plan = Ptero_Plans::get( $item->plan_id );
		$price = $plan ? Ptero_Plans::price_for_cycle( $plan, $item->billing_cycle ) : 0;
		$total = (float) $price * (int) $item->quantity;
		if ( $item->coupon_code && class_exists( 'Ptero_Coupons' ) ) {
			$total = Ptero_Coupons::apply( $item->coupon_code, $total );
		}
		return round( $total, 2 );
	}

	public function ajax_add() {
		check_ajax_referer( 'ptero_host_nonce', 'nonce' );
		global $wpdb;

		$plan_id = (int) ( $_POST['plan_id'] ?? 0 );
		$cycle   = sanitize_text_field( $_POST['billing_cycle'] ?? 'monthly' );
		$name    = sanitize_text_field( $_POST['server_name'] ?? '' );
		$plan    = Ptero_Plans::get( $plan_id );

		if ( ! $plan || $plan->status !== 'active' ) {
			wp_send_json_error( array( 'message' => __( 'That plan is not available.', 'ptero-host' ) ) );
		}
		if ( Ptero_Plans::price_for_cycle( $plan, $cycle ) === null ) {
			wp_send_json_error( array( 'message' => __( 'That billing cycle is not offered for this plan.', 'ptero-host' ) ) );
		}
		if ( $plan->stock !== null && $plan->stock <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'This plan is out of stock.', 'ptero-host' ) ) );
		}

		$client = Ptero_Client_Auth::instance()->current_client();

		$wpdb->insert( $this->table(), array(
			'session_key'   => $this->session_key(),
			'client_id'     => $client ? $client->id : null,
			'plan_id'       => $plan_id,
			'server_name'   => $name ?: ( $plan->name . ' Server' ),
			'billing_cycle' => $cycle,
			'quantity'      => 1,
		) );

		wp_send_json_success( array( 'message' => __( 'Added to cart.', 'ptero-host' ), 'count' => count( $this->items() ) ) );
	}

	public function ajax_remove() {
		check_ajax_referer( 'ptero_host_nonce', 'nonce' );
		global $wpdb;
		$id = (int) ( $_POST['item_id'] ?? 0 );
		$wpdb->delete( $this->table(), array( 'id' => $id, 'session_key' => $this->session_key() ) );
		wp_send_json_success();
	}

	public function ajax_checkout() {
		check_ajax_referer( 'ptero_host_nonce', 'nonce' );
		global $wpdb;

		$client = Ptero_Client_Auth::instance()->current_client();
		if ( ! $client ) {
			wp_send_json_error( array( 'message' => __( 'Please log in or create an account to check out.', 'ptero-host' ) ) );
		}

		$items = $this->items();
		if ( ! $items ) {
			wp_send_json_error( array( 'message' => __( 'Your cart is empty.', 'ptero-host' ) ) );
		}

		$invoice_id = Ptero_Billing::create_invoice_from_cart( $client, $items, $this );
		if ( ! $invoice_id ) {
			wp_send_json_error( array( 'message' => __( 'Could not create invoice. Please try again.', 'ptero-host' ) ) );
		}

		$wpdb->delete( $this->table(), array( 'session_key' => $this->session_key() ) );

		wp_send_json_success( array(
			'message'    => __( 'Invoice created!', 'ptero-host' ),
			'invoice_id' => $invoice_id,
			'redirect'   => add_query_arg( 'invoice', $invoice_id, get_option( 'ptero_invoice_page_url', '' ) ),
		) );
	}

	public function render_cart( $atts ) {
		ob_start();
		include PTEROHOST_PATH . 'templates/cart.php';
		return ob_get_clean();
	}

	public function render_checkout( $atts ) {
		ob_start();
		include PTEROHOST_PATH . 'templates/checkout.php';
		return ob_get_clean();
	}
}
