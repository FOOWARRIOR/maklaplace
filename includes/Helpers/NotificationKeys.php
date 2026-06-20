<?php
/**
 * Notification keys.
 *
 * @package MaklaPlace\Helpers
 */

namespace MaklaPlace\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Centralized notification record keys.
 */
final class NotificationKeys {
	public const RECIPIENT_USER_ID = 'maklaplace_notification_recipient_user_id';
	public const SENDER_USER_ID    = 'maklaplace_notification_sender_user_id';
	public const EVENT_TYPE        = 'maklaplace_notification_event_type';
	public const MESSAGE           = 'maklaplace_notification_message';
	public const ORDER_ID          = 'maklaplace_notification_order_id';
	public const CHEF_ID           = 'maklaplace_notification_chef_id';
	public const READ_STATUS       = 'maklaplace_notification_read_status';
	public const CREATED_AT        = 'maklaplace_notification_created_at';
}
