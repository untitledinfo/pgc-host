<?php
/**
 * Automatic server deployment ("auto provisioning").
 * Creates the panel user (if new), picks a node with capacity in the plan's
 * location, reserves an allocation, creates the server from the selected egg
 * (Minecraft / Paper / any egg), then writes the Cloudflare DNS records for
 * the ordered subdomain — exactly like a Paymenter auto-setup flow.
 *
 * @package Pterodactyl_Hosting
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PHM_Provisioning {

	/**
	 * Ordered deploy stages: key => [ label, percent ]. Drives both the
	 * order-page progress bar (via phm_order_status) and the admin order
	 * list. 'queued' is written the moment an order is scheduled for
	 * deploy, before the background event has actually picked it up.
	 *
	 * A method (not a class const) because __() isn't a constant expression
	 * — const arrays in PHP can't hold translated strings.
	 *
	 * @return array<string,array{0:string,1:int}>
	 */
	public static function stages() {
		return [
			'queued'     => [ __( 'Queued for deployment', 'pterodactyl-hosting' ), 5 ],
			'account'    => [ __( 'Setting up your panel account', 'pterodactyl-hosting' ), 20 ],
			'node'       => [ __( 'Finding a server with free capacity', 'pterodactyl-hosting' ), 40 ],
			'allocation' => [ __( 'Reserving a port', 'pterodactyl-hosting' ), 55 ],
			'creating'   => [ __( 'Creating your game server', 'pterodactyl-hosting' ), 75 ],
			'dns'        => [ __( 'Pointing your subdomain at the server', 'pterodactyl-hosting' ), 90 ],
			'done'       => [ __( 'Done', 'pterodactyl-hosting' ), 100 ],
		];
	}

	public static function init() {
		add_action( 'phm_deploy_order_event', [ __CLASS__, 'deploy' ] );
	}

	/**
	 * Queue a background deploy instead of running it inline in the AJAX
	 * request — lets the storefront show a live progress bar instead of
	 * one long spinner on the "Place order" button. WP-Cron fires the
	 * single event via an (almost) immediate loopback request.
	 */
	public static function queue_deploy( $order_id ) {
		self::set_stage( (int) $order_id, 'queued' );
		if ( ! wp_next_scheduled( 'phm_deploy_order_event', [ (int) $order_id ] ) ) {
			wp_schedule_single_event( time(), 'phm_deploy_order_event', [ (int) $order_id ] );
		}
		spawn_cron();
	}

	private static function set_stage( $order_id, $stage ) {
		$stages = self::stages();
		if ( isset( $stages[ $stage ] ) ) {
			PHM_DB::update_order( $order_id, [ 'stage' => $stage ] );
		}
	}

	/**
	 * Deploy a paid order. Safe to call multiple times — it no-ops when the
	 * order already has a server.
	 *
	 * @return true|WP_Error
	 */
	public static function deploy( $order_id ) {
		$order = PHM_DB::get_order( $order_id );
		if ( ! $order ) {
			return new WP_Error( 'phm_no_order', __( 'Order not found.', 'pterodactyl-hosting' ) );
		}
		if ( $order->server_id ) {
			return true; // already deployed.
		}
		if ( ! PHM_Settings::is_configured() ) {
			return self::fail( $order, __( 'Panel is not configured.', 'pterodactyl-hosting' ) );
		}

		PHM_DB::update_order( $order->id, [ 'status' => 'provisioning' ] );
		self::set_stage( $order->id, 'account' );
		PHM_DB::log( 'info', sprintf( 'Deploying order %s…', $order->order_number ) );

		// 1) Panel user ----------------------------------------------------
		$ptero_user_id = (int) $order->ptero_user_id;
		if ( ! $ptero_user_id ) {
			$user = PHM_API::find_user_by_email( $order->email );
			if ( $user && ! empty( $user['id'] ) ) {
				$ptero_user_id = (int) $user['id'];
				PHM_DB::update_order( $order->id, [
					'credential_note' => __( 'Log in to the panel with your existing panel password.', 'pterodactyl-hosting' ),
				] );
			} else {
				$username = self::username_from( $order->email, $order->customer_name );
				// Reuse the customer's WordPress password on the panel account
				// they're about to get, so they only have one password to
				// remember. Only available for a short window after they last
				// logged in/registered (see PHM_Password_Bridge) — falls back
				// to a random panel-only password otherwise, same as before.
				$password = ! empty( $order->wp_user_id ) ? PHM_Password_Bridge::consume( (int) $order->wp_user_id ) : '';
				$created  = PHM_API::create_user( $order->email, $username, $password );
				if ( ! $created['ok'] || empty( $created['data']['attributes']['id'] ) ) {
					return self::fail( $order, 'Create user failed: ' . $created['error'] );
				}
				$ptero_user_id = (int) $created['data']['attributes']['id'];
				PHM_DB::update_order( $order->id, [
					'credential_note' => $password
						? __( 'Log in to the panel with your WordPress email + password — same login, nothing new to remember.', 'pterodactyl-hosting' )
						: __( 'A panel password was generated for you — use "Forgot password" on the panel login page (with your account email) to set your own.', 'pterodactyl-hosting' ),
				] );
				PHM_DB::log(
					'info',
					$password
						? sprintf( 'Order %s: panel account created using their WordPress password.', $order->order_number )
						: sprintf( 'Order %s: panel account created with an auto-generated password (no captured WP password available — emailed separately).', $order->order_number )
				);
			}
			PHM_DB::update_order( $order->id, [ 'ptero_user_id' => $ptero_user_id ] );
		}

		// 2) Egg (game / server type — Minecraft, Paper, Forge…) -----------
		$egg = PHM_DB::get_egg( $order->egg_id );
		if ( ! $egg ) {
			return self::fail( $order, sprintf( __( 'Egg #%d missing locally — run “Sync Now” in settings.', 'pterodactyl-hosting' ), $order->egg_id ) );
		}
		$variables   = json_decode( (string) $egg->variables, true );
		$environment = [];
		if ( is_array( $variables ) ) {
			foreach ( $variables as $var ) {
				if ( empty( $var['env_variable'] ) ) {
					continue;
				}
				$default = isset( $var['default'] ) ? (string) $var['default'] : '';
				// Friendly defaults for common Minecraft-family eggs.
				if ( 'SERVER_JARFILE' === $var['env_variable'] && '' === $default ) {
					$default = 'server.jar';
				}
				if ( in_array( $var['env_variable'], [ 'MINECRAFT_VERSION', 'SERVER_VERSION', 'VANILLA_VERSION' ], true ) && '' === $default ) {
					$default = 'latest';
				}
				if ( in_array( $var['env_variable'], [ 'BUILD_NUMBER', 'PAPER_BUILD' ], true ) && '' === $default ) {
					$default = 'latest';
				}
				$environment[ $var['env_variable'] ] = $default;
			}
		}
		// SERVER_NAME only when the egg actually declares it — unknown keys are
		// stripped by the panel, but sending fewer unknown keys keeps the
		// request minimal and predictable.
		if ( isset( $environment['SERVER_NAME'] ) ) {
			$environment['SERVER_NAME'] = $order->subdomain ? $order->fqdn : ( $order->server_label ? $order->server_label : $order->plan_name );
		}

		// 3) Node with free capacity ---------------------------------------
		self::set_stage( $order->id, 'node' );
		$limits = [
			'memory' => (int) self::meta( $order, 'memory', 2048 ),
			'swap'   => (int) self::meta( $order, 'swap', 0 ),
			'disk'   => (int) self::meta( $order, 'disk', 10240 ),
			'io'     => (int) self::meta( $order, 'io', 500 ),
			'cpu'    => (int) self::meta( $order, 'cpu', 100 ),
		];
		$node = self::pick_node( (int) $order->location_id, $limits );
		if ( ! $node ) {
			return self::fail( $order, __( 'No node has enough free RAM/disk for this plan.', 'pterodactyl-hosting' ) );
		}

		// 4) Free allocation on the node -----------------------------------
		self::set_stage( $order->id, 'allocation' );
		$allocations = PHM_API::node_allocations( $node->node_id );
		if ( ! $allocations['ok'] ) {
			return self::fail( $order, 'Allocation lookup failed: ' . $allocations['error'] );
		}
		$allocation = null;
		foreach ( $allocations['data'] as $alloc ) {
			if ( empty( $alloc['assigned'] ) ) {
				$allocation = $alloc;
				break;
			}
		}
		if ( ! $allocation ) {
			return self::fail( $order, sprintf( __( 'Node %s has no free ports/allocations.', 'pterodactyl-hosting' ), $node->name ) );
		}

		// 5) Create the server ---------------------------------------------
		self::set_stage( $order->id, 'creating' );
		$server_name = $order->subdomain
			? $order->subdomain
			: ( $order->server_label ? sanitize_title( $order->server_label ) : sanitize_title( $order->plan_name . '-' . $order->id ) );
		$payload     = [
			'external_id'   => 'phm-order-' . $order->id,
			'name'          => $server_name,
			'description'   => sprintf( '%s — %s', $order->order_number, $order->email ),
			'user'          => $ptero_user_id,
			'egg'           => (int) $egg->egg_id,
			'docker_image'  => $egg->docker_image,
			'startup'       => $egg->startup,
			'environment'   => $environment,
			'skip_scripts'  => false,
			'limits'        => $limits,
			'feature_limits' => [
				'databases'   => (int) self::meta( $order, 'databases', 1 ),
				'allocations' => (int) self::meta( $order, 'extra_allocations', 0 ),
				'backups'     => (int) self::meta( $order, 'backups', 1 ),
			],
			// NOTE: we do NOT send the `deploy` object together with
			// `allocation.default` — the panel requires `deploy.locations` when
			// `deploy` is present and would reject an empty array for
			// "any location" plans. We already picked a concrete free
			// allocation on a node in the right location ourselves.
			'allocation'    => [ 'default' => (int) $allocation['id'] ],
			'start_on_completion' => true,
		];

		$created = PHM_API::create_server( $payload );
		if ( ! $created['ok'] || empty( $created['data']['attributes']['id'] ) ) {
			return self::fail( $order, 'Create server failed: ' . $created['error'] );
		}
		$server = $created['data']['attributes'];
		$ip     = isset( $allocation['ip'] ) ? $allocation['ip'] : '';
		$ip     = ( ! empty( $allocation['ip_alias'] ) ) ? $allocation['ip_alias'] : $ip;
		$port   = isset( $allocation['port'] ) ? (int) $allocation['port'] : 0;

		// 6) Cloudflare DNS (subdomain cart) --------------------------------
		$dns_records = [];
		$settings    = PHM_Settings::get();
		if ( ! empty( $order->subdomain ) && PHM_Cloudflare::enabled() ) {
			self::set_stage( $order->id, 'dns' );
			if ( ! $ip ) {
				$ip = gethostbyname( $node->fqdn );
			}
			$dns = PHM_Cloudflare::create_subdomain(
				$order->subdomain,
				$ip,
				$port,
				! empty( $settings['cf_create_srv'] )
			);
			if ( $dns['ok'] ) {
				$dns_records = $dns['records'];
				PHM_DB::log( 'success', sprintf( 'Cloudflare: %s → %s:%d', $order->fqdn, $ip, $port ) );
			} else {
				PHM_DB::log( 'warning', sprintf( 'Cloudflare DNS failed for %s: %s', $order->fqdn, $dns['error'] ) );
			}
		}

		// 7) Finalise -------------------------------------------------------
		PHM_DB::update_order( $order->id, [
			'status'            => 'active',
			'stage'             => 'done',
			'server_id'         => (int) $server['id'],
			'server_identifier' => isset( $server['identifier'] ) ? (string) $server['identifier'] : '',
			'server_ip'         => (string) $ip,
			'server_port'       => (int) $port,
			'next_due_at'       => PHM_Billing::add_period(),
			'reminder_sent'     => 0,
			'dns_records'       => wp_json_encode( $dns_records ),
			'error_message'     => '',
		] );

		PHM_DB::log( 'success', sprintf( 'Order %s deployed — server #%s.', $order->order_number, $server['id'] ) );
		PHM_Notifications::server_deployed( PHM_DB::get_order( $order->id ) );

		do_action( 'phm_server_deployed', $order->id, (int) $server['id'] );
		return true;
	}

	private static function fail( $order, $message ) {
		PHM_DB::update_order( $order->id, [
			'status'        => 'failed',
			'error_message' => $message,
		] );
		PHM_DB::log( 'error', sprintf( 'Order %s failed: %s', $order->order_number, $message ) );
		return new WP_Error( 'phm_deploy', $message );
	}

	/**
	 * Product limits live on the product row; the order stores the plan id so
	 * we read the product back (cached per request).
	 */
	private static $product_cache = [];

	private static function meta( $order, $key, $default ) {
		$pid = (int) $order->product_id;
		if ( ! array_key_exists( $pid, self::$product_cache ) ) {
			self::$product_cache[ $pid ] = PHM_DB::get_product( $pid );
		}
		$product = self::$product_cache[ $pid ];
		if ( $product && isset( $product->{$key} ) ) {
			return $product->{$key};
		}
		return $default;
	}

	/**
	 * Find a node in the requested location with enough free RAM + disk.
	 */
	private static function pick_node( $location_id, array $limits ) {
		$nodes = $location_id ? PHM_DB::get_nodes_for_location( $location_id ) : PHM_DB::get_nodes();
		foreach ( (array) $nodes as $node ) {
			if ( (int) $node->is_public !== 1 ) {
				continue;
			}
			$free_mem  = ( $node->memory + max( 0, $node->memory_overallocate ) ) - $node->memory_used;
			$free_disk = ( $node->disk + max( 0, $node->disk_overallocate ) ) - $node->disk_used;
			if ( $free_mem >= $limits['memory'] && $free_disk >= $limits['disk'] ) {
				return $node;
			}
		}
		return null;
	}

	private static function username_from( $email, $name ) {
		$base = sanitize_user( strstr( $email, '@', true ) ?: $email, true );
		if ( strlen( $base ) < 3 ) {
			$base = sanitize_user( strstr( $name . 'user', '@', true ), true );
		}
		return substr( $base, 0, 20 ) . wp_generate_password( 3, false, false );
	}

	/**
	 * Suspend / delete server for a cancelled order.
	 */
	public static function terminate( $order_id, $delete = false ) {
		$order = PHM_DB::get_order( $order_id );
		if ( ! $order || ! $order->server_id ) {
			return true;
		}
		$res = $delete ? PHM_API::delete_server( $order->server_id ) : PHM_API::suspend_server( $order->server_id );
		if ( $res['ok'] || 404 === $res['status'] ) {
			$records = json_decode( (string) $order->dns_records, true );
			if ( is_array( $records ) && $records ) {
				PHM_Cloudflare::delete_records( $records );
			}
			PHM_DB::update_order( $order->id, [
				'status'    => 'cancelled',
				'server_id' => 0,
			] );
			PHM_DB::log( 'info', sprintf( 'Order %s %s.', $order->order_number, $delete ? 'deleted' : 'suspended' ) );
			return true;
		}
		return new WP_Error( 'phm_terminate', $res['error'] );
	}
}
