<?php
/**
 * Listener registry.
 *
 * @package MaklaPlace\Core\Events
 */

namespace MaklaPlace\Core\Events;

defined( 'ABSPATH' ) || exit;

final class ListenerRegistry {

	/**
	 * @var array<int, EventListenerInterface>
	 */
	private array $listeners = array();

	public function register_listener( EventListenerInterface $listener ) : void {
		$this->listeners[] = $listener;
	}

	/**
	 * @return array<int, EventListenerInterface>
	 */
	public function get_listeners() : array {
		return $this->listeners;
	}
}
