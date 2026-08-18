<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Ptero_Cron {

	private static $instance = null;
	public static function instance() {
		if ( self::$instance === null ) self::$instance = new self();
		return self::$instance;
	}

	private function __construct() {
		add_action( 'ptero_host_daily_cron', array( $this, 'run_daily' ) );
		add_action( 'ptero_host_hourly_sync', array( $this, 'run_hourly_sync' ) );
		add_filter( 'cron_schedules', array( $this, 'add_schedules' ) );
	}

	public function add_schedules( $schedules ) {
		$schedules['ptero_15min'] = array( 'interval' => 900, 'display' => 'Every 15 Minutes' );
		return $schedules;
	}

	/** Handles expiry reminders + auto suspension after grace period. */
	public function run_daily() {
		global $wpdb;
		$table = $wpdb->prefix . 'ptero_servers';
		$grace = intval( get_option( 'ptero_grace_period_days', 3 ) );

		// 3-day-before-expiry renewal reminders
		$soon = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE status = 'active' AND expires_at BETWEEN %s AND %s",
			date( 'Y-m-d H:i:s' ),
			date( 'Y-m-d H:i:s', strtotime( '+3 days' ) )
		) );
		foreach ( $soon as $s ) {
			Ptero_Notifications::instance()->send_renewal_reminder( $s->id );
		}

		// Past grace period -> auto suspend
		$overdue = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE status = 'active' AND expires_at < %s",
			date( 'Y-m-d H:i:s', strtotime( "-{$grace} days" ) )
		) );
		$api = new Ptero_API();
		foreach ( $overdue as $s ) {
			if ( $s->ptero_server_id ) {
				$api->suspend_server( $s->ptero_server_id );
			}
			$wpdb->update( $table, array( 'status' => 'suspended' ), array( 'id' => $s->id ) );
			Ptero_Notifications::instance()->send_suspended_notice( $s->id );
		}

		// Pending orders older than 7 days with no payment -> mark expired/cancelled
		$stale = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE status = 'pending' AND created_at < %s",
			date( 'Y-m-d H:i:s', strtotime( '-7 days' ) )
		) );
		foreach ( $stale as $s ) {
			$wpdb->update( $table, array( 'status' => 'cancelled' ), array( 'id' => $s->id ) );
		}
	}

	/** Lightweight sync - could ping panel for orphaned/mismatched servers. */
	public function run_hourly_sync() {
		do_action( 'ptero_host_before_sync' );
		// Reserved for future: reconcile local DB vs panel state.
		do_action( 'ptero_host_after_sync' );
	}
}
