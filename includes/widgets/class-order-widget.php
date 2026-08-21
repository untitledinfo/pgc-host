<?php
/**
 * Elementor "Order / Checkout Cart" Widget.
 *
 * @package Pterodactyl_Hosting
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( '\Elementor\Widget_Base' ) ) :

class PHM_Widget_Order extends \Elementor\Widget_Base {

	public function get_name() {
		return 'phm_order';
	}

	public function get_title() {
		return __( 'Hosting Order & Checkout (Pterodactyl)', 'pterodactyl-hosting' );
	}

	public function get_icon() {
		return 'eicon-cart';
	}

	public function get_categories() {
		return [ 'phm-hosting' ];
	}

	public function get_keywords() {
		return [ 'hosting', 'checkout', 'cart', 'order', 'pterodactyl', 'minecraft' ];
	}

	protected function register_controls() {
		$this->start_controls_section( 'content_section', [
			'label' => __( 'Order Cart Settings', 'pterodactyl-hosting' ),
			'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
		] );

		$this->add_control( 'notice', [
			'type' => \Elementor\Controls_Manager::RAW_HTML,
			'raw'  => __( 'This widget renders the complete interactive game server checkout with subdomain selector, coupon codes, and 250+ payment gateways.', 'pterodactyl-hosting' ),
		] );

		$this->end_controls_section();
	}

	protected function render() {
		echo PHM_Frontend::shortcode_order([]);
	}
}

endif;
