<?php
/**
 * AJAX endpoints.
 *
 * Admin (manage_options + nonce):
 *  - phm_test_connection  Test panel API + Cloudflare, then AUTO-SYNC and
 *                         return fresh DB data so the UI reloads itself.
 *  - phm_sync_now         Manual full sync, returns rendered Database Data HTML.
 *  - phm_get_db_data      Rendered synced-data tables (auto reload).
 *
 * Public (nopriv ok):
 *  - phm_check_subdomain  Live subdomain availability for the cart.
 *  - phm_apply_coupon     Live promo code validation & discount calculation.
 *  - phm_refresh_nonce    Refresh security nonces dynamically for cached tabs.
 *  - phm_place_order      Place an order (supports logged in & guest instant checkout).
 *  - phm_order_status     Poll provisioning progress & reveal deployed server details.
 *
 * @package Pterodactyl_Hosting
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PHM_Ajax {

	/** Names that must never be sold as subdomains. */
	private static $reserved = [
		'www', 'mail', 'smtp', 'imap', 'pop', 'ftp', 'panel', 'cpanel', 'api', 'cdn',
		'admin', 'blog', 'shop', 'store', 'host', 'hosting', 'ns1', 'ns2', 'webmail',
		'play', 'mc', 'minecraft', 'test', 'status', 'support', 'billing', 'client',
	];

	public static function init() {
		// Admin.
		add_action( 'wp_ajax_phm_test_connection', [ __CLASS__, 'admin_test_connection' ] );
		add_action( 'wp_ajax_phm_sync_now', [ __CLASS__, 'admin_sync_now' ] );
		add_action( 'wp_ajax_phm_get_db_data', [ __CLASS__, 'admin_get_db_data' ] );
		add_action( 'wp_ajax_phm_cf_resolve_zone', [ __CLASS__, 'admin_cf_resolve_zone' ] );

		// Public.
		add_action( 'wp_ajax_phm_check_subdomain', [ __CLASS__, 'check_subdomain' ] );
		add_action( 'wp_ajax_nopriv_phm_check_subdomain', [ __CLASS__, 'check_subdomain' ] );
		add_action( 'wp_ajax_phm_apply_coupon', [ __CLASS__, 'apply_coupon' ] );
		add_action( 'wp_ajax_nopriv_phm_apply_coupon', [ __CLASS__, 'apply_coupon' ] );
		add_action( 'wp_ajax_phm_refresh_nonce', [ __CLASS__, 'refresh_nonce' ] );
		add_action( 'wp_ajax_nopriv_phm_refresh_nonce', [ __CLASS__, 'refresh_nonce' ] );
		add_action( 'wp_ajax_phm_place_order', [ __CLASS__, 'place_order' ] );
		add_action( 'wp_ajax_nopriv_phm_place_order', [ __CLASS__, 'place_order' ] );
		add_action( 'wp_ajax_phm_order_status', [ __CLASS__, 'order_status' ] );
		add_action( 'wp_ajax_nopriv_phm_order_status', [ __CLASS__, 'order_status' ] );
	}

	private static function require_admin() {
		check_ajax_referer( 'phm_admin', 'nonce' );
		if ( ! current_user_can( PHM_Admin::capability() ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'pterodactyl-hosting' ) ], 403 );
		}
	}

	private static function require_public_nonce() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : ( isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '' );
		if ( ! wp_verify_nonce( $nonce, 'phm_public' ) && ! wp_verify_nonce( $nonce, 'phm_order' ) && ! is_user_logged_in() ) {
			// Nonce expired on cached page - notify client to refresh nonce cleanly.
			wp_send_json_error( [
				'code'          => 'nonce_expired',
				'message'       => __( 'Security token refreshed. Please try submitting again.', 'pterodactyl-hosting' ),
				'refreshed_nonce' => wp_create_nonce( 'phm_public' ),
			], 403 );
		}
	}

	/* ---------------------------------------------------------------------
	 * Admin endpoints
	 * ------------------------------------------------------------------- */

	public static function admin_test_connection() {
		self::require_admin();

		$panel = PHM_API::test_connection();
		$cf    = null;
		if ( PHM_Cloudflare::enabled() ) {
			$cf = PHM_Cloudflare::test();
		}

		if ( is_array( $panel ) && $panel['ok'] ) {
			$sync = PHM_Sync::sync_all();
			wp_send_json_success( [
				'panel'      => $panel,
				'cf'         => $cf,
				'synced'     => is_wp_error( $sync ) ? false : $sync,
				'sync_error' => is_wp_error( $sync ) ? $sync->get_error_message() : '',
				'db_html'    => self::render_db_data_html(),
				'last_sync'  => PHM_Sync::last_sync_human(),
			] );
		}

		wp_send_json_error( [
			'panel' => $panel,
			'cf'    => $cf,
		] );
	}

	public static function admin_sync_now() {
		self::require_admin();
		$result = PHM_Sync::sync_all();
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}
		wp_send_json_success( [
			'counts'    => $result,
			'db_html'   => self::render_db_data_html(),
			'last_sync' => PHM_Sync::last_sync_human(),
		] );
	}

	public static function admin_get_db_data() {
		self::require_admin();
		wp_send_json_success( [
			'db_html'   => self::render_db_data_html(),
			'last_sync' => PHM_Sync::last_sync_human(),
			'counts'    => PHM_DB::counts(),
		] );
	}

	public static function admin_cf_resolve_zone() {
		self::require_admin();
		$domain = isset( $_POST['domain'] ) ? sanitize_text_field( wp_unslash( $_POST['domain'] ) ) : PHM_Settings::get()['cf_base_domain'];
		$zone   = PHM_Cloudflare::resolve_zone_id( $domain );
		if ( is_wp_error( $zone ) ) {
			wp_send_json_error( [ 'message' => $zone->get_error_message() ] );
		}
		PHM_Settings::update( [ 'cf_zone_id' => $zone ] );
		wp_send_json_success( [ 'zone_id' => $zone, 'message' => sprintf(
			/* translators: %s: zone id */
			__( 'Zone found and saved: %s', 'pterodactyl-hosting' ), $zone
		) ] );
	}

	public static function render_db_data_html() {
		ob_start();
		require PHM_PATH . 'admin/views/database.php';
		return ob_get_clean();
	}

	/* ---------------------------------------------------------------------
	 * Public endpoints
	 * ------------------------------------------------------------------- */

	public static function refresh_nonce() {
		wp_send_json_success( [
			'nonce' => wp_create_nonce( 'phm_public' ),
		] );
	}

	public static function apply_coupon() {
		self::require_public_nonce();

		$code       = isset( $_POST['coupon_code'] ) ? sanitize_text_field( wp_unslash( $_POST['coupon_code'] ) ) : '';
		$product_id = isset( $_POST['product_id'] ) ? (int) $_POST['product_id'] : 0;
		$amount     = isset( $_POST['amount'] ) ? (float) $_POST['amount'] : 0.0;

		$result = PHM_Coupons::validate( $code, $product_id, $amount );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		wp_send_json_success( $result );
	}

	public static function check_subdomain() {
		self::require_public_nonce();

		$sub = isset( $_POST['subdomain'] ) ? strtolower( sanitize_text_field( wp_unslash( $_POST['subdomain'] ) ) ) : '';
		$sub = preg_replace( '/[^a-z0-9-]/', '', $sub );

		if ( strlen( $sub ) < 3 || strlen( $sub ) > 32 ) {
			wp_send_json_error( [ 'code' => 'invalid' ] );
		}
		if ( in_array( $sub, self::$reserved, true ) ) {
			wp_send_json_error( [ 'code' => 'reserved' ] );
		}

		$fqdn = PHM_Cloudflare::base_domain() ? $sub . '.' . PHM_Cloudflare::base_domain() : $sub;

		if ( PHM_DB::subdomain_taken( $fqdn ) ) {
			wp_send_json_error( [ 'code' => 'taken', 'fqdn' => $fqdn ] );
		}
		if ( PHM_Cloudflare::enabled() && PHM_Cloudflare::record_exists( $fqdn ) ) {
			wp_send_json_error( [ 'code' => 'taken', 'fqdn' => $fqdn ] );
		}

		wp_send_json_success( [ 'fqdn' => $fqdn ] );
	}

	public static function place_order() {
		self::require_public_nonce();

		// Handle user authentication (logged in or seamless guest checkout).
		$wp_user = null;
		if ( is_user_logged_in() ) {
			$wp_user = wp_get_current_user();
		} else {
			// Guest checkout data.
			$guest_email    = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
			$guest_username = isset( $_POST['username'] ) ? sanitize_user( wp_unslash( $_POST['username'] ), true ) : '';
			$guest_password = isset( $_POST['password'] ) ? (string) $_POST['password'] : '';

			if ( ! is_email( $guest_email ) ) {
				wp_send_json_error( [ 'message' => __( 'Please provide a valid email address.', 'pterodactyl-hosting' ) ] );
			}

			$existing_user = get_user_by( 'email', $guest_email );
			if ( $existing_user ) {
				wp_send_json_error( [
					'code'    => 'login_required',
					'message' => __( 'An account with that email already exists. Please log in to continue.', 'pterodactyl-hosting' ),
				] );
			} else {
				if ( ! $guest_username ) {
					$guest_username = sanitize_user( strstr( $guest_email, '@', true ), true );
				}
				if ( username_exists( $guest_username ) ) {
					$guest_username .= wp_rand( 100, 999 );
				}
				if ( ! $guest_password ) {
					$guest_password = wp_generate_password( 16, true, true );
				}

				$new_user_id = wp_create_user( $guest_username, $guest_password, $guest_email );
				if ( is_wp_error( $new_user_id ) ) {
					wp_send_json_error( [ 'message' => $new_user_id->get_error_message() ] );
				}

				$wp_user = get_user_by( 'id', $new_user_id );
				wp_set_current_user( $new_user_id );
				wp_set_auth_cookie( $new_user_id, true );

				// Capture password in bridge for Pterodactyl creation.
				if ( class_exists( 'PHM_Password_Bridge' ) ) {
					set_transient( 'phm_pw_' . $new_user_id, $guest_password, 15 * MINUTE_IN_SECONDS );
				}
			}
		}

		$name  = $wp_user->display_name ? $wp_user->display_name : $wp_user->user_login;
		$email = $wp_user->user_email;

		$product_id = isset( $_POST['product_id'] ) ? (int) $_POST['product_id'] : 0;
		$product    = PHM_DB::get_product( $product_id );

		if ( ! $product_id ) {
			wp_send_json_error( [ 'code' => 'stale', 'message' => __( 'Please choose a plan.', 'pterodactyl-hosting' ) ] );
		}
		if ( ! $product || ! $product->active ) {
			wp_send_json_error( [ 'code' => 'stale', 'message' => __( 'This plan is no longer available. Please refresh the page.', 'pterodactyl-hosting' ) ] );
		}
		if ( 0 === (int) $product->stock ) {
			wp_send_json_error( [ 'code' => 'stale', 'message' => __( 'That plan just sold out. Please select another plan.', 'pterodactyl-hosting' ) ] );
		}

		$discord = isset( $_POST['discord'] ) ? sanitize_text_field( wp_unslash( $_POST['discord'] ) ) : '';
		$sub     = isset( $_POST['subdomain'] ) ? strtolower( preg_replace( '/[^a-z0-9-]/', '', sanitize_text_field( wp_unslash( $_POST['subdomain'] ) ) ) ) : '';
		$label   = isset( $_POST['server_label'] ) ? sanitize_text_field( wp_unslash( $_POST['server_label'] ) ) : '';
		$egg_id  = isset( $_POST['egg_id'] ) ? (int) $_POST['egg_id'] : (int) $product->egg_id;
		$method  = isset( $_POST['payment_method'] ) ? sanitize_key( wp_unslash( $_POST['payment_method'] ) ) : 'manual';
		$amount  = (float) $product->price + (float) $product->setup_fee;

		// Handle coupon.
		$coupon_code     = isset( $_POST['coupon_code'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST['coupon_code'] ) ) ) : '';
		$discount_amount = 0.0;
		if ( $coupon_code ) {
			$coupon_res = PHM_Coupons::validate( $coupon_code, $product->id, $amount );
			if ( ! is_wp_error( $coupon_res ) && ! empty( $coupon_res['valid'] ) ) {
				$discount_amount = (float) $coupon_res['discount_amount'];
				$amount          = (float) $coupon_res['final_total'];
				PHM_DB::increment_coupon_uses( $coupon_code );
			}
		}

		$is_free = $amount <= 0;
		if ( $is_free ) {
			$method = 'free';
		}

		$settings = PHM_Settings::get();
		if ( PHM_Cloudflare::enabled() ) {
			if ( ! empty( $settings['cf_subdomain_required'] ) && '' === $sub ) {
				wp_send_json_error( [ 'message' => __( 'Please choose a subdomain for your server.', 'pterodactyl-hosting' ) ] );
			}
			if ( '' !== $sub ) {
				if ( strlen( $sub ) < 3 || strlen( $sub ) > 32 || in_array( $sub, self::$reserved, true ) ) {
					wp_send_json_error( [ 'message' => __( 'That subdomain is invalid or reserved.', 'pterodactyl-hosting' ) ] );
				}
				$fqdn = $sub . '.' . PHM_Cloudflare::base_domain();
				if ( PHM_DB::subdomain_taken( $fqdn ) || PHM_Cloudflare::record_exists( $fqdn ) ) {
					wp_send_json_error( [ 'message' => sprintf( __( '%s is already taken.', 'pterodactyl-hosting' ), $fqdn ) ] );
				}
			}
		} else {
			$sub = '';
			if ( strlen( trim( $label ) ) < 2 ) {
				$base_name = $wp_user->display_name ? $wp_user->display_name : $wp_user->user_login;
				$label     = sprintf( '%s — %s', $base_name, $product->name );
			}
		}

		// Egg verification.
		$egg = PHM_DB::get_egg( $egg_id );
		if ( ! $egg || (int) $egg->nest_id !== (int) $product->nest_id ) {
			$egg_id = (int) $product->egg_id;
			$egg    = PHM_DB::get_egg( $egg_id );
		}
		if ( ! $egg ) {
			wp_send_json_error( [ 'message' => __( 'Server type not available — sync the panel in settings.', 'pterodactyl-hosting' ) ] );
		}

		$order_id = PHM_DB::create_order( [
			'product_id'      => (int) $product->id,
			'plan_name'       => $product->name,
			'customer_name'   => $name,
			'email'           => $email,
			'wp_user_id'      => (int) $wp_user->ID,
			'discord'         => $discord,
			'subdomain'       => $sub,
			'server_label'    => $label ? $label : $product->name,
			'fqdn'            => $sub ? $sub . '.' . PHM_Cloudflare::base_domain() : '',
			'nest_id'         => (int) $product->nest_id,
			'egg_id'          => $egg_id,
			'egg_name'        => $egg->name,
			'location_id'     => (int) $product->location_id,
			'payment_method'  => $method,
			'gateway_id'      => $method,
			'amount'          => $amount,
			'discount_amount' => $discount_amount,
			'coupon_code'     => $coupon_code,
			'currency'        => $product->currency,
		] );

		if ( ! $order_id ) {
			wp_send_json_error( [ 'message' => __( 'Could not create the order, please try again.', 'pterodactyl-hosting' ) ] );
		}

		PHM_DB::decrement_stock( (int) $product->id );
		$order = PHM_DB::get_order( $order_id );

		// WooCommerce checkout integration.
		if ( ! $is_free && 'woocommerce' === $method && function_exists( 'WC' ) ) {
			$redirect = PHM_WooCommerce::checkout_url_for( $order );
			if ( $redirect ) {
				wp_send_json_success( [ 'order' => self::order_payload( $order ), 'redirect' => $redirect ] );
			}
		}

		PHM_Notifications::order_placed( $order );

		$deploying = false;
		if ( $is_free || ! empty( $settings['auto_deploy_on_order'] ) ) {
			PHM_DB::update_order( $order_id, [ 'status' => 'paid' ] );
			PHM_Provisioning::queue_deploy( $order_id );
			$deploying = true;
			// Free servers: also try to deploy in this request so the
			// progress bar doesn't sit on "Queued" forever when WP-Cron
			// is disabled. deploy() is locked + idempotent.
			if ( $is_free ) {
				PHM_Provisioning::deploy_now( $order_id );
			}
		}

		$fresh = PHM_DB::get_order( $order_id );
		wp_send_json_success( [
			'order'          => self::order_payload( $fresh ),
			'deploying'      => $deploying,
			'already_active' => $fresh && 'active' === $fresh->status,
		] );
	}

	public static function order_status() {
		self::require_public_nonce();

		$order_id = isset( $_POST['order_id'] ) ? (int) $_POST['order_id'] : 0;
		$order    = $order_id ? PHM_DB::get_order( $order_id ) : null;

		if ( ! $order ) {
			wp_send_json_error( [ 'message' => __( 'Order not found.', 'pterodactyl-hosting' ) ] );
		}

		$owns = is_user_logged_in() && (int) $order->wp_user_id === get_current_user_id();
		$staff = current_user_can( class_exists( 'PHM_Admin' ) ? PHM_Admin::capability() : 'manage_options' );
		$fresh = ! empty( $order->created_at ) && strtotime( $order->created_at ) > ( time() - 20 * MINUTE_IN_SECONDS );
		if ( ! $owns && ! $staff && ! $fresh ) {
			wp_send_json_error( [ 'message' => __( 'Order not found.', 'pterodactyl-hosting' ) ] );
		}

		// Self-heal: if cron never fired, deploy from this poll.
		if ( empty( $order->server_id ) && 'failed' !== $order->status ) {
			PHM_Provisioning::maybe_kick_deploy( $order->id );
			$order = PHM_DB::get_order( $order->id );
		}

		$stages  = PHM_Provisioning::stages();
		$stage   = $order->stage && isset( $stages[ $order->stage ] ) ? $order->stage : 'queued';
		$meta    = $stages[ $stage ];
		$percent = 'failed' === $order->status ? 100 : (int) $meta[1];

		wp_send_json_success( [
			'status'       => $order->status,
			'status_label' => PHM_Orders::status_label( $order->status ),
			'stage'        => $stage,
			'stage_label'  => $meta[0],
			'percent'      => $percent,
			'error'        => $order->error_message,
			'order'        => self::order_payload( $order ),
		] );
	}

	public static function order_payload( $order ) {
		$address = PHM_Frontend::public_address( $order );

		return [
			'id'              => (int) $order->id,
			'number'          => $order->order_number,
			'server_name'     => $order->server_label ? $order->server_label : $order->plan_name,
			'status'          => $order->status,
			'status_label'    => PHM_Orders::status_label( $order->status ),
			'plan'            => $order->plan_name,
			'egg'             => $order->egg_name,
			'amount'          => PHM_Plans::format_price( $order->amount, $order->currency ),
			'discount_amount' => (float) $order->discount_amount,
			'coupon_code'     => $order->coupon_code,
			'fqdn'            => $order->fqdn,
			'server_address'  => $address,
			'email'           => $order->email,
			'panel_user_id'   => (int) $order->ptero_user_id,
			'server_id'       => (int) $order->server_id,
			'server_identifier' => $order->server_identifier,
			'panel_url'       => $order->server_id ? PHM_Settings::panel_url() : ( PHM_Settings::panel_url() ? PHM_Settings::panel_url() : '' ),
			'panel_login_url' => $order->server_id ? PHM_Cookie_Login::url_for_current_user( $order->server_identifier ) : '',
			'credential_note' => $order->credential_note ? $order->credential_note : __( 'Log in to your panel with your account email and password.', 'pterodactyl-hosting' ),
		];
	}
}
