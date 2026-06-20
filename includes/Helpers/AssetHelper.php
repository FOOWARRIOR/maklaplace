<?php
/**
 * Asset helper utilities.
 *
 * @package MaklaPlace\Helpers
 */

namespace MaklaPlace\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Asset URL helpers.
 */
final class AssetHelper {

	/**
	 * Get the plugin asset URL.
	 *
	 * @param string $path Relative asset path.
	 * @return string
	 */
	public static function url( string $path = '' ) : string {
		$path = ltrim( $path, '/\\' );

		return trailingslashit( MAKLAPLACE_URL . 'assets' ) . $path;
	}
}
