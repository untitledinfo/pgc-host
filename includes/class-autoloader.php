<?php
/**
 * Simple PSR-style autoloader for PHM_* classes.
 *
 * @package Pterodactyl_Hosting
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PHM_Autoloader {

	/**
	 * Class => file map for classes that do not follow the standard rule.
	 *
	 * @var array<string,string>
	 */
	private static $file_map = [
		'PHM_Elementor' => 'includes/class-elementor-widget.php',
	];

	public static function register() {
		spl_autoload_register( [ __CLASS__, 'autoload' ] );
	}

	/**
	 * @param string $class Fully qualified class name.
	 */
	public static function autoload( $class ) {
		if ( strpos( $class, 'PHM_' ) !== 0 ) {
			return;
		}

		// Elementor widgets live in their own subfolder and are only loaded
		// by PHM_Elementor AFTER Elementor itself is available.
		if ( strpos( $class, 'PHM_Widget_' ) === 0 ) {
			$slug = strtolower( str_replace( '_', '-', substr( $class, 11 ) ) );
			$path = PHM_PATH . 'includes/widgets/class-' . $slug . '-widget.php';
			if ( file_exists( $path ) ) {
				require_once $path;
			}
			return;
		}

		if ( isset( self::$file_map[ $class ] ) ) {
			$path = PHM_PATH . self::$file_map[ $class ];
		} else {
			$slug = strtolower( str_replace( '_', '-', substr( $class, 4 ) ) );
			$path = PHM_PATH . 'includes/class-' . $slug . '.php';
		}

		if ( file_exists( $path ) ) {
			require_once $path;
		}
	}
}
