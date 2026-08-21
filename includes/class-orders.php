<?php
/**
 * Orders: admin actions (mark paid → auto deploy, retry, cancel, terminate)
 * plus the public tracking logic.
 *
 * @package Pterodactyl_Hosting
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PHM_Orders {

	const STATUSES = [ 'pending', 'paid', 'provisioning', 'active', 'suspended', 'failed', 'cancelled' ];

	public static function init() {
		add_action( 'admin_post_phm_order_action', [ __CLASS__, 'handle_action' ] );
	}

	public static function status_label( $status ) {
		$labels = [
			'pending'      => __( 'Pending payment', 'pterodactyl-hosting' ),
			'paid'         => __( 'Paid — queued', 'pterodactyl-hosting' ),
			'provisioning' => __( 'Deploying…', 'pterodactyl-hosting' ),
			'active'       => __( 'Active', 'pterodactyl-hosting' ),
			'suspended'    => __( 'Suspended (overdue)', 'pterodactyl-hosting' ),
			'failed'       => __( 'Deploy failed', 'pterodactyl-hosting' ),
			'cancelled'    => __( 'Cancelled', 'pterodactyl-hosting' ),
		];
		return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
	}

	public static function status_class( $status ) {
		$map = [
			'pending'      => 'warning',
			'paid'         => 'info',
			'provisioning' => 'info',
			'active'       => 'success',
			'suspended'    => 'warning',
			'failed'       => 'error',
			'cancelled'    => 'muted',
		];
		return isset( $map[ $status ] ) ? $map[ $status ] : 'muted';
	}

	/**
	 * Admin order actions: mark_paid (auto deploy), deploy, retry, cancel,
	 * terminate, delete_record.
	 */
	public static function handle_action() {
		if ( ! current_user_can( PHM_Admin::capability() ) ) {
			wp_die( esc_html__( 'Permission denied.', 'pterodactyl-hosting' ) );
		}
		check_admin_referer( 'phm_order_action' );

		$do     = isset( $_GET['do'] ) ? sanitize_key( $_GET['do'] ) : '';
		$id     = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		$order  = PHM_DB::get_order( $id );
		$notice = 'error';

		if ( ! $order ) {
			wp_safe_redirect( admin_url( 'admin.php?page=phm-orders&phm_msg=missing' ) );
			exit;
		}

		switch ( $do ) {
			case 'mark_paid':
				PHM_DB::update_order( $id, [ 'status' => 'paid' ] );
				$notice = 'paid';
				if ( ! empty( PHM_Settings::get()['auto_deploy_on_paid'] ) ) {
					$result = PHM_Provisioning::deploy( $id );
					$notice = is_wp_error( $result ) ? 'deploy_failed' : 'deployed';
				}
				break;

			case 'deploy':
			case 'retry':
				// Retry after failure: reset error state first.
				if ( 'failed' === $order->status ) {
					PHM_DB::update_order( $id, [ 'status' => 'paid', 'error_message' => '' ] );
				}
				$result = PHM_Provisioning::deploy( $id );
				$notice = is_wp_error( $result ) ? 'deploy_failed' : 'deployed';
				break;

			case 'renew':
				$result = PHM_Billing::renew( $id );
				$notice = is_wp_error( $result ) ? 'error' : 'saved';
				break;

			case 'suspend':
				if ( $order->server_id ) {
					PHM_API::suspend_server( $order->server_id );
				}
				PHM_DB::update_order( $id, [ 'status' => 'suspended' ] );
				$notice = 'saved';
				break;

			case 'unsuspend':
				if ( $order->server_id ) {
					PHM_API::unsuspend_server( $order->server_id );
				}
				PHM_DB::update_order( $id, [ 'status' => 'active' ] );
				$notice = 'saved';
				break;

			case 'cancel':
				PHM_DB::update_order( $id, [ 'status' => 'cancelled' ] );
				PHM_DB::restore_stock( $order->product_id );
				$notice = 'cancelled';
				break;

			case 'terminate':
				$result = PHM_Provisioning::terminate( $id, false );
				$notice = is_wp_error( $result ) ? 'error' : 'cancelled';
				break;

			case 'delete':
				$result = PHM_Provisioning::terminate( $id, true );
				if ( ! is_wp_error( $result ) ) {
					PHM_DB::restore_stock( $order->product_id );
				}
				$notice = is_wp_error( $result ) ? 'error' : 'deleted';
				break;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=phm-orders&phm_msg=' . $notice ) );
		exit;
	}
}
