<?php
/**
 * Generic event object.
 *
 * @package MaklaPlace\Core\Events
 */

namespace MaklaPlace\Core\Events;

defined( 'ABSPATH' ) || exit;

/**
 * Immutable event payload.
 */
final class Event {

	public function __construct(
		public readonly string $name,
		public readonly string $timestamp,
		public readonly array $payload = array(),
		public readonly int $user_id = 0,
		public readonly int $order_id = 0,
		public readonly int $chef_id = 0
	) {
	}
}
