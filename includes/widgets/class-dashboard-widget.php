<?php
/**
 * Elementor "Client Dashboard" Widget.
 *
 * @package Pterodactyl_Hosting
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( '\Elementor\Widget_Base' ) ) :

class PHM_Widget_Dashboard extends \Elementor\Widget_Base {

	public function get_name() {
		return 'phm_dashboard';
	}

	public function get_title() {
		return __( 'Client Dashboard', 'pterodactyl-hosting' );
	}

	public function get_icon() {
		return 'eicon-dashboard';
	}

	public function get_categories() {
		return [ 'phm-hosting' ];
	}

	public function get_keywords() {
		return [ 'dashboard', 'servers', 'pterodactyl', 'client', 'tickets', 'billing', 'hosting' ];
	}

	public function get_style_depends() {
		return [ 'phm-frontend' ];
	}

	public function get_script_depends() {
		return [ 'phm-frontend' ];
	}

	protected function register_controls() {
		$this->start_controls_section( 'content_section', [
			'label' => __( 'Dashboard', 'pterodactyl-hosting' ),
			'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
		] );

		$this->add_control( 'default_tab', [
			'label'   => __( 'Default Tab', 'pterodactyl-hosting' ),
			'type'    => \Elementor\Controls_Manager::SELECT,
			'default' => 'servers',
			'options' => [
				'servers' => __( 'My Services', 'pterodactyl-hosting' ),
				'billing' => __( 'Billing', 'pterodactyl-hosting' ),
				'tickets' => __( 'Support Tickets', 'pterodactyl-hosting' ),
			],
		] );

		$this->end_controls_section();

		PHM_Elementor::add_theme_style_controls( $this );
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		PHM_Frontend::enqueue_and_localize();
		echo PHM_Dashboard::shortcode( [ // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			'tab' => isset( $settings['default_tab'] ) ? $settings['default_tab'] : 'servers',
		] );
	}
}

endif;
