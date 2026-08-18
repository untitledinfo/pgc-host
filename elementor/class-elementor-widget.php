<?php
if ( ! defined( 'ABSPATH' ) ) exit;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

class Ptero_Elementor_Order_Widget extends Widget_Base {

	public function get_name() { return 'ptero_order_form'; }
	public function get_title() { return __( 'Game Server Order Form', 'ptero-host' ); }
	public function get_icon() { return 'eicon-form-horizontal'; }
	public function get_categories() { return array( 'ptero-host' ); }
	public function get_keywords() { return array( 'pterodactyl', 'game server', 'hosting', 'order form' ); }

	protected function register_controls() {
		$this->start_controls_section( 'content_section', array(
			'label' => __( 'Content', 'ptero-host' ),
			'tab'   => Controls_Manager::TAB_CONTENT,
		) );

		$this->add_control( 'title', array(
			'label'   => __( 'Form Title', 'ptero-host' ),
			'type'    => Controls_Manager::TEXT,
			'default' => __( 'Order Your Game Server', 'ptero-host' ),
		) );

		$this->add_control( 'min_ram', array(
			'label'   => __( 'Minimum RAM (MB)', 'ptero-host' ),
			'type'    => Controls_Manager::NUMBER,
			'default' => 512,
		) );

		$this->add_control( 'max_ram', array(
			'label'   => __( 'Maximum RAM (MB)', 'ptero-host' ),
			'type'    => Controls_Manager::NUMBER,
			'default' => 16384,
		) );

		$this->add_control( 'min_cpu', array(
			'label'   => __( 'Minimum CPU (%)', 'ptero-host' ),
			'type'    => Controls_Manager::NUMBER,
			'default' => 25,
		) );

		$this->add_control( 'max_cpu', array(
			'label'   => __( 'Maximum CPU (%)', 'ptero-host' ),
			'type'    => Controls_Manager::NUMBER,
			'default' => 400,
		) );

		$this->end_controls_section();

		$this->start_controls_section( 'style_section', array(
			'label' => __( 'Style', 'ptero-host' ),
			'tab'   => Controls_Manager::TAB_STYLE,
		) );

		$this->add_control( 'accent_color', array(
			'label'     => __( 'Accent Color', 'ptero-host' ),
			'type'      => Controls_Manager::COLOR,
			'default'   => '#6c5ce7',
			'selectors' => array(
				'{{WRAPPER}} .ptero-btn' => 'background-color: {{VALUE}};',
				'{{WRAPPER}} .ptero-cost-box strong' => 'color: {{VALUE}};',
				'{{WRAPPER}} input[type=range]' => 'accent-color: {{VALUE}};',
			),
		) );

		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		echo Ptero_Order_Form::instance()->render( array(
			'title'   => $settings['title'],
			'min_ram' => $settings['min_ram'],
			'max_ram' => $settings['max_ram'],
			'min_cpu' => $settings['min_cpu'],
			'max_cpu' => $settings['max_cpu'],
		) );
	}
}
