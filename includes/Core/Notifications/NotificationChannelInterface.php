<?php
/**
 * Notification channel contract.
 *
 * @package MaklaPlace\Core\Notifications
 */

namespace MaklaPlace\Core\Notifications;

defined( 'ABSPATH' ) || exit;

interface NotificationChannelInterface {

	public function send( Notification $notification ) : bool;

	public function isEnabled() : bool;

	public function getChannelName() : string;
}
