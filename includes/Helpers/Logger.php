<?php
/**
 * Placeholder logger.
 *
 * @package MaklaPlace\Helpers
 */

namespace MaklaPlace\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Simple logger placeholder for future use.
 */
final class Logger {

	/**
	 * Log a message.
	 *
	 * @param string $message Log message.
	 * @return void
	 */
	public function info( string $message ) : void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[MaklaPlace] ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	/**
	 * Log an error message.
	 *
	 * @param string $message Log message.
	 * @return void
	 */
	public function error( string $message ) : void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[MaklaPlace][ERROR] ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}
}
