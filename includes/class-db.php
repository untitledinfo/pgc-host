<?php
/**
 * Database layer: custom tables + typed helpers.
 *
 * Tables created (all prefixed with $wpdb->prefix):
 *  - phm_locations  synced Pterodactyl locations ("database data → locations")
 *  - phm_nests      synced nests (games, e.g. Minecraft)
 *  - phm_eggs       synced eggs with their env variables (Paper, Vanilla, Forge…)
 *  - phm_nodes      synced nodes with free resources
 *  - phm_products   sellable plans
 *  - phm_orders     customer orders / provisioning state
 *  - phm_sync_log   sync + provisioning log
 *
 * @package Pterodactyl_Hosting
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PHM_DB {

	const TABLES = [ 'locations', 'nests', 'eggs', 'nodes', 'products', 'orders', 'sync_log', 'tickets', 'ticket_replies', 'coupons', 'gateways' ];

	public static function table( $name ) {
		global $wpdb;
		$map = [
			'locations'      => $wpdb->prefix . 'phm_locations',
			'nests'          => $wpdb->prefix . 'phm_nests',
			'eggs'           => $wpdb->prefix . 'phm_eggs',
			'nodes'          => $wpdb->prefix . 'phm_nodes',
			'products'       => $wpdb->prefix . 'phm_products',
			'orders'         => $wpdb->prefix . 'phm_orders',
			'sync_log'       => $wpdb->prefix . 'phm_sync_log',
			'tickets'        => $wpdb->prefix . 'phm_tickets',
			'ticket_replies' => $wpdb->prefix . 'phm_ticket_replies',
			'coupons'        => $wpdb->prefix . 'phm_coupons',
			'gateways'       => $wpdb->prefix . 'phm_gateways',
		];
		return isset( $map[ $name ] ) ? $map[ $name ] : '';
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset_collate = $wpdb->get_charset_collate();

		$locations = 'CREATE TABLE ' . self::table( 'locations' ) . " (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			location_id BIGINT(20) NOT NULL,
			short VARCHAR(190) NOT NULL DEFAULT '',
			long_description VARCHAR(255) NOT NULL DEFAULT '',
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY location_id (location_id)
		) $charset_collate;";

		$nests = 'CREATE TABLE ' . self::table( 'nests' ) . " (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			nest_id BIGINT(20) NOT NULL,
			name VARCHAR(190) NOT NULL DEFAULT '',
			description TEXT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY nest_id (nest_id)
		) $charset_collate;";

		$eggs = 'CREATE TABLE ' . self::table( 'eggs' ) . " (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			egg_id BIGINT(20) NOT NULL,
			nest_id BIGINT(20) NOT NULL,
			name VARCHAR(190) NOT NULL DEFAULT '',
			description TEXT NULL,
			docker_image VARCHAR(255) NOT NULL DEFAULT '',
			startup TEXT NULL,
			variables LONGTEXT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY egg_id (egg_id),
			KEY nest_id (nest_id)
		) $charset_collate;";

		$nodes = 'CREATE TABLE ' . self::table( 'nodes' ) . " (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			node_id BIGINT(20) NOT NULL,
			name VARCHAR(190) NOT NULL DEFAULT '',
			location_id BIGINT(20) NOT NULL DEFAULT 0,
			fqdn VARCHAR(255) NOT NULL DEFAULT '',
			scheme VARCHAR(10) NOT NULL DEFAULT 'https',
			memory BIGINT(20) NOT NULL DEFAULT 0,
			memory_overallocate BIGINT(20) NOT NULL DEFAULT 0,
			memory_used BIGINT(20) NOT NULL DEFAULT 0,
			disk BIGINT(20) NOT NULL DEFAULT 0,
			disk_overallocate BIGINT(20) NOT NULL DEFAULT 0,
			disk_used BIGINT(20) NOT NULL DEFAULT 0,
			is_public TINYINT(1) NOT NULL DEFAULT 1,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY node_id (node_id),
			KEY location_id (location_id)
		) $charset_collate;";

		$products = 'CREATE TABLE ' . self::table( 'products' ) . " (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(190) NOT NULL,
			slug VARCHAR(190) NOT NULL,
			description TEXT NULL,
			nest_id BIGINT(20) NOT NULL DEFAULT 0,
			egg_id BIGINT(20) NOT NULL DEFAULT 0,
			location_id BIGINT(20) NOT NULL DEFAULT 0,
			memory BIGINT(20) NOT NULL DEFAULT 1024,
			swap BIGINT(20) NOT NULL DEFAULT 0,
			disk BIGINT(20) NOT NULL DEFAULT 5120,
			io INT(11) NOT NULL DEFAULT 500,
			cpu INT(11) NOT NULL DEFAULT 100,
			`databases` INT(11) NOT NULL DEFAULT 1,
			extra_allocations INT(11) NOT NULL DEFAULT 0,
			backups INT(11) NOT NULL DEFAULT 1,
			price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
			setup_fee DECIMAL(12,2) NOT NULL DEFAULT 0.00,
			currency VARCHAR(10) NOT NULL DEFAULT 'USD',
			stock INT(11) NOT NULL DEFAULT -1,
			featured TINYINT(1) NOT NULL DEFAULT 0,
			sort_order INT(11) NOT NULL DEFAULT 0,
			active TINYINT(1) NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug),
			KEY active (active)
		) $charset_collate;";

		$orders = 'CREATE TABLE ' . self::table( 'orders' ) . " (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			order_number VARCHAR(40) NOT NULL,
			product_id BIGINT(20) NOT NULL DEFAULT 0,
			plan_name VARCHAR(190) NOT NULL DEFAULT '',
			customer_name VARCHAR(190) NOT NULL DEFAULT '',
			email VARCHAR(190) NOT NULL DEFAULT '',
			discord VARCHAR(190) NOT NULL DEFAULT '',
			subdomain VARCHAR(63) NOT NULL DEFAULT '',
			server_label VARCHAR(60) NOT NULL DEFAULT '',
			fqdn VARCHAR(255) NOT NULL DEFAULT '',
			nest_id BIGINT(20) NOT NULL DEFAULT 0,
			egg_id BIGINT(20) NOT NULL DEFAULT 0,
			egg_name VARCHAR(190) NOT NULL DEFAULT '',
			location_id BIGINT(20) NOT NULL DEFAULT 0,
			payment_method VARCHAR(60) NOT NULL DEFAULT '',
			amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
			discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
			coupon_code VARCHAR(50) NOT NULL DEFAULT '',
			gateway_id VARCHAR(50) NOT NULL DEFAULT '',
			currency VARCHAR(10) NOT NULL DEFAULT 'USD',
			status VARCHAR(30) NOT NULL DEFAULT 'pending',
			stage VARCHAR(40) NOT NULL DEFAULT '',
			payment_ref VARCHAR(190) NOT NULL DEFAULT '',
			wp_user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			ptero_user_id BIGINT(20) NOT NULL DEFAULT 0,
			server_id BIGINT(20) NOT NULL DEFAULT 0,
			server_identifier VARCHAR(60) NOT NULL DEFAULT '',
			server_ip VARCHAR(255) NOT NULL DEFAULT '',
			server_port INT(11) NOT NULL DEFAULT 0,
			next_due_at DATETIME NULL,
			reminder_sent TINYINT(1) NOT NULL DEFAULT 0,
			dns_records TEXT NULL,
			error_message TEXT NULL,
			credential_note VARCHAR(255) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY order_number (order_number),
			KEY status (status),
			KEY email (email),
			KEY subdomain (subdomain),
			KEY wp_user_id (wp_user_id)
		) $charset_collate;";

		$sync_log = 'CREATE TABLE ' . self::table( 'sync_log' ) . " (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			level VARCHAR(20) NOT NULL DEFAULT 'info',
			message TEXT NOT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY level (level)
		) $charset_collate;";

		$tickets = 'CREATE TABLE ' . self::table( 'tickets' ) . " (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			ticket_number VARCHAR(40) NOT NULL,
			wp_user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			order_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			department VARCHAR(60) NOT NULL DEFAULT 'Technical',
			subject VARCHAR(190) NOT NULL DEFAULT '',
			priority VARCHAR(20) NOT NULL DEFAULT 'normal',
			status VARCHAR(20) NOT NULL DEFAULT 'open',
			last_reply_by VARCHAR(20) NOT NULL DEFAULT 'customer',
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY ticket_number (ticket_number),
			KEY wp_user_id (wp_user_id),
			KEY status (status)
		) $charset_collate;";

		$ticket_replies = 'CREATE TABLE ' . self::table( 'ticket_replies' ) . " (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			ticket_id BIGINT(20) UNSIGNED NOT NULL,
			wp_user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			author_name VARCHAR(190) NOT NULL DEFAULT '',
			is_staff TINYINT(1) NOT NULL DEFAULT 0,
			message LONGTEXT NOT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY ticket_id (ticket_id)
		) $charset_collate;";

		$coupons = 'CREATE TABLE ' . self::table( 'coupons' ) . " (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			code VARCHAR(50) NOT NULL,
			discount_type VARCHAR(20) NOT NULL DEFAULT 'percent',
			discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
			min_spend DECIMAL(12,2) NOT NULL DEFAULT 0.00,
			max_uses INT(11) NOT NULL DEFAULT -1,
			uses_count INT(11) NOT NULL DEFAULT 0,
			product_ids TEXT NULL,
			expires_at DATETIME NULL,
			active TINYINT(1) NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY code (code),
			KEY active (active)
		) $charset_collate;";

		$gateways = 'CREATE TABLE ' . self::table( 'gateways' ) . " (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			gateway_id VARCHAR(50) NOT NULL,
			name VARCHAR(190) NOT NULL,
			type VARCHAR(50) NOT NULL DEFAULT 'crypto',
			api_key TEXT NULL,
			api_secret TEXT NULL,
			merchant_id VARCHAR(190) NOT NULL DEFAULT '',
			webhook_secret VARCHAR(255) NOT NULL DEFAULT '',
			custom_url TEXT NULL,
			instructions TEXT NULL,
			test_mode TINYINT(1) NOT NULL DEFAULT 0,
			active TINYINT(1) NOT NULL DEFAULT 0,
			sort_order INT(11) NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY gateway_id (gateway_id),
			KEY active (active)
		) $charset_collate;";

		dbDelta( $locations );
		dbDelta( $nests );
		dbDelta( $eggs );
		dbDelta( $nodes );
		dbDelta( $products );
		dbDelta( $orders );
		dbDelta( $sync_log );
		dbDelta( $tickets );
		dbDelta( $ticket_replies );
		dbDelta( $coupons );
		dbDelta( $gateways );

		// Seed standard default gateways if empty.
		self::seed_default_gateways();

		// dbDelta fails silently on privilege/schema problems — verify and say so.
		$missing = self::tables_exist();
		if ( $missing ) {
			self::log( 'error', 'DB install ran but these tables are still missing: ' . implode( ', ', $missing ) . ' (grant the DB user CREATE/ALTER privileges)' );
		} else {
			self::log( 'success', 'Database tables verified OK.' );
			set_transient( 'phm_tables_ok', 1, 5 * MINUTE_IN_SECONDS );
		}
		return $missing;
	}

	/**
	 * Which of our tables are missing right now? Empty array = all present.
	 * (The original "Table wp_phm_products doesn't exist" case: the version
	 * option was already stored, so a version-only upgrade check skipped
	 * reinstalling forever — this existence check is the reliable signal.)
	 *
	 * @return string[]
	 */
	public static function tables_exist() {
		global $wpdb;
		$missing = [];
		foreach ( self::TABLES as $t ) {
			$name  = self::table( $t );
			$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $name ) );
			if ( $found !== $name ) {
				$missing[] = $t;
			}
		}
		return $missing;
	}

	/**
	 * Self-healing repair: when a write fails because a table/column is
	 * missing, rebuild the schema once (throttled) and report whether it worked.
	 *
	 * @return bool true when tables are all present after the attempt.
	 */
	private static function repair_missing_tables( $err ) {
		$err = (string) $err;
		$looks_missing = (
			false !== stripos( $err, "doesn't exist" ) ||
			false !== stripos( $err, 'no such table' ) ||
			false !== stripos( $err, 'Unknown column' )
		);
		if ( ! $looks_missing ) {
			return false; // not a schema problem — don't touch anything.
		}
		if ( get_transient( 'phm_repair_lock' ) ) {
			return false; // another request is repairing / already tried this minute.
		}
		set_transient( 'phm_repair_lock', 1, MINUTE_IN_SECONDS );
		delete_transient( 'phm_tables_ok' );
		$missing = self::install();
		if ( ! $missing ) {
			self::log( 'success', 'Database tables repaired automatically after a failed write.' );
		} else {
			self::log( 'error', 'Auto-repair attempted but tables still missing: ' . implode( ', ', $missing ) );
		}
		return [] === $missing;
	}

	/* ---------------------------------------------------------------------
	 * Synced-data helpers
	 * ------------------------------------------------------------------- */

	public static function upsert_location( $row ) {
		return self::upsert( 'locations', $row, [ 'location_id' => (int) $row['location_id'] ] );
	}

	public static function upsert_nest( $row ) {
		return self::upsert( 'nests', $row, [ 'nest_id' => (int) $row['nest_id'] ] );
	}

	public static function upsert_egg( $row ) {
		return self::upsert( 'eggs', $row, [ 'egg_id' => (int) $row['egg_id'] ] );
	}

	public static function upsert_node( $row ) {
		return self::upsert( 'nodes', $row, [ 'node_id' => (int) $row['node_id'] ] );
	}

	private static function upsert( $table, array $row, array $where ) {
		global $wpdb;
		$table_name = self::table( $table );
		$row['updated_at'] = current_time( 'mysql' );
		$exists = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$table_name} WHERE " . key( $where ) . ' = %d LIMIT 1',
			current( $where )
		) );
		if ( $exists ) {
			$wpdb->update( $table_name, $row, [ 'id' => (int) $exists ] );
		} else {
			$wpdb->insert( $table_name, $row );
		}
	}

	public static function get_locations() {
		global $wpdb;
		return $wpdb->get_results( 'SELECT * FROM ' . self::table( 'locations' ) . ' ORDER BY short ASC' );
	}

	public static function get_nests() {
		global $wpdb;
		return $wpdb->get_results( 'SELECT * FROM ' . self::table( 'nests' ) . ' ORDER BY name ASC' );
	}

	public static function get_eggs( $nest_id = 0 ) {
		global $wpdb;
		$table = self::table( 'eggs' );
		if ( $nest_id ) {
			return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE nest_id = %d ORDER BY name ASC", $nest_id ) );
		}
		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY nest_id ASC, name ASC" );
	}

	public static function get_egg( $egg_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'eggs' ) . ' WHERE egg_id = %d LIMIT 1', (int) $egg_id ) );
	}

	public static function get_nodes() {
		global $wpdb;
		return $wpdb->get_results( 'SELECT * FROM ' . self::table( 'nodes' ) . ' ORDER BY name ASC' );
	}

	public static function get_nodes_for_location( $location_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'nodes' ) . ' WHERE location_id = %d ORDER BY name ASC', (int) $location_id ) );
	}

	public static function log( $level, $message ) {
		global $wpdb;
		$wpdb->insert( self::table( 'sync_log' ), [
			'level'      => sanitize_key( $level ),
			'message'    => (string) $message,
			'created_at' => current_time( 'mysql' ),
		] );
	}

	public static function get_logs( $limit = 50 ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'sync_log' ) . ' ORDER BY id DESC LIMIT %d', (int) $limit ) );
	}

	public static function counts() {
		global $wpdb;
		$out = [];
		foreach ( [ 'locations', 'nests', 'eggs', 'nodes', 'products', 'orders', 'tickets' ] as $t ) {
			$out[ $t ] = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table( $t ) );
		}
		return $out;
	}

	/* ---------------------------------------------------------------------
	 * Products
	 * ------------------------------------------------------------------- */

	public static function get_products( $active_only = false ) {
		global $wpdb;
		$sql = 'SELECT * FROM ' . self::table( 'products' );
		if ( $active_only ) {
			$sql .= ' WHERE active = 1';
		}
		$sql .= ' ORDER BY sort_order ASC, price ASC';
		return $wpdb->get_results( $sql );
	}

	public static function get_product( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'products' ) . ' WHERE id = %d LIMIT 1', (int) $id ) );
	}

	/**
	 * Insert/update a product, SURFACE real failures, and self-heal
	 * schema problems.
	 *
	 * Old behaviour: $wpdb->insert() failing (e.g. table never created, or a
	 * schema mismatch on an upgraded site) returned insert_id = 0, the caller
	 * redirected to action=edit&id=0 with a fake "Saved." banner, and PHP's
	 * empty('0') bounced the view back to an empty list. Now:
	 *  1. a missing table/column triggers an automatic repair + ONE retry;
	 *  2. anything else returns WP_Error with the real $wpdb->last_error,
	 *     which the admin banner prints and the sync log records.
	 *
	 * @return int|WP_Error Product ID, or WP_Error with the real DB message.
	 */
	public static function save_product( array $data, $id = 0 ) {
		global $wpdb;

		for ( $attempt = 0; $attempt < 2; $attempt++ ) {
			$now = current_time( 'mysql' );
			$data['updated_at'] = $now;

			if ( $id ) {
				$ok     = $wpdb->update( self::table( 'products' ), $data, [ 'id' => (int) $id ] );
				$new_id = (int) $id;
			} else {
				$data['created_at'] = $now;
				$ok     = $wpdb->insert( self::table( 'products' ), $data );
				$new_id = (int) $wpdb->insert_id;
			}

			if ( false !== $ok && $new_id ) {
				return $new_id;
			}

			$err = $wpdb->last_error ? $wpdb->last_error : __( 'Database rejected the product write.', 'pterodactyl-hosting' );

			// Missing table/column? Repair the schema and retry ONCE —
			// fixes "Table 'wp_phm_products' doesn't exist" without the admin
			// having to deactivate/reactivate anything.
			if ( ! self::repair_missing_tables( $err ) ) {
				break; // not repairable (privileges, wrong data type, …) → report.
			}
		}

		self::log( 'error', 'Product save failed' . ( $id ? ' (#' . (int) $id . ')' : '' ) . ': ' . $err );
		return new WP_Error( $id ? 'phm_db_update' : 'phm_db_insert', $err );
	}

	public static function delete_product( $id ) {
		global $wpdb;
		return $wpdb->delete( self::table( 'products' ), [ 'id' => (int) $id ] );
	}

	/* ---------------------------------------------------------------------
	 * Orders
	 * ------------------------------------------------------------------- */

	public static function next_order_number() {
		global $wpdb;
		$year = gmdate( 'Y' );
		$like = $wpdb->esc_like( 'PHM-' . $year . '-' ) . '%';
		$last = $wpdb->get_var( $wpdb->prepare( 'SELECT order_number FROM ' . self::table( 'orders' ) . ' WHERE order_number LIKE %s ORDER BY id DESC LIMIT 1', $like ) );
		$next = 1;
		if ( $last && preg_match( '/-(\d+)$/', $last, $m ) ) {
			$next = (int) $m[1] + 1;
		}
		return 'PHM-' . $year . '-' . str_pad( (string) $next, 6, '0', STR_PAD_LEFT );
	}

	public static function create_order( array $data ) {
		global $wpdb;
		$now = current_time( 'mysql' );
		$defaults = [
			'order_number' => self::next_order_number(),
			'status'       => 'pending',
			'created_at'   => $now,
			'updated_at'   => $now,
		];
		$wpdb->insert( self::table( 'orders' ), array_merge( $defaults, $data ) );
		return (int) $wpdb->insert_id;
	}

	public static function update_order( $id, array $data ) {
		global $wpdb;
		$data['updated_at'] = current_time( 'mysql' );
		return $wpdb->update( self::table( 'orders' ), $data, [ 'id' => (int) $id ] );
	}

	public static function get_order( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'orders' ) . ' WHERE id = %d LIMIT 1', (int) $id ) );
	}

	public static function get_order_by_number( $number, $email = '' ) {
		global $wpdb;
		$sql = $wpdb->prepare( 'SELECT * FROM ' . self::table( 'orders' ) . ' WHERE order_number = %s', sanitize_text_field( $number ) );
		if ( $email ) {
			$sql .= $wpdb->prepare( ' AND email = %s', sanitize_email( $email ) );
		}
		return $wpdb->get_row( $sql . ' LIMIT 1' );
	}

	public static function get_orders( $status = '' ) {
		global $wpdb;
		$sql = 'SELECT * FROM ' . self::table( 'orders' );
		if ( $status ) {
			$sql .= $wpdb->prepare( ' WHERE status = %s', sanitize_key( $status ) );
		}
		return $wpdb->get_results( $sql . ' ORDER BY id DESC LIMIT 500' );
	}

	/**
	 * The Pterodactyl panel user ID linked to a WordPress user, found from
	 * their most recent order that actually has one on file. Used to sync a
	 * changed WordPress password onto their existing panel account, and by
	 * the one-click panel access shortcode. Returns 0 if this WP user has
	 * never had a panel account created for them through the plugin.
	 */
	/**
	 * All orders/servers belonging to a WordPress account — the data behind
	 * the "My Servers" tab of [phm_dashboard].
	 */
	public static function get_orders_for_wp_user( $wp_user_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM ' . self::table( 'orders' ) . ' WHERE wp_user_id = %d ORDER BY id DESC',
			(int) $wp_user_id
		) );
	}

	public static function get_ptero_user_id_for_wp_user( $wp_user_id ) {
		global $wpdb;
		$id = $wpdb->get_var( $wpdb->prepare(
			'SELECT ptero_user_id FROM ' . self::table( 'orders' ) . ' WHERE wp_user_id = %d AND ptero_user_id > 0 ORDER BY id DESC LIMIT 1',
			(int) $wp_user_id
		) );
		return (int) $id;
	}

	public static function subdomain_taken( $fqdn ) {
		global $wpdb;
		$count = $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(*) FROM ' . self::table( 'orders' ) . " WHERE fqdn = %s AND status IN ('pending','paid','provisioning','active')",
			strtolower( $fqdn )
		) );
		return (int) $count > 0;
	}

	/* ---------------------------------------------------------------------
	 * Stock + billing helpers
	 * ------------------------------------------------------------------- */

	public static function decrement_stock( $product_id ) {
		global $wpdb;
		// stock = -1 means unlimited; only decrement finite positive stock.
		$wpdb->query( $wpdb->prepare(
			'UPDATE ' . self::table( 'products' ) . ' SET stock = stock - 1 WHERE id = %d AND stock > 0',
			(int) $product_id
		) );
	}

	public static function restore_stock( $product_id ) {
		global $wpdb;
		$wpdb->query( $wpdb->prepare(
			'UPDATE ' . self::table( 'products' ) . ' SET stock = stock + 1 WHERE id = %d AND stock >= 0',
			(int) $product_id
		) );
	}

	/**
	 * Active orders whose renewal is due within N days and not yet reminded.
	 */
	public static function orders_due_soon( $days ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM ' . self::table( 'orders' ) . " WHERE status = 'active' AND next_due_at IS NOT NULL AND reminder_sent = 0 AND next_due_at <= DATE_ADD(%s, INTERVAL %d DAY)",
			current_time( 'mysql' ), (int) $days
		) );
	}

	/**
	 * Active orders past their due date (for auto-suspend).
	 */
	public static function orders_overdue() {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM ' . self::table( 'orders' ) . " WHERE status = 'active' AND next_due_at IS NOT NULL AND next_due_at < %s",
			current_time( 'mysql' )
		) );
	}

	/* ---------------------------------------------------------------------
	 * Support tickets
	 * ------------------------------------------------------------------- */

	public static function next_ticket_number() {
		global $wpdb;
		$year = gmdate( 'Y' );
		$like = $wpdb->esc_like( 'TKT-' . $year . '-' ) . '%';
		$last = $wpdb->get_var( $wpdb->prepare( 'SELECT ticket_number FROM ' . self::table( 'tickets' ) . ' WHERE ticket_number LIKE %s ORDER BY id DESC LIMIT 1', $like ) );
		$next = 1;
		if ( $last && preg_match( '/-(\d+)$/', $last, $m ) ) {
			$next = (int) $m[1] + 1;
		}
		return 'TKT-' . $year . '-' . str_pad( (string) $next, 5, '0', STR_PAD_LEFT );
	}

	public static function create_ticket( array $data ) {
		global $wpdb;
		$now      = current_time( 'mysql' );
		$defaults = [
			'ticket_number' => self::next_ticket_number(),
			'status'        => 'open',
			'priority'      => 'normal',
			'last_reply_by' => 'customer',
			'created_at'    => $now,
			'updated_at'    => $now,
		];
		$wpdb->insert( self::table( 'tickets' ), array_merge( $defaults, $data ) );
		return (int) $wpdb->insert_id;
	}

	public static function update_ticket( $id, array $data ) {
		global $wpdb;
		$data['updated_at'] = current_time( 'mysql' );
		return $wpdb->update( self::table( 'tickets' ), $data, [ 'id' => (int) $id ] );
	}

	public static function get_ticket( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'tickets' ) . ' WHERE id = %d LIMIT 1', (int) $id ) );
	}

	/**
	 * A ticket, but only if it belongs to this WordPress user — used on the
	 * customer-facing dashboard so one customer can never open another's
	 * ticket by guessing/incrementing the ID in the URL.
	 */
	public static function get_ticket_for_wp_user( $id, $wp_user_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . self::table( 'tickets' ) . ' WHERE id = %d AND wp_user_id = %d LIMIT 1',
			(int) $id, (int) $wp_user_id
		) );
	}

	public static function get_tickets_for_wp_user( $wp_user_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM ' . self::table( 'tickets' ) . ' WHERE wp_user_id = %d ORDER BY updated_at DESC',
			(int) $wp_user_id
		) );
	}

	public static function get_tickets( $status = '' ) {
		global $wpdb;
		$sql = 'SELECT * FROM ' . self::table( 'tickets' );
		if ( $status ) {
			$sql .= $wpdb->prepare( ' WHERE status = %s', sanitize_key( $status ) );
		}
		return $wpdb->get_results( $sql . ' ORDER BY updated_at DESC LIMIT 500' );
	}

	public static function ticket_counts() {
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT status, COUNT(*) AS c FROM ' . self::table( 'tickets' ) . ' GROUP BY status', ARRAY_A );
		$out  = [ 'open' => 0, 'customer-reply' => 0, 'answered' => 0, 'closed' => 0 ];
		foreach ( (array) $rows as $row ) {
			$out[ $row['status'] ] = (int) $row['c'];
		}
		return $out;
	}

	public static function add_ticket_reply( $ticket_id, array $data ) {
		global $wpdb;
		$data['ticket_id']  = (int) $ticket_id;
		$data['created_at'] = current_time( 'mysql' );
		$wpdb->insert( self::table( 'ticket_replies' ), $data );
		return (int) $wpdb->insert_id;
	}

	public static function get_ticket_replies( $ticket_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM ' . self::table( 'ticket_replies' ) . ' WHERE ticket_id = %d ORDER BY id ASC',
			(int) $ticket_id
		) );
	}

	/* ---------------------------------------------------------------------
	 * Coupons
	 * ------------------------------------------------------------------- */

	public static function get_coupons( $active_only = false ) {
		global $wpdb;
		$sql = 'SELECT * FROM ' . self::table( 'coupons' );
		if ( $active_only ) {
			$sql .= ' WHERE active = 1';
		}
		$sql .= ' ORDER BY id DESC';
		return $wpdb->get_results( $sql );
	}

	public static function get_coupon( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'coupons' ) . ' WHERE id = %d LIMIT 1', (int) $id ) );
	}

	public static function get_coupon_by_code( $code ) {
		global $wpdb;
		$code = strtoupper( trim( (string) $code ) );
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'coupons' ) . ' WHERE UPPER(code) = %s LIMIT 1', $code ) );
	}

	public static function save_coupon( array $data, $id = 0 ) {
		global $wpdb;
		$now = current_time( 'mysql' );
		$data['updated_at'] = $now;
		$data['code'] = strtoupper( trim( (string) $data['code'] ) );

		if ( $id ) {
			$ok = $wpdb->update( self::table( 'coupons' ), $data, [ 'id' => (int) $id ] );
			return false !== $ok ? (int) $id : new WP_Error( 'phm_coupon_update', $wpdb->last_error );
		}

		$data['created_at'] = $now;
		$ok = $wpdb->insert( self::table( 'coupons' ), $data );
		return false !== $ok ? (int) $wpdb->insert_id : new WP_Error( 'phm_coupon_insert', $wpdb->last_error );
	}

	public static function delete_coupon( $id ) {
		global $wpdb;
		return $wpdb->delete( self::table( 'coupons' ), [ 'id' => (int) $id ] );
	}

	public static function increment_coupon_uses( $code ) {
		global $wpdb;
		$code = strtoupper( trim( (string) $code ) );
		$wpdb->query( $wpdb->prepare(
			'UPDATE ' . self::table( 'coupons' ) . ' SET uses_count = uses_count + 1 WHERE UPPER(code) = %s',
			$code
		) );
	}

	/* ---------------------------------------------------------------------
	 * Payment Gateways
	 * ------------------------------------------------------------------- */

	public static function get_gateways( $active_only = false ) {
		global $wpdb;
		$sql = 'SELECT * FROM ' . self::table( 'gateways' );
		if ( $active_only ) {
			$sql .= ' WHERE active = 1';
		}
		$sql .= ' ORDER BY sort_order ASC, name ASC';
		return $wpdb->get_results( $sql );
	}

	public static function get_gateway( $gateway_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'gateways' ) . ' WHERE gateway_id = %s LIMIT 1', sanitize_key( $gateway_id ) ) );
	}

	public static function save_gateway( array $data, $id = 0 ) {
		global $wpdb;
		$now = current_time( 'mysql' );
		$data['updated_at'] = $now;

		if ( $id ) {
			$ok = $wpdb->update( self::table( 'gateways' ), $data, [ 'id' => (int) $id ] );
			return false !== $ok ? (int) $id : new WP_Error( 'phm_gateway_update', $wpdb->last_error );
		}

		$data['created_at'] = $now;
		$ok = $wpdb->insert( self::table( 'gateways' ), $data );
		return false !== $ok ? (int) $wpdb->insert_id : new WP_Error( 'phm_gateway_insert', $wpdb->last_error );
	}

	public static function delete_gateway( $id ) {
		global $wpdb;
		return $wpdb->delete( self::table( 'gateways' ), [ 'id' => (int) $id ] );
	}

	public static function seed_default_gateways() {
		global $wpdb;
		$table = self::table( 'gateways' );
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		if ( $count > 0 ) {
			return;
		}

		$defaults = [
			[
				'gateway_id'   => 'manual',
				'name'         => 'Bank Transfer / UPI / Manual',
				'type'         => 'manual',
				'instructions' => 'Send payment to our official account or scan QR code, then submit your transaction reference.',
				'active'       => 1,
				'sort_order'   => 1,
			],
			[
				'gateway_id'   => 'cryptomus',
				'name'         => 'Cryptomus (Crypto)',
				'type'         => 'crypto',
				'instructions' => 'Pay seamlessly with 30+ Cryptocurrencies (BTC, ETH, USDT, SOL, TRX, etc.)',
				'active'       => 0,
				'sort_order'   => 2,
			],
			[
				'gateway_id'   => 'nowpayments',
				'name'         => 'NOWPayments (Crypto 200+ coins)',
				'type'         => 'crypto',
				'instructions' => 'Accept 200+ cryptocurrencies with instant auto-confirmation.',
				'active'       => 0,
				'sort_order'   => 3,
			],
			[
				'gateway_id'   => 'coinpayments',
				'name'         => 'CoinPayments (Crypto)',
				'type'         => 'crypto',
				'instructions' => 'Pay with major cryptocurrencies worldwide.',
				'active'       => 0,
				'sort_order'   => 4,
			],
			[
				'gateway_id'   => 'stripe',
				'name'         => 'Stripe (Credit / Debit Cards)',
				'type'         => 'fiat',
				'instructions' => 'Visa, Mastercard, American Express, Apple Pay, Google Pay.',
				'active'       => 0,
				'sort_order'   => 5,
			],
			[
				'gateway_id'   => 'paypal',
				'name'         => 'PayPal Express / Cards',
				'type'         => 'fiat',
				'instructions' => 'Pay securely with your PayPal account or linked card.',
				'active'       => 0,
				'sort_order'   => 6,
			],
			[
				'gateway_id'   => 'razorpay',
				'name'         => 'Razorpay (India UPI / Cards / NetBanking)',
				'type'         => 'fiat',
				'instructions' => 'Instant UPI (Google Pay, PhonePe, Paytm), NetBanking and Cards.',
				'active'       => 0,
				'sort_order'   => 7,
			],
			[
				'gateway_id'   => 'paystack',
				'name'         => 'Paystack (Africa / Global)',
				'type'         => 'fiat',
				'instructions' => 'Card, Bank Transfer, USSD and Mobile Money.',
				'active'       => 0,
				'sort_order'   => 8,
			],
			[
				'gateway_id'   => 'flutterwave',
				'name'         => 'Flutterwave',
				'type'         => 'fiat',
				'instructions' => 'Cards, Mobile Money, M-Pesa, Bank Transfer in 30+ currencies.',
				'active'       => 0,
				'sort_order'   => 9,
			],
			[
				'gateway_id'   => 'universal_api',
				'name'         => 'Universal Payment API / Webhook (250+ APIs)',
				'type'         => 'api',
				'instructions' => 'Connect external payment gateway webhooks or custom payment systems via instant IPN callback.',
				'active'       => 1,
				'sort_order'   => 10,
			],
		];

		$now = current_time( 'mysql' );
		foreach ( $defaults as $g ) {
			$g['created_at'] = $now;
			$g['updated_at'] = $now;
			$wpdb->insert( $table, $g );
		}
	}
}

