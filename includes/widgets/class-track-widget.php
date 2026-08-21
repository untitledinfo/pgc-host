<?php
/**
 * Elementor "Track Order" Widget.
 *
 * @package Pterodactyl_Hosting
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( '\Elementor\Widget_Base' ) ) :

class PHM_Widget_Track extends \Elementor\Widget_Base {

	public function get_name() {
		return 'phm_track';
	}

	public function get_title() {
		return __( 'Track Order', 'pterodactyl-hosting' );
	}

	public function get_icon() {
		return 'eicon-search';
	}

	public function get_categories() {
		return [ 'phm-hosting' ];
	}

	public function get_keywords() {
		return [ 'track', 'order', 'status', 'hosting', 'pterodactyl' ];
	}

	public function get_style_depends() {
		return [ 'phm-frontend' ];
	}

	public function get_script_depends() {
		return [ 'phm-frontend' ];
	}

	protected function register_controls() {
		$this->start_controls_section( 'content_section', [
			'label' => __( 'Tracking', 'pterodactyl-hosting' ),
			'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
		] );

		$this->add_control( 'notice', [
			'type' => \Elementor\Controls_Manager::RAW_HTML,
			'raw'  => __( 'Lets customers look up an order by number + email. Server IPs are never shown.', 'pterodactyl-hosting' ),
		] );

		$this->end_controls_section();

		PHM_Elementor::add_theme_style_controls( $this );
	}

	protected function render() {
		PHM_Frontend::enqueue_and_localize();
		echo PHM_Frontend::shortcode_track(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

endif;
