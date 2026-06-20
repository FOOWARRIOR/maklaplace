<?php
/**
 * User meta key aliases.
 *
 * @package MaklaPlace\Helpers
 */

namespace MaklaPlace\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Convenience aliases for user meta keys.
 */
final class UserMetaKeys {
	public const CHEF_VERIFICATION_STATUS = UserMeta::CHEF_VERIFICATION_STATUS;
	public const CHEF_VERIFICATION_DATE   = UserMeta::CHEF_VERIFICATION_DATE;
	public const CHEF_APPROVAL_DATE       = UserMeta::CHEF_APPROVAL_DATE;
	public const CHEF_PROFILE_COMPLETION  = UserMeta::CHEF_PROFILE_COMPLETION;
	public const CHEF_PHONE_NUMBER        = UserMeta::CHEF_PHONE_NUMBER;
	public const CHEF_ADDRESS             = UserMeta::CHEF_ADDRESS;
	public const CHEF_AVATAR              = UserMeta::CHEF_AVATAR;
	public const CHEF_COVER_IMAGE         = UserMeta::CHEF_COVER_IMAGE;
	public const CUSTOMER_PHONE_NUMBER    = UserMeta::CUSTOMER_PHONE_NUMBER;
	public const CUSTOMER_DEFAULT_ADDRESS = UserMeta::CUSTOMER_DEFAULT_ADDRESS;
	public const CUSTOMER_SAVED_ADDRESSES = UserMeta::CUSTOMER_SAVED_ADDRESSES;
}
