<?php
/**
 * Elementor "Hosting Plans" widget.
 *
 * IMPORTANT: this file is only ever included from PHM_Elementor::register_widgets()
 * which already verified Elementor is loaded — but we still wrap the class in a
 * class_exists() guard so the file is 100% fatal-proof on its own. This is the
 * permanent fix for:
 *   Class "Elementor\Widget_Base" not found (class-elementor-widget.php:7)
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
		return __( 'Hosting Plans (Pterodactyl)', 'pterodactyl-hosting' );
	}

	public function get_icon() {
		return 'eicon-price-table';
	}

	public function get_categories() {
		return [ 'phm-hosting' ];
	}

	public function get_keywords() {
		return [ 'hosting', 'minecraft', 'pterodactyl', 'plans', 'pricing' ];
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
			'label'       => __( 'Only game / nest', 'pterodactyl-hosting' ),
			'type'        => \Elementor\Controls_Manager::TEXT,
			'description' => __( 'Optional — nest ID from panel to filter plans.', 'pterodactyl-hosting' ),
		] );

		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		echo PHM_Frontend::render_plans([
			'columns' => isset( $settings['columns'] ) ? (int) $settings['columns'] : 3,
			'nest'    => isset( $settings['nest'] ) ? (int) $settings['nest'] : 0,
		]);
	}
}

endif; // class_exists( '\Elementor\Widget_Base' )
