<?php
/**
 * User management foundation.
 *
 * @package MaklaPlace\Core
 */

namespace MaklaPlace\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Registers roles and capabilities.
 */
final class UserManager {

	/**
	 * Capability manager.
	 *
	 * @var CapabilityManager
	 */
	private CapabilityManager $capabilities;

	/**
	 * Constructor.
	 *
	 * @param CapabilityManager $capabilities Capability manager.
	 */
	public function __construct( CapabilityManager $capabilities ) {
		$this->capabilities = $capabilities;
	}

	/**
	 * Register roles and capabilities.
	 *
	 * @return void
	 */
	public function register() : void {
		$this->register_roles();
		$this->capabilities->grant_admin_caps();
	}

	/**
	 * Check whether a user has a role.
	 *
	 * @param int    $user_id User ID.
	 * @param string $role Role name.
	 * @return bool
	 */
	public function user_has_role( int $user_id, string $role ) : bool {
		$user = get_userdata( $user_id );

		return $user instanceof \WP_User && in_array( $role, (array) $user->roles, true );
	}

	/**
	 * Check whether a user has a capability.
	 *
	 * @param int    $user_id User ID.
	 * @param string $capability Capability name.
	 * @return bool
	 */
	public function user_can( int $user_id, string $capability ) : bool {
		$user = get_userdata( $user_id );

		return $user instanceof \WP_User && user_can( $user, $capability );
	}

	/**
	 * Register custom roles.
	 *
	 * @return void
	 */
	private function register_roles() : void {
		add_role(
			'maklaplace_chef',
			'Chef',
			array_fill_keys( $this->capabilities->chef(), true )
		);

		add_role(
			'maklaplace_customer',
			'Customer',
			array_fill_keys( $this->capabilities->customer(), true )
		);
	}
}
