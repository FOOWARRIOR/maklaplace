<?php
/**
 * Capability service.
 *
 * @package MaklaPlace\Core
 */

namespace MaklaPlace\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Capability checking service.
 */
final class CapabilityService {

	/**
	 * Determine whether a user can perform an action.
	 *
	 * @param int    $user_id User ID.
	 * @param string $capability Capability name.
	 * @return bool
	 */
	public function can( int $user_id, string $capability ) : bool {
		$user = get_userdata( $user_id );

		return $user instanceof \WP_User && user_can( $user, $capability );
	}
}
