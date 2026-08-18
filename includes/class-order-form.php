<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Ptero_Order_Form {

	private static $instance = null;
	public static function instance() {
		if ( self::$instance === null ) self::$instance = new self();
		return self::$instance;
	}

	private function __construct() {
		add_shortcode( 'ptero_order_form', array( $this, 'render' ) );
	}

	/**
	 * $atts: title, nest_id (restrict to one game category), min_ram, max_ram, min_cpu, max_cpu
	 */
	public function render( $atts = array() ) {
		$atts = shortcode_atts( array(
			'title'   => 'Order Your Game Server',
			'nest_id' => '',
			'min_ram' => 512,
			'max_ram' => 16384,
			'min_cpu' => 25,
			'max_cpu' => 400,
		), $atts, 'ptero_order_form' );

		wp_enqueue_style( 'ptero-host' );
		wp_enqueue_script( 'ptero-host-calculator' );

		$api = new Ptero_API();
		ob_start();

		if ( ! $api->is_configured() ) {
			echo '<p style="color:#b91c1c;">' . esc_html__( 'Hosting order form is not configured yet. Please set the Pterodactyl panel URL and API key in Ptero Hosting → Settings.', 'ptero-host' ) . '</p>';
			return ob_get_clean();
		}

		$locations = $api->get_locations();
		$nests     = $api->get_nests();

		if ( is_wp_error( $locations ) || is_wp_error( $nests ) ) {
			echo '<p style="color:#b91c1c;">' . esc_html__( 'Could not reach the hosting panel right now. Please try again shortly.', 'ptero-host' ) . '</p>';
			return ob_get_clean();
		}

		include PTEROHOST_PATH . 'templates/order-form.php';

		return ob_get_clean();
	}
}
