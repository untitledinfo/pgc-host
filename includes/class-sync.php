<?php
/**
 * Panel → WordPress synchronisation.
 * Pulls locations, nests, eggs (with variables) and nodes into the local
 * database so the storefront and the admin "Database Data" screen work even
 * when the panel is temporarily unreachable.
 *
 * @package Pterodactyl_Hosting
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PHM_Sync {

	/**
	 * @return array|WP_Error Counts per entity or WP_Error on failure.
	 */
	public static function sync_all() {
		if ( ! PHM_Settings::is_configured() ) {
			return new WP_Error( 'phm_not_configured', __( 'Configure the Panel URL and API key first.', 'pterodactyl-hosting' ) );
		}

		$started = microtime( true );
		$counts  = [ 'locations' => 0, 'nests' => 0, 'eggs' => 0, 'nodes' => 0 ];

		// Locations ---------------------------------------------------------
		$locations = PHM_API::locations();
		if ( ! $locations['ok'] ) {
			PHM_DB::log( 'error', 'Sync failed at locations: ' . $locations['error'] );
			return new WP_Error( 'phm_sync_locations', $locations['error'] );
		}
		foreach ( $locations['data'] as $loc ) {
			PHM_DB::upsert_location( [
				'location_id'      => (int) $loc['id'],
				'short'            => isset( $loc['short'] ) ? (string) $loc['short'] : '',
				'long_description' => isset( $loc['long'] ) ? (string) $loc['long'] : '',
			] );
			$counts['locations']++;
		}

		// Nests + eggs ------------------------------------------------------
		$nests = PHM_API::nests();
		if ( ! $nests['ok'] ) {
			PHM_DB::log( 'error', 'Sync failed at nests: ' . $nests['error'] );
			return new WP_Error( 'phm_sync_nests', $nests['error'] );
		}
		foreach ( $nests['data'] as $nest ) {
			$nest_id = (int) $nest['id'];
			PHM_DB::upsert_nest( [
				'nest_id'     => $nest_id,
				'name'        => isset( $nest['name'] ) ? (string) $nest['name'] : '',
				'description' => isset( $nest['description'] ) ? (string) $nest['description'] : '',
			] );
			$counts['nests']++;

			$eggs = PHM_API::nest_eggs( $nest_id );
			if ( ! $eggs['ok'] ) {
				PHM_DB::log( 'warning', sprintf( 'Egg sync failed for nest #%d: %s', $nest_id, $eggs['error'] ) );
				continue;
			}
			foreach ( $eggs['data'] as $egg ) {
				$variables = [];
				if ( ! empty( $egg['relationships']['variables']['data'] ) ) {
					foreach ( $egg['relationships']['variables']['data'] as $var ) {
						$attr = isset( $var['attributes'] ) ? $var['attributes'] : [];
						if ( empty( $attr['env_variable'] ) ) {
							continue;
						}
						$variables[] = [
							'name'         => isset( $attr['name'] ) ? $attr['name'] : $attr['env_variable'],
							'env_variable' => $attr['env_variable'],
							'default'      => isset( $attr['default_value'] ) ? (string) $attr['default_value'] : '',
							'required'     => ! empty( $attr['user_viewable'] ),
						];
					}
				}
				PHM_DB::upsert_egg( [
					'egg_id'       => (int) $egg['id'],
					'nest_id'      => $nest_id,
					'name'         => isset( $egg['name'] ) ? (string) $egg['name'] : '',
					'description'  => isset( $egg['description'] ) ? (string) $egg['description'] : '',
					'docker_image' => isset( $egg['docker_image'] ) ? (string) $egg['docker_image'] : '',
					'startup'      => isset( $egg['startup'] ) ? (string) $egg['startup'] : '',
					'variables'    => wp_json_encode( $variables ),
				] );
				$counts['eggs']++;
			}
		}

		// Nodes -------------------------------------------------------------
		$nodes = PHM_API::nodes();
		if ( ! $nodes['ok'] ) {
			PHM_DB::log( 'error', 'Sync failed at nodes: ' . $nodes['error'] );
			return new WP_Error( 'phm_sync_nodes', $nodes['error'] );
		}
		foreach ( $nodes['data'] as $node ) {
			PHM_DB::upsert_node( [
				'node_id'             => (int) $node['id'],
				'name'                => isset( $node['name'] ) ? (string) $node['name'] : '',
				'location_id'         => isset( $node['location_id'] ) ? (int) $node['location_id'] : 0,
				'fqdn'                => isset( $node['fqdn'] ) ? (string) $node['fqdn'] : '',
				'scheme'              => isset( $node['scheme'] ) ? (string) $node['scheme'] : 'https',
				'memory'              => isset( $node['memory'] ) ? (int) $node['memory'] : 0,
				'memory_overallocate' => isset( $node['memory_overallocate'] ) ? (int) $node['memory_overallocate'] : 0,
				'memory_used'         => isset( $node['allocated_resources']['memory'] ) ? (int) $node['allocated_resources']['memory'] : 0,
				'disk'                => isset( $node['disk'] ) ? (int) $node['disk'] : 0,
				'disk_overallocate'   => isset( $node['disk_overallocate'] ) ? (int) $node['disk_overallocate'] : 0,
				'disk_used'           => isset( $node['allocated_resources']['disk'] ) ? (int) $node['allocated_resources']['disk'] : 0,
				'is_public'           => ! empty( $node['public'] ) ? 1 : 0,
			] );
			$counts['nodes']++;
		}

		$elapsed = round( microtime( true ) - $started, 2 );
		update_option( 'phm_last_sync', current_time( 'mysql' ) );
		update_option( 'phm_last_sync_counts', $counts );
		PHM_DB::log( 'success', sprintf(
			'Sync completed in %ss — %d locations, %d nests, %d eggs, %d nodes.',
			$elapsed, $counts['locations'], $counts['nests'], $counts['eggs'], $counts['nodes']
		) );

		return $counts;
	}

	public static function last_sync_human() {
		$last = get_option( 'phm_last_sync' );
		if ( ! $last ) {
			return __( 'Never', 'pterodactyl-hosting' );
		}
		return sprintf( '%s (%s)', $last, human_time_diff( strtotime( $last ), current_time( 'timestamp' ) ) . ' ' . __( 'ago', 'pterodactyl-hosting' ) );
	}
}
