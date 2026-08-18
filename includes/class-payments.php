<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Ptero_Payments {

	private static $instance = null;
	public static function instance() {
		if ( self::$instance === null ) self::$instance = new self();
		return self::$instance;
	}

	private function __construct() {
		add_action( 'woocommerce_order_status_completed', array( $this, 'on_wc_order_paid' ) );
		add_action( 'woocommerce_order_status_processing', array( $this, 'on_wc_order_paid' ) );
	}

	/**
	 * Creates a hidden WooCommerce product on the fly for this order's price and
	 * returns a checkout URL. Requires WooCommerce active.
	 */
	public function create_woocommerce_order( $ptero_order_id, $price ) {
		if ( ! class_exists( 'WC_Product_Simple' ) ) {
			return '';
		}

		$product = new WC_Product_Simple();
		$product->set_name( 'Game Server Order #' . $ptero_order_id );
		$product->set_regular_price( $price );
		$product->set_catalog_visibility( 'hidden' );
		$product->set_virtual( true );
		$product->save();

		update_post_meta( $product->get_id(), '_ptero_order_id', $ptero_order_id );

		return add_query_arg( array(
			'add-to-cart' => $product->get_id(),
			'quantity'    => 1,
		), wc_get_checkout_url() );
	}

	public function on_wc_order_paid( $wc_order_id ) {
		$order = wc_get_order( $wc_order_id );
		if ( ! $order ) return;

		foreach ( $order->get_items() as $item ) {
			$product_id = $item->get_product_id();
			$ptero_order_id = get_post_meta( $product_id, '_ptero_order_id', true );
			if ( $ptero_order_id ) {
				Ptero_Order_Handler::instance()->provision( intval( $ptero_order_id ) );
			}
		}
	}
}
