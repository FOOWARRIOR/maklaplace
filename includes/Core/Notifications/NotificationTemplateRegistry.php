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
			'order.created'    => 'Your order has been received. Order #{order_id} is now pending.',
			'order.accepted'   => 'Your order has been accepted.',
			'order.preparing'  => 'Your order is being prepared.',
			'order.ready'       => 'Your order is ready.',
			'order.completed'  => 'Your order has been completed.',
			'order.cancelled'  => 'Your order has been cancelled.',
		),
		'chef' => array(
			'order.created'     => 'New order #{order_id} from {customer_name} for {order_total} has been created.',
			'order.accepted'    => 'Order #{order_id} from {customer_name} has been accepted.',
			'order.preparing'   => 'You started preparing order #{order_id} for {customer_name}.',
			'order.ready'       => 'Order #{order_id} for {customer_name} is ready for pickup.',
			'order.completed'   => 'Order #{order_id} for {customer_name} has been completed.',
			'new_order'          => 'You have received a new order.',
			'order.cancelled'   => 'An order has been cancelled.',
			'wallet.commission_added' => 'A commission has been added to your wallet.',
			'wallet.ready'       => 'Your wallet is ready for collection.',
			'wallet.deduction_recorded' => 'A wallet deduction has been recorded.',
		),
		'administrator' => array(
			'order.created'      => 'A new order #{order_id} has been created for {chef_name}.',
			'order.accepted'     => 'Order #{order_id} has been accepted by the chef.',
			'order.preparing'    => 'Order #{order_id} is now being prepared.',
			'order.ready'        => 'Order #{order_id} is ready for collection or dispatch.',
			'order.completed'    => 'Order #{order_id} has been completed.',
			'order.cancelled'    => 'Order #{order_id} has been cancelled.',
			'wallet.commission_added' => 'A commission was added to a wallet.',
			'chef.registered'    => 'A new chef has registered.',
			'wallet.threshold_reached' => 'A wallet threshold has been reached.',
			'wallet.threshold'   => 'A wallet threshold has been reached.',
			'wallet.ready'       => 'A wallet is ready for collection.',
			'wallet.deduction_recorded' => 'A wallet deduction has been recorded.',
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
	 * Render a template with placeholder replacements.
	 *
	 * @param string $audience Audience name.
	 * @param string $template_key Template key.
	 * @param array<string, string|int|float> $replacements Placeholder replacements.
	 * @return string
	 */
	public function render( string $audience, string $template_key, array $replacements = array() ) : string {
		$template = $this->get( $audience, $template_key );
		if ( '' === $template ) {
			$template = $this->find( $template_key );
		}

		if ( '' === $template ) {
			return '';
		}

		foreach ( $replacements as $placeholder => $value ) {
			$template = str_replace( '{' . sanitize_key( (string) $placeholder ) . '}', (string) $value, $template );
		}

		return $template;
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
