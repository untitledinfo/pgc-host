<?php
/**
 * Elementor "Open Game Panel" Widget.
 *
 * @package Pterodactyl_Hosting
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( '\Elementor\Widget_Base' ) ) :

class PHM_Widget_Login extends \Elementor\Widget_Base {

	public function get_name() {
		return 'phm_panel_login';
	}

	public function get_title() {
		return __( 'Open Game Panel', 'pterodactyl-hosting' );
	}

	public function get_icon() {
		return 'eicon-lock-user';
	}

	public function get_categories() {
		return [ 'phm-hosting' ];
	}

	public function get_keywords() {
		return [ 'panel', 'login', 'pterodactyl', 'sso', 'console' ];
	}

	public function get_style_depends() {
		return [ 'phm-frontend' ];
	}

	protected function register_controls() {
		$this->start_controls_section( 'content_section', [
			'label' => __( 'Button', 'pterodactyl-hosting' ),
			'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
		] );

		$this->add_control( 'label', [
			'label'   => __( 'Button label', 'pterodactyl-hosting' ),
			'type'    => \Elementor\Controls_Manager::TEXT,
			'default' => __( 'Open Game Panel', 'pterodactyl-hosting' ),
		] );

		$this->end_controls_section();

		PHM_Elementor::add_theme_style_controls( $this );
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		PHM_Frontend::enqueue_and_localize();
		$url   = PHM_Cookie_Login::url_for_current_user();
		$label = ! empty( $settings['label'] ) ? $settings['label'] : __( 'Open Game Panel', 'pterodactyl-hosting' );
		if ( ! $url ) {
			$editing = class_exists( '\Elementor\Plugin' )
				&& isset( \Elementor\Plugin::$instance->editor )
				&& method_exists( \Elementor\Plugin::$instance->editor, 'is_edit_mode' )
				&& \Elementor\Plugin::$instance->editor->is_edit_mode();
			if ( $editing ) {
				echo '<a class="phm-btn phm-btn-primary" href="#">' . esc_html( $label ) . '</a>';
			}
			return;
		}
		echo '<a class="phm-btn phm-btn-primary" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
	}
}

endif;
