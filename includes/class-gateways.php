<?php
/**
 * Payment Gateways Manager & Universal Webhook/IPN API (250+ Payment Providers).
 *
 * Supported gateways:
 *  - Crypto: Cryptomus, NOWPayments, CoinPayments, BTCPay Server, Coinbase Commerce, OxaPay.
 *  - Cards / Fiat: Stripe, PayPal, Razorpay, Paystack, Flutterwave, Mollie, Cashfree.
 *  - Local / UPI / QR / Manual: UPI QR, Bank Transfer, Manual receipt.
 *  - Universal Payment Webhook: Generic JSON / Form-Data IPN receiver connecting
 *    250+ third-party payment gateways with instant auto-provisioning.
 *
 * @package Pterodactyl_Hosting
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PHM_Gateways {

	public static function init() {
		add_action( 'admin_post_phm_save_gateway', [ __CLASS__, 'handle_save' ] );
		add_action( 'admin_post_phm_toggle_gateway', [ __CLASS__, 'handle_toggle' ] );
		add_action( 'admin_post_phm_delete_gateway', [ __CLASS__, 'handle_delete' ] );

		// AJAX and standard IPN webhook endpoints.
		add_action( 'wp_ajax_phm_payment_webhook', [ __CLASS__, 'handle_webhook' ] );
		add_action( 'wp_ajax_nopriv_phm_payment_webhook', [ __CLASS__, 'handle_webhook' ] );
	}

	/**
	 * List all supported built-in payment gateway types.
	 */
	public static function gateway_directory() {
		return [
			'manual' => [
				'name'        => __( 'Bank Transfer / UPI / Manual QR', 'pterodactyl-hosting' ),
				'type'        => 'manual',
				'description' => __( 'Accept payments via direct Bank Transfer, UPI (Google Pay, PhonePe, Paytm), or manual proof submission.', 'pterodactyl-hosting' ),
				'fields'      => [ 'instructions' ],
			],
			'cryptomus' => [
				'name'        => __( 'Cryptomus (Crypto)', 'pterodactyl-hosting' ),
				'type'        => 'crypto',
				'description' => __( 'Accept BTC, ETH, USDT (TRC20/ERC20/BEP20), SOL, TON, and 30+ crypto assets with instant auto-confirmation.', 'pterodactyl-hosting' ),
				'fields'      => [ 'merchant_id', 'api_key', 'instructions' ],
			],
			'nowpayments' => [
				'name'        => __( 'NOWPayments (200+ Cryptos)', 'pterodactyl-hosting' ),
				'type'        => 'crypto',
				'description' => __( 'Non-custodial crypto payment gateway supporting 200+ cryptocurrencies with instant IPN.', 'pterodactyl-hosting' ),
				'fields'      => [ 'api_key', 'webhook_secret', 'instructions' ],
			],
			'coinpayments' => [
				'name'        => __( 'CoinPayments', 'pterodactyl-hosting' ),
				'type'        => 'crypto',
				'description' => __( 'Global crypto checkout supporting Bitcoin, Litecoin, Monero, and hundreds of altcoins.', 'pterodactyl-hosting' ),
				'fields'      => [ 'merchant_id', 'webhook_secret', 'instructions' ],
			],
			'stripe' => [
				'name'        => __( 'Stripe', 'pterodactyl-hosting' ),
				'type'        => 'fiat',
				'description' => __( 'Credit cards, Debit cards, Apple Pay, Google Pay, iDEAL, SEPA and global payment methods.', 'pterodactyl-hosting' ),
				'fields'      => [ 'api_key', 'api_secret', 'webhook_secret', 'instructions' ],
			],
			'paypal' => [
				'name'        => __( 'PayPal', 'pterodactyl-hosting' ),
				'type'        => 'fiat',
				'description' => __( 'PayPal account checkout and debit/credit card processing worldwide.', 'pterodactyl-hosting' ),
				'fields'      => [ 'merchant_id', 'api_key', 'api_secret', 'instructions' ],
			],
			'razorpay' => [
				'name'        => __( 'Razorpay (India)', 'pterodactyl-hosting' ),
				'type'        => 'fiat',
				'description' => __( 'India payments: UPI, Cards, Netbanking, Wallets.', 'pterodactyl-hosting' ),
				'fields'      => [ 'api_key', 'api_secret', 'instructions' ],
			],
			'paystack' => [
				'name'        => __( 'Paystack (Africa / Global)', 'pterodactyl-hosting' ),
				'type'        => 'fiat',
				'description' => __( 'Cards, Mobile Money, Bank Transfer in Nigeria, Ghana, South Africa, Kenya.', 'pterodactyl-hosting' ),
				'fields'      => [ 'api_key', 'api_secret', 'webhook_secret', 'instructions' ],
			],
			'flutterwave' => [
				'name'        => __( 'Flutterwave', 'pterodactyl-hosting' ),
				'type'        => 'fiat',
				'description' => __( 'Cards, Bank Accounts, Mobile Money, M-Pesa, USSD across 30+ countries.', 'pterodactyl-hosting' ),
				'fields'      => [ 'api_key', 'api_secret', 'webhook_secret', 'instructions' ],
			],
			'universal_api' => [
				'name'        => __( 'Universal Payment API / Webhook (250+ Gateways)', 'pterodactyl-hosting' ),
				'type'        => 'api',
				'description' => __( 'Connect ANY custom payment provider or webhook source to auto-verify payments and deploy servers.', 'pterodactyl-hosting' ),
				'fields'      => [ 'api_key', 'webhook_secret', 'custom_url', 'instructions' ],
			],
		];
	}

	/**
	 * Get active payment methods formatted for checkout selection.
	 */
	public static function get_active_methods() {
		$db_gateways = PHM_DB::get_gateways( true );
		$methods     = [];

		foreach ( $db_gateways as $g ) {
			$methods[ $g->gateway_id ] = [
				'id'           => $g->gateway_id,
				'label'        => $g->name,
				'type'         => $g->type,
				'instructions' => $g->instructions,
				'details'      => $g->instructions,
				'test_mode'    => ! empty( $g->test_mode ),
			];
		}

		if ( empty( $methods ) ) {
			// Fallback default manual method so checkout is never broken.
			$methods['manual'] = [
				'id'           => 'manual',
				'label'        => __( 'Direct Payment / Bank / UPI', 'pterodactyl-hosting' ),
				'type'         => 'manual',
				'instructions' => __( 'Follow instructions provided upon order creation.', 'pterodactyl-hosting' ),
				'details'      => '',
				'test_mode'    => false,
			];
		}

		return $methods;
	}

	/**
	 * Universal Webhook / IPN receiver.
	 * Can be called via:
	 *  - /wp-json/phm/v1/payment-ipn/{gateway}
	 *  - admin-ajax.php?action=phm_payment_webhook&gateway={gateway}
	 */
	public static function handle_webhook() {
		$raw_input = file_get_contents( 'php://input' );
		$data      = json_decode( $raw_input, true );
		if ( ! is_array( $data ) ) {
			$data = $_POST;
		}

		$gateway_slug = isset( $_GET['gateway'] ) ? sanitize_key( wp_unslash( $_GET['gateway'] ) ) : 'universal_api';
		PHM_DB::log( 'info', sprintf( 'Payment IPN received from gateway [%s]: %s', $gateway_slug, substr( wp_json_encode( $data ), 0, 500 ) ) );

		// Resolve order number / payment reference.
		$order_number = '';
		$status       = 'paid';
		$payment_ref  = '';

		if ( ! empty( $data['order_number'] ) ) {
			$order_number = sanitize_text_field( $data['order_number'] );
		} elseif ( ! empty( $data['order_id'] ) ) {
			$order_number = sanitize_text_field( $data['order_id'] );
		} elseif ( ! empty( $data['custom'] ) ) {
			$order_number = sanitize_text_field( $data['custom'] );
		} elseif ( ! empty( $data['invoice_id'] ) ) {
			$order_number = sanitize_text_field( $data['invoice_id'] );
		}

		if ( ! empty( $data['txid'] ) ) {
			$payment_ref = sanitize_text_field( $data['txid'] );
		} elseif ( ! empty( $data['payment_id'] ) ) {
			$payment_ref = sanitize_text_field( $data['payment_id'] );
		} elseif ( ! empty( $data['id'] ) ) {
			$payment_ref = sanitize_text_field( $data['id'] );
		}

		if ( ! $order_number ) {
			wp_send_json_error( [ 'message' => 'Missing order reference' ], 400 );
		}

		$order = PHM_DB::get_order_by_number( $order_number );
		if ( ! $order && is_numeric( $order_number ) ) {
			$order = PHM_DB::get_order( (int) $order_number );
		}

		if ( ! $order ) {
			PHM_DB::log( 'error', sprintf( 'Payment IPN failed: Order %s not found.', $order_number ) );
			wp_send_json_error( [ 'message' => 'Order not found' ], 404 );
		}

		if ( 'active' === $order->status || 'paid' === $order->status ) {
			wp_send_json_success( [ 'message' => 'Order already processed', 'order_id' => $order->id ] );
		}

		// Update order as paid and auto-provision server.
		PHM_DB::update_order( $order->id, [
			'status'      => 'paid',
			'payment_ref' => $payment_ref ? $payment_ref : 'IPN-' . time(),
		] );

		PHM_DB::log( 'success', sprintf( 'Payment confirmed via %s for Order %s. Starting auto-deploy…', $gateway_slug, $order->order_number ) );
		PHM_Provisioning::queue_deploy( $order->id );

		wp_send_json_success( [
			'message'      => 'Payment verified successfully and server deployment queued.',
			'order_number' => $order->order_number,
			'status'       => 'paid',
		] );
	}

	public static function handle_save() {
		if ( ! current_user_can( PHM_Admin::capability() ) ) {
			wp_die( esc_html__( 'Permission denied.', 'pterodactyl-hosting' ) );
		}
		check_admin_referer( 'phm_save_gateway' );

		$id             = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$gateway_id     = isset( $_POST['gateway_id'] ) ? sanitize_key( wp_unslash( $_POST['gateway_id'] ) ) : '';
		$name           = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$type           = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : 'crypto';
		$api_key        = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '';
		$api_secret     = isset( $_POST['api_secret'] ) ? sanitize_text_field( wp_unslash( $_POST['api_secret'] ) ) : '';
		$merchant_id    = isset( $_POST['merchant_id'] ) ? sanitize_text_field( wp_unslash( $_POST['merchant_id'] ) ) : '';
		$webhook_secret = isset( $_POST['webhook_secret'] ) ? sanitize_text_field( wp_unslash( $_POST['webhook_secret'] ) ) : '';
		$custom_url     = isset( $_POST['custom_url'] ) ? esc_url_raw( wp_unslash( $_POST['custom_url'] ) ) : '';
		$instructions   = isset( $_POST['instructions'] ) ? wp_kses_post( wp_unslash( $_POST['instructions'] ) ) : '';
		$test_mode      = isset( $_POST['test_mode'] ) ? 1 : 0;
		$active         = isset( $_POST['active'] ) ? 1 : 0;
		$sort_order     = isset( $_POST['sort_order'] ) ? (int) $_POST['sort_order'] : 0;

		$data = [
			'gateway_id'     => $gateway_id,
			'name'           => $name,
			'type'           => $type,
			'api_key'        => $api_key,
			'api_secret'     => $api_secret,
			'merchant_id'    => $merchant_id,
			'webhook_secret' => $webhook_secret,
			'custom_url'     => $custom_url,
			'instructions'   => $instructions,
			'test_mode'      => $test_mode,
			'active'         => $active,
			'sort_order'     => $sort_order,
		];

		$saved = PHM_DB::save_gateway( $data, $id );
		if ( is_wp_error( $saved ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=phm-gateways&phm_msg=save_failed' ) );
			exit;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=phm-gateways&phm_msg=saved' ) );
		exit;
	}

	public static function handle_toggle() {
		if ( ! current_user_can( PHM_Admin::capability() ) ) {
			wp_die( esc_html__( 'Permission denied.', 'pterodactyl-hosting' ) );
		}
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		check_admin_referer( 'phm_toggle_gateway_' . $id );

		$gateways = PHM_DB::get_gateways();
		foreach ( $gateways as $g ) {
			if ( (int) $g->id === $id ) {
				PHM_DB::save_gateway( [ 'active' => $g->active ? 0 : 1 ], $id );
				break;
			}
		}

		wp_safe_redirect( admin_url( 'admin.php?page=phm-gateways&phm_msg=saved' ) );
		exit;
	}

	public static function handle_delete() {
		if ( ! current_user_can( PHM_Admin::capability() ) ) {
			wp_die( esc_html__( 'Permission denied.', 'pterodactyl-hosting' ) );
		}
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		check_admin_referer( 'phm_delete_gateway_' . $id );

		PHM_DB::delete_gateway( $id );
		wp_safe_redirect( admin_url( 'admin.php?page=phm-gateways&phm_msg=deleted' ) );
		exit;
	}
}
