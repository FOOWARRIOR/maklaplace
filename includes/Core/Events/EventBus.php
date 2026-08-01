<?php
/**
 * Event bus.
 *
 * @package MaklaPlace\Core\Events
 */

namespace MaklaPlace\Core\Events;

defined( 'ABSPATH' ) || exit;

final class EventBus {

	public function __construct( private ListenerRegistry $registry ) {
	}

	public function register_listener( EventListenerInterface $listener ) : void {
		$this->registry->register_listener( $listener );
	}

	public function publish( Event $event ) : void {
		foreach ( $this->registry->get_listeners() as $listener ) {
			if ( ! in_array( $event->name, $listener->get_event_names(), true ) ) {
				continue;
			}

			if ( ! $listener->is_enabled() ) {
				continue;
			}

			try {
				$listener->handle( $event );
			} catch ( \Throwable $exception ) {
				error_log( sprintf( 'MaklaPlace event listener failure (%s): %s', $event->name, $exception->getMessage() ) );
			}
		}

		do_action( 'maklaplace_event_published', $event );
	}
}
