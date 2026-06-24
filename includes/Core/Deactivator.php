<?php
/**
 * Plugin deactivation handler.
 *
 * @package MaklaPlace\Core
 */

namespace MaklaPlace\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Handles plugin deactivation.
 */
final class Deactivator {

	/**
	 * Run deactivation tasks.
	 *
	 * @return void
	 */
	public static function deactivate() : void {
		flush_rewrite_rules();
	}
}
