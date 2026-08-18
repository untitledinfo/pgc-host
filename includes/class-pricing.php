<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Ptero_Pricing {

	public static function calculate( array $args ) {
		$base    = floatval( get_option( 'ptero_price_base', 0 ) );
		$ram_p   = floatval( get_option( 'ptero_price_per_ram_mb', 0.05 ) );
		$cpu_p   = floatval( get_option( 'ptero_price_per_cpu_pct', 0.8 ) );
		$disk_p  = floatval( get_option( 'ptero_price_per_disk_mb', 0.01 ) );
		$ip_p    = floatval( get_option( 'ptero_price_dedicated_ip', 300 ) );
		$backup_p= floatval( get_option( 'ptero_price_backup', 20 ) );
		$db_p    = floatval( get_option( 'ptero_price_database', 20 ) );

		$total = $base;
		$total += ( $args['ram']  ?? 0 ) * $ram_p;
		$total += ( $args['cpu']  ?? 0 ) * $cpu_p;
		$total += ( $args['disk'] ?? 0 ) * $disk_p;
		$total += ! empty( $args['dedicated_ip'] ) ? $ip_p : 0;
		$total += ( $args['backups']   ?? 0 ) * $backup_p;
		$total += ( $args['databases'] ?? 0 ) * $db_p;

		if ( ! empty( $args['billing_cycle'] ) ) {
			$multipliers = array( 'monthly' => 1, 'quarterly' => 3 * 0.95, 'yearly' => 12 * 0.85 );
			$total *= $multipliers[ $args['billing_cycle'] ] ?? 1;
		}

		if ( ! empty( $args['coupon'] ) ) {
			$total = Ptero_Coupons::apply( $args['coupon'], $total );
		}

		return round( $total, 2 );
	}
}
