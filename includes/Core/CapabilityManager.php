<?php
/**
 * Capability manager.
 *
 * @package MaklaPlace\Core
 */

namespace MaklaPlace\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and exposes MaklaPlace capabilities.
 */
final class CapabilityManager {

	/**
	 * All plugin capabilities.
	 *
	 * @return array<int, string>
	 */
	public function all() : array {
		return array(
			'maklaplace_manage_own_profile',
			'maklaplace_manage_own_menu',
			'maklaplace_manage_own_orders',
			'maklaplace_view_own_wallet',
			'maklaplace_view_analytics',
			'maklaplace_place_orders',
			'maklaplace_manage_addresses',
			'maklaplace_manage_favorites',
			'maklaplace_leave_reviews',
			'maklaplace_view_own_orders',
		);
	}

	/**
	 * Chef capabilities.
	 *
	 * @return array<int, string>
	 */
	public function chef() : array {
		return array(
			'maklaplace_manage_own_profile',
			'maklaplace_manage_own_menu',
			'maklaplace_manage_own_orders',
			'maklaplace_view_own_wallet',
			'maklaplace_view_analytics',
		);
	}

	/**
	 * Customer capabilities.
	 *
	 * @return array<int, string>
	 */
	public function customer() : array {
		return array(
			'maklaplace_place_orders',
			'maklaplace_manage_addresses',
			'maklaplace_manage_favorites',
			'maklaplace_leave_reviews',
			'maklaplace_view_own_orders',
		);
	}

	/**
	 * Grant all plugin capabilities to administrators.
	 *
	 * @return void
	 */
	public function grant_admin_caps() : void {
		$role = get_role( 'administrator' );

		if ( ! $role ) {
			return;
		}

		foreach ( $this->all() as $capability ) {
			$role->add_cap( $capability );
		}
	}

	/**
	 * Remove all plugin capabilities from administrators.
	 *
	 * @return void
	 */
	public function revoke_admin_caps() : void {
		$role = get_role( 'administrator' );

		if ( ! $role ) {
			return;
		}

		foreach ( $this->all() as $capability ) {
			$role->remove_cap( $capability );
		}
	}
}
