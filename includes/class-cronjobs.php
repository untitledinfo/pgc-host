<?php
/**
 * Cron-based auto-sync ("auto reload") of panel data.
 *
 * @package Pterodactyl_Hosting
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PHM_Cronjobs {

	const HOOK         = 'phm_cron_sync';
	const HOOK_BILLING = 'phm_cron_billing';

	public static function init() {
		add_action( self::HOOK, [ 'PHM_Sync', 'sync_all' ] );
		add_action( self::HOOK_BILLING, [ 'PHM_Billing', 'run' ] );
		add_filter( 'cron_schedules', [ __CLASS__, 'schedules' ] );
	}

	public static function schedules( $schedules ) {
		if ( ! isset( $schedules['phm_15min'] ) ) {
			$schedules['phm_15min'] = [
				'interval' => 15 * MINUTE_IN_SECONDS,
				'display'  => __( 'Every 15 minutes (PHM)', 'pterodactyl-hosting' ),
			];
		}
		return $schedules;
	}

	public static function schedule() {
		self::unschedule();
		$settings = PHM_Settings::get();
		$allowed  = [ 'phm_15min', 'hourly', 'twicedaily', 'daily' ];
		$recur    = in_array( $settings['auto_sync'], $allowed, true ) ? $settings['auto_sync'] : 'hourly';
		if ( 'off' !== $settings['auto_sync'] ) {
			wp_schedule_event( time() + 300, $recur, self::HOOK );
		}
		// Billing always runs daily (reminders + auto-suspend are internal
		// no-ops when disabled in settings).
		wp_schedule_event( time() + 600, 'daily', self::HOOK_BILLING );
	}

	public static function unschedule() {
		foreach ( [ self::HOOK, self::HOOK_BILLING ] as $hook ) {
			$ts = wp_next_scheduled( $hook );
			while ( $ts ) {
				wp_unschedule_event( $ts, $hook );
				$ts = wp_next_scheduled( $hook );
			}
		}
	}

	/** Reschedule when the auto_sync setting changes. */
	public static function reschedule() {
		self::schedule();
	}
}
