<?php
/**
 * Metadata service.
 *
 * @package MaklaPlace\Core
 */

namespace MaklaPlace\Core;

use MaklaPlace\Helpers\UserMeta;

defined( 'ABSPATH' ) || exit;

/**
 * User metadata access service.
 */
final class MetadataService {

	/**
	 * Get a user meta value.
	 *
	 * @param int    $user_id User ID.
	 * @param string $key Meta key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public function get( int $user_id, string $key, mixed $default = null ) : mixed {
		return UserMeta::get( $user_id, $key, $default );
	}

	/**
	 * Set a user meta value.
	 *
	 * @param int    $user_id User ID.
	 * @param string $key Meta key.
	 * @param mixed  $value Meta value.
	 * @return int|bool
	 */
	public function set( int $user_id, string $key, mixed $value ) : int|bool {
		return UserMeta::set( $user_id, $key, $value );
	}
}
