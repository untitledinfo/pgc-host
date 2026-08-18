<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Creates/upgrades every table the billing system (plans, clients, cart,
 * invoices, transactions, tickets) needs. Called on activation AND on
 * 'plugins_loaded' (guarded by a version check) so upgrading the zip over
 * an existing install still gets new tables/columns without deactivating.
 */
class Ptero_DB_Installer {

	const DB_VERSION_OPTION = 'ptero_db_version';
	const DB_VERSION        = '1.1.0';

	public static function maybe_upgrade() {
		if ( get_option( self::DB_VERSION_OPTION ) !== self::DB_VERSION ) {
			self::install();
			update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
		}
	}

	public static function install() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		if ( ! function_exists( 'dbDelta' ) && file_exists( ABSPATH . 'wp-admin/includes/upgrade.php' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}
		if ( ! function_exists( 'dbDelta' ) ) return;

		$p = $wpdb->prefix;

		// ---- Clients (own login system, separate from / optionally synced to WP users)
		dbDelta( "CREATE TABLE {$p}ptero_clients (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			wp_user_id BIGINT UNSIGNED DEFAULT NULL,
			name VARCHAR(191) NOT NULL,
			email VARCHAR(191) NOT NULL,
			password_hash VARCHAR(255) NOT NULL,
			auth_token VARCHAR(64) DEFAULT NULL,
			token_expires DATETIME DEFAULT NULL,
			balance DECIMAL(10,2) NOT NULL DEFAULT 0,
			currency VARCHAR(10) DEFAULT 'PKR',
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			verify_code VARCHAR(64) DEFAULT NULL,
			verified TINYINT(1) DEFAULT 0,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY email (email),
			KEY auth_token (auth_token)
		) {$charset_collate};" );

		// ---- Plans / Products (the "config options" — cpu/ram/disk/image/thumbnail/pricing)
		dbDelta( "CREATE TABLE {$p}ptero_plans (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(191) NOT NULL,
			slug VARCHAR(191) NOT NULL,
			description TEXT,
			image_url VARCHAR(500) DEFAULT NULL,
			thumbnail_url VARCHAR(500) DEFAULT NULL,
			nest_id BIGINT UNSIGNED DEFAULT NULL,
			egg_id BIGINT UNSIGNED DEFAULT NULL,
			location_id BIGINT UNSIGNED DEFAULT NULL,
			cpu INT UNSIGNED NOT NULL DEFAULT 100,
			ram INT UNSIGNED NOT NULL DEFAULT 1024,
			disk INT UNSIGNED NOT NULL DEFAULT 5120,
			backups INT UNSIGNED NOT NULL DEFAULT 1,
			databases INT UNSIGNED NOT NULL DEFAULT 1,
			allocations INT UNSIGNED NOT NULL DEFAULT 1,
			swap INT UNSIGNED NOT NULL DEFAULT 0,
			price_hourly DECIMAL(10,2) DEFAULT NULL,
			price_daily DECIMAL(10,2) DEFAULT NULL,
			price_weekly DECIMAL(10,2) DEFAULT NULL,
			price_monthly DECIMAL(10,2) DEFAULT NULL,
			price_quarterly DECIMAL(10,2) DEFAULT NULL,
			price_yearly DECIMAL(10,2) DEFAULT NULL,
			setup_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
			currency VARCHAR(10) DEFAULT 'PKR',
			stock INT DEFAULT NULL,
			featured TINYINT(1) DEFAULT 0,
			sort_order INT DEFAULT 0,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY status (status)
		) {$charset_collate};" );

		// ---- Cart (per client or guest session key)
		dbDelta( "CREATE TABLE {$p}ptero_cart (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			session_key VARCHAR(64) NOT NULL,
			client_id BIGINT UNSIGNED DEFAULT NULL,
			plan_id BIGINT UNSIGNED NOT NULL,
			server_name VARCHAR(191) DEFAULT NULL,
			billing_cycle VARCHAR(20) NOT NULL DEFAULT 'monthly',
			quantity INT UNSIGNED NOT NULL DEFAULT 1,
			coupon_code VARCHAR(64) DEFAULT NULL,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY session_key (session_key),
			KEY client_id (client_id)
		) {$charset_collate};" );

		// ---- Invoices
		dbDelta( "CREATE TABLE {$p}ptero_invoices (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			client_id BIGINT UNSIGNED NOT NULL,
			invoice_number VARCHAR(32) NOT NULL,
			subtotal DECIMAL(10,2) NOT NULL DEFAULT 0,
			discount DECIMAL(10,2) NOT NULL DEFAULT 0,
			total DECIMAL(10,2) NOT NULL DEFAULT 0,
			currency VARCHAR(10) DEFAULT 'PKR',
			status VARCHAR(20) NOT NULL DEFAULT 'unpaid',
			gateway VARCHAR(40) DEFAULT NULL,
			coupon_code VARCHAR(64) DEFAULT NULL,
			due_at DATETIME DEFAULT NULL,
			paid_at DATETIME DEFAULT NULL,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY invoice_number (invoice_number),
			KEY client_id (client_id),
			KEY status (status)
		) {$charset_collate};" );

		dbDelta( "CREATE TABLE {$p}ptero_invoice_items (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			invoice_id BIGINT UNSIGNED NOT NULL,
			plan_id BIGINT UNSIGNED DEFAULT NULL,
			server_id BIGINT UNSIGNED DEFAULT NULL,
			description VARCHAR(255) NOT NULL,
			billing_cycle VARCHAR(20) DEFAULT 'monthly',
			quantity INT UNSIGNED NOT NULL DEFAULT 1,
			unit_price DECIMAL(10,2) NOT NULL DEFAULT 0,
			total DECIMAL(10,2) NOT NULL DEFAULT 0,
			PRIMARY KEY (id),
			KEY invoice_id (invoice_id)
		) {$charset_collate};" );

		// ---- Transactions (wallet top-ups + gateway payments)
		dbDelta( "CREATE TABLE {$p}ptero_transactions (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			client_id BIGINT UNSIGNED NOT NULL,
			invoice_id BIGINT UNSIGNED DEFAULT NULL,
			type VARCHAR(20) NOT NULL DEFAULT 'payment',
			gateway VARCHAR(40) DEFAULT NULL,
			gateway_ref VARCHAR(191) DEFAULT NULL,
			amount DECIMAL(10,2) NOT NULL DEFAULT 0,
			currency VARCHAR(10) DEFAULT 'PKR',
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY client_id (client_id)
		) {$charset_collate};" );

		// ---- Support tickets
		dbDelta( "CREATE TABLE {$p}ptero_tickets (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			client_id BIGINT UNSIGNED NOT NULL,
			subject VARCHAR(191) NOT NULL,
			department VARCHAR(60) DEFAULT 'general',
			priority VARCHAR(20) NOT NULL DEFAULT 'medium',
			status VARCHAR(20) NOT NULL DEFAULT 'open',
			server_id BIGINT UNSIGNED DEFAULT NULL,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY client_id (client_id),
			KEY status (status)
		) {$charset_collate};" );

		dbDelta( "CREATE TABLE {$p}ptero_ticket_replies (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			ticket_id BIGINT UNSIGNED NOT NULL,
			sender_type VARCHAR(10) NOT NULL DEFAULT 'client',
			sender_name VARCHAR(191) DEFAULT NULL,
			message TEXT NOT NULL,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY ticket_id (ticket_id)
		) {$charset_collate};" );
	}
}
