<?php
/**
 * Real WhatsApp provider adapter.
 *
 * @package MaklaPlace\Core\Notifications
 */

namespace MaklaPlace\Core\Notifications;

defined( 'ABSPATH' ) || exit;

final class WhatsAppHttpProvider implements WhatsAppProviderInterface {

	public function __construct(
		private bool $enabled,
		private string $api_endpoint,
		private string $api_token,
		private string $sender_phone_number,
		private bool $sandbox_mode = false
	) {
	}

	public function send( array $payload ) : array {
		if ( ! $this->is_enabled() ) {
			return array(
				'success'  => false,
				'response' => null,
				'error'    => __( 'WhatsApp provider is disabled.', 'maklaplace' ),
			);
		}

		if ( '' === $this->api_endpoint ) {
			return array(
				'success'  => false,
				'response' => null,
				'error'    => __( 'WhatsApp API endpoint is not configured.', 'maklaplace' ),
			);
		}

		$request = array(
			'method'  => 'POST',
			'timeout' => 15,
			'headers' => array(
				'Content-Type'  => 'application/json',
				'Authorization' => $this->api_token !== '' ? 'Bearer ' . $this->api_token : '',
			),
			'body'    => wp_json_encode(
				array_merge(
					$payload,
					array(
						'sender_phone_number' => $this->sender_phone_number,
						'sandbox_mode'        => $this->sandbox_mode,
					)
				)
			),
		);

		$response = wp_remote_post( $this->api_endpoint, $request );
		if ( is_wp_error( $response ) ) {
			return array(
				'success'  => false,
				'response' => null,
				'error'    => $response->get_error_message(),
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( $code < 200 || $code >= 300 ) {
			return array(
				'success'  => false,
				'response' => $body,
				'error'    => sprintf( 'Unexpected provider response code %d.', $code ),
			);
		}

		return array(
			'success'  => true,
			'response' => $body,
			'error'    => null,
		);
	}

	public function is_enabled() : bool {
		return $this->enabled;
	}

	public function get_provider_name() : string {
		return 'real';
	}
}
