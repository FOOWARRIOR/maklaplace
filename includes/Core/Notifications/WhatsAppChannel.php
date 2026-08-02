<?php
/**
 * WhatsApp notification channel.
 *
 * @package MaklaPlace\Core\Notifications
 */

namespace MaklaPlace\Core\Notifications;

defined( 'ABSPATH' ) || exit;

final class WhatsAppChannel implements NotificationChannelInterface {

	public function __construct( private WhatsAppProviderFactory $provider_factory ) {
	}

	public function send( Notification $notification ) : bool {
		$provider = $this->provider_factory->create();
		$payload  = array(
			'recipient_user_id' => $notification->recipient_user_id,
			'title'             => $notification->title,
			'message'           => $notification->message,
			'type'              => $notification->type,
			'priority'          => $notification->priority,
			'order_id'          => $notification->order_id,
			'chef_id'           => $notification->chef_id,
			'metadata'          => $notification->metadata,
			'created_at'        => $notification->created_at,
		);

		if ( ! $provider->is_enabled() ) {
			error_log( sprintf( 'MaklaPlace WhatsApp provider disabled: %s', $provider->get_provider_name() ) );
			return false;
		}

		$result = $provider->send( $payload );

		if ( ! empty( $result['success'] ) ) {
			error_log(
				sprintf(
					'MaklaPlace WhatsApp sent via %s: %s',
					$provider->get_provider_name(),
					wp_json_encode( $result['response'] )
				)
			);
			return true;
		}

		error_log(
			sprintf(
				'MaklaPlace WhatsApp failed via %s: %s',
				$provider->get_provider_name(),
				(string) ( $result['error'] ?? __( 'Unknown provider error.', 'maklaplace' ) )
			)
		);

		return false;
	}

	public function isEnabled() : bool {
		return true;
	}

	public function getChannelName() : string {
		return 'whatsapp';
	}
}
