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
			'order_received'   => 'Your order has been received.',
			'order_created'    => 'Order #{{1}} is in the kitchen, {{2}}.',
			'order_accepted'   => 'Great news, {{2}}! Your order #{{1}} has been accepted.',
			'order_preparing'  => 'Your order #{{1}} is now being prepared.',
			'order_ready'      => 'Good news, {{2}}! Order #{{1}} is ready.',
			'order_completed'  => 'Your order #{{1}} has been completed. Enjoy your meal, {{2}}.',
			'order_cancelled'  => 'Your order #{{1}} has been cancelled, {{2}}.',
		),
		'chef' => array(
			'chef_order_created'          => 'New order #{{1}} from {{2}} for {{3}} is ready for you.',
			'chef_order_accepted'         => 'You accepted order #{{1}} from {{2}}.',
			'chef_order_preparing'        => 'Order #{{1}} for {{2}} is now in progress.',
			'chef_order_ready'            => 'Order #{{1}} for {{2}} is ready for pickup.',
			'chef_order_completed'        => 'Order #{{1}} for {{2}} has been completed successfully.',
			'chef_new_order'              => 'You have received a new order.',
			'chef_order_cancelled'        => 'Order #{{1}} was cancelled.',
			'chef_wallet_commission_added' => 'Nice work, {{1}}. A commission was added to your wallet.',
			'chef_wallet_ready'           => 'Your wallet is ready for collection, {{1}}.',
			'chef_wallet_deduction_recorded' => 'A wallet deduction has been recorded.',
		),
		'administrator' => array(
			'admin_order_created'          => 'A new order #{{1}} has been created for {{2}}.',
			'admin_order_accepted'         => 'Order #{{1}} has been accepted by the chef.',
			'admin_order_preparing'        => 'Order #{{1}} is now being prepared.',
			'admin_order_ready'            => 'Order #{{1}} is ready for collection or dispatch.',
			'admin_order_completed'        => 'Order #{{1}} has been completed.',
			'admin_order_cancelled'        => 'Order #{{1}} has been cancelled.',
			'admin_wallet_commission_added' => 'A commission was added to a wallet.',
			'admin_chef_registered'        => 'A new chef has registered.',
			'admin_wallet_threshold_reached' => 'A wallet threshold has been reached.',
			'admin_wallet_threshold'       => 'A wallet threshold has been reached.',
			'admin_wallet_ready'           => 'A wallet is ready for collection.',
			'admin_wallet_deduction_recorded' => 'A wallet deduction has been recorded.',
			'admin_system_alert'           => 'A system alert requires attention.',
			'admin_delivery'               => 'A delivery update is available.',
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
		$audience      = $this->normalize_key( $audience );
		$template_key  = $this->normalize_key( $template_key );

		return $this->templates[ $audience ][ $template_key ] ?? '';
	}

	/**
	 * Find a template by event key across all audiences.
	 *
	 * @param string $template_key Template key.
	 * @return string
	 */
	public function find( string $template_key ) : string {
		$template_key = $this->normalize_key( $template_key );
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
	 * @param array<int|string, string|int|float> $replacements Placeholder replacements.
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

		$values = array_values( $replacements );
		foreach ( $values as $index => $value ) {
			$template = str_replace( '{{' . (string) ( $index + 1 ) . '}}', (string) $value, $template );
		}

		return $template;
	}

	/**
	 * Normalize a template or audience key.
	 *
	 * @param string $key Key to normalize.
	 * @return string
	 */
	private function normalize_key( string $key ) : string {
		$key = strtolower( $key );
		$key = str_replace( array( '.', '-' ), '_', $key );
		return sanitize_key( $key );
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
