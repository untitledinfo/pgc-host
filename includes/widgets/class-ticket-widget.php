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
		return __( 'Support Tickets', 'pterodactyl-hosting' );
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

	public function get_style_depends() {
		return [ 'phm-frontend' ];
	}

	public function get_script_depends() {
		return [ 'phm-frontend' ];
	}

	protected function register_controls() {
		$this->start_controls_section( 'content_section', [
			'label' => __( 'Tickets', 'pterodactyl-hosting' ),
			'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
		] );

		$this->add_control( 'mode', [
			'label'   => __( 'Display Mode', 'pterodactyl-hosting' ),
			'type'    => \Elementor\Controls_Manager::SELECT,
			'default' => 'create',
			'options' => [
				'create' => __( 'Ticket creation form', 'pterodactyl-hosting' ),
				'list'   => __( 'Full tickets dashboard', 'pterodactyl-hosting' ),
			],
		] );

		$this->end_controls_section();

		PHM_Elementor::add_theme_style_controls( $this );
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		PHM_Frontend::enqueue_and_localize();
		if ( isset( $settings['mode'] ) && 'list' === $settings['mode'] ) {
			echo PHM_Dashboard::shortcode( [ 'tab' => 'tickets' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} else {
			echo PHM_Tickets::shortcode_create(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}
}

endif;
