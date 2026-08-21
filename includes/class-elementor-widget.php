<?php
/**
 * Elementor integration loader.
 * Registers PGC Hosting widgets safely into Elementor and exposes shared
 * Theme Builder style controls so widgets inherit global colors/fonts.
 *
 * @package Pterodactyl_Hosting
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PHM_Elementor {

	public static function init() {
		if ( ! did_action( 'elementor/loaded' ) ) {
			add_action( 'elementor/loaded', [ __CLASS__, 'register_hooks' ] );
			return;
		}
		self::register_hooks();
	}

	public static function register_hooks() {
		add_action( 'elementor/widgets/register', [ __CLASS__, 'register_widgets' ] );
		add_action( 'elementor/elements/categories_registered', [ __CLASS__, 'register_category' ] );
		add_action( 'elementor/frontend/after_enqueue_styles', [ 'PHM_Frontend', 'enqueue_and_localize' ] );
		add_action( 'elementor/preview/enqueue_styles', [ 'PHM_Frontend', 'enqueue_and_localize' ] );
		add_action( 'elementor/editor/after_enqueue_styles', [ 'PHM_Frontend', 'enqueue_and_localize' ] );
	}

	/**
	 * @param \Elementor\Elements_Manager $elements_manager
	 */
	public static function register_category( $elements_manager ) {
		if ( ! method_exists( $elements_manager, 'add_category' ) ) {
			return;
		}
		$elements_manager->add_category( 'phm-hosting', [
			'title' => __( 'PGC Hosting', 'pterodactyl-hosting' ),
			'icon'  => 'fa fa-server',
		] );
	}

	/**
	 * Shared Theme Builder style controls: primary/text/card colors map to
	 * CSS custom properties so the storefront matches the active theme kit.
	 *
	 * @param \Elementor\Widget_Base $widget
	 */
	public static function add_theme_style_controls( $widget ) {
		if ( ! class_exists( '\Elementor\Controls_Manager' ) ) {
			return;
		}

		$widget->start_controls_section( 'phm_theme_style', [
			'label' => __( 'Theme & Colors', 'pterodactyl-hosting' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		] );

		$primary_args = [
			'label'     => __( 'Primary Color', 'pterodactyl-hosting' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => [
				'{{WRAPPER}}' => '--phm-primary: {{VALUE}}; --phm-primary-dark: {{VALUE}}; --phm-border-active: {{VALUE}};',
			],
		];
		$text_args = [
			'label'     => __( 'Text Color', 'pterodactyl-hosting' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => [
				'{{WRAPPER}}' => '--phm-text: {{VALUE}};',
			],
		];
		$card_args = [
			'label'     => __( 'Card Background', 'pterodactyl-hosting' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => [
				'{{WRAPPER}}' => '--phm-bg-card: {{VALUE}}; --phm-bg-alt: {{VALUE}};',
			],
		];
		$border_args = [
			'label'     => __( 'Border Color', 'pterodactyl-hosting' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => [
				'{{WRAPPER}}' => '--phm-border: {{VALUE}};',
			],
		];

		if ( class_exists( '\Elementor\Core\Kits\Documents\Tabs\Global_Colors' ) ) {
			$primary_args['global'] = [ 'default' => \Elementor\Core\Kits\Documents\Tabs\Global_Colors::COLOR_PRIMARY ];
			$text_args['global']    = [ 'default' => \Elementor\Core\Kits\Documents\Tabs\Global_Colors::COLOR_TEXT ];
		}

		$widget->add_control( 'phm_primary_color', $primary_args );
		$widget->add_control( 'phm_text_color', $text_args );
		$widget->add_control( 'phm_card_bg', $card_args );
		$widget->add_control( 'phm_border_color', $border_args );

		$widget->add_control( 'phm_radius', [
			'label'      => __( 'Corner Radius', 'pterodactyl-hosting' ),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => [ 'px' ],
			'range'      => [ 'px' => [ 'min' => 0, 'max' => 32 ] ],
			'selectors'  => [
				'{{WRAPPER}}' => '--phm-radius: {{SIZE}}{{UNIT}};',
			],
		] );

		$widget->end_controls_section();

		if ( class_exists( '\Elementor\Group_Control_Typography' ) ) {
			$widget->start_controls_section( 'phm_type_style', [
				'label' => __( 'Typography', 'pterodactyl-hosting' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			] );
			$widget->add_group_control(
				\Elementor\Group_Control_Typography::get_type(),
				[
					'name'     => 'phm_typography',
					'selector' => '{{WRAPPER}} .phm-store, {{WRAPPER}} .phm-checkout-wrap, {{WRAPPER}} .phm-dashboard-wrap, {{WRAPPER}} .phm-track-wrap, {{WRAPPER}} .phm-status-widget-wrap, {{WRAPPER}} .phm-ticket-create-wrap',
				]
			);
			$widget->end_controls_section();
		}

		$widget->start_controls_section( 'phm_button_style', [
			'label' => __( 'Buttons', 'pterodactyl-hosting' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		] );
		$widget->add_control( 'phm_btn_color', [
			'label'     => __( 'Button Text', 'pterodactyl-hosting' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => [
				'{{WRAPPER}} .phm-btn-primary' => 'color: {{VALUE}} !important;',
			],
		] );
		$widget->add_control( 'phm_btn_bg', [
			'label'     => __( 'Button Background', 'pterodactyl-hosting' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => [
				'{{WRAPPER}} .phm-btn-primary' => 'background: {{VALUE}};',
			],
		] );
		$widget->end_controls_section();
	}

	/**
	 * @param \Elementor\Widgets_Manager $manager
	 */
	public static function register_widgets( $manager ) {
		if ( ! class_exists( '\Elementor\Widget_Base' ) || ! method_exists( $manager, 'register' ) ) {
			return;
		}

		require_once PHM_PATH . 'includes/widgets/class-plans-widget.php';
		require_once PHM_PATH . 'includes/widgets/class-order-widget.php';
		require_once PHM_PATH . 'includes/widgets/class-dashboard-widget.php';
		require_once PHM_PATH . 'includes/widgets/class-ticket-widget.php';
		require_once PHM_PATH . 'includes/widgets/class-status-widget.php';
		require_once PHM_PATH . 'includes/widgets/class-track-widget.php';
		require_once PHM_PATH . 'includes/widgets/class-login-widget.php';

		foreach ( [
			'PHM_Widget_Plans',
			'PHM_Widget_Order',
			'PHM_Widget_Dashboard',
			'PHM_Widget_Ticket',
			'PHM_Widget_Status',
			'PHM_Widget_Track',
			'PHM_Widget_Login',
		] as $class ) {
			if ( class_exists( $class ) ) {
				$manager->register( new $class() );
			}
		}
	}
}
