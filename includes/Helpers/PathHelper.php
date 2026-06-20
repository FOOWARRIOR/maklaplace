<?php
/**
 * Path helper utilities.
 *
 * @package MaklaPlace\Helpers
 */

namespace MaklaPlace\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Path-related helper methods.
 */
final class PathHelper {

	/**
	 * Join path segments.
	 *
	 * @param string ...$segments Path segments.
	 * @return string
	 */
	public static function join( string ...$segments ) : string {
		$segments = array_filter(
			$segments,
			static fn( string $segment ) : bool => '' !== $segment
		);

		return implode( DIRECTORY_SEPARATOR, array_map( 'trim', $segments ) );
	}

	/**
	 * Normalize directory separators.
	 *
	 * @param string $path Path to normalize.
	 * @return string
	 */
	public static function normalize( string $path ) : string {
		return str_replace( array( '/', '\\' ), DIRECTORY_SEPARATOR, $path );
	}
}
