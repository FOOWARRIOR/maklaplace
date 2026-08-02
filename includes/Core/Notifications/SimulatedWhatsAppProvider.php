<?php
/**
 * Simulated WhatsApp provider.
 *
 * @package MaklaPlace\Core\Notifications
 */

namespace MaklaPlace\Core\Notifications;

defined( 'ABSPATH' ) || exit;

final class SimulatedWhatsAppProvider implements WhatsAppProviderInterface {

	public function __construct(
		private bool $enabled = true,
		private string $sender_phone_number = ''
	) {
	}

	public function send( array $payload ) : array {
		error_log(
			sprintf(
				'MaklaPlace WhatsApp simulated provider send via %s: %s',
				$this->sender_phone_number ?: 'default',
				wp_json_encode( $payload )
			)
		);

		return array(
			'success'  => true,
			'response' => array( 'simulated' => true ),
			'error'    => null,
		);
	}

	public function is_enabled() : bool {
		return $this->enabled;
	}

	public function get_provider_name() : string {
		return 'simulated';
	}
}
