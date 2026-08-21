<?php
/**
 * Elementor "Hosting Plans" widget.
 *
 * @package Pterodactyl_Hosting
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( '\Elementor\Widget_Base' ) ) :

class PHM_Widget_Plans extends \Elementor\Widget_Base {

	public function get_name() {
		return 'phm_plans';
	}

	public function get_title() {
		return __( 'Hosting Plans', 'pterodactyl-hosting' );
	}

	public function get_icon() {
		return 'eicon-price-table';
	}

	public function get_categories() {
		return [ 'phm-hosting' ];
	}

	public function get_keywords() {
		return [ 'hosting', 'minecraft', 'pterodactyl', 'plans', 'pricing', 'pgc' ];
	}

	public function get_style_depends() {
		return [ 'phm-frontend' ];
	}

	public function get_script_depends() {
		return [ 'phm-frontend' ];
	}

	protected function register_controls() {
		$this->start_controls_section( 'content', [
			'label' => __( 'Plans', 'pterodactyl-hosting' ),
		] );

		$this->add_control( 'columns', [
			'label'   => __( 'Columns', 'pterodactyl-hosting' ),
			'type'    => \Elementor\Controls_Manager::SELECT,
			'default' => '3',
			'options' => [
				'2' => '2',
				'3' => '3',
				'4' => '4',
			],
		] );

		$this->add_control( 'nest', [
			'label'       => __( 'Only game / nest ID', 'pterodactyl-hosting' ),
			'type'        => \Elementor\Controls_Manager::TEXT,
			'description' => __( 'Optional — nest ID from the panel to filter plans.', 'pterodactyl-hosting' ),
		] );

		$this->add_control( 'button_text', [
			'label'   => __( 'Button text', 'pterodactyl-hosting' ),
			'type'    => \Elementor\Controls_Manager::TEXT,
			'default' => __( 'Configure & Order', 'pterodactyl-hosting' ),
		] );

		$this->end_controls_section();

		PHM_Elementor::add_theme_style_controls( $this );
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		PHM_Frontend::enqueue_and_localize();
		echo PHM_Frontend::render_plans( [ // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			'columns'     => isset( $settings['columns'] ) ? (int) $settings['columns'] : 3,
			'nest'        => isset( $settings['nest'] ) ? (int) $settings['nest'] : 0,
			'button_text' => isset( $settings['button_text'] ) ? $settings['button_text'] : '',
		] );
	}
}

endif;
