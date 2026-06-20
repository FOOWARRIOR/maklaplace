<?php
/**
 * Role service.
 *
 * @package MaklaPlace\Core
 */

namespace MaklaPlace\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Role checking service.
 */
final class RoleService {

	/**
	 * Check if a user has a role.
	 *
	 * @param int    $user_id User ID.
	 * @param string $role Role name.
	 * @return bool
	 */
	public function has_role( int $user_id, string $role ) : bool {
		$user = get_userdata( $user_id );

		return $user instanceof \WP_User && in_array( $role, (array) $user->roles, true );
	}
}
