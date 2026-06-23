<?php
/**
 * Notification service.
 *
 * @package MaklaPlace\Core
 */

namespace MaklaPlace\Core;

use MaklaPlace\Helpers\NotificationKeys;

defined( 'ABSPATH' ) || exit;

/**
 * Handles internal notification storage and dispatch preparation.
 */
final class NotificationService {

	/**
	 * Create and store a notification.
	 *
	 * @param array<string, mixed> $data Notification data.
	 * @return array<string, mixed>
	 */
	public function create( array $data ) : array {
		$notifications = $this->get_store();
		$notification = array(
			'id'                         => $this->next_id( $notifications ),
			NotificationKeys::RECIPIENT_USER_ID => absint( $data[ NotificationKeys::RECIPIENT_USER_ID ] ?? 0 ),
			NotificationKeys::SENDER_USER_ID    => absint( $data[ NotificationKeys::SENDER_USER_ID ] ?? 0 ),
			NotificationKeys::EVENT_TYPE        => sanitize_text_field( (string) ( $data[ NotificationKeys::EVENT_TYPE ] ?? '' ) ),
			NotificationKeys::MESSAGE           => wp_kses_post( (string) ( $data[ NotificationKeys::MESSAGE ] ?? '' ) ),
			NotificationKeys::ORDER_ID          => absint( $data[ NotificationKeys::ORDER_ID ] ?? 0 ),
			NotificationKeys::CHEF_ID           => absint( $data[ NotificationKeys::CHEF_ID ] ?? 0 ),
			NotificationKeys::READ_STATUS       => ! empty( $data[ NotificationKeys::READ_STATUS ] ) ? 'read' : 'unread',
			NotificationKeys::CREATED_AT        => current_time( 'mysql' ),
		);

		$notifications[ $notification['id'] ] = $notification;
		$this->save_store( $notifications );
		$this->dispatch( $notification );

		return $notification;
	}

	/**
	 * Get notifications for a recipient.
	 *
	 * @param int $user_id User ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_for_user( int $user_id ) : array {
		return array_values(
			array_filter(
				$this->get_store(),
				static fn( array $notification ) : bool => (int) $notification[ NotificationKeys::RECIPIENT_USER_ID ] === $user_id
			)
		);
	}

	/**
	 * Mark a notification as read.
	 *
	 * @param int $notification_id Notification ID.
	 * @return bool
	 */
	public function mark_read( int $notification_id ) : bool {
		$notifications = $this->get_store();

		if ( ! isset( $notifications[ $notification_id ] ) ) {
			return false;
		}

		$notifications[ $notification_id ][ NotificationKeys::READ_STATUS ] = 'read';
		$this->save_store( $notifications );

		return true;
	}

	/**
	 * Get all stored notifications.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_all() : array {
		return array_values( $this->get_store() );
	}

	/**
	 * Build a message for a supported event.
	 *
	 * @param string $event_type Event type.
	 * @param array<string, mixed> $context Context.
	 * @return string
	 */
	public function format_message( string $event_type, array $context = array() ) : string {
		$templates = array(
			'order_created'            => __( 'Your order has been received.', 'maklaplace' ),
			'order_status_changed'     => __( 'Your order status has been updated.', 'maklaplace' ),
			'order_completed'          => __( 'Your order has been completed.', 'maklaplace' ),
			'order_cancelled'          => __( 'Your order has been cancelled.', 'maklaplace' ),
			'commission_added'         => __( 'A commission has been added to your wallet.', 'maklaplace' ),
			'wallet_threshold_reached' => __( 'Your wallet has reached the collection threshold.', 'maklaplace' ),
			'wallet_status_changed'    => __( 'Your wallet status has changed.', 'maklaplace' ),
			'chef_approved'            => __( 'Your chef profile has been approved.', 'maklaplace' ),
			'chef_rejected'            => __( 'Your chef profile has been rejected.', 'maklaplace' ),
			'chef_suspended'           => __( 'Your chef profile has been suspended.', 'maklaplace' ),
		);

		return $templates[ $event_type ] ?? __( 'You have a new notification.', 'maklaplace' );
	}

	/**
	 * Notify from an event.
	 *
	 * @param string               $event_type Event type.
	 * @param array<string, mixed> $context Context.
	 * @return array<string, mixed>
	 */
	public function notify_from_event( string $event_type, array $context = array() ) : array {
		$message = $this->format_message( $event_type, $context );

		return $this->create(
			array(
				NotificationKeys::RECIPIENT_USER_ID => absint( $context['recipient_user_id'] ?? 0 ),
				NotificationKeys::SENDER_USER_ID    => absint( $context['sender_user_id'] ?? 0 ),
				NotificationKeys::EVENT_TYPE        => $event_type,
				NotificationKeys::MESSAGE           => $message,
				NotificationKeys::ORDER_ID          => absint( $context['order_id'] ?? 0 ),
				NotificationKeys::CHEF_ID           => absint( $context['chef_id'] ?? 0 ),
			)
		);
	}

	/**
	 * Dispatch to future channels.
	 *
	 * @param array<string, mixed> $notification Notification record.
	 * @return void
	 */
	private function dispatch( array $notification ) : void {
		do_action( 'maklaplace_notification_created', $notification );
	}

	/**
	 * Get stored notifications.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function get_store() : array {
		$items = get_option( 'maklaplace_notifications', array() );
		return is_array( $items ) ? $items : array();
	}

	/**
	 * Save stored notifications.
	 *
	 * @param array<int, array<string, mixed>> $items Items.
	 * @return void
	 */
	private function save_store( array $items ) : void {
		update_option( 'maklaplace_notifications', $items, false );
	}

	/**
	 * Get the next ID.
	 *
	 * @param array<int, array<string, mixed>> $items Items.
	 * @return int
	 */
	private function next_id( array $items ) : int {
		return empty( $items ) ? 1 : ( max( array_keys( $items ) ) + 1 );
	}
}
