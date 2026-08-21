<?php
/**
 * Storefront: shortcodes [phm_plans], [phm_order], [phm_track], [phm_ticket_create], [phm_tickets] and assets.
 *
 * @package Pterodactyl_Hosting
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PHM_Frontend {

	public static function init() {
		add_shortcode( 'phm_plans', [ __CLASS__, 'shortcode_plans' ] );
		add_shortcode( 'phm_order', [ __CLASS__, 'shortcode_order' ] );
		add_shortcode( 'phm_track', [ __CLASS__, 'shortcode_track' ] );
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'register_assets' ] );
		add_action( 'elementor/frontend/after_register_styles', [ __CLASS__, 'register_assets' ] );
		add_action( 'elementor/frontend/after_enqueue_scripts', [ __CLASS__, 'enqueue_and_localize' ] );
		add_action( 'elementor/editor/after_enqueue_styles', [ __CLASS__, 'enqueue_and_localize' ] );
		add_action( 'elementor/preview/enqueue_styles', [ __CLASS__, 'enqueue_and_localize' ] );
	}

	/**
	 * Customer-facing connect address. Never exposes the raw node IP —
	 * hostname/subdomain only. Empty string means "connect via the panel".
	 */
	public static function public_address( $order ) {
		if ( ! empty( $order->fqdn ) ) {
			return (string) $order->fqdn;
		}
		return '';
	}

	public static function register_assets() {
		wp_register_style( 'phm-frontend', PHM_URL . 'assets/frontend.css', [], PHM_VERSION );
		wp_register_script( 'phm-frontend', PHM_URL . 'assets/frontend.js', [], PHM_VERSION, true );

		global $post;
		$should_enqueue = false;

		if ( isset( $_GET['plan'] ) || isset( $_GET['phm_tab'] ) || isset( $_GET['phm_ticket'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$should_enqueue = true;
		}

		if ( $post instanceof WP_Post ) {
			if (
				has_shortcode( $post->post_content, 'phm_plans' ) ||
				has_shortcode( $post->post_content, 'phm_order' ) ||
				has_shortcode( $post->post_content, 'phm_track' ) ||
				has_shortcode( $post->post_content, 'phm_dashboard' ) ||
				has_shortcode( $post->post_content, 'phm_ticket_create' ) ||
				has_shortcode( $post->post_content, 'phm_tickets' )
			) {
				$should_enqueue = true;
			}
			// Check Elementor metadata.
			$elementor_data = get_post_meta( $post->ID, '_elementor_data', true );
			if ( is_string( $elementor_data ) && false !== strpos( $elementor_data, 'phm_' ) ) {
				$should_enqueue = true;
			}
		}

		if ( $should_enqueue ) {
			self::enqueue_and_localize();
		}
	}

	public static function enqueue_and_localize() {
		if ( ! wp_style_is( 'phm-frontend', 'registered' ) ) {
			wp_register_style( 'phm-frontend', PHM_URL . 'assets/frontend.css', [], PHM_VERSION );
		}
		if ( ! wp_script_is( 'phm-frontend', 'registered' ) ) {
			wp_register_script( 'phm-frontend', PHM_URL . 'assets/frontend.js', [], PHM_VERSION, true );
		}
		wp_enqueue_style( 'phm-frontend' );
		wp_enqueue_script( 'phm-frontend' );

		$eggs_by_nest = [];
		foreach ( PHM_DB::get_eggs() as $egg ) {
			$eggs_by_nest[ (int) $egg->nest_id ][] = [
				'id'          => (int) $egg->egg_id,
				'name'        => $egg->name,
				'description' => wp_strip_all_tags( (string) $egg->description ),
			];
		}
		$locations = [];
		foreach ( PHM_DB::get_locations() as $loc ) {
			$locations[ (int) $loc->location_id ] = $loc->short;
		}

		wp_localize_script( 'phm-frontend', 'PHM_PUBLIC', [
			'ajax'              => admin_url( 'admin-ajax.php' ),
			'nonce'             => wp_create_nonce( 'phm_public' ),
			'isLoggedIn'        => is_user_logged_in() ? 1 : 0,
			'eggsByNest'        => $eggs_by_nest,
			'locations'         => $locations,
			'baseDomain'        => PHM_Cloudflare::enabled() ? PHM_Cloudflare::base_domain() : '',
			'subdomainOn'       => PHM_Cloudflare::enabled() ? 1 : 0,
			'subdomainRequired' => (int) PHM_Settings::get()['cf_subdomain_required'],
			'i18n'              => [
				'checking'          => __( 'Checking availability…', 'pterodactyl-hosting' ),
				'available'         => __( 'available!', 'pterodactyl-hosting' ),
				'taken'             => __( 'already taken', 'pterodactyl-hosting' ),
				'invalid'           => __( '3–32 chars, letters/numbers/dashes', 'pterodactyl-hosting' ),
				'error'             => __( 'Something went wrong. Please try again.', 'pterodactyl-hosting' ),
				'choosePlan'        => __( 'Please choose a plan.', 'pterodactyl-hosting' ),
				'getFree'           => __( 'Deploy Free Server', 'pterodactyl-hosting' ),
				'nameServer'        => __( 'Please name your server.', 'pterodactyl-hosting' ),
				'deployTitle'       => __( 'Deploying your game server…', 'pterodactyl-hosting' ),
				'deploySuccess'     => __( 'Server Successfully Deployed! 🎉', 'pterodactyl-hosting' ),
				'deployFailedTitle' => __( 'Deployment failed', 'pterodactyl-hosting' ),
				'deployFailedHint'  => __( 'Our team has been notified. You can also open a support ticket with your order number below.', 'pterodactyl-hosting' ),
				'openPanel'         => __( 'Open Game Panel →', 'pterodactyl-hosting' ),
				'copy'              => __( 'Copy', 'pterodactyl-hosting' ),
				'copied'            => __( 'Copied! ✓', 'pterodactyl-hosting' ),
				'applyingCoupon'    => __( 'Applying coupon…', 'pterodactyl-hosting' ),
				'connectViaPanel'   => __( 'Connect through the Game Panel — the server address is private.', 'pterodactyl-hosting' ),
			],
		] );
	}

	public static function no_cache() {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		if ( ! defined( 'DONOTCACHEOBJECT' ) ) {
			define( 'DONOTCACHEOBJECT', true );
		}
		if ( function_exists( 'nocache_headers' ) ) {
			nocache_headers();
		}
	}

	public static function shortcode_plans( $atts ) {
		$atts = shortcode_atts( [ 'columns' => 3, 'nest' => 0 ], $atts, 'phm_plans' );
		return self::render_plans( $atts );
	}

	public static function render_plans( $args = [] ) {
		self::no_cache();
		self::enqueue_and_localize();
		$args = wp_parse_args( $args, [ 'columns' => 3, 'nest' => 0, 'button_text' => '' ] );

		$products  = array_values( array_filter( PHM_DB::get_products( true ), function ( $p ) use ( $args ) {
			return ! $args['nest'] || (int) $p->nest_id === (int) $args['nest'];
		} ) );
		$nests     = PHM_DB::get_nests();
		$locations = [];
		foreach ( PHM_DB::get_locations() as $loc ) {
			$locations[ (int) $loc->location_id ] = $loc;
		}

		ob_start();
		require PHM_PATH . 'templates/plans.php';
		return ob_get_clean();
	}

	public static function shortcode_order( $atts ) {
		self::no_cache();
		self::enqueue_and_localize();

		$product = null;
		if ( ! empty( $_GET['plan'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$product = PHM_DB::get_product( (int) $_GET['plan'] );
		}
		$products = PHM_DB::get_products( true );
		$nests    = PHM_DB::get_nests();
		$methods  = PHM_Gateways::get_active_methods();
		$wp_user  = wp_get_current_user();

		ob_start();
		require PHM_PATH . 'templates/order.php';
		return ob_get_clean();
	}

	public static function shortcode_track() {
		self::enqueue_and_localize();
		$order = null;
		$error = '';
		if ( isset( $_POST['phm_track_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['phm_track_nonce'] ), 'phm_track' ) ) {
			$number = isset( $_POST['order_number'] ) ? sanitize_text_field( wp_unslash( $_POST['order_number'] ) ) : '';
			$email  = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
			$order  = $number && $email ? PHM_DB::get_order_by_number( $number, $email ) : null;
			if ( ! $order ) {
				$error = __( 'No order found with that number + email.', 'pterodactyl-hosting' );
			}
		}
		ob_start();
		require PHM_PATH . 'templates/track.php';
		return ob_get_clean();
	}
}
