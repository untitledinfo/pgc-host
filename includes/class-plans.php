<?php
/**
 * Plans: registration hooks + admin CRUD handlers.
 *
 * ---------------------------------------------------------------------------
 * OLD BUG (fatal):
 *   Pterodactyl Hosting Manager hit an error in init:
 *   Too few arguments to function add_action(), 1 passed in
 *   .../pterodactyl-hosting/includes/class-plans.php on line 32
 *   and at least 2 expected (plugin.php:446)
 *
 * CAUSE:
 *   Line 32 called add_action('init') with no callback argument.
 *
 * FIX:
 *   Every add_action() call below passes both required arguments —
 *   the hook name AND a valid callback.
 * ---------------------------------------------------------------------------
 *
 * @package Pterodactyl_Hosting
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PHM_Plans {

	public static function init() {
		// FIXED — previously: add_action( 'init' );  ← missing callback = fatal.
		add_action( 'init', [ __CLASS__, 'register_shortcodes' ] );

		// Admin-post CRUD endpoints (also fixed to always pass 2 args).
		add_action( 'admin_post_phm_save_plan', [ __CLASS__, 'handle_save_plan' ] );
		add_action( 'admin_post_phm_delete_plan', [ __CLASS__, 'handle_delete_plan' ] );
		add_action( 'admin_post_phm_toggle_plan', [ __CLASS__, 'handle_toggle_plan' ] );
		add_action( 'admin_post_phm_import_plan', [ __CLASS__, 'handle_import_plan' ] );
	}

	public static function register_shortcodes() {
		add_shortcode( 'phm_plans', [ 'PHM_Frontend', 'shortcode_plans' ] );
	}

	/**
	 * One-click "Create plan from egg" helper: prefills a product from a
	 * synced egg (e.g. Minecraft → Paper) so pricing/limits can be adjusted.
	 */
	public static function handle_import_plan() {
		if ( ! current_user_can( PHM_Admin::capability() ) ) {
			wp_die( esc_html__( 'Permission denied.', 'pterodactyl-hosting' ) );
		}
		check_admin_referer( 'phm_import_plan' );

		$egg = PHM_DB::get_egg( isset( $_GET['egg_id'] ) ? (int) $_GET['egg_id'] : 0 );
		if ( ! $egg ) {
			wp_safe_redirect( admin_url( 'admin.php?page=phm-products&phm_msg=egg_missing' ) );
			exit;
		}

		$id = PHM_DB::save_product( [
			'name'        => $egg->name,
			'slug'        => sanitize_title( $egg->name . '-' . wp_generate_password( 4, false, false ) ),
			'description' => wp_strip_all_tags( (string) $egg->description ),
			'nest_id'     => (int) $egg->nest_id,
			'egg_id'      => (int) $egg->egg_id,
			'location_id' => 0,
			'memory'      => 2048,
			'swap'        => 0,
			'disk'        => 10240,
			'io'          => 500,
			'cpu'         => 100,
			'databases'   => 1,
			'extra_allocations' => 0,
			'backups'     => 1,
			'price'       => 0,
			'setup_fee'   => 0,
			'currency'    => PHM_Settings::get()['default_currency'],
			'stock'       => -1,
			'featured'    => 0,
			'sort_order'  => 0,
			'active'      => 0, // created disabled until admin reviews pricing.
		] );

		if ( is_wp_error( $id ) ) {
			wp_safe_redirect( add_query_arg( [
				'page'         => 'phm-products',
				'phm_msg'      => 'save_failed',
				'phm_db_error' => rawurlencode( $id->get_error_message() ),
			], admin_url( 'admin.php' ) ) );
			exit;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=phm-products&action=edit&id=' . $id . '&phm_msg=imported' ) );
		exit;
	}

	public static function handle_save_plan() {
		if ( ! current_user_can( PHM_Admin::capability() ) ) {
			wp_die( esc_html__( 'Permission denied.', 'pterodactyl-hosting' ) );
		}
		check_admin_referer( 'phm_save_plan' );

		$id    = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$name  = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		if ( '' === $name ) {
			wp_safe_redirect( admin_url( 'admin.php?page=phm-products&phm_msg=name_required' ) );
			exit;
		}

		$nest_id = isset( $_POST['nest_id'] ) ? (int) $_POST['nest_id'] : 0;
		$egg_id  = isset( $_POST['egg_id'] ) ? (int) $_POST['egg_id'] : 0;
		$msg     = 'saved';

		// Keep the egg consistent with the chosen nest (game). If the admin
		// toggled the nest, the previously-selected egg no longer matches —
		// auto-select the first egg of the nest (e.g. Minecraft → Paper) and
		// tell them we did it. Refuse only when the nest has no synced eggs.
		$egg = $egg_id ? PHM_DB::get_egg( $egg_id ) : null;
		if ( ! $egg || ( $nest_id && (int) $egg->nest_id !== $nest_id ) ) {
			$fixed = null;
			foreach ( (array) PHM_DB::get_eggs( $nest_id ) as $candidate ) {
				$fixed = $candidate;
				break;
			}
			if ( ! $fixed ) {
				wp_safe_redirect( admin_url( 'admin.php?page=phm-products&phm_msg=no_eggs_sync' ) );
				exit;
			}
			if ( $egg_id && (int) $fixed->egg_id !== $egg_id ) {
				$msg = 'egg_fixed';
			}
			$egg_id = (int) $fixed->egg_id;
		}

		$slug = isset( $_POST['slug'] ) && '' !== $_POST['slug']
			? sanitize_title( wp_unslash( $_POST['slug'] ) )
			: sanitize_title( $name );

		$data = [
			'name'        => $name,
			'slug'        => $slug,
			'description' => isset( $_POST['description'] ) ? wp_kses_post( wp_unslash( $_POST['description'] ) ) : '',
			'nest_id'     => $nest_id,
			'egg_id'      => $egg_id,
			'location_id' => isset( $_POST['location_id'] ) ? (int) $_POST['location_id'] : 0,
			'memory'      => isset( $_POST['memory'] ) ? max( 128, (int) $_POST['memory'] ) : 1024,
			'swap'        => isset( $_POST['swap'] ) ? (int) $_POST['swap'] : 0,
			'disk'        => isset( $_POST['disk'] ) ? max( 512, (int) $_POST['disk'] ) : 5120,
			'io'          => isset( $_POST['io'] ) ? max( 10, min( 1000, (int) $_POST['io'] ) ) : 500,
			'cpu'         => isset( $_POST['cpu'] ) ? max( 10, (int) $_POST['cpu'] ) : 100,
			'databases'   => isset( $_POST['databases'] ) ? max( 0, (int) $_POST['databases'] ) : 0,
			'extra_allocations' => isset( $_POST['extra_allocations'] ) ? max( 0, (int) $_POST['extra_allocations'] ) : 0,
			'backups'     => isset( $_POST['backups'] ) ? max( 0, (int) $_POST['backups'] ) : 0,
			'price'       => isset( $_POST['price'] ) ? max( 0, (float) $_POST['price'] ) : 0,
			'setup_fee'   => isset( $_POST['setup_fee'] ) ? max( 0, (float) $_POST['setup_fee'] ) : 0,
			'currency'    => isset( $_POST['currency'] ) ? strtoupper( substr( sanitize_key( wp_unslash( $_POST['currency'] ) ), 0, 8 ) ) : 'USD',
			'stock'       => isset( $_POST['stock'] ) ? max( -1, (int) $_POST['stock'] ) : -1,
			'featured'    => ! empty( $_POST['featured'] ) ? 1 : 0,
			'sort_order'  => isset( $_POST['sort_order'] ) ? (int) $_POST['sort_order'] : 0,
			'active'      => ! empty( $_POST['active'] ) ? 1 : 0,
		];

		// Keep slug unique.
		global $wpdb;
		$existing = $wpdb->get_var( $wpdb->prepare(
			'SELECT id FROM ' . PHM_DB::table( 'products' ) . ' WHERE slug = %s AND id != %d LIMIT 1',
			$slug, $id
		) );
		if ( $existing ) {
			$data['slug'] = $slug . '-' . wp_generate_password( 4, false, false );
		}

		$new_id = PHM_DB::save_product( $data, $id );
		if ( is_wp_error( $new_id ) ) {
			// Surface the REAL error instead of a fake "Saved." + id=0 bounce.
			wp_safe_redirect( add_query_arg( [
				'page'         => 'phm-products',
				'action'       => $id ? 'edit' : 'new',
				'id'           => $id,
				'phm_msg'      => 'save_failed',
				'phm_db_error' => rawurlencode( $new_id->get_error_message() ),
			], admin_url( 'admin.php' ) ) );
			exit;
		}
		wp_safe_redirect( admin_url( 'admin.php?page=phm-products&action=edit&id=' . $new_id . '&phm_msg=' . $msg ) );
		exit;
	}

	public static function handle_delete_plan() {
		if ( ! current_user_can( PHM_Admin::capability() ) ) {
			wp_die( esc_html__( 'Permission denied.', 'pterodactyl-hosting' ) );
		}
		check_admin_referer( 'phm_delete_plan' );
		PHM_DB::delete_product( isset( $_GET['id'] ) ? (int) $_GET['id'] : 0 );
		wp_safe_redirect( admin_url( 'admin.php?page=phm-products&phm_msg=deleted' ) );
		exit;
	}

	public static function handle_toggle_plan() {
		if ( ! current_user_can( PHM_Admin::capability() ) ) {
			wp_die( esc_html__( 'Permission denied.', 'pterodactyl-hosting' ) );
		}
		check_admin_referer( 'phm_toggle_plan' );
		$id      = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		$product = PHM_DB::get_product( $id );
		if ( $product ) {
			PHM_DB::save_product( [ 'active' => $product->active ? 0 : 1 ], $id );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=phm-products' ) );
		exit;
	}

	/**
	 * Price label helper shared by admin + frontend.
	 */
	public static function format_price( $amount, $currency ) {
		$symbol = 'USD' === strtoupper( $currency ) ? '$' : '';
		$label  = $symbol . number_format( (float) $amount, fmod( (float) $amount, 1 ) ? 2 : 0 );
		return $symbol ? $label : $label . ' ' . strtoupper( $currency );
	}

	/**
	 * Human RAM/disk label. Plans under 1 GB used to render as "0 GB".
	 */
	public static function format_memory( $mb ) {
		$mb = (float) $mb;
		if ( $mb >= 1024 ) {
			$gb = $mb / 1024;
			$label = fmod( $gb, 1 ) ? number_format( $gb, 1 ) : (string) (int) $gb;
			return $label . ' GB';
		}
		return (string) (int) $mb . ' MB';
	}
}
