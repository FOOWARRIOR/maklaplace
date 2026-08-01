<?php
/**
 * Channel registry.
 *
 * @package MaklaPlace\Core\Notifications
 */

namespace MaklaPlace\Core\Notifications;

defined( 'ABSPATH' ) || exit;

final class ChannelRegistry {

	/**
	 * @var array<string, NotificationChannelInterface>
	 */
	private array $channels = array();

	public function register_channel( NotificationChannelInterface $channel ) : void {
		$this->channels[ $channel->getChannelName() ] = $channel;
	}

	/**
	 * @return array<string, NotificationChannelInterface>
	 */
	public function get_channels() : array {
		return $this->channels;
	}

	/**
	 * @return array<string, NotificationChannelInterface>
	 */
	public function get_active_channels() : array {
		return array_filter(
			$this->channels,
			static fn( NotificationChannelInterface $channel ) : bool => $channel->isEnabled()
		);
	}
}
