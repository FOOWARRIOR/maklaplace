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

		$recipient_phone = sanitize_text_field( (string) ( $payload['recipient_phone_number'] ?? '' ) );
		$template_name = sanitize_text_field( (string) ( $payload['template_name'] ?? 'hello_world' ) );
		$template_language = sanitize_text_field( (string) ( $payload['template_language'] ?? 'en_US' ) );
		$template_parameters = isset( $payload['template_parameters'] ) && is_array( $payload['template_parameters'] ) ? $payload['template_parameters'] : array();
		$endpoint = add_query_arg(
			array(
				'_reqName'          => 'object:phone-number-id/messages',
				'_reqSrc'           => 'WhatsAppBusinessSendMessageCAPI',
				'locale'            => sanitize_text_field( (string) ( $payload['locale'] ?? 'fr_FR' ) ),
				'messaging_product' => 'whatsapp',
				'template'          => wp_json_encode(
					array(
						'name'      => $template_name,
						'language'   => array( 'code' => $template_language ),
						'components' => ! empty( $template_parameters ) ? array(
							array(
								'type'       => 'body',
								'parameters' => array_map(
									static fn( $item ) : array => array(
										'type' => 'text',
										'text' => (string) $item,
									),
									array_values( $template_parameters )
								),
							),
						) : array(),
					)
				),
				'to'               => $recipient_phone,
				'type'             => 'template',
				'xref'             => sanitize_text_field( (string) ( $payload['xref'] ?? 'maklaplace' ) ),
				'access_token'     => $this->api_token,
			),
			$this->api_endpoint
		);

		$request_body = array(
			'messaging_product' => 'whatsapp',
			'to'                => $recipient_phone,
			'type'              => 'template',
			'template'          => array(
				'name'       => $template_name,
				'language'    => array(
					'code' => $template_language,
				),
			),
		);

		if ( ! empty( $template_parameters ) ) {
			$request_body['template']['components'] = array(
				array(
					'type'       => 'body',
					'parameters' => array_map(
						static fn( $item ) : array => array(
							'type' => 'text',
							'text' => (string) $item,
						),
						array_values( $template_parameters )
					),
				),
			);
		}

		$request = array(
			'method'  => 'POST',
			'timeout' => 15,
			'headers' => array(
				'Content-Type' => 'application/json',
			),
			'body'    => wp_json_encode( $request_body ),
		);

		$response = wp_remote_post( $endpoint, $request );
		if ( is_wp_error( $response ) ) {
			return array(
				'success'  => false,
				'response' => array(
					'code'    => 0,
					'body'    => null,
					'headers' => array(),
				),
				'error'    => $response->get_error_message(),
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$parsed_body = json_decode( $body, true );

		if ( $code < 200 || $code >= 300 ) {
			return array(
				'success'  => false,
				'response' => array(
					'code'    => $code,
					'body'    => $parsed_body ? $parsed_body : $body,
					'headers' => wp_remote_retrieve_headers( $response ),
				),
				'error'    => sprintf( 'Unexpected provider response code %d.', $code ),
			);
		}

		return array(
			'success'  => true,
			'response' => array(
				'code'    => $code,
				'body'    => $parsed_body ? $parsed_body : $body,
				'headers' => wp_remote_retrieve_headers( $response ),
			),
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
