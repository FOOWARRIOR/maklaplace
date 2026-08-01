<?php
/**
 * Analytics event listener.
 *
 * @package MaklaPlace\Core\Events\Listeners
 */

namespace MaklaPlace\Core\Events\Listeners;

use MaklaPlace\Core\AnalyticsService;
use MaklaPlace\Core\Events\Event;
use MaklaPlace\Core\Events\EventListenerInterface;

defined( 'ABSPATH' ) || exit;

final class AnalyticsListener implements EventListenerInterface {

	public function __construct( private AnalyticsService $analytics ) {
	}

	public function handle( Event $event ) : void {
		if ( str_starts_with( $event->name, 'wallet.' ) ) {
			$this->analytics->record_commission_event(
				array_merge(
					$event->payload,
					array(
						'event'        => $event->name,
						'chef_user_id' => $event->chef_id,
						'order_id'     => $event->order_id,
					)
				)
			);
			return;
		}

		$this->analytics->record_order_event(
			array(
				'event'        => $event->name,
				'order_id'     => $event->order_id,
				'chef_user_id' => $event->chef_id,
				'total'        => isset( $event->payload['total'] ) ? (float) $event->payload['total'] : 0.0,
				'status'       => (string) ( $event->payload['status'] ?? '' ),
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
		);
	}
}
