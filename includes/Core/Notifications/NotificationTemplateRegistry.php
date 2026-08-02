<?php
/**
 * Notification template registry.
 *
 * @package MaklaPlace\Core\Notifications
 */

namespace MaklaPlace\Core\Notifications;

defined( 'ABSPATH' ) || exit;

final class NotificationTemplateRegistry {

	/**
	 * @var array<string, array<string, string>>
	 */
	private array $templates = array(
		'customer' => array(
			'order.received'   => 'Your order has been received.',
			'order.accepted'    => 'Your order has been accepted.',
			'order.preparing'   => 'Your order is being prepared.',
			'order.ready'       => 'Your order is ready.',
			'order.completed'   => 'Your order has been completed.',
			'order.cancelled'   => 'Your order has been cancelled.',
		),
		'chef' => array(
			'new_order'          => 'You have received a new order.',
			'order.cancelled'    => 'An order has been cancelled.',
			'wallet.ready'       => 'Your wallet is ready for collection.',
		),
		'administrator' => array(
			'chef.registered'    => 'A new chef has registered.',
			'wallet.threshold'   => 'A wallet threshold has been reached.',
			'system.alert'       => 'A system alert requires attention.',
			'delivery'          => 'A delivery update is available.',
		),
	);

	/**
	 * Get a template for a role and key.
	 *
	 * @param string $audience Audience name.
	 * @param string $template_key Template key.
	 * @return string
	 */
	public function get( string $audience, string $template_key ) : string {
		return $this->templates[ sanitize_key( $audience ) ][ sanitize_key( $template_key ) ] ?? '';
	}

	/**
	 * Find a template by event key across all audiences.
	 *
	 * @param string $template_key Template key.
	 * @return string
	 */
	public function find( string $template_key ) : string {
		$template_key = sanitize_key( $template_key );
		foreach ( $this->templates as $audience_templates ) {
			if ( isset( $audience_templates[ $template_key ] ) ) {
				return $audience_templates[ $template_key ];
			}
		}

		return '';
	}

	/**
	 * Get all templates.
	 *
	 * @return array<string, array<string, string>>
	 */
	public function all() : array {
		return $this->templates;
	}
}
