<?php
/**
 * WhatsApp provider contract.
 *
 * @package MaklaPlace\Core\Notifications
 */

namespace MaklaPlace\Core\Notifications;

defined( 'ABSPATH' ) || exit;

/**
 * Provider adapter for delivering WhatsApp messages.
 */
interface WhatsAppProviderInterface {

	/**
	 * Send a message payload.
	 *
	 * @param array<string, mixed> $payload Provider payload.
	 * @return array{success: bool, response: mixed, error: string|null}
	 */
	public function send( array $payload ) : array;

	/**
	 * Determine whether the provider is enabled.
	 *
	 * @return bool
	 */
	public function is_enabled() : bool;

	/**
	 * Get provider name.
	 *
	 * @return string
	 */
	public function get_provider_name() : string;
}
