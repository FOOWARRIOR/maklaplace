<?php
/**
 * Channel-independent notification value object.
 *
 * @package MaklaPlace\Core\Notifications
 */

namespace MaklaPlace\Core\Notifications;

defined( 'ABSPATH' ) || exit;

/**
 * Immutable notification payload shared across channels.
 */
final class Notification {

	public function __construct(
		public readonly int $recipient_user_id,
		public readonly string $title,
		public readonly string $message,
		public readonly string $type,
		public readonly string $priority = 'normal',
		public readonly int $order_id = 0,
		public readonly int $chef_id = 0,
		public readonly array $metadata = array(),
		public readonly string $created_at = ''
	) {
	}
}
