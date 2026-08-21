<?php
/**
 * Plugin Name:       Pterodactyl Hosting Manager
 * Plugin URI:        https://github.com/untitledinfo/PGCHOST
 * Description:       Sell Pterodactyl game servers (Minecraft, Paper, Forge & any egg) from WordPress — Paymenter-style order flow with synced nests/eggs/locations, subdomain cart with automatic Cloudflare DNS, manual or WooCommerce payments and fully automatic server deployment.
 * Version:           3.1.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            PGCHOST
 * Author URI:        https://pgcmc.fun
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       pterodactyl-hosting
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'PHM_VERSION', '3.0.0' );
define( 'PHM_FILE', __FILE__ );
define( 'PHM_PATH', plugin_dir_path( __FILE__ ) );
define( 'PHM_URL', plugin_dir_url( __FILE__ ) );

require_once PHM_PATH . 'includes/class-autoloader.php';
PHM_Autoloader::register();

register_activation_hook( __FILE__, [ 'PHM_Plugin', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'PHM_Plugin', 'deactivate' ] );

PHM_Plugin::boot();
