<?php
/**
 * Optional WooCommerce bridge (Paymenter-style gateway): if WooCommerce is
 * active, paying through a Woo checkout completes the PHM order and the
 * server auto-deploys. Everything is guarded so the plugin works fine
 * without WooCommerce too.
 *
 * @package Pterodactyl_Hosting
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PHM_WooCommerce {

	public static function init() {
		// Defer: plugin files load before WooCommerce's class may exist,
		// so check on plugins_loaded (priority 20) instead of at file load.
		add_action( 'plugins_loaded', [ __CLASS__, 'maybe_boot' ], 20 );
	}

	public static function maybe_boot() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}
		add_action( 'woocommerce_payment_complete', [ __CLASS__, 'on_payment_complete' ] );
		add_action( 'woocommerce_order_status_completed', [ __CLASS__, 'on_payment_complete' ] );
	}

	/**
	 * Build a hidden Woo product on the fly holding the PHM order reference,
	 * then send the customer to a normal Woo checkout with all gateways.
	 */
	public static function checkout_url_for( $order ) {
		$product_id = self::hidden_product_id();
		if ( ! $product_id || ! function_exists( 'WC' ) ) {
			return '';
		}

		WC()->cart->empty_cart();
		$item = WC()->cart->add_to_cart( $product_id, 1, 0, [], [
			'phm_order_id'     => $order->id,
			'phm_order_number' => $order->order_number,
			'phm_price'        => (float) $order->amount,
			'phm_label'        => $order->plan_name . ' — ' . $order->order_number,
		] );
		if ( ! $item ) {
			return '';
		}
		// Belt-and-braces fallback for gateways that drop cart item meta
		// before the order is created — on_payment_complete() reads this if
		// the order-item meta lookup below comes up empty.
		if ( WC()->session ) {
			WC()->session->set( 'phm_order_id', (int) $order->id );
		}
		add_filter( 'woocommerce_cart_item_price', [ __CLASS__, 'override_price_display' ], 10, 3 );

		return function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : '';
	}

	/**
	 * Keep the PHM order id attached to the created Woo order, then deploy.
	 */
	public static function on_payment_complete( $wc_order_id ) {
		if ( ! function_exists( 'wc_get_order' ) ) {
			return;
		}
		$wc = wc_get_order( $wc_order_id );
		if ( ! $wc ) {
			return;
		}
		foreach ( $wc->get_items() as $item ) {
			// Stored on the cart item as 'phm_order_id' (no underscore) in
			// checkout_url_for() — WooCommerce copies custom cart item data to
			// order item meta under the SAME key, so this must match exactly or
			// the lookup always misses and the order never gets marked paid.
			$phm_id = $item->get_meta( 'phm_order_id' );
			if ( ! $phm_id ) {
				// Some gateways drop cart item meta — fall back to session meta.
				$phm_id = WC()->session ? WC()->session->get( 'phm_order_id' ) : 0;
			}
			$order = $phm_id ? PHM_DB::get_order( (int) $phm_id ) : null;
			if ( $order && 'pending' === $order->status ) {
				PHM_DB::update_order( $order->id, [
					'status'      => 'paid',
					'payment_ref' => 'WC-' . $wc_order_id,
				] );
				if ( ! empty( PHM_Settings::get()['auto_deploy_on_paid'] ) ) {
					PHM_Provisioning::deploy( $order->id );
				}
			}
		}
	}

	public static function override_price_display( $price, $item, $key ) {
		$data = $item->get_data();
		if ( is_array( $data ) && isset( $data['phm_price'] ) ) {
			return wc_price( (float) $data['phm_price'] );
		}
		return $price;
	}

	/**
	 * Reusable placeholder product used as the Woo cart line for PHM orders.
	 */
	private static function hidden_product_id() {
		$id = (int) get_option( 'phm_woo_product_id', 0 );
		if ( $id && 'product' === get_post_type( $id ) ) {
			return $id;
		}
		$id = wp_insert_post( [
			'post_type'    => 'product',
			'post_title'   => 'Game Server (PGC Hosting)',
			'post_status'  => 'publish',
			'post_content' => 'Internal product used for Pterodactyl Hosting orders.',
		] );
		if ( $id && ! is_wp_error( $id ) ) {
			update_post_meta( $id, '_price', 0 );
			update_post_meta( $id, '_regular_price', 0 );
			update_post_meta( $id, '_virtual', 'yes' );
			update_post_meta( $id, '_visibility', 'hidden' );
			update_option( 'phm_woo_product_id', $id );
			return $id;
		}
		return 0;
	}
}
