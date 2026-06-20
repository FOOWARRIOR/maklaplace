<?php
/**
 * Plugin activation handler.
 *
 * @package MaklaPlace\Core
 */

namespace MaklaPlace\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Handles plugin activation.
 */
final class Activator {

	/**
	 * Run activation tasks.
	 *
	 * @return void
	 */
	public static function activate() : void {
		global $wpdb;

		$manager = new DatabaseManager( $wpdb );
		$manager->install();

		$capabilities = new CapabilityManager();
		$user_manager = new UserManager( $capabilities );
		$user_manager->register();
	}
}
