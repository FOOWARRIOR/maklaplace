<?php
/**
 * Event listener contract.
 *
 * @package MaklaPlace\Core\Events
 */

namespace MaklaPlace\Core\Events;

defined( 'ABSPATH' ) || exit;

interface EventListenerInterface {

	public function handle( Event $event ) : void;

	public function is_enabled() : bool;

	public function get_event_names() : array;
}
