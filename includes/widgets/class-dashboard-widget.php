<?php
/**
 * Elementor "User Server Dashboard" Widget.
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
		return __( 'Client Dashboard & Servers (Pterodactyl)', 'pterodactyl-hosting' );
	}

	public function get_icon() {
		return 'eicon-dashboard';
	}

	public function get_categories() {
		return [ 'phm-hosting' ];
	}

	public function get_keywords() {
		return [ 'dashboard', 'servers', 'pterodactyl', 'client', 'tickets' ];
	}

	protected function register_controls() {
		$this->start_controls_section( 'content_section', [
			'label' => __( 'Dashboard Settings', 'pterodactyl-hosting' ),
			'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
		] );

		$this->add_control( 'default_tab', [
			'label'   => __( 'Default Tab', 'pterodactyl-hosting' ),
			'type'    => \Elementor\Controls_Manager::SELECT,
			'default' => 'servers',
			'options' => [
				'servers' => __( 'My Servers', 'pterodactyl-hosting' ),
				'tickets' => __( 'Support Tickets', 'pterodactyl-hosting' ),
			],
		] );

		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		echo PHM_Dashboard::shortcode( [ 'tab' => isset( $settings['default_tab'] ) ? $settings['default_tab'] : 'servers' ] );
	}
}

endif;
