<?php
/**
 * Elementor integration loader.
 * Registers PGC Hosting widgets safely into Elementor.
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
	 * @param \Elementor\Widgets_Manager $manager
	 */
	public static function register_widgets( $manager ) {
		if ( ! class_exists( '\Elementor\Widget_Base' ) || ! method_exists( $manager, 'register' ) ) {
			return;
		}

		// Include all widgets.
		require_once PHM_PATH . 'includes/widgets/class-plans-widget.php';
		require_once PHM_PATH . 'includes/widgets/class-order-widget.php';
		require_once PHM_PATH . 'includes/widgets/class-dashboard-widget.php';
		require_once PHM_PATH . 'includes/widgets/class-ticket-widget.php';
		require_once PHM_PATH . 'includes/widgets/class-status-widget.php';

		if ( class_exists( 'PHM_Widget_Plans' ) ) {
			$manager->register( new PHM_Widget_Plans() );
		}
		if ( class_exists( 'PHM_Widget_Order' ) ) {
			$manager->register( new PHM_Widget_Order() );
		}
		if ( class_exists( 'PHM_Widget_Dashboard' ) ) {
			$manager->register( new PHM_Widget_Dashboard() );
		}
		if ( class_exists( 'PHM_Widget_Ticket' ) ) {
			$manager->register( new PHM_Widget_Ticket() );
		}
		if ( class_exists( 'PHM_Widget_Status' ) ) {
			$manager->register( new PHM_Widget_Status() );
		}
	}
}
