<?php
/**
 * WhatsApp notification channel placeholder.
 *
 * @package MaklaPlace\Core\Notifications
 */

namespace MaklaPlace\Core\Notifications;

defined( 'ABSPATH' ) || exit;

final class WhatsAppChannel implements NotificationChannelInterface {

	public function send( Notification $notification ) : bool {
		$this->log_simulated_delivery( $notification );
		return $this->send_via_provider( $notification );
	}

	public function isEnabled() : bool {
		return true;
	}

	public function getChannelName() : string {
		return 'whatsapp';
	}

	/**
	 * Future provider integration point.
	 *
	 * Replace this method body with a real WhatsApp API client call when a
	 * provider is selected.
	 *
	 * @param Notification $notification Notification payload.
	 * @return bool
	 */
	private function send_via_provider( Notification $notification ) : bool {
		do_action( 'maklaplace_notification_channel_whatsapp_send', $notification );
		return true;
	}

	/**
	 * Log simulated delivery for local development.
	 *
	 * @param Notification $notification Notification payload.
	 * @return void
	 */
	private function log_simulated_delivery( Notification $notification ) : void {
		error_log(
			sprintf(
				'MaklaPlace WhatsApp simulated send to user %d: %s | %s',
				$notification->recipient_user_id,
				$notification->title,
				$notification->message
			)
		);
	}
}
