<?php
/**
 * Notification event listener.
 *
 * @package MaklaPlace\Core\Events\Listeners
 */

namespace MaklaPlace\Core\Events\Listeners;

use MaklaPlace\Core\Events\Event;
use MaklaPlace\Core\Events\EventListenerInterface;
use MaklaPlace\Core\NotificationService;
use MaklaPlace\Helpers\NotificationKeys;

defined( 'ABSPATH' ) || exit;

final class NotificationListener implements EventListenerInterface {

	public function __construct( private NotificationService $notifications ) {
	}

	public function handle( Event $event ) : void {
		$this->notifications->notify_from_event(
			$event->name,
			array(
				'recipient_user_id' => $event->user_id,
				'order_id'          => $event->order_id,
				'chef_id'           => $event->chef_id,
				'metadata'          => $event->payload,
				'title'             => $this->title_from_event( $event->name ),
				'priority'          => (string) ( $event->payload['priority'] ?? 'normal' ),
			)
		);
	}

	public function is_enabled() : bool {
		return true;
	}

	public function get_event_names() : array {
		return array(
			'order.created',
			'order.accepted',
			'order.preparing',
			'order.ready',
			'order.completed',
			'order.cancelled',
			'wallet.commission_added',
			'wallet.threshold_reached',
			'wallet.deduction_recorded',
			'chef.registered',
			'chef.approved',
			'chef.rejected',
			'chef.suspended',
			'customer.registered',
		);
	}

	private function title_from_event( string $event_name ) : string {
		return ucwords( str_replace( array( '.', '_', '-' ), ' ', $event_name ) );
	}
}
