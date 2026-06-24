<?php
/**
 * Order field keys.
 *
 * @package MaklaPlace\Helpers
 */

namespace MaklaPlace\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Centralized order data keys.
 */
final class OrderKeys {
	public const CUSTOMER_USER_ID        = 'maklaplace_order_customer_user_id';
	public const CUSTOMER_NAME           = 'maklaplace_order_customer_name';
	public const CUSTOMER_PHONE          = 'maklaplace_order_customer_phone';
	public const DELIVERY_ADDRESS        = 'maklaplace_order_delivery_address';
	public const CHEF_USER_ID            = 'maklaplace_order_chef_user_id';
	public const CHEF_PROFILE_ID         = 'maklaplace_order_chef_profile_id';
	public const ITEMS                   = 'maklaplace_order_items';
	public const SUBTOTAL                = 'maklaplace_order_subtotal';
	public const TOTAL_AMOUNT            = 'maklaplace_order_total_amount';
	public const CURRENCY                = 'maklaplace_order_currency';
	public const PAYMENT_METHOD          = 'maklaplace_order_payment_method';
	public const STATUS                  = 'maklaplace_order_status';
	public const CREATED_AT              = 'maklaplace_order_created_at';
	public const UPDATED_AT              = 'maklaplace_order_updated_at';
	public const CUSTOMER_NOTES          = 'maklaplace_order_customer_notes';
	public const CHEF_NOTES              = 'maklaplace_order_chef_notes';
	public const SUBMISSION_HASH         = 'maklaplace_order_submission_hash';
}
