<?php
/**
 * Plugin Name: Pterodactyl Hosting Manager
 * Plugin URI:  https://pgcmc.fun
 * Description: Sell and manage Pterodactyl-powered game servers straight from WordPress. Order form (with Elementor widget), live location/RAM/CPU/IP + cost calculator, WooCommerce/manual payment, client dashboard, auto provisioning, auto suspend/renew, and more.
 * Version:     1.1.0
 * Author:      Firepdx / PGC
 * Text Domain: ptero-host
 * Requires PHP: 7.4
 * Tested up to PHP: 8.5
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Guard against the plugin folder being included twice (e.g. duplicated
// during a manual FTP upload) — re-declaring the classes below would
// otherwise throw a fatal "Cannot redeclare class" error.
if ( defined( 'PTEROHOST_VERSION' ) ) {
	return;
}

define( 'PTEROHOST_VERSION', '1.1.0' );
define( 'PTEROHOST_PATH', plugin_dir_path( __FILE__ ) );
define( 'PTEROHOST_URL', plugin_dir_url( __FILE__ ) );

// ---- Minimum requirements -------------------------------------------------
// Fail gracefully with an admin notice instead of a fatal white-screen error
// if the host is running an old PHP version.
if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
	add_action( 'admin_notices', function () {
		echo '<div class="notice notice-error"><p>';
		echo esc_html__( 'Pterodactyl Hosting Manager requires PHP 7.4 or higher. Your server is running PHP ' . PHP_VERSION . '. Please ask your host to upgrade PHP, then reactivate the plugin.', 'ptero-host' );
		echo '</p></div>';
	} );
	return;
}

// ---- Core includes -------------------------------------------------------
// Loaded defensively: if a file is missing (e.g. an incomplete upload), show
// an admin notice instead of a fatal "failed to open stream" error.
$ptero_host_includes = array(
	'includes/class-ptero-api.php',
	'includes/class-admin-settings.php',
	'includes/class-pricing.php',
	'includes/class-order-handler.php',
	'includes/class-order-form.php',
	'includes/class-client-dashboard.php',
	'includes/class-cron.php',
	'includes/class-payments.php',
	'includes/class-notifications.php',
	'includes/class-rest-api.php',
	'includes/class-coupons.php',
	'includes/class-affiliates.php',
	'includes/class-db-installer.php',
	'includes/class-client-auth.php',
	'includes/class-plans.php',
	'includes/class-cart.php',
	'includes/class-billing.php',
	'includes/class-tickets.php',
	'includes/class-rest-api-v2.php',
);

$ptero_host_missing = array();
foreach ( $ptero_host_includes as $ptero_host_file ) {
	$ptero_host_full_path = PTEROHOST_PATH . $ptero_host_file;
	if ( file_exists( $ptero_host_full_path ) ) {
		require_once $ptero_host_full_path;
	} else {
		$ptero_host_missing[] = $ptero_host_file;
	}
}
unset( $ptero_host_file, $ptero_host_full_path );

if ( ! empty( $ptero_host_missing ) ) {
	add_action( 'admin_notices', function () use ( $ptero_host_missing ) {
		echo '<div class="notice notice-error"><p>';
		echo esc_html__( 'Pterodactyl Hosting Manager could not load the following files (the upload may be incomplete — try deleting the plugin folder and re-uploading the zip):', 'ptero-host' );
		echo '<br><code>' . esc_html( implode( ', ', $ptero_host_missing ) ) . '</code>';
		echo '</p></div>';
	} );
	return;
}

final class PteroHost_Plugin {

	private static $instance = null;

	public static function instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

		add_action( 'init', array( $this, 'init' ) );
		// Hook directly onto Elementor's own 'elementor/loaded' action rather
		// than guessing at plugins_loaded priority ordering against Elementor's
		// callback — this guarantees Widget_Base etc. are already defined
		// whenever our callback runs, and simply never fires if Elementor
		// isn't active.
		add_action( 'elementor/loaded', array( $this, 'maybe_load_elementor' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function init() {
		try {
			load_plugin_textdomain( 'ptero-host', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

			Ptero_Admin_Settings::instance();
			Ptero_Order_Handler::instance();
			Ptero_Order_Form::instance();
			Ptero_Client_Dashboard::instance();
			Ptero_Cron::instance();
			Ptero_Payments::instance();
			Ptero_Notifications::instance();
			Ptero_REST_API::instance();
			Ptero_Coupons::instance();
			Ptero_Affiliates::instance();

			Ptero_DB_Installer::maybe_upgrade();
			Ptero_Client_Auth::instance();
			Ptero_Plans::instance();
			Ptero_Cart::instance();
			Ptero_Billing::instance();
			Ptero_Tickets::instance();
			Ptero_REST_API_V2::instance();

			add_shortcode( 'ptero_blog_posts', array( $this, 'render_blog_posts_shortcode' ) );
			add_action( 'admin_init', array( $this, 'register_billing_settings' ) );
		} catch ( \Throwable $e ) {
			$this->report_error( $e, 'init' );
		}
	}

	public function maybe_load_elementor() {
		try {
			if ( class_exists( '\Elementor\Widget_Base' ) && file_exists( PTEROHOST_PATH . 'elementor/class-elementor-widget.php' ) ) {
				require_once PTEROHOST_PATH . 'elementor/class-elementor-widget.php';
				if ( file_exists( PTEROHOST_PATH . 'elementor/class-elementor-billing-widgets.php' ) ) {
					require_once PTEROHOST_PATH . 'elementor/class-elementor-billing-widgets.php';
				}
				add_action( 'elementor/widgets/register', function ( $widgets_manager ) {
					try {
						if ( class_exists( '\Ptero_Elementor_Order_Widget' ) ) {
							$widgets_manager->register( new \Ptero_Elementor_Order_Widget() );
						}
						foreach ( array( 'Ptero_Elementor_Plans_Widget', 'Ptero_Elementor_Pricing_Table_Widget', 'Ptero_Elementor_Ticket_Widget', 'Ptero_Elementor_Blog_Widget' ) as $widget_class ) {
							if ( class_exists( $widget_class ) ) {
								$widgets_manager->register( new $widget_class() );
							}
						}
					} catch ( \Throwable $e ) {
						$this->report_error( $e, 'elementor widget registration' );
					}
				} );
				add_action( 'elementor/elements/categories_registered', function ( $elements_manager ) {
					try {
						$elements_manager->add_category( 'ptero-host', array(
							'title' => __( 'Pterodactyl Hosting', 'ptero-host' ),
							'icon'  => 'fa fa-server',
						) );
					} catch ( \Throwable $e ) {
						$this->report_error( $e, 'elementor category registration' );
					}
				} );
			}
		} catch ( \Throwable $e ) {
			$this->report_error( $e, 'maybe_load_elementor' );
		}
	}

	public function enqueue_assets() {
		try {
			wp_register_style( 'ptero-host', PTEROHOST_URL . 'assets/css/style.css', array(), PTEROHOST_VERSION );
			wp_register_style( 'ptero-host-client', PTEROHOST_URL . 'assets/css/client.css', array(), PTEROHOST_VERSION );
			wp_register_script( 'ptero-host-calculator', PTEROHOST_URL . 'assets/js/calculator.js', array( 'jquery' ), PTEROHOST_VERSION, true );
			wp_register_script( 'ptero-host-dashboard', PTEROHOST_URL . 'assets/js/dashboard.js', array( 'jquery' ), PTEROHOST_VERSION, true );
			wp_register_script( 'ptero-host-client', PTEROHOST_URL . 'assets/js/client.js', array( 'jquery' ), PTEROHOST_VERSION, true );

			$localize_args = array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'ptero_host_nonce' ),
				'currency' => get_option( 'ptero_currency', 'PKR' ),
			);
			wp_localize_script( 'ptero-host-calculator', 'PteroHost', $localize_args );
			wp_localize_script( 'ptero-host-client', 'PteroHost', $localize_args );

			// Billing/plans/cart/tickets shortcodes and Elementor widgets all rely on
			// the PteroHost JS object + shared styles, so load them site-wide on the
			// front end (small footprint) rather than trying to detect every shortcode.
			if ( ! is_admin() ) {
				wp_enqueue_style( 'ptero-host-client' );
				wp_enqueue_script( 'ptero-host-client' );
			}
		} catch ( \Throwable $e ) {
			$this->report_error( $e, 'enqueue_assets' );
		}
	}

	public function register_billing_settings() {
		register_setting( 'ptero_host_settings', 'ptero_sync_wp_user' );
		register_setting( 'ptero_host_settings', 'ptero_auto_show_locations' );
		register_setting( 'ptero_host_settings', 'ptero_dashboard_page_url' );
		register_setting( 'ptero_host_settings', 'ptero_checkout_page_url' );
		register_setting( 'ptero_host_settings', 'ptero_invoice_page_url' );
	}

	public function render_blog_posts_shortcode( $atts ) {
		$atts = shortcode_atts( array( 'count' => 3, 'columns' => 3 ), $atts );
		$q = new WP_Query( array( 'post_type' => 'post', 'posts_per_page' => (int) $atts['count'], 'post_status' => 'publish' ) );
		ob_start();
		echo '<div class="ptero-blog-grid" style="--ptero-cols: ' . (int) $atts['columns'] . ';">';
		while ( $q->have_posts() ) {
			$q->the_post();
			echo '<a class="ptero-blog-card" href="' . esc_url( get_permalink() ) . '">';
			if ( has_post_thumbnail() ) echo '<div class="ptero-blog-thumb">' . get_the_post_thumbnail( null, 'medium' ) . '</div>';
			echo '<h4>' . esc_html( get_the_title() ) . '</h4>';
			echo '<p>' . esc_html( wp_trim_words( get_the_excerpt(), 20 ) ) . '</p></a>';
		}
		wp_reset_postdata();
		echo '</div>';
		return ob_get_clean();
	}

	/**
	 * Last-resort safety net: log the real error for debug.log and show a
	 * dismissible admin notice instead of letting an unexpected runtime error
	 * take down the whole site with the white-screen "critical error" page.
	 */
	private function report_error( \Throwable $e, $context ) {
		error_log( sprintf(
			'[Pterodactyl Hosting Manager] Error in %s: %s in %s:%d',
			$context, $e->getMessage(), $e->getFile(), $e->getLine()
		) );

		add_action( 'admin_notices', function () use ( $e, $context ) {
			if ( ! current_user_can( 'manage_options' ) ) return;
			echo '<div class="notice notice-error"><p><strong>Pterodactyl Hosting Manager</strong> hit an error in <code>' . esc_html( $context ) . '</code>: ';
			echo esc_html( $e->getMessage() ) . ' (' . esc_html( basename( $e->getFile() ) . ':' . $e->getLine() ) . ')</p></div>';
		} );
	}

	public function activate() {
		try {
			global $wpdb;
			$charset_collate = $wpdb->get_charset_collate();

			if ( ! function_exists( 'dbDelta' ) && file_exists( ABSPATH . 'wp-admin/includes/upgrade.php' ) ) {
				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			}
			if ( ! function_exists( 'dbDelta' ) ) {
				return; // Should never happen on a real WP install, but avoid a fatal just in case.
			}

			// Orders / servers table
			$table = $wpdb->prefix . 'ptero_servers';
			dbDelta( "CREATE TABLE {$table} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				user_id BIGINT UNSIGNED NOT NULL,
				ptero_server_id BIGINT UNSIGNED DEFAULT NULL,
				ptero_identifier VARCHAR(64) DEFAULT NULL,
				server_name VARCHAR(191) NOT NULL,
				egg_id BIGINT UNSIGNED NOT NULL,
				location_id BIGINT UNSIGNED NOT NULL,
				node_id BIGINT UNSIGNED DEFAULT NULL,
				ram INT UNSIGNED NOT NULL,
				cpu INT UNSIGNED NOT NULL,
				disk INT UNSIGNED NOT NULL,
				backups INT UNSIGNED DEFAULT 0,
				databases INT UNSIGNED DEFAULT 0,
				dedicated_ip TINYINT(1) DEFAULT 0,
				ip_address VARCHAR(64) DEFAULT NULL,
				port INT DEFAULT NULL,
				price DECIMAL(10,2) NOT NULL DEFAULT 0,
				currency VARCHAR(10) DEFAULT 'PKR',
				status VARCHAR(32) NOT NULL DEFAULT 'pending',
				billing_cycle VARCHAR(20) DEFAULT 'monthly',
				expires_at DATETIME DEFAULT NULL,
				created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				KEY user_id (user_id),
				KEY status (status)
			) {$charset_collate};" );

			// Coupons table
			$ctable = $wpdb->prefix . 'ptero_coupons';
			dbDelta( "CREATE TABLE {$ctable} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				code VARCHAR(64) NOT NULL,
				type VARCHAR(20) NOT NULL DEFAULT 'percent',
				amount DECIMAL(10,2) NOT NULL,
				max_uses INT DEFAULT NULL,
				used INT DEFAULT 0,
				expires_at DATETIME DEFAULT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY code  (code)
			) {$charset_collate};" );

			if ( class_exists( 'Ptero_DB_Installer' ) ) {
				Ptero_DB_Installer::install();
				update_option( Ptero_DB_Installer::DB_VERSION_OPTION, Ptero_DB_Installer::DB_VERSION );
			}

			if ( ! wp_next_scheduled( 'ptero_host_daily_cron' ) ) {
				wp_schedule_event( time(), 'daily', 'ptero_host_daily_cron' );
			}
			if ( ! wp_next_scheduled( 'ptero_host_hourly_sync' ) ) {
				wp_schedule_event( time(), 'hourly', 'ptero_host_hourly_sync' );
			}
		} catch ( \Throwable $e ) {
			$this->report_error( $e, 'activate' );
		}
	}

	public function deactivate() {
		try {
			wp_clear_scheduled_hook( 'ptero_host_daily_cron' );
			wp_clear_scheduled_hook( 'ptero_host_hourly_sync' );
		} catch ( \Throwable $e ) {
			$this->report_error( $e, 'deactivate' );
		}
	}
}

try {
	PteroHost_Plugin::instance();
} catch ( \Throwable $e ) {
	error_log( '[Pterodactyl Hosting Manager] Fatal during bootstrap: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );
	add_action( 'admin_notices', function () use ( $e ) {
		if ( ! current_user_can( 'manage_options' ) ) return;
		echo '<div class="notice notice-error"><p><strong>Pterodactyl Hosting Manager failed to start:</strong> ';
		echo esc_html( $e->getMessage() ) . ' (' . esc_html( basename( $e->getFile() ) . ':' . $e->getLine() ) . ')</p></div>';
	} );
}
