<?php
/**
 * Admin area: menu pages (Dashboard, Products, Orders, Database Data,
 * Settings) + assets.
 *
 * @package Pterodactyl_Hosting
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PHM_Admin {

	public static function init() {
		add_action( 'admin_menu', [ __CLASS__, 'menu' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'assets' ] );
		add_action( 'admin_notices', [ __CLASS__, 'configuration_notice' ] );
		add_action( 'admin_post_phm_save_settings', [ __CLASS__, 'handle_save_settings' ] );
		add_action( 'admin_post_phm_create_pages', [ __CLASS__, 'handle_create_pages' ] );
		add_action( 'admin_post_phm_repair_db', [ __CLASS__, 'handle_repair_db' ] );

		// --- "Sorry, you are not allowed to access this page" fixes ---------
		// 1) Legacy slugs from older plugin builds → redirect to new pages.
		add_action( 'admin_init', [ __CLASS__, 'legacy_slug_redirects' ], 1 );
		// 2) One of OUR pages is denied (custom roles) → helpful message
		//    instead of the bare core wp_die().
		add_action( 'admin_page_access_denied', [ __CLASS__, 'access_denied_helper' ] );
		// 3) Multisite network admin gets a pointer to the per-site screens.
		add_action( 'network_admin_menu', [ __CLASS__, 'network_menu' ] );

		// Convenient Settings link on the plugins list.
		add_filter( 'plugin_action_links_' . plugin_basename( PHM_FILE ), [ __CLASS__, 'plugin_links' ] );

		// Bust cached checkout-page lookup when pages change.
		add_action( 'save_post_page', [ __CLASS__, 'flush_page_cache' ] );
		add_action( 'deleted_post', [ __CLASS__, 'flush_page_cache' ] );
	}

	public static function flush_page_cache() {
		delete_transient( 'phm_order_page_url' );
	}

	/**
	 * The capability required for all PHM admin screens and actions.
	 * Filterable so hosts with custom admin roles can remap in one line:
	 *   add_filter( 'phm_admin_capability', function () { return 'manage_woocommerce'; } );
	 */
	public static function capability() {
		return apply_filters( 'phm_admin_capability', 'manage_options' );
	}

	/**
	 * Old builds of the plugin used different page slugs. Hitting one (saved
	 * bookmark, old link, browser history) throws core's
	 * "Sorry, you are not allowed to access this page." — redirect instead.
	 */
	public static function legacy_slug_redirects() {
		if ( ! is_admin() || empty( $_GET['page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$page = sanitize_key( wp_unslash( $_GET['page'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$map  = [
			'pterodactyl'           => 'phm-dashboard',
			'pterodactyl-hosting'   => 'phm-dashboard',
			'pterodactyl-manager'   => 'phm-dashboard',
			'pterodactyl-plans'     => 'phm-products',
			'pterodactyl-products'  => 'phm-products',
			'pterodactyl-orders'    => 'phm-orders',
			'pterodactyl-settings'  => 'phm-settings',
			'pterodactyl-database'  => 'phm-database',
			'phm'                   => 'phm-dashboard',
		];
		if ( isset( $map[ $page ] ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=' . $map[ $page ] ) );
			exit;
		}
	}

	/**
	 * Fires when a registered admin page denies access — give a usable
	 * explanation instead of the bare "not allowed" dead end.
	 */
	public static function access_denied_helper() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( 0 !== strpos( $page, 'phm-' ) ) {
			return; // not our screen.
		}
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url( admin_url( 'admin.php?page=' . $page ) ) );
			exit;
		}
		wp_die(
			'<h1>PGC Hosting — access denied</h1>' .
			'<p>Your account does not have the <code>' . esc_html( self::capability() ) . '</code> capability needed for this screen.</p>' .
			'<p><strong>Fix:</strong> log in as a full administrator, or add this to wp-config.php / an MU plugin to remap the capability:</p>' .
			'<p><code>add_filter( \'phm_admin_capability\', function() { return \'manage_woocommerce\'; } );</code></p>',
			'PGC Hosting',
			[ 'response' => 403, 'back_link' => true ]
		);
	}

	/**
	 * On multisite, the network admin has no per-site tables — point to the
	 * site admin instead of dying with "not allowed".
	 */
	public static function network_menu() {
		if ( ! is_multisite() ) {
			return;
		}
		add_menu_page(
			__( 'PGC Hosting', 'pterodactyl-hosting' ),
			__( 'PGC Hosting', 'pterodactyl-hosting' ),
			'manage_network_options',
			'phm-network',
			function () {
				wp_safe_redirect( admin_url( 'admin.php?page=phm-dashboard' ) );
				exit;
			},
			'dashicons-cloud',
			56
		);
	}

	/**
	 * @param array $links
	 * @return array
	 */
	public static function plugin_links( $links ) {
		$settings = '<a href="' . esc_url( admin_url( 'admin.php?page=phm-settings' ) ) . '">' . esc_html__( 'Settings', 'pterodactyl-hosting' ) . '</a>';
		array_unshift( $links, $settings );
		return $links;
	}

	public static function menu() {
		$cap = self::capability();
		add_menu_page(
			__( 'PGC Hosting', 'pterodactyl-hosting' ),
			__( 'PGC Hosting', 'pterodactyl-hosting' ),
			$cap,
			'phm-dashboard',
			[ __CLASS__, 'render_dashboard' ],
			'dashicons-cloud',
			56
		);
		add_submenu_page( 'phm-dashboard', __( 'Dashboard', 'pterodactyl-hosting' ), __( 'Dashboard', 'pterodactyl-hosting' ), $cap, 'phm-dashboard', [ __CLASS__, 'render_dashboard' ] );
		add_submenu_page( 'phm-dashboard', __( 'Products / Plans', 'pterodactyl-hosting' ), __( 'Products', 'pterodactyl-hosting' ), $cap, 'phm-products', [ __CLASS__, 'render_products' ] );
		add_submenu_page( 'phm-dashboard', __( 'Coupons & Promo Codes', 'pterodactyl-hosting' ), __( 'Coupons', 'pterodactyl-hosting' ), $cap, 'phm-coupons', [ __CLASS__, 'render_coupons' ] );
		add_submenu_page( 'phm-dashboard', __( 'Orders', 'pterodactyl-hosting' ), __( 'Orders', 'pterodactyl-hosting' ), $cap, 'phm-orders', [ __CLASS__, 'render_orders' ] );
		add_submenu_page( 'phm-dashboard', __( 'Payment Gateways & APIs', 'pterodactyl-hosting' ), __( 'Payment Gateways', 'pterodactyl-hosting' ), $cap, 'phm-gateways', [ __CLASS__, 'render_gateways' ] );
		add_submenu_page( 'phm-dashboard', __( 'Support Tickets', 'pterodactyl-hosting' ), self::tickets_menu_label(), $cap, 'phm-tickets', [ __CLASS__, 'render_tickets' ] );
		add_submenu_page( 'phm-dashboard', __( 'Database Data', 'pterodactyl-hosting' ), __( 'Database Data', 'pterodactyl-hosting' ), $cap, 'phm-database', [ __CLASS__, 'render_database' ] );
		add_submenu_page( 'phm-dashboard', __( 'Settings', 'pterodactyl-hosting' ), __( 'Settings', 'pterodactyl-hosting' ), $cap, 'phm-settings', [ __CLASS__, 'render_settings' ] );
	}

	public static function assets( $hook ) {
		if ( false === strpos( (string) $hook, 'phm-' ) && false === strpos( (string) $hook, 'pgc-hosting' ) ) {
			return;
		}
		wp_enqueue_style( 'phm-admin', PHM_URL . 'assets/admin.css', [], PHM_VERSION );
		wp_enqueue_script( 'phm-admin', PHM_URL . 'assets/admin.js', [], PHM_VERSION, true );
		wp_localize_script( 'phm-admin', 'PHM_ADMIN', [
			'ajax'  => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'phm_admin' ),
			'i18n'  => [
				'testing'  => __( 'Testing connection…', 'pterodactyl-hosting' ),
				'syncing'  => __( 'Syncing from panel…', 'pterodactyl-hosting' ),
				'sync_ok'  => __( 'Sync complete — data reloaded.', 'pterodactyl-hosting' ),
				'failed'   => __( 'Failed', 'pterodactyl-hosting' ),
				'ok'       => __( 'Connected', 'pterodactyl-hosting' ),
			],
		] );
	}

	public static function configuration_notice() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || false === strpos( (string) $screen->id, 'phm-' ) ) {
			return;
		}
		if ( PHM_Settings::is_configured() ) {
			return;
		}
		echo '<div class="notice notice-warning"><p>';
		echo esc_html__( 'Pterodactyl Hosting Manager is not connected yet.', 'pterodactyl-hosting' ) . ' ';
		echo '<a href="' . esc_url( admin_url( 'admin.php?page=phm-settings' ) ) . '">' . esc_html__( 'Enter your panel URL + API key', 'pterodactyl-hosting' ) . '</a>';
		echo '</p></div>';
	}

	/**
	 * Classic settings form submit → save, test, auto-sync, then redirect back
	 * with a flag so the page reloads the synced data panels.
	 */
	public static function handle_save_settings() {
		if ( ! current_user_can( self::capability() ) ) {
			wp_die( esc_html__( 'Permission denied.', 'pterodactyl-hosting' ) );
		}
		check_admin_referer( 'phm_save_settings' );

		$old_sync = PHM_Settings::get()['auto_sync'];
		PHM_Settings::update( isset( $_POST['phm'] ) ? (array) wp_unslash( $_POST['phm'] ) : [] );

		if ( PHM_Settings::get()['auto_sync'] !== $old_sync ) {
			PHM_Cronjobs::reschedule();
		}

		// Auto test + sync so the user immediately sees the database data.
		$test = PHM_API::test_connection();
		$flag = 'settings_saved';
		if ( is_array( $test ) && ! empty( $test['ok'] ) ) {
			PHM_Sync::sync_all();
			$flag = 'settings_saved_synced';
		} elseif ( is_array( $test ) && ! empty( $test['message'] ) && PHM_Settings::is_configured() ) {
			$flag = 'settings_saved_connfailed';
		}

		wp_safe_redirect( admin_url( 'admin.php?page=phm-settings&phm_msg=' . $flag ) );
		exit;
	}

	/**
	 * One-click store page creation from the dashboard.
	 */
	public static function handle_create_pages() {
		if ( ! current_user_can( self::capability() ) ) {
			wp_die( esc_html__( 'Permission denied.', 'pterodactyl-hosting' ) );
		}
		check_admin_referer( 'phm_create_pages' );
		PHM_Store::ensure_pages();
		PHM_DB::log( 'info', 'Storefront pages ensured (plans / order / track).' );
		wp_safe_redirect( admin_url( 'admin.php?page=phm-dashboard&phm_msg=pages_created' ) );
		exit;
	}

	/**
	 * "Repair database tables" — rebuilds the schema with dbDelta and
	 * reports which tables are still missing (if any).
	 */
	public static function handle_repair_db() {
		if ( ! current_user_can( self::capability() ) ) {
			wp_die( esc_html__( 'Permission denied.', 'pterodactyl-hosting' ) );
		}
		check_admin_referer( 'phm_repair_db' );

		PHM_DB::install();
		delete_transient( 'phm_tables_ok' );
		$missing = PHM_DB::tables_exist();

		if ( $missing ) {
			wp_safe_redirect( add_query_arg( [
				'page'         => 'phm-dashboard',
				'phm_msg'      => 'db_repair_failed',
				'phm_db_error' => rawurlencode( 'Still missing: ' . implode( ', ', $missing ) . ' — the WordPress DB user needs CREATE/ALTER privileges.' ),
			], admin_url( 'admin.php' ) ) );
		} else {
			wp_safe_redirect( admin_url( 'admin.php?page=phm-dashboard&phm_msg=db_repaired' ) );
		}
		exit;
	}

	/* ---------------------------- Views ---------------------------------- */

	private static function view( $file ) {
		require PHM_PATH . 'admin/views/' . $file;
	}

	public static function render_dashboard()  { self::view( 'dashboard.php' ); }
	public static function render_products()   { self::view( 'products.php' ); }
	public static function render_coupons()    { self::view( 'coupons.php' ); }
	public static function render_orders()     { self::view( 'orders.php' ); }
	public static function render_gateways()   { self::view( 'gateways.php' ); }
	public static function render_database()   { self::view( 'database-page.php' ); }
	public static function render_settings()   { self::view( 'settings.php' ); }

	public static function render_tickets() {
		$id = isset( $_GET['view'] ) ? (int) $_GET['view'] : 0; // phpcs:ignore WordPress.Security.NonceVerification
		if ( $id ) {
			self::view( 'ticket-view.php' );
		} else {
			self::view( 'tickets.php' );
		}
	}

	/**
	 * "Support Tickets" menu label with an unread-count badge, same visual
	 * pattern WordPress core uses for Comments/Updates counts.
	 */
	private static function tickets_menu_label() {
		$counts = PHM_DB::ticket_counts();
		$open   = (int) $counts['open'] + (int) $counts['customer-reply'];
		$label  = __( 'Support Tickets', 'pterodactyl-hosting' );
		if ( $open > 0 ) {
			$label .= ' <span class="awaiting-mod count-' . (int) $open . '"><span class="pending-count">' . (int) $open . '</span></span>';
		}
		return $label;
	}

	/**
	 * Render an admin banner message for ?phm_msg=… query flags.
	 */
	public static function render_msg() {
		if ( empty( $_GET['phm_msg'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$code = sanitize_key( wp_unslash( $_GET['phm_msg'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$map  = [
			'saved'                  => [ 'success', __( 'Saved.', 'pterodactyl-hosting' ) ],
			'deleted'                => [ 'success', __( 'Deleted.', 'pterodactyl-hosting' ) ],
			'pages_created'          => [ 'success', __( 'Store pages ready — Plans, Order and Tracking pages are published.', 'pterodactyl-hosting' ) ],
			'egg_fixed'              => [ 'warning', __( 'Saved — note: the egg did not belong to the selected game, so the first matching egg was auto-selected. Double-check it.', 'pterodactyl-hosting' ) ],
			'no_eggs_sync'           => [ 'error', __( 'The selected game has no synced eggs. Run “Sync now” first, then edit the plan.', 'pterodactyl-hosting' ) ],
			'imported'               => [ 'success', __( 'Plan imported from egg — review pricing then activate.', 'pterodactyl-hosting' ) ],
			'settings_saved'         => [ 'success', __( 'Settings saved.', 'pterodactyl-hosting' ) ],
			'settings_saved_synced'  => [ 'success', __( 'Settings saved — connection OK, panel data synced automatically.', 'pterodactyl-hosting' ) ],
			'settings_saved_connfailed' => [ 'error', __( 'Settings saved, but the panel connection test failed. Check URL + API key.', 'pterodactyl-hosting' ) ],
			'paid'                   => [ 'success', __( 'Order marked paid.', 'pterodactyl-hosting' ) ],
			'deployed'               => [ 'success', __( 'Server deployed automatically.', 'pterodactyl-hosting' ) ],
			'deploy_failed'          => [ 'error', __( 'Deployment failed — see the order error message and sync log.', 'pterodactyl-hosting' ) ],
			'cancelled'              => [ 'success', __( 'Order cancelled / server suspended.', 'pterodactyl-hosting' ) ],
			'name_required'          => [ 'error', __( 'Plan name is required.', 'pterodactyl-hosting' ) ],
			'save_failed'            => [ 'error', self::db_error_text( __( 'Could not save the plan — the database rejected the write.', 'pterodactyl-hosting' ) ) ],
			'db_repaired'            => [ 'success', __( 'Database tables repaired — all 7 tables exist now. Try saving again.', 'pterodactyl-hosting' ) ],
			'db_repair_failed'       => [ 'error', self::db_error_text( __( 'Table repair did not complete.', 'pterodactyl-hosting' ) ) ],
			'missing'                => [ 'error', __( 'Record not found.', 'pterodactyl-hosting' ) ],
			'ticket_replied'         => [ 'success', __( 'Reply sent.', 'pterodactyl-hosting' ) ],
			'error'                  => [ 'error', __( 'Action failed.', 'pterodactyl-hosting' ) ],
		];
		if ( isset( $map[ $code ] ) ) {
			// wp_kses_post (not esc_html) so the error text can include the
			// "Repair database tables" action link.
			printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $map[ $code ][0] ), wp_kses_post( $map[ $code ][1] ) );
		}
	}

	/**
	 * Real database error text for banners — so a silent failure can never
	 * hide behind a fake success message. Also links to the one-click repair.
	 */
	private static function db_error_text( $intro = '' ) {
		$detail = isset( $_GET['phm_db_error'] ) ? sanitize_text_field( rawurldecode( wp_unslash( $_GET['phm_db_error'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$msg = $intro ? $intro . ' ' : '';
		if ( $detail ) {
			$msg .= sprintf(
				/* translators: %s: MySQL error message */
				__( 'Database said: %s', 'pterodactyl-hosting' ),
				$detail
			);
		}
		$repair = wp_nonce_url( admin_url( 'admin-post.php?action=phm_repair_db' ), 'phm_repair_db' );
		$msg .= ' <a href="' . esc_url( $repair ) . '">' . __( 'Run “Repair database tables”', 'pterodactyl-hosting' ) . '</a>';
		return $msg;
	}
}
