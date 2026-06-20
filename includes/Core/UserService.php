<?php
/**
 * User lookup service.
 *
 * @package MaklaPlace\Core
 */

namespace MaklaPlace\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Centralized user lookup helper.
 */
final class UserService {

	/**
	 * Find a user by ID.
	 *
	 * @param int $user_id User ID.
	 * @return \WP_User|null
	 */
	public function find_by_id( int $user_id ) : ?\WP_User {
		$user = get_userdata( $user_id );

		return $user instanceof \WP_User ? $user : null;
	}
}
