<?php
/**
 * Elementor "Support Ticket System" Widget.
 *
 * @package Pterodactyl_Hosting
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( '\Elementor\Widget_Base' ) ) :

class PHM_Widget_Ticket extends \Elementor\Widget_Base {

	public function get_name() {
		return 'phm_ticket';
	}

	public function get_title() {
		return __( 'Support Ticket Box (Pterodactyl)', 'pterodactyl-hosting' );
	}

	public function get_icon() {
		return 'eicon-comments';
	}

	public function get_categories() {
		return [ 'phm-hosting' ];
	}

	public function get_keywords() {
		return [ 'support', 'ticket', 'help', 'pterodactyl', 'chat' ];
	}

	protected function register_controls() {
		$this->start_controls_section( 'content_section', [
			'label' => __( 'Ticket View Settings', 'pterodactyl-hosting' ),
			'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
		] );

		$this->add_control( 'mode', [
			'label'   => __( 'Display Mode', 'pterodactyl-hosting' ),
			'type'    => \Elementor\Controls_Manager::SELECT,
			'default' => 'create',
			'options' => [
				'create' => __( 'Ticket Creation Form Only', 'pterodactyl-hosting' ),
				'list'   => __( 'Full Tickets Dashboard & Chat', 'pterodactyl-hosting' ),
			],
		] );

		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		if ( isset( $settings['mode'] ) && 'list' === $settings['mode'] ) {
			echo PHM_Dashboard::shortcode( [ 'tab' => 'tickets' ] );
		} else {
			echo PHM_Tickets::shortcode_create();
		}
	}
}

endif;
