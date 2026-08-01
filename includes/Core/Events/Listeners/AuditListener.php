<?php
/**
 * Audit event listener.
 *
 * @package MaklaPlace\Core\Events\Listeners
 */

namespace MaklaPlace\Core\Events\Listeners;

use MaklaPlace\Core\Events\Event;
use MaklaPlace\Core\Events\EventListenerInterface;

defined( 'ABSPATH' ) || exit;

final class AuditListener implements EventListenerInterface {

	public function handle( Event $event ) : void {
		error_log(
			wp_json_encode(
				array(
					'event'      => $event->name,
					'timestamp'  => $event->timestamp,
					'user_id'    => $event->user_id,
					'order_id'   => $event->order_id,
					'chef_id'    => $event->chef_id,
					'payload'    => $event->payload,
				)
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
}
