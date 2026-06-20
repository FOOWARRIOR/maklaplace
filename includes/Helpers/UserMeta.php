<?php
/**
 * User meta helpers.
 *
 * @package MaklaPlace\Helpers
 */

namespace MaklaPlace\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Centralized helpers for user metadata.
 */
final class UserMeta {

	public const CHEF_VERIFICATION_STATUS = 'maklaplace_chef_verification_status';
	public const CHEF_VERIFICATION_DATE   = 'maklaplace_chef_verification_date';
	public const CHEF_APPROVAL_DATE       = 'maklaplace_chef_approval_date';
	public const CHEF_PROFILE_COMPLETION  = 'maklaplace_chef_profile_completion';
	public const CHEF_PHONE_NUMBER        = 'maklaplace_chef_phone_number';
	public const CHEF_ADDRESS             = 'maklaplace_chef_address';
	public const CHEF_AVATAR              = 'maklaplace_chef_avatar';
	public const CHEF_COVER_IMAGE         = 'maklaplace_chef_cover_image';
	public const CUSTOMER_PHONE_NUMBER    = 'maklaplace_customer_phone_number';
	public const CUSTOMER_DEFAULT_ADDRESS = 'maklaplace_customer_default_address';
	public const CUSTOMER_SAVED_ADDRESSES = 'maklaplace_customer_saved_addresses';
	public const WALLET_BALANCE           = '_maklaplace_wallet_balance';
	public const WALLET_STATUS            = '_maklaplace_wallet_status';
	public const WALLET_LAST_UPDATED      = '_maklaplace_wallet_last_updated';
	public const COMMISSION_PROCESSED     = '_maklaplace_commission_processed';

	/**
	 * Get a user meta value.
	 *
	 * @param int    $user_id User ID.
	 * @param string $key Meta key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public static function get( int $user_id, string $key, mixed $default = null ) : mixed {
		$value = get_user_meta( $user_id, $key, true );

		return '' === $value ? $default : $value;
	}

	/**
	 * Update a user meta value.
	 *
	 * @param int    $user_id User ID.
	 * @param string $key Meta key.
	 * @param mixed  $value Meta value.
	 * @return int|bool
	 */
	public static function set( int $user_id, string $key, mixed $value ) : int|bool {
		return update_user_meta( $user_id, $key, $value );
	}
}
