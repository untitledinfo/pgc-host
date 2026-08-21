<?php
/**
 * Pterodactyl Application API client.
 *
 * @package Pterodactyl_Hosting
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PHM_API {

	const BASE = '/api/application';

	/**
	 * Perform a request against the panel application API.
	 *
	 * @return array{ok:bool,status:int,data:mixed,error:string}
	 */
	public static function request( $method, $endpoint, $body = null ) {
		$panel = PHM_Settings::panel_url();
		$key   = PHM_Settings::app_key();

		if ( ! $panel || ! $key ) {
			return self::result( false, 0, null, __( 'Panel URL or API key is not configured.', 'pterodactyl-hosting' ) );
		}

		$url = $panel . self::BASE . '/' . ltrim( (string) $endpoint, '/' );
		$args = [
			'method'  => strtoupper( $method ),
			'timeout' => 45,
			'headers' => [
				'Authorization' => 'Bearer ' . $key,
				'Accept'        => 'application/json',
				'Content-Type'  => 'application/json',
			],
		];
		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			return self::result( false, 0, null, $response->get_error_message() );
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$raw    = wp_remote_retrieve_body( $response );
		$data   = json_decode( $raw, true );

		if ( $status >= 200 && $status < 300 ) {
			return self::result( true, $status, $data, '' );
		}

		$message = self::extract_error( $data, $status );
		return self::result( false, $status, $data, $message );
	}

	private static function result( $ok, $status, $data, $error ) {
		return [ 'ok' => $ok, 'status' => $status, 'data' => $data, 'error' => $error ];
	}

	private static function extract_error( $data, $status ) {
		if ( is_array( $data ) && ! empty( $data['errors'][0]['detail'] ) ) {
			return (string) $data['errors'][0]['detail'];
		}
		$map = [
			401 => __( 'API key invalid or missing permission (401 Unauthorized).', 'pterodactyl-hosting' ),
			403 => __( 'API key lacks the required permissions (403 Forbidden). Use an Application API key with full scope.', 'pterodactyl-hosting' ),
			404 => __( 'Not found (404). Check the panel URL.', 'pterodactyl-hosting' ),
			500 => __( 'Panel server error (500).', 'pterodactyl-hosting' ),
		];
		return isset( $map[ $status ] ) ? $map[ $status ] : sprintf( 'HTTP %d', $status );
	}

	/**
	 * Fetch a list endpoint, following pagination automatically.
	 *
	 * @return array{ok:bool,status:int,data:array,error:string}
	 */
	public static function paginate( $endpoint ) {
		$sep   = ( false === strpos( $endpoint, '?' ) ) ? '?' : '&';
		$page  = 1;
		$items = [];
		$first = null;

		do {
			$res = self::request( 'GET', $endpoint . $sep . 'page=' . $page . '&per_page=50' );
			if ( ! $res['ok'] ) {
				return [ 'ok' => false, 'status' => $res['status'], 'data' => $items, 'error' => $res['error'] ];
			}
			if ( null === $first ) {
				$first = $res['data'];
			}
			if ( ! empty( $res['data']['data'] ) && is_array( $res['data']['data'] ) ) {
				foreach ( $res['data']['data'] as $row ) {
					$items[] = isset( $row['attributes'] ) ? $row['attributes'] : $row;
				}
			}
			$last  = isset( $res['data']['meta']['pagination']['total_pages'] ) ? (int) $res['data']['meta']['pagination']['total_pages'] : 1;
			$page++;
		} while ( $page <= $last && $page <= 40 ); // hard cap for safety.

		return [ 'ok' => true, 'status' => $res['status'], 'data' => $items, 'error' => '' ];
	}

	/* ------------------------- Simple endpoints ------------------------- */

	/**
	 * Account details. Route layout differs between panel versions/forks:
	 *  - some expose GET /api/application/account
 	 *  - some only expose GET /api/application (account at the API root)
	 *  - Pterodactyl 0.7-style forks expose neither
	 * We try them in order and treat "route not found" as non-fatal.
	 */
	public static function account() {
		$res = self::request( 'GET', 'account' );
		if ( ! $res['ok'] && 404 === $res['status'] ) {
			$res = self::request( 'GET', '/' ); // account at application root.
		}
		return $res;
	}

	public static function locations() {
		return self::paginate( 'locations' );
	}

	public static function nests() {
		return self::paginate( 'nests' );
	}

	public static function nest_eggs( $nest_id ) {
		return self::paginate( 'nests/' . (int) $nest_id . '/eggs?include=variables' );
	}

	public static function nodes() {
		return self::paginate( 'nodes' );
	}

	public static function node_allocations( $node_id ) {
		return self::paginate( 'nodes/' . (int) $node_id . '/allocations' );
	}

	public static function servers() {
		return self::paginate( 'servers' );
	}

	public static function find_user_by_email( $email ) {
		$res = self::request( 'GET', 'users?filter[email]=' . rawurlencode( $email ) );
		if ( $res['ok'] && ! empty( $res['data']['data'][0]['attributes'] ) ) {
			return $res['data']['data'][0]['attributes'];
		}
		return null;
	}

	public static function create_user( $email, $username, $password = '' ) {
		return self::request( 'POST', 'users', [
			'email'      => $email,
			'username'   => $username,
			'first_name' => $username,
			'last_name'  => 'Customer',
			'password'   => '' !== $password ? $password : wp_generate_password( 16, true, true ),
		] );
	}

	/**
	 * Fetch a single panel user by their Pterodactyl user ID.
	 */
	public static function get_user( $ptero_user_id ) {
		return self::request( 'GET', 'users/' . (int) $ptero_user_id );
	}

	/**
	 * Set a new password on an existing panel account (used to keep the
	 * panel password in sync with WordPress after a "Forgot password"
	 * reset / profile change, and to mint a fresh one-time password for
	 * the one-click panel access flow).
	 *
	 * Pterodactyl's user-update endpoint validates the FULL user object on
	 * PATCH (not just the changed field), so we fetch the account first and
	 * resend its existing email/username/names alongside the new password —
	 * otherwise the API rejects the request or silently blanks the other
	 * fields depending on panel version.
	 *
	 * @return true|WP_Error
	 */
	public static function update_user_password( $ptero_user_id, $password ) {
		$current = self::get_user( $ptero_user_id );
		if ( ! $current['ok'] || empty( $current['data']['attributes'] ) ) {
			return new WP_Error( 'phm_panel_user_missing', $current['error'] ?: __( 'Panel account not found.', 'pterodactyl-hosting' ) );
		}
		$a = $current['data']['attributes'];
		$res = self::request( 'PATCH', 'users/' . (int) $ptero_user_id, [
			'email'      => $a['email'],
			'username'   => $a['username'],
			'first_name' => $a['first_name'],
			'last_name'  => $a['last_name'],
			'password'   => (string) $password,
		] );
		if ( ! $res['ok'] ) {
			return new WP_Error( 'phm_panel_password_sync_failed', $res['error'] );
		}
		return true;
	}

	public static function create_server( array $payload ) {
		return self::request( 'POST', 'servers', $payload );
	}

	public static function suspend_server( $server_id ) {
		return self::request( 'POST', 'servers/' . (int) $server_id . '/suspend' );
	}

	public static function unsuspend_server( $server_id ) {
		return self::request( 'POST', 'servers/' . (int) $server_id . '/unsuspend' );
	}

	public static function delete_server( $server_id, $force = false ) {
		return self::request( 'DELETE', 'servers/' . (int) $server_id . ( $force ? '/force' : '' ) );
	}

	/* ------------------------- Connection test -------------------------- */

	/**
	 * Connection test that does NOT depend on a single route.
	 * Probes the core list endpoints (users, nodes, locations, nests,
	 * servers) — those exist on every Pterodactyl 1.x-compatible panel.
	 * The account route (missing on some panels, e.g. the reported
	 * "The route api/application/account could not be found") is optional
	 * and only used for the display name.
	 *
	 * @return array{ok:bool,message:string,detail:array}
	 */
	public static function test_connection() {
		$probes  = [ 'users', 'nodes', 'locations', 'nests', 'servers' ];
		$detail  = [];
		$working = 0;
		$errors  = [];

		foreach ( $probes as $endpoint ) {
			$res = self::request( 'GET', $endpoint . '?per_page=1' );
			if ( $res['ok'] && isset( $res['data']['meta']['pagination']['total'] ) ) {
				$detail[ $endpoint ] = (int) $res['data']['meta']['pagination']['total'];
				$working++;
			} else {
				$detail[ $endpoint ] = -1;
				if ( $res['error'] ) {
					$errors[ $res['status'] ] = $res['error'];
				}
			}
		}

		if ( 0 === $working ) {
			// Prefer the most actionable error (auth/permission first).
			$message = '';
			foreach ( [ 401, 403, 404, 500, 0 ] as $code ) {
				if ( isset( $errors[ $code ] ) ) {
					$message = $errors[ $code ];
					break;
				}
			}
			return [ 'ok' => false, 'message' => $message ?: __( 'No endpoints reachable — check the panel URL.', 'pterodactyl-hosting' ), 'detail' => $detail ];
		}

		// Optional: account route for the display name (never fatal).
		$email   = '';
		$account = self::account();
		if ( $account['ok'] && ! empty( $account['data']['attributes']['email'] ) ) {
			$email = $account['data']['attributes']['email'];
		}

		$message = $email
			/* translators: %s: account email */
			? sprintf( __( 'Connected as %s', 'pterodactyl-hosting' ), $email )
			: __( 'Connected (API key accepted)', 'pterodactyl-hosting' );

		if ( $working < count( $probes ) ) {
			$message .= ' — ' . sprintf(
				/* translators: 1: working scope count, 2: total scopes */
				__( 'warning: only %1$d/%2$d scopes readable, create the key with ALL scopes', 'pterodactyl-hosting' ),
				$working, count( $probes )
			);
		}

		return [ 'ok' => true, 'message' => $message, 'detail' => $detail ];
	}
}
