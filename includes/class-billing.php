<?php
/**
 * Billing automation: renewal reminders + automatic suspension of overdue
 * servers (daily cron). This is the "hosting manager" half of the plugin —
 * servers live on a monthly cycle from the moment they are deployed.
 *
 * @package Pterodactyl_Hosting
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PHM_Billing {

	/**
	 * Next due date N months from a given base (defaults: settings period).
	 */
	public static function add_period( $from_ts = null ) {
		$s      = PHM_Settings::get();
		$months = max( 1, (int) $s['billing_period_months'] );
		$base   = $from_ts ? (int) $from_ts : time();
		return gmdate( 'Y-m-d H:i:s', strtotime( '+' . $months . ' month', $base ) );
	}

	/**
	 * Daily cron: reminder emails, then suspend overdue servers.
	 */
	public static function run() {
		$s = PHM_Settings::get();

		// 1) Reminders ------------------------------------------------------
		$days = (int) $s['billing_reminder_days'];
		if ( $days > 0 ) {
			foreach ( (array) PHM_DB::orders_due_soon( $days ) as $order ) {
				PHM_Notifications::renewal_reminder( $order );
				PHM_DB::update_order( $order->id, [ 'reminder_sent' => 1 ] );
			}
		}

		// 2) Auto-suspend overdue -------------------------------------------
		if ( empty( $s['billing_auto_suspend'] ) ) {
			return;
		}
		foreach ( (array) PHM_DB::orders_overdue() as $order ) {
			self::suspend_overdue( $order );
		}
	}

	/**
	 * Suspend one overdue order's server on the panel.
	 */
	public static function suspend_overdue( $order ) {
		if ( $order->server_id ) {
			$res = PHM_API::suspend_server( $order->server_id );
			if ( ! $res['ok'] && 404 !== $res['status'] ) {
				PHM_DB::log( 'error', sprintf( 'Auto-suspend failed for %s: %s', $order->order_number, $res['error'] ) );
				return new WP_Error( 'phm_suspend', $res['error'] );
			}
		}
		PHM_DB::update_order( $order->id, [ 'status' => 'suspended' ] );
		PHM_DB::log( 'warning', sprintf( 'Order %s auto-suspended (overdue since %s).', $order->order_number, $order->next_due_at ) );
		PHM_Notifications::server_suspended( $order );
		return true;
	}

	/**
	 * Renew: push due date forward, unsuspend and reactivate if suspended.
	 */
	public static function renew( $order_id ) {
		$order = PHM_DB::get_order( $order_id );
		if ( ! $order ) {
			return new WP_Error( 'phm_no_order', __( 'Order not found.', 'pterodactyl-hosting' ) );
		}

		// Extend from the LATER of now / current due date — never backwards.
		$base = time();
		if ( ! empty( $order->next_due_at ) && strtotime( $order->next_due_at ) > $base ) {
			$base = strtotime( $order->next_due_at );
		}
		$data = [
			'next_due_at'   => self::add_period( $base ),
			'reminder_sent' => 0,
		];

		if ( 'suspended' === $order->status ) {
			if ( $order->server_id ) {
				$res = PHM_API::unsuspend_server( $order->server_id );
				if ( ! $res['ok'] && 404 !== $res['status'] ) {
					return new WP_Error( 'phm_unsuspend', $res['error'] );
				}
			}
			$data['status'] = 'active';
			PHM_DB::log( 'success', sprintf( 'Order %s renewed + unsuspended.', $order->order_number ) );
		} elseif ( in_array( $order->status, [ 'active', 'paid', 'provisioning' ], true ) ) {
			$data['status'] = $order->status;
			PHM_DB::log( 'info', sprintf( 'Order %s renewed until %s.', $order->order_number, $data['next_due_at'] ) );
		}

		PHM_DB::update_order( $order->id, $data );
		return $data['next_due_at'];
	}
}
