<?php
/**
 * Settings registry. All panel/Cloudflare configuration lives in one option.
 * Secrets can alternatively be supplied via wp-config constants so they are
 * never stored in the database:
 *   define('PHM_PANEL_URL', 'https://panel.example.com');
 *   define('PHM_APP_KEY',   'ptla_...');
 *   define('PHM_CF_TOKEN',  '...');
 *
 * @package Pterodactyl_Hosting
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PHM_Settings {

	const OPTION = 'phm_settings';

	public static function defaults() {
		return [
			'panel_url'              => '',
			'app_api_key'            => '',
			'cf_enabled'             => 0,
			'cf_auth_type'           => 'token', // token = Bearer API token; global = email + Global API key.
			'cf_api_email'           => '',
			'cf_api_token'           => '',
			'cf_zone_id'             => '',
			'cf_base_domain'         => '',
			'cf_create_srv'          => 1,
			'cf_proxied'             => 0,       // keep DNS-only for game traffic by default.
			'cf_subdomain_required'  => 0,
			'default_currency'       => 'USD',
			'auto_sync'              => 'hourly',
			'auto_deploy_on_paid'    => 1,
			'auto_deploy_on_order'   => 0, // deploy instantly without payment (testing only).
			'discord_webhook'        => '',
			'notify_email_admin'     => 1,
			'notify_email_customer'  => 1,
			'pay_easypaisa_enabled'  => 0,
			'pay_easypaisa_details'  => '',
			'pay_jazzcash_enabled'   => 0,
			'pay_jazzcash_details'   => '',
			'pay_bank_enabled'       => 0,
			'pay_bank_details'       => '',
			'pay_card_enabled'       => 0,
			'pay_card_details'       => '',
			'billing_auto_suspend'   => 1,  // suspend overdue servers automatically.
			'billing_reminder_days'  => 3,  // email customer this many days before due date.
			'billing_period_months'  => 1,  // renewal period applied on deploy/renew.
			'success_page_text'      => '',
		];
	}

	public static function init() {
		add_action( 'admin_init', [ __CLASS__, 'register' ] );
	}

	public static function register() {
		register_setting( 'phm_settings_group', self::OPTION, [
			'sanitize_callback' => [ __CLASS__, 'sanitize' ],
		] );
	}

	public static function seed_defaults() {
		if ( ! get_option( self::OPTION ) ) {
			add_option( self::OPTION, self::defaults() );
		}
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function get() {
		$saved = get_option( self::OPTION, [] );
		if ( ! is_array( $saved ) ) {
			$saved = [];
		}
		$settings = array_merge( self::defaults(), $saved );

		// wp-config constants always win and are never shown in the UI.
		if ( defined( 'PHM_PANEL_URL' ) && PHM_PANEL_URL ) {
			$settings['panel_url'] = (string) PHM_PANEL_URL;
		}

		$settings['panel_url']      = untrailingslashit( (string) $settings['panel_url'] );
		$settings['cf_base_domain'] = strtolower( trim( (string) $settings['cf_base_domain'], " ." ) );

		return $settings;
	}

	public static function update( array $values ) {
		$current = (array) get_option( self::OPTION, [] );
		update_option( self::OPTION, array_merge( $current, self::sanitize( $values ) ) );
	}

	/**
	 * Application API key, constant takes precedence.
	 */
	public static function app_key() {
		if ( defined( 'PHM_APP_KEY' ) && PHM_APP_KEY ) {
			return (string) PHM_APP_KEY;
		}
		$s = self::get();
		return isset( $s['app_api_key'] ) ? (string) $s['app_api_key'] : '';
	}

	public static function cf_token() {
		if ( defined( 'PHM_CF_TOKEN' ) && PHM_CF_TOKEN ) {
			return (string) PHM_CF_TOKEN;
		}
		$s = self::get();
		return isset( $s['cf_api_token'] ) ? (string) $s['cf_api_token'] : '';
	}

	public static function is_configured() {
		$s = self::get();
		return ! empty( $s['panel_url'] ) && '' !== self::app_key();
	}

	public static function panel_url() {
		$s = self::get();
		return $s['panel_url'];
	}

	public static function sanitize( $input ) {
		$input = is_array( $input ) ? $input : [];
		$out   = [];

		$out['panel_url']     = isset( $input['panel_url'] ) ? untrailingslashit( esc_url_raw( trim( (string) $input['panel_url'] ) ) ) : '';
		$out['cf_base_domain'] = isset( $input['cf_base_domain'] ) ? strtolower( trim( sanitize_text_field( (string) $input['cf_base_domain'] ), " ." ) ) : '';
		$out['cf_zone_id']    = isset( $input['cf_zone_id'] ) ? sanitize_text_field( (string) $input['cf_zone_id'] ) : '';
		$out['cf_api_email']  = isset( $input['cf_api_email'] ) ? sanitize_email( (string) $input['cf_api_email'] ) : '';
		$out['cf_auth_type']  = ( isset( $input['cf_auth_type'] ) && 'global' === $input['cf_auth_type'] ) ? 'global' : 'token';
		$out['billing_reminder_days'] = isset( $input['billing_reminder_days'] ) ? max( 0, (int) $input['billing_reminder_days'] ) : 3;
		$out['billing_period_months'] = isset( $input['billing_period_months'] ) ? max( 1, (int) $input['billing_period_months'] ) : 1;

		// Keep old secrets when the masked placeholder is submitted unchanged.
		$current = (array) get_option( self::OPTION, [] );
		foreach ( [ 'app_api_key', 'cf_api_token' ] as $secret ) {
			$value = isset( $input[ $secret ] ) ? trim( (string) $input[ $secret ] ) : '';
			if ( '' === $value || false !== strpos( $value, '•' ) || false !== strpos( $value, '***' ) ) {
				$out[ $secret ] = isset( $current[ $secret ] ) ? (string) $current[ $secret ] : '';
			} else {
				$out[ $secret ] = sanitize_text_field( $value );
			}
		}

		$out['default_currency'] = isset( $input['default_currency'] ) ? strtoupper( sanitize_key( (string) $input['default_currency'] ) ) : 'USD';
		$out['auto_sync']        = isset( $input['auto_sync'] ) ? sanitize_key( (string) $input['auto_sync'] ) : 'hourly';

		foreach ( [ 'cf_enabled', 'cf_create_srv', 'cf_proxied', 'cf_subdomain_required', 'auto_deploy_on_paid', 'auto_deploy_on_order', 'notify_email_admin', 'notify_email_customer', 'pay_easypaisa_enabled', 'pay_jazzcash_enabled', 'pay_bank_enabled', 'pay_card_enabled', 'billing_auto_suspend' ] as $bool ) {
			$out[ $bool ] = ! empty( $input[ $bool ] ) ? 1 : 0;
		}

		foreach ( [ 'discord_webhook', 'pay_easypaisa_details', 'pay_jazzcash_details', 'pay_bank_details', 'pay_card_details', 'success_page_text' ] as $text ) {
			$out[ $text ] = isset( $input[ $text ] ) ? wp_kses_post( (string) $input[ $text ] ) : '';
		}

		return $out;
	}

	/**
	 * Mask a secret for display: ptla_a1b2••••••z9y8
	 */
	public static function mask( $secret ) {
		$secret = (string) $secret;
		if ( strlen( $secret ) <= 8 ) {
			return $secret ? '••••••••' : '';
		}
		return substr( $secret, 0, 9 ) . '••••••••' . substr( $secret, -4 );
	}

	/**
	 * Enabled payment methods for checkout.
	 *
	 * @return array<string,array<string,string>>
	 */
	public static function payment_methods() {
		$s       = self::get();
		$methods = [];
		$defs    = [
			'easypaisa' => [ 'label' => __( 'EasyPaisa', 'pterodactyl-hosting' ), 'enabled' => $s['pay_easypaisa_enabled'], 'details' => $s['pay_easypaisa_details'] ],
			'jazzcash'  => [ 'label' => __( 'JazzCash', 'pterodactyl-hosting' ), 'enabled' => $s['pay_jazzcash_enabled'], 'details' => $s['pay_jazzcash_details'] ],
			'bank'      => [ 'label' => __( 'Bank Transfer', 'pterodactyl-hosting' ), 'enabled' => $s['pay_bank_enabled'], 'details' => $s['pay_bank_details'] ],
			'card'      => [ 'label' => __( 'Card / International', 'pterodactyl-hosting' ), 'enabled' => $s['pay_card_enabled'], 'details' => $s['pay_card_details'] ],
		];
		foreach ( $defs as $key => $def ) {
			if ( ! empty( $def['enabled'] ) ) {
				$methods[ $key ] = $def;
			}
		}
		// WooCommerce acts as an extra gateway when active.
		if ( class_exists( 'WooCommerce' ) ) {
			$methods['woocommerce'] = [ 'label' => __( 'Online payment (WooCommerce)', 'pterodactyl-hosting' ), 'details' => '' ];
		}
		if ( ! $methods ) {
			$methods['manual'] = [ 'label' => __( 'Manual / Discord ticket', 'pterodactyl-hosting' ), 'details' => __( 'Our team will contact you with payment instructions after you place the order.', 'pterodactyl-hosting' ) ];
		}
		return $methods;
	}
}
