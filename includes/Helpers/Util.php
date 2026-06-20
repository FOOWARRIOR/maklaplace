<?php
/**
 * General utility helpers.
 *
 * @package MaklaPlace\Helpers
 */

namespace MaklaPlace\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * General-purpose utilities.
 */
final class Util {

	/**
	 * Determine whether a value is blank.
	 *
	 * @param mixed $value Value to inspect.
	 * @return bool
	 */
	public static function is_blank( mixed $value ) : bool {
		return null === $value || '' === $value || array() === $value;
	}
}
