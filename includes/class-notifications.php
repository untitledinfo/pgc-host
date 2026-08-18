<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Ptero_Notifications {

	private static $instance = null;
	public static function instance() {
		if ( self::$instance === null ) self::$instance = new self();
		return self::$instance;
	}

	private function get_order( $order_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ptero_servers';
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $order_id ) );
	}

	private function mail_user( $order, $subject, $message ) {
		$user = get_user_by( 'id', $order->user_id );
		if ( ! $user ) return;
		wp_mail( $user->user_email, '[' . get_bloginfo( 'name' ) . '] ' . $subject, $message );
	}

	public function send_order_received( $order_id ) {
		$order = $this->get_order( $order_id );
		if ( ! $order ) return;
		$msg = sprintf(
			"Thanks for your order #%d — %s\nPrice: %s %s / %s\n\nPayment instructions:\n%s\n\nYour server will be provisioned once payment is confirmed.",
			$order_id, $order->server_name, $order->price, $order->currency, $order->billing_cycle,
			get_option( 'ptero_manual_instructions' )
		);
		$this->mail_user( $order, 'Order Received #' . $order_id, $msg );

		$admin = get_option( 'admin_email' );
		wp_mail( $admin, 'New Server Order #' . $order_id, "New order received: {$order->server_name} ({$order->ram}MB/{$order->cpu}%/{$order->disk}MB) — {$order->price} {$order->currency}." );
	}

	public function send_server_ready( $order_id ) {
		$order = $this->get_order( $order_id );
		if ( ! $order ) return;
		$msg = sprintf(
			"Your server \"%s\" is ready!\nIP: %s\nRAM: %d MB | CPU: %d%% | Disk: %d MB\nManage it here: %s\n\nExpires: %s",
			$order->server_name,
			$order->ip_address ? $order->ip_address . ':' . $order->port : 'See dashboard',
			$order->ram, $order->cpu, $order->disk,
			home_url( '/my-servers/' ),
			$order->expires_at
		);
		$this->mail_user( $order, 'Your Server Is Ready 🎮', $msg );
	}

	public function send_renewal_reminder( $order_id ) {
		$order = $this->get_order( $order_id );
		if ( ! $order ) return;
		$this->mail_user( $order, 'Renewal Reminder — ' . $order->server_name,
			"Your server \"{$order->server_name}\" expires on {$order->expires_at}. Please renew to avoid suspension." );
	}

	public function send_suspended_notice( $order_id ) {
		$order = $this->get_order( $order_id );
		if ( ! $order ) return;
		$this->mail_user( $order, 'Server Suspended — ' . $order->server_name,
			"Your server \"{$order->server_name}\" has been suspended due to non-payment. Renew to restore access." );
	}
}
