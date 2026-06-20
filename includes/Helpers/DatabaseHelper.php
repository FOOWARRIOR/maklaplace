<?php
/**
 * Database helper utilities.
 *
 * @package MaklaPlace\Helpers
 */

namespace MaklaPlace\Helpers;

use MaklaPlace\Core\DatabaseManager;

defined( 'ABSPATH' ) || exit;

/**
 * Database helper methods.
 */
final class DatabaseHelper {

	/**
	 * Get a plugin table name.
	 *
	 * @param string $suffix Table suffix.
	 * @return string
	 */
	public static function table_name( string $suffix ) : string {
		global $wpdb;

		return $wpdb->prefix . 'maklaplace_' . ltrim( $suffix, '_' );
	}

	/**
	 * Get the current schema version.
	 *
	 * @return string
	 */
	public static function schema_version() : string {
		return DatabaseManager::SCHEMA_VERSION;
	}
}
