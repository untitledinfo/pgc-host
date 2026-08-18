<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Thin wrapper around the Pterodactyl Application API (server-side, admin key)
 * and the Client API (per-user, used for power actions / resource usage).
 */
class Ptero_API {

	private $panel_url;
	private $app_key;   // Application API key (ptla_...)
	private $client_key; // Optional client API key (ptlc_...) for admin-level client calls

	public function __construct() {
		$this->panel_url  = untrailingslashit( get_option( 'ptero_panel_url', '' ) );
		$this->app_key    = get_option( 'ptero_app_api_key', '' );
		$this->client_key = get_option( 'ptero_client_api_key', '' );
	}

	public function is_configured() {
		return ! empty( $this->panel_url ) && ! empty( $this->app_key );
	}

	// ---- Low level request helpers ---------------------------------------

	private function request( $method, $endpoint, $body = null, $use_client_key = false ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'ptero_not_configured', __( 'Pterodactyl API is not configured yet.', 'ptero-host' ) );
		}

		$key = $use_client_key && $this->client_key ? $this->client_key : $this->app_key;

		$args = array(
			'method'  => $method,
			'timeout' => 20,
			'headers' => array(
				'Authorization' => 'Bearer ' . $key,
				'Accept'        => 'Application/vnd.pterodactyl.v1+json',
				'Content-Type'  => 'application/json',
			),
		);

		if ( $body !== null ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( $this->panel_url . $endpoint, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$raw  = wp_remote_retrieve_body( $response );
		$data = json_decode( $raw, true );

		if ( $code >= 400 ) {
			$msg = isset( $data['errors'][0]['detail'] ) ? $data['errors'][0]['detail'] : 'Pterodactyl API error (' . $code . ')';
			return new WP_Error( 'ptero_api_error', $msg, array( 'status' => $code, 'raw' => $data ) );
		}

		return $data;
	}

	public function test_connection() {
		return $this->request( 'GET', '/api/application/nodes?per_page=1' );
	}

	// ---- Locations / Nodes / Nests / Eggs ---------------------------------

	public function get_locations() {
		$res = $this->request( 'GET', '/api/application/locations' );
		if ( is_wp_error( $res ) ) return $res;
		return $res['data'] ?? array();
	}

	public function get_nodes( $location_id = null ) {
		$res = $this->request( 'GET', '/api/application/nodes?include=allocations,location' );
		if ( is_wp_error( $res ) ) return $res;
		$nodes = $res['data'] ?? array();
		if ( $location_id ) {
			$nodes = array_filter( $nodes, function ( $n ) use ( $location_id ) {
				return (int) $n['attributes']['location_id'] === (int) $location_id;
			} );
		}
		return array_values( $nodes );
	}

	/** Returns free RAM/CPU/Disk for a node so we never oversell. */
	public function get_node_capacity( $node_id ) {
		$node = $this->request( 'GET', "/api/application/nodes/{$node_id}?include=allocations" );
		if ( is_wp_error( $node ) ) return $node;
		$a = $node['attributes'];
		return array(
			'memory_total'     => $a['memory'],
			'memory_allocated' => $a['allocated_resources']['memory'] ?? 0,
			'disk_total'       => $a['disk'],
			'disk_allocated'   => $a['allocated_resources']['disk'] ?? 0,
		);
	}

	public function get_nests() {
		$res = $this->request( 'GET', '/api/application/nests' );
		if ( is_wp_error( $res ) ) return $res;
		return $res['data'] ?? array();
	}

	public function get_eggs( $nest_id ) {
		$res = $this->request( 'GET', "/api/application/nests/{$nest_id}/eggs?include=variables" );
		if ( is_wp_error( $res ) ) return $res;
		return $res['data'] ?? array();
	}

	/** Find a free allocation (IP:port) on a node, optionally requesting a dedicated IP. */
	public function find_free_allocation( $node_id, $dedicated_ip = false ) {
		$res = $this->request( 'GET', "/api/application/nodes/{$node_id}/allocations?per_page=200" );
		if ( is_wp_error( $res ) ) return $res;
		$free = array_values( array_filter( $res['data'] ?? array(), function ( $alloc ) {
			return empty( $alloc['attributes']['assigned'] );
		} ) );
		if ( empty( $free ) ) {
			return new WP_Error( 'no_allocation', __( 'No free ports available on this node.', 'ptero-host' ) );
		}
		return $free[0]['attributes']['id'];
	}

	// ---- Users --------------------------------------------------------------

	public function find_or_create_user( WP_User $wp_user ) {
		$existing = $this->request( 'GET', '/api/application/users?filter[email]=' . rawurlencode( $wp_user->user_email ) );
		if ( ! is_wp_error( $existing ) && ! empty( $existing['data'] ) ) {
			return $existing['data'][0]['attributes']['id'];
		}

		$created = $this->request( 'POST', '/api/application/users', array(
			'email'      => $wp_user->user_email,
			'username'   => sanitize_user( $wp_user->user_login ),
			'first_name' => $wp_user->first_name ?: $wp_user->display_name,
			'last_name'  => $wp_user->last_name ?: '.',
			'password'   => wp_generate_password( 16 ),
		) );

		if ( is_wp_error( $created ) ) return $created;
		return $created['attributes']['id'];
	}

	// ---- Servers --------------------------------------------------------------

	public function create_server( array $args ) {
		/**
		 * $args: name, user (ptero user id), egg, docker_image, startup, environment[],
		 * memory, swap, disk, io, cpu, allocation_id, databases, backups
		 */
		$payload = array(
			'name'        => $args['name'],
			'user'        => $args['user'],
			'egg'         => $args['egg'],
			'docker_image'=> $args['docker_image'],
			'startup'     => $args['startup'],
			'environment' => $args['environment'],
			'limits'      => array(
				'memory' => $args['memory'],
				'swap'   => $args['swap'] ?? 0,
				'disk'   => $args['disk'],
				'io'     => $args['io'] ?? 500,
				'cpu'    => $args['cpu'],
			),
			'feature_limits' => array(
				'databases' => $args['databases'] ?? 0,
				'backups'   => $args['backups'] ?? 0,
				'allocations' => 1,
			),
			'allocation' => array(
				'default' => $args['allocation_id'],
			),
			'start_on_completion' => true,
		);

		return $this->request( 'POST', '/api/application/servers', $payload );
	}

	public function suspend_server( $ptero_server_id ) {
		return $this->request( 'POST', "/api/application/servers/{$ptero_server_id}/suspend" );
	}

	public function unsuspend_server( $ptero_server_id ) {
		return $this->request( 'POST', "/api/application/servers/{$ptero_server_id}/unsuspend" );
	}

	public function delete_server( $ptero_server_id ) {
		return $this->request( 'DELETE', "/api/application/servers/{$ptero_server_id}" );
	}

	public function update_server_build( $ptero_server_id, $memory, $cpu, $disk, $allocation_id = null ) {
		$payload = array(
			'allocation' => $allocation_id,
			'memory'     => $memory,
			'swap'       => 0,
			'disk'       => $disk,
			'io'         => 500,
			'cpu'        => $cpu,
			'feature_limits' => array( 'databases' => 5, 'backups' => 5, 'allocations' => 1 ),
		);
		return $this->request( 'PATCH', "/api/application/servers/{$ptero_server_id}/build", $payload );
	}

	// ---- Client API (per-server live stats + power) --------------------------

	public function get_resource_usage( $server_identifier ) {
		return $this->request( 'GET', "/api/client/servers/{$server_identifier}/resources", null, true );
	}

	public function send_power_action( $server_identifier, $signal ) {
		// signal: start | stop | restart | kill
		return $this->request( 'POST', "/api/client/servers/{$server_identifier}/power", array( 'signal' => $signal ), true );
	}
}
