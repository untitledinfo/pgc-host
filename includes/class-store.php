<?php
/**
 * Storefront pages: auto-creates [phm_plans], [phm_order] and [phm_track]
 * pages on activation so the store "just works" — plans are visible and the
 * Configure & Order buttons have somewhere real to go.
 *
 * @package Pterodactyl_Hosting
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PHM_Store {

	const OPTION = 'phm_store_pages';

	/**
	 * @return array<string,int> shortcode => page_id
	 */
	public static function pages() {
		$pages = get_option( self::OPTION, [] );
		return is_array( $pages ) ? $pages : [];
	}

	/**
	 * URL of the page holding a shortcode (already-created page wins,
	 * auto-created fallback second).
	 */
	public static function page_url( $shortcode ) {
		$pages = self::pages();
		if ( ! empty( $pages[ $shortcode ] ) && 'publish' === get_post_status( (int) $pages[ $shortcode ] ) ) {
			return get_permalink( (int) $pages[ $shortcode ] );
		}
		return '';
	}

	/**
	 * Create the three store pages if they don't exist yet. Idempotent —
	 * never duplicates, and reuses any page that already has the shortcode.
	 *
	 * @return array<string,int> shortcode => page_id
	 */
	public static function ensure_pages() {
		$defs = [
			'phm_plans'         => [ __( 'Game Server Plans', 'pterodactyl-hosting' ), '[phm_plans]' ],
			'phm_order'         => [ __( 'Order a Server', 'pterodactyl-hosting' ), '[phm_order]' ],
			'phm_track'         => [ __( 'Track My Order', 'pterodactyl-hosting' ), '[phm_track]' ],
			'phm_dashboard'     => [ __( 'My Account', 'pterodactyl-hosting' ), '[phm_dashboard]' ],
			'phm_ticket_create' => [ __( 'Open Support Ticket', 'pterodactyl-hosting' ), '[phm_ticket_create]' ],
		];

		$pages   = self::pages();
		$changed = false;

		foreach ( $defs as $shortcode => $conf ) {
			list( $title, $content ) = $conf;

			// 1. Known option.
			if ( ! empty( $pages[ $shortcode ] ) && 'publish' === get_post_status( (int) $pages[ $shortcode ] ) ) {
				continue;
			}

			// 2. Re-use any existing page containing the shortcode.
			$existing = self::find_page_with_shortcode( $shortcode );
			if ( $existing ) {
				$pages[ $shortcode ] = $existing;
				$changed = true;
				continue;
			}

			// 3. Create a fresh page.
			$page_id = wp_insert_post( [
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_name'    => sanitize_title( $title ),
				'post_content' => $content,
			] );
			if ( $page_id && ! is_wp_error( $page_id ) ) {
				$pages[ $shortcode ] = (int) $page_id;
				$changed = true;
			}
		}

		if ( $changed ) {
			update_option( self::OPTION, $pages );
		}
		return $pages;
	}

	/**
	 * @return int Page ID or 0.
	 */
	private static function find_page_with_shortcode( $shortcode ) {
		$cached = get_transient( 'phm_page_' . $shortcode );
		if ( false !== $cached ) {
			return (int) $cached;
		}
		$found = 0;
		foreach ( get_posts( [ 'post_type' => 'page', 'posts_per_page' => 100, 'fields' => 'ids', 'post_status' => 'publish' ] ) as $page_id ) {
			if ( has_shortcode( get_post_field( 'post_content', $page_id ), $shortcode ) ) {
				$found = (int) $page_id;
				break;
			}
		}
		set_transient( 'phm_page_' . $shortcode, $found, DAY_IN_SECONDS );
		return $found;
	}
}
