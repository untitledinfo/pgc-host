<?php
/**
 * Main plugin orchestrator.
 *
 * @package Pterodactyl_Hosting
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PHM_Plugin {

	/**
	 * @var bool
	 */
	private static $booted = false;

	public static function boot() {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;

		// Upgrade routine when the stored version differs.
		add_action( 'plugins_loaded', [ __CLASS__, 'maybe_upgrade' ], 5 );

		// Core services.
		PHM_Settings::init();
		PHM_Ajax::init();
		PHM_Plans::init();
		PHM_Orders::init();
		PHM_Frontend::init();
		PHM_Cronjobs::init();
		PHM_WooCommerce::init();
		PHM_Password_Bridge::init();
		PHM_Password_Sync::init();
		PHM_Cookie_Login::init();
		PHM_Provisioning::init();
		PHM_Tickets::init();
		PHM_Dashboard::init();
		PHM_Coupons::init();
		PHM_Gateways::init();
		PHM_API_Endpoints::init();

		// Elementor integration — safe loader, only hooks into Elementor when
		// Elementor is actually present. Fixes:
		//   "maybe_load_elementor: Class Elementor\Widget_Base not found"
		PHM_Elementor::init();

		if ( is_admin() ) {
			PHM_Admin::init();
		}
	}

	/**
	 * Activation: create tables, seed defaults, create storefront pages,
	 * schedule the auto-sync + billing crons.
	 */
	public static function activate() {
		PHM_DB::install();
		PHM_Settings::seed_defaults();
		PHM_Cronjobs::schedule();
		PHM_Store::ensure_pages();
		flush_rewrite_rules();
	}

	public static function deactivate() {
		PHM_Cronjobs::unschedule();
		flush_rewrite_rules();
	}

	public static function maybe_upgrade() {
		$installed = get_option( 'phm_version', '0' );

		// Version number told the truth no longer: after the original
		// "Table 'wp_phm_products' doesn't exist" incident the version option
		// could be stored while tables were missing. Verify table EXISTENCE
		// (cached for 5 minutes to avoid 7 SHOW TABLES per request).
		$repair_needed = false;
		if ( is_admin() && false === get_transient( 'phm_tables_ok' ) ) {
			$missing = PHM_DB::tables_exist();
			if ( $missing ) {
				$repair_needed = true;
			} else {
				set_transient( 'phm_tables_ok', 1, 5 * MINUTE_IN_SECONDS );
			}
		}

		if ( $repair_needed || version_compare( $installed, PHM_VERSION, '<' ) ) {
			PHM_DB::install(); // logs + verifies internally.
			PHM_Cronjobs::schedule();
			update_option( 'phm_version', PHM_VERSION );
		}
	}
}
