<?php
/**
 * WhatsApp provider factory.
 *
 * @package MaklaPlace\Core\Notifications
 */

namespace MaklaPlace\Core\Notifications;

defined( 'ABSPATH' ) || exit;

/**
 * Resolve the configured WhatsApp provider.
 */
final class WhatsAppProviderFactory {

	/**
	 * Create a provider from settings.
	 *
	 * @return WhatsAppProviderInterface
	 */
	public function create() : WhatsAppProviderInterface {
		$settings  = get_option( 'maklaplace_settings', array() );
		$settings  = is_array( $settings ) ? $settings : array();
		$whatsapp  = isset( $settings['notifications']['whatsapp'] ) && is_array( $settings['notifications']['whatsapp'] ) ? $settings['notifications']['whatsapp'] : array();
		$provider  = sanitize_key( (string) ( $whatsapp['provider'] ?? 'simulated' ) );
		$enabled   = ! empty( $whatsapp['enabled'] );
		$endpoint  = esc_url_raw( (string) ( $whatsapp['api_endpoint'] ?? '' ) );
		$token     = sanitize_text_field( (string) ( $whatsapp['api_token'] ?? '' ) );
		$sender    = sanitize_text_field( (string) ( $whatsapp['sender_phone_number'] ?? '' ) );
		$sandbox   = ! empty( $whatsapp['sandbox_mode'] );

		if ( 'real' === $provider ) {
			return new WhatsAppHttpProvider( $enabled, $endpoint, $token, $sender, $sandbox );
		}

		return new SimulatedWhatsAppProvider( $enabled, $sender );
	}
}
