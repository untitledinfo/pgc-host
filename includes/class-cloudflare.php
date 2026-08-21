<?php
/**
 * Cloudflare DNS automation — creates A + SRV records for customer subdomains
 * so a Minecraft server is reachable as play.example.com without a port.
 *
 * @package Pterodactyl_Hosting
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PHM_Cloudflare {

	const API = 'https://api.cloudflare.com/client/v4/';

	public static function enabled() {
		$s = PHM_Settings::get();
		if ( empty( $s['cf_enabled'] ) || empty( $s['cf_base_domain'] ) ) {
			return false;
		}
		if ( '' === PHM_Settings::cf_token() ) {
			return false;
		}
		if ( 'global' === ( isset( $s['cf_auth_type'] ) ? $s['cf_auth_type'] : 'token' ) && empty( $s['cf_api_email'] ) ) {
			return false;
		}
		return true;
	}

	public static function base_domain() {
		$s = PHM_Settings::get();
		return $s['cf_base_domain'];
	}

	/**
	 * Zone ID from settings; if empty, resolve + remember it from the base
	 * domain so the user only has to paste a token + domain.
	 */
	public static function zone_id() {
		$s = PHM_Settings::get();
		if ( ! empty( $s['cf_zone_id'] ) ) {
			return $s['cf_zone_id'];
		}
		if ( empty( $s['cf_base_domain'] ) ) {
			return '';
		}
		$resolved = self::resolve_zone_id( $s['cf_base_domain'] );
		if ( ! is_wp_error( $resolved ) ) {
			PHM_Settings::update( [ 'cf_zone_id' => $resolved ] );
			return $resolved;
		}
		return '';
	}

	/**
	 * Look up a zone ID by domain name via the Cloudflare API.
	 *
	 * @return string|WP_Error
	 */
	public static function resolve_zone_id( $domain ) {
		$domain = strtolower( trim( (string) $domain, " ." ) );
		if ( '' === $domain ) {
			return new WP_Error( 'phm_cf_domain', __( 'Enter a base domain first.', 'pterodactyl-hosting' ) );
		}
		$res = self::request( 'GET', 'zones?name=' . rawurlencode( $domain ) . '&status=active' );
		if ( ! $res['ok'] || ! is_array( $res['data'] ) || ! count( $res['data'] ) ) {
			// Sub-domain entered? Walk up the labels until a zone matches.
			$parts = explode( '.', $domain );
			while ( count( $parts ) > 2 ) {
				array_shift( $parts );
				$parent = implode( '.', $parts );
				$res    = self::request( 'GET', 'zones?name=' . rawurlencode( $parent ) . '&status=active' );
				if ( $res['ok'] && is_array( $res['data'] ) && count( $res['data'] ) ) {
					break;
				}
			}
		}
		if ( ! $res['ok'] ) {
			return new WP_Error( 'phm_cf_auth', $res['error'] );
		}
		if ( ! is_array( $res['data'] ) || empty( $res['data'][0]['id'] ) ) {
			return new WP_Error( 'phm_cf_zone', sprintf(
				/* translators: %s: domain */
				__( 'No active Cloudflare zone found for %s — is the domain added to this Cloudflare account?', 'pterodactyl-hosting' ),
				$domain
			) );
		}
		return (string) $res['data'][0]['id'];
	}

	/**
	 * Cloudflare supports two credential types — picked in settings:
	 *  - "token":  scoped API Token    → Authorization: Bearer <token>  (recommended)
	 *  - "global": Global API Key      → X-Auth-Email + X-Auth-Key headers
	 *
	 * @return array<string,string>
	 */
	private static function auth_headers() {
		$s    = PHM_Settings::get();
		$type = isset( $s['cf_auth_type'] ) ? $s['cf_auth_type'] : 'token';
		if ( 'global' === $type && ! empty( $s['cf_api_email'] ) ) {
			return [
				'X-Auth-Email' => (string) $s['cf_api_email'],
				'X-Auth-Key'   => PHM_Settings::cf_token(),
				'Content-Type' => 'application/json',
			];
		}
		return [
			'Authorization' => 'Bearer ' . PHM_Settings::cf_token(),
			'Content-Type'  => 'application/json',
		];
	}

	/**
	 * @return array{ok:bool,data:mixed,error:string}
	 */
	private static function request( $method, $endpoint, $body = null ) {
		$args = [
			'method'  => strtoupper( $method ),
			'timeout' => 30,
			'headers' => self::auth_headers(),
		];
		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}
		$res = wp_remote_request( self::API . ltrim( $endpoint, '/' ), $args );
		if ( is_wp_error( $res ) ) {
			return [ 'ok' => false, 'data' => null, 'error' => $res->get_error_message() ];
		}
		$data = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( ! empty( $data['success'] ) ) {
			return [ 'ok' => true, 'data' => isset( $data['result'] ) ? $data['result'] : null, 'error' => '' ];
		}
		$err = '';
		if ( ! empty( $data['errors'][0]['message'] ) ) {
			$err = $data['errors'][0]['message'];
		} else {
			$err = sprintf( 'Cloudflare HTTP %d', (int) wp_remote_retrieve_response_code( $res ) );
		}
		return [ 'ok' => false, 'data' => $data, 'error' => $err ];
	}

	/**
	 * Check whether a DNS record already exists for the given FQDN.
	 */
	public static function record_exists( $fqdn ) {
		$zone = self::zone_id();
		if ( ! $zone ) {
			return false;
		}
		$res = self::request( 'GET', 'zones/' . $zone . '/dns_records?name=' . rawurlencode( strtolower( $fqdn ) ) );
		if ( ! $res['ok'] || ! is_array( $res['data'] ) ) {
			return false;
		}
		return count( $res['data'] ) > 0;
	}

	/**
	 * Create the address record (+ optional Minecraft SRV record) for a
	 * subdomain. Record type is chosen automatically:
	 *   - target is an IPv4 address → A record
	 *   - target is a hostname      → CNAME record
	 *
	 * @return array{ok:bool,records:array,error:string}
	 */
	public static function create_subdomain( $subdomain, $ip, $port = 0, $create_srv = true ) {
		$zone      = self::zone_id();
		$subdomain = strtolower( trim( (string) $subdomain ) );
		$base      = self::base_domain();
		$fqdn      = $subdomain . '.' . $base;
		$records   = [];

		if ( ! $zone ) {
			return [ 'ok' => false, 'records' => [], 'error' => __( 'Cloudflare zone could not be resolved — check credentials + base domain.', 'pterodactyl-hosting' ) ];
		}

		$s       = PHM_Settings::get();
		$proxied = ! empty( $s['cf_proxied'] );
		// A records for game traffic should stay DNS-only (grey cloud);
		// the proxied option exists for web-facing (CNAME/HTTP) use cases.
		$is_ip   = (bool) filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 );
		$type    = $is_ip ? 'A' : 'CNAME';
		$content = $is_ip ? $ip : rtrim( (string) $ip, '.' );

		if ( ! $content ) {
			return [ 'ok' => false, 'records' => [], 'error' => __( 'Missing target IP/hostname for the DNS record.', 'pterodactyl-hosting' ) ];
		}

		$main = self::request( 'POST', 'zones/' . $zone . '/dns_records', [
			'type'    => $type,
			'name'    => $fqdn,
			'content' => $content,
			'ttl'     => 1, // Auto.
			'proxied' => $proxied,
		] );
		if ( ! $main['ok'] ) {
			return [ 'ok' => false, 'records' => $records, 'error' => $main['error'] ];
		}
		$records[] = [ 'id' => isset( $main['data']['id'] ) ? $main['data']['id'] : '', 'type' => $type, 'name' => $fqdn ];

		// Minecraft SRV record: _minecraft._tcp.subdomain → target + port.
		// Lets players connect without typing the port. Works on CF free plan.
		if ( $create_srv && $port && 25565 !== (int) $port ) {
			$srv = self::request( 'POST', 'zones/' . $zone . '/dns_records', [
				'type' => 'SRV',
				'name' => '_minecraft._tcp.' . $fqdn,
				'data' => [
					'service'  => '_minecraft',
					'proto'    => '_tcp',
					'name'     => $fqdn,
					'priority' => 0,
					'weight'   => 5,
					'port'     => (int) $port,
					'target'   => $fqdn,
				],
				'ttl'  => 1,
			] );
			if ( $srv['ok'] ) {
				$records[] = [ 'id' => isset( $srv['data']['id'] ) ? $srv['data']['id'] : '', 'type' => 'SRV', 'name' => '_minecraft._tcp.' . $fqdn ];
			}
		}

		return [ 'ok' => true, 'records' => $records, 'error' => '' ];
	}

	/**
	 * Delete previously created records.
	 *
	 * @param array $records List of ['id'=>...] rows saved on the order.
	 */
	public static function delete_records( array $records ) {
		$zone = self::zone_id();
		if ( ! $zone ) {
			return;
		}
		foreach ( $records as $record ) {
			if ( ! empty( $record['id'] ) ) {
				self::request( 'DELETE', 'zones/' . $zone . '/dns_records/' . rawurlencode( (string) $record['id'] ) );
			}
		}
	}

	public static function test() {
		$s    = PHM_Settings::get();
		$zone = self::zone_id();
		if ( ! $zone ) {
			return [ 'ok' => false, 'message' => __( 'Zone could not be resolved — check credentials + base domain.', 'pterodactyl-hosting' ) ];
		}
		$res = self::request( 'GET', 'zones/' . $zone );
		if ( $res['ok'] && ! empty( $res['data']['name'] ) ) {
			$type = ( 'global' === ( isset( $s['cf_auth_type'] ) ? $s['cf_auth_type'] : 'token' ) ) ? __( 'Global API key', 'pterodactyl-hosting' ) : __( 'API token', 'pterodactyl-hosting' );
			return [ 'ok' => true, 'message' => sprintf(
				/* translators: 1: zone name, 2: credential type */
				__( 'Zone OK: %1$s (%2$s)', 'pterodactyl-hosting' ),
				$res['data']['name'], $type
			) ];
		}
		return [ 'ok' => false, 'message' => $res['error'] ];
	}
}
