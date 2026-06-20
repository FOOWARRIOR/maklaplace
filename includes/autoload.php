<?php
/**
 * Plugin autoloader bootstrap.
 *
 * @package MaklaPlace
 */

defined( 'ABSPATH' ) || exit;

$composer_autoload = MAKLAPLACE_PATH . 'vendor/autoload.php';

if ( file_exists( $composer_autoload ) ) {
	require_once $composer_autoload;
}

spl_autoload_register(
	static function ( string $class ) : void {
		$prefix = 'MaklaPlace\\';

		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}

		$relative_class = substr( $class, strlen( $prefix ) );
		$relative_path  = str_replace( '\\', DIRECTORY_SEPARATOR, $relative_class );
		$file           = MAKLAPLACE_PATH . 'includes/' . $relative_path . '.php';

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
);
