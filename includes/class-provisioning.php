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
	 * Queue a background deploy. WP-Cron is unreliable on many hosts
	 * (DISABLE_WP_CRON, loopback blocked, delayed), so we:
	 *  1. schedule a single event as a backup,
	 *  2. spawn_cron() to try to fire it now,
	 *  3. also run on shutdown of the current request so free/auto
	 *     deploys actually happen even when cron never ticks.
	 */
	public static function queue_deploy( $order_id ) {
		$order_id = (int) $order_id;
		self::set_stage( $order_id, 'queued' );

		if ( ! wp_next_scheduled( 'phm_deploy_order_event', [ $order_id ] ) ) {
			wp_schedule_single_event( time(), 'phm_deploy_order_event', [ $order_id ] );
		}
		if ( function_exists( 'spawn_cron' ) ) {
			spawn_cron();
		}

		add_action( 'shutdown', static function () use ( $order_id ) {
			self::deploy( $order_id );
		}, 1 );
	}

	/**
	 * Run deploy in the current request (free plans, status poll kick).
	 * Safe to call while a queue is also pending — deploy() no-ops once
	 * a server exists and is locked against double-create.
	 *
	 * @return true|WP_Error
	 */
	public static function deploy_now( $order_id ) {
		if ( function_exists( 'ignore_user_abort' ) ) {
			ignore_user_abort( true );
		}
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 120 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
		return self::deploy( $order_id );
	}

	/**
	 * If an order is paid/queued/provisioning but still has no server,
	 * kick deploy from the status-poll AJAX request. This is the
	 * self-heal for hosts where WP-Cron never runs.
	 */
	public static function maybe_kick_deploy( $order_id ) {
		$order = PHM_DB::get_order( $order_id );
		if ( ! $order || ! empty( $order->server_id ) ) {
			return true;
		}
		$kickable = in_array( $order->status, [ 'paid', 'provisioning' ], true )
			|| 'queued' === $order->stage
			|| ( 'pending' === $order->status && (float) $order->amount <= 0 );
		if ( ! $kickable ) {
			return true;
		}
		return self::deploy_now( $order->id );
	}

	private static function lock_key( $order_id ) {
		return 'phm_deploy_lock_' . (int) $order_id;
	}

	private static function acquire_lock( $order_id ) {
		$key = self::lock_key( $order_id );
		if ( get_transient( $key ) ) {
			return false;
		}
		set_transient( $key, 1, 3 * MINUTE_IN_SECONDS );
		return true;
	}

	private static function release_lock( $order_id ) {
		delete_transient( self::lock_key( $order_id ) );
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
		if ( ! self::acquire_lock( $order->id ) ) {
			return true; // another request is already deploying this order.
		}

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 120 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
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
				if ( ( ! $created['ok'] || empty( $created['data']['attributes']['id'] ) ) && false !== stripos( (string) $created['error'], 'username' ) ) {
					$username = self::username_from( $order->email, $order->customer_name . wp_generate_password( 4, false, false ) );
					$created  = PHM_API::create_user( $order->email, $username, $password );
				}
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
		$built = self::environment_for_egg( $egg, $order );
		$environment = $built['environment'];
		if ( ! empty( $built['docker_image'] ) ) {
			$egg->docker_image = $built['docker_image'];
		}
		if ( ! empty( $built['startup'] ) ) {
			$egg->startup = $built['startup'];
		}
		if ( empty( $egg->docker_image ) || empty( $egg->startup ) ) {
			return self::fail( $order, __( 'Egg is missing a Docker image or startup command — run “Sync Now” in settings.', 'pterodactyl-hosting' ) );
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
		$final = [
			'status'            => 'active',
			'stage'             => 'done',
			'server_id'         => (int) $server['id'],
			'server_identifier' => isset( $server['identifier'] ) ? (string) $server['identifier'] : '',
			'server_ip'         => (string) $ip,
			'server_port'       => (int) $port,
			'reminder_sent'     => 0,
			'dns_records'       => wp_json_encode( $dns_records ),
			'error_message'     => '',
		];
		if ( (float) $order->amount > 0 ) {
			$final['next_due_at'] = PHM_Billing::add_period();
		}

		PHM_DB::update_order( $order->id, $final );
		self::release_lock( $order->id );

		PHM_DB::log( 'success', sprintf( 'Order %s deployed — server #%s.', $order->order_number, $server['id'] ) );
		PHM_Notifications::server_deployed( PHM_DB::get_order( $order->id ) );

		do_action( 'phm_server_deployed', $order->id, (int) $server['id'] );
		return true;
	}

	private static function fail( $order, $message ) {
		self::release_lock( $order->id );
		PHM_DB::update_order( $order->id, [
			'status'        => 'failed',
			'error_message' => $message,
		] );
		PHM_DB::log( 'error', sprintf( 'Order %s failed: %s', $order->order_number, $message ) );
		return new WP_Error( 'phm_deploy', $message );
	}

	/**
	 * Build the egg environment object. Prefers locally synced variables;
	 * falls back to a live panel fetch so required keys are never missing.
	 *
	 * @param object $egg
	 * @param object $order
	 * @return array{environment:array,docker_image:string,startup:string}
	 */
	private static function environment_for_egg( $egg, $order ) {
		$variables    = json_decode( (string) $egg->variables, true );
		$docker_image = (string) $egg->docker_image;
		$startup      = (string) $egg->startup;

		if ( ! is_array( $variables ) || empty( $variables ) || '' === $docker_image || '' === $startup ) {
			$live = PHM_API::egg( $egg->nest_id, $egg->egg_id );
			if ( ! empty( $live['ok'] ) ) {
				$attr = ! empty( $live['data']['attributes'] ) ? $live['data']['attributes'] : [];
				if ( empty( $docker_image ) && ! empty( $attr['docker_image'] ) ) {
					$docker_image = (string) $attr['docker_image'];
				}
				if ( empty( $docker_image ) && ! empty( $attr['docker_images'] ) && is_array( $attr['docker_images'] ) ) {
					$first = reset( $attr['docker_images'] );
					$docker_image = (string) $first;
				}
				if ( empty( $startup ) && ! empty( $attr['startup'] ) ) {
					$startup = (string) $attr['startup'];
				}
				$rels = [];
				if ( ! empty( $attr['relationships'] ) ) {
					$rels = $attr['relationships'];
				} elseif ( ! empty( $live['data']['relationships'] ) ) {
					$rels = $live['data']['relationships'];
				}
				if ( empty( $variables ) && ! empty( $rels['variables']['data'] ) ) {
					$variables = [];
					foreach ( $rels['variables']['data'] as $var ) {
						$a = isset( $var['attributes'] ) ? $var['attributes'] : [];
						if ( empty( $a['env_variable'] ) ) {
							continue;
						}
						$variables[] = [
							'env_variable' => $a['env_variable'],
							'default'      => isset( $a['default_value'] ) ? (string) $a['default_value'] : '',
						];
					}
				}
			}
		}

		$environment = [];
		if ( is_array( $variables ) ) {
			foreach ( $variables as $var ) {
				if ( empty( $var['env_variable'] ) ) {
					continue;
				}
				$default = isset( $var['default'] ) ? (string) $var['default'] : '';
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
		if ( isset( $environment['SERVER_NAME'] ) ) {
			$environment['SERVER_NAME'] = $order->subdomain ? $order->fqdn : ( $order->server_label ? $order->server_label : $order->plan_name );
		}

		return [
			'environment'  => $environment,
			'docker_image' => $docker_image,
			'startup'      => $startup,
		];
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
		if ( empty( $nodes ) ) {
			$nodes = PHM_DB::get_nodes();
		}

		$candidates = [];
		foreach ( (array) $nodes as $node ) {
			$candidates[] = $node;
		}
		// Prefer public nodes, but fall back to any node — some panels mark
		// every node as not-public and that previously blocked all deploys.
		usort( $candidates, static function ( $a, $b ) {
			return ( (int) $b->is_public ) - ( (int) $a->is_public );
		} );

		foreach ( $candidates as $node ) {
			// Pterodactyl stores memory_overallocate / disk_overallocate as a
			// PERCENT (20 = +20%), not extra megabytes.
			$mem_cap  = (int) $node->memory * ( 1 + max( 0, (int) $node->memory_overallocate ) / 100 );
			$disk_cap = (int) $node->disk * ( 1 + max( 0, (int) $node->disk_overallocate ) / 100 );
			$free_mem  = $mem_cap - (int) $node->memory_used;
			$free_disk = $disk_cap - (int) $node->disk_used;
			if ( $free_mem >= $limits['memory'] && $free_disk >= $limits['disk'] ) {
				return $node;
			}
		}
		return null;
	}

	private static function username_from( $email, $name ) {
		$local = strstr( (string) $email, '@', true );
		$base  = strtolower( preg_replace( '/[^a-z0-9]/', '', (string) ( $local ? $local : $email ) ) );
		if ( strlen( $base ) < 3 ) {
			$base = strtolower( preg_replace( '/[^a-z0-9]/', '', (string) $name ) );
		}
		if ( strlen( $base ) < 3 ) {
			$base = 'user';
		}
		return substr( $base, 0, 16 ) . wp_generate_password( 4, false, false );
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
