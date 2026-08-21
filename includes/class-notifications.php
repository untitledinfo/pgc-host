<?php
/**
 * Email + Discord notifications for the order lifecycle.
 *
 * @package Pterodactyl_Hosting
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PHM_Notifications {

	public static function order_placed( $order ) {
		$s       = PHM_Settings::get();
		$is_free = (float) $order->amount <= 0;

		if ( ! empty( $s['notify_email_admin'] ) ) {
			$subject = sprintf( '[%s] %s %s', get_bloginfo( 'name' ), __( 'New order', 'pterodactyl-hosting' ), $order->order_number );
			$body    = sprintf(
				"New order received:\n\nOrder:  %s\nPlan:   %s\nEgg:    %s\nAmount: %s %s\nName:   %s\nEmail:  %s\nDiscord:%s\nDomain: %s\nMethod: %s\n\nMark it paid in wp-admin → PGC Hosting → Orders.",
				$order->order_number, $order->plan_name, $order->egg_name,
				$order->amount, $order->currency,
				$order->customer_name, $order->email, $order->discord,
				$order->fqdn, $order->payment_method
			);
			wp_mail( get_option( 'admin_email' ), $subject, $body );
		}

		if ( ! empty( $s['notify_email_customer'] ) ) {
			$subject = sprintf( __( 'Your order %s — payment instructions', 'pterodactyl-hosting' ), $order->order_number );
			$methods = PHM_Settings::payment_methods();
			$details = isset( $methods[ $order->payment_method ]['details'] ) ? $methods[ $order->payment_method ]['details'] : '';
			$body    = sprintf(
				"Hi %s,\n\nThanks for your order %s (%s).\nTotal: %s %s\n\nPayment method: %s\n%s\n\nOnce we confirm your payment your server deploys automatically — you will receive another email with the panel login and server address.\n\n— %s",
				$order->customer_name, $order->order_number, $order->plan_name,
				$order->amount, $order->currency,
				isset( $methods[ $order->payment_method ]['label'] ) ? $methods[ $order->payment_method ]['label'] : $order->payment_method,
				$details ? "\n" . wp_strip_all_tags( $details ) . "\n" : '',
				get_bloginfo( 'name' )
			);
			wp_mail( $order->email, $subject, $body );
		}

		self::discord( sprintf( '🛒 **New order %s** — %s (%s %s)\n👤 %s · %s\n🌐 %s · 💳 %s',
			$order->order_number, $order->plan_name, $order->amount, $order->currency,
			$order->customer_name, $order->email,
			$order->fqdn ?: '—', $order->payment_method
		) );
	}

	public static function server_deployed( $order ) {
		$s    = PHM_Settings::get();
		$addr = PHM_Frontend::public_address( $order );
		if ( ! $addr ) {
			$addr = __( 'Connect through the Game Panel (node IP is private)', 'pterodactyl-hosting' );
		}

		if ( ! empty( $s['notify_email_customer'] ) ) {
			$subject = sprintf( __( 'Your server is live — %s', 'pterodactyl-hosting' ), $order->order_number );
			$note    = $order->credential_note ? $order->credential_note : __( 'Use "Forgot password" on the panel login page if you don\'t know your password.', 'pterodactyl-hosting' );
			$body    = sprintf(
				"Hi %s,\n\nYour %s server is online!\n\nPanel:  %s\nLogin:  %s\n%s\nHostname: %s\n\nOrder: %s\n\n— %s",
				$order->customer_name,
				$order->plan_name,
				PHM_Settings::panel_url(),
				$order->email,
				$note,
				$addr,
				$order->order_number,
				get_bloginfo( 'name' )
			);
			wp_mail( $order->email, $subject, $body );
		}

		self::discord( sprintf( '✅ **Deployed %s** — server ID %s\n🌐 Hostname: `%s`', $order->order_number, $order->server_id, $addr ) );
	}

	public static function renewal_reminder( $order ) {
		$s = PHM_Settings::get();
		if ( empty( $s['notify_email_customer'] ) || empty( $order->next_due_at ) ) {
			return;
		}
		$subject = sprintf( __( '⏰ Renewal reminder — %s', 'pterodactyl-hosting' ), $order->order_number );
		$body    = sprintf(
			"Hi %s,\n\nYour server for %s renews on %s.\nPrice: %s %s\n\nIf payment is not received by the due date the server is suspended automatically. Suspended servers are kept for a grace period, then deleted.\n\nOrder: %s\n— %s",
			$order->customer_name, $order->plan_name, $order->next_due_at,
			$order->amount, $order->currency, $order->order_number, get_bloginfo( 'name' )
		);
		wp_mail( $order->email, $subject, $body );
	}

	public static function server_suspended( $order ) {
		$s = PHM_Settings::get();
		if ( ! empty( $s['notify_email_customer'] ) ) {
			$subject = sprintf( __( 'Server suspended — %s', 'pterodactyl-hosting' ), $order->order_number );
			$body    = sprintf(
				"Hi %s,\n\nYour %s server was suspended because the renewal (due %s) is overdue.\nPay your renewal and we will unsuspend it immediately — your files are safe.\n\nOrder: %s\n— %s",
				$order->customer_name, $order->plan_name, $order->next_due_at, $order->order_number, get_bloginfo( 'name' )
			);
			wp_mail( $order->email, $subject, $body );
		}
		self::discord( sprintf( '⏸️ **Suspended %s** — overdue since %s', $order->order_number, $order->next_due_at ) );
	}

	/* ---------------------------------------------------------------------
	 * Support tickets
	 * ------------------------------------------------------------------- */

	public static function ticket_created( $ticket, $wp_user, $message ) {
		$s = PHM_Settings::get();
		if ( ! empty( $s['notify_email_admin'] ) ) {
			$subject = sprintf( '[%s] %s %s — %s', get_bloginfo( 'name' ), __( 'New support ticket', 'pterodactyl-hosting' ), $ticket->ticket_number, $ticket->subject );
			$body    = sprintf(
				"New support ticket:\n\nTicket:   %s\nFrom:     %s (%s)\nPriority: %s\n\n%s\n\nReply in wp-admin → PGC Hosting → Support Tickets.",
				$ticket->ticket_number, $wp_user->display_name, $wp_user->user_email,
				PHM_Tickets::priority_label( $ticket->priority ), $message
			);
			wp_mail( get_option( 'admin_email' ), $subject, $body );
		}
		self::discord( sprintf( '🎫 **New ticket %s** — %s\n👤 %s (%s)', $ticket->ticket_number, $ticket->subject, $wp_user->display_name, $wp_user->user_email ) );
	}

	public static function ticket_customer_reply( $ticket, $wp_user, $message ) {
		$s = PHM_Settings::get();
		if ( ! empty( $s['notify_email_admin'] ) ) {
			$subject = sprintf( '[%s] %s %s', get_bloginfo( 'name' ), __( 'Customer replied to ticket', 'pterodactyl-hosting' ), $ticket->ticket_number );
			$body    = sprintf(
				"%s replied to ticket %s (%s):\n\n%s\n\nReply in wp-admin → PGC Hosting → Support Tickets.",
				$wp_user->display_name, $ticket->ticket_number, $ticket->subject, $message
			);
			wp_mail( get_option( 'admin_email' ), $subject, $body );
		}
		self::discord( sprintf( '💬 **Customer reply on %s** — %s', $ticket->ticket_number, $ticket->subject ) );
	}

	public static function ticket_staff_reply( $ticket, $message ) {
		$s = PHM_Settings::get();
		if ( empty( $s['notify_email_customer'] ) ) {
			return;
		}
		$customer = get_userdata( $ticket->wp_user_id );
		if ( ! $customer ) {
			return;
		}
		$subject = sprintf( __( 'Reply to your ticket %s', 'pterodactyl-hosting' ), $ticket->ticket_number );
		$body    = sprintf(
			"Hi %s,\n\nSupport replied to your ticket %s (%s):\n\n%s\n\nLog in to reply: %s\n\n— %s",
			$customer->display_name, $ticket->ticket_number, $ticket->subject, $message,
			PHM_Store::page_url( 'phm_dashboard' ) ?: home_url( '/' ),
			get_bloginfo( 'name' )
		);
		wp_mail( $customer->user_email, $subject, $body );
	}

	public static function discord( $content ) {
		$s = PHM_Settings::get();
		if ( empty( $s['discord_webhook'] ) ) {
			return;
		}
		wp_remote_post( $s['discord_webhook'], [
			'timeout' => 10,
			'headers' => [ 'Content-Type' => 'application/json' ],
			'body'    => wp_json_encode( [ 'content' => $content ] ),
		] );
	}
}
