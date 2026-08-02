<?php
/**
 * Notification service.
 *
 * @package MaklaPlace\Core
 */

namespace MaklaPlace\Core;

use MaklaPlace\Core\Notifications\ChannelRegistry;
use MaklaPlace\Core\Notifications\Notification;
use MaklaPlace\Core\Notifications\NotificationChannelInterface;
use MaklaPlace\Core\Notifications\NotificationTemplateRegistry;
use MaklaPlace\Helpers\NotificationKeys;

defined( 'ABSPATH' ) || exit;

/**
 * Handles notification persistence and channel dispatch.
 */
final class NotificationService {

	public function __construct(
		private ChannelRegistry $channels,
		private ?NotificationTemplateRegistry $templates = null
	) {
	}

	/**
	 * Create and store a notification.
	 *
	 * @param array<string, mixed> $data Notification data.
	 * @return array<string, mixed>
	 */
	public function create( array $data ) : array {
		$notifications = $this->get_store();
		$notification = $this->build_legacy_record( $data, $notifications );
		$notifications[ $notification['id'] ] = $notification;
		$this->save_store( $notifications );
		$this->dispatch( $this->to_notification_object( $notification ) );

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
	 * @param string               $event_type Event type.
	 * @param array<string, mixed> $context Context.
	 * @return string
	 */
	public function format_message( string $event_type, array $context = array() ) : string {
		if ( $this->templates instanceof NotificationTemplateRegistry ) {
			$template = $this->templates->find( $event_type );
			if ( '' !== $template ) {
				return $template;
			}
		}

		$templates = array(
			'order.created'            => __( 'Your order has been received.', 'maklaplace' ),
			'order.accepted'           => __( 'Your order has been accepted.', 'maklaplace' ),
			'order.preparing'          => __( 'Your order is being prepared.', 'maklaplace' ),
			'order.ready'              => __( 'Your order is ready.', 'maklaplace' ),
			'order.completed'          => __( 'Your order has been completed.', 'maklaplace' ),
			'order.cancelled'          => __( 'Your order has been cancelled.', 'maklaplace' ),
			'order_created'            => __( 'Your order has been received.', 'maklaplace' ),
			'order_confirmed'          => __( 'Your order has been confirmed.', 'maklaplace' ),
			'order_status_changed'     => __( 'Your order status has been updated.', 'maklaplace' ),
			'order_completed'          => __( 'Your order has been completed.', 'maklaplace' ),
			'order_cancelled'          => __( 'Your order has been cancelled.', 'maklaplace' ),
			'wallet.commission_added'  => __( 'A commission has been added to your wallet.', 'maklaplace' ),
			'wallet.threshold_reached' => __( 'Your wallet has reached the collection threshold.', 'maklaplace' ),
			'wallet.deduction_recorded' => __( 'A wallet deduction has been recorded.', 'maklaplace' ),
			'commission_added'         => __( 'A commission has been added to your wallet.', 'maklaplace' ),
			'wallet_threshold_reached' => __( 'Your wallet has reached the collection threshold.', 'maklaplace' ),
			'wallet_status_changed'    => __( 'Your wallet status has changed.', 'maklaplace' ),
			'chef.registered'          => __( 'Your chef profile has been registered.', 'maklaplace' ),
			'chef.approved'            => __( 'Your chef profile has been approved.', 'maklaplace' ),
			'chef.rejected'            => __( 'Your chef profile has been rejected.', 'maklaplace' ),
			'chef.suspended'           => __( 'Your chef profile has been suspended.', 'maklaplace' ),
			'chef_approved'            => __( 'Your chef profile has been approved.', 'maklaplace' ),
			'chef_rejected'            => __( 'Your chef profile has been rejected.', 'maklaplace' ),
			'chef_suspended'           => __( 'Your chef profile has been suspended.', 'maklaplace' ),
			'customer.registered'      => __( 'Your customer account has been created.', 'maklaplace' ),
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
		$notification = new Notification(
			absint( $context['recipient_user_id'] ?? 0 ),
			(string) ( $context['title'] ?? $this->format_title( $event_type ) ),
			$this->format_message( $event_type, $context ),
			$event_type,
			(string) ( $context['priority'] ?? 'normal' ),
			absint( $context['order_id'] ?? 0 ),
			absint( $context['chef_id'] ?? 0 ),
			is_array( $context['metadata'] ?? null ) ? $context['metadata'] : array(),
			current_time( 'mysql' )
		);

		return $this->create(
			array(
				NotificationKeys::RECIPIENT_USER_ID => $notification->recipient_user_id,
				NotificationKeys::SENDER_USER_ID    => absint( $context['sender_user_id'] ?? 0 ),
				NotificationKeys::EVENT_TYPE        => $notification->type,
				NotificationKeys::MESSAGE           => $notification->message,
				NotificationKeys::ORDER_ID          => $notification->order_id,
				NotificationKeys::CHEF_ID           => $notification->chef_id,
				NotificationKeys::READ_STATUS       => false,
			)
		);
	}

	/**
	 * Build a notification object from a stored record.
	 *
	 * @param array<string, mixed> $record Stored record.
	 * @return Notification
	 */
	public function to_notification_object( array $record ) : Notification {
		return new Notification(
			(int) ( $record[ NotificationKeys::RECIPIENT_USER_ID ] ?? 0 ),
			$this->format_title( (string) ( $record[ NotificationKeys::EVENT_TYPE ] ?? '' ) ),
			(string) ( $record[ NotificationKeys::MESSAGE ] ?? '' ),
			(string) ( $record[ NotificationKeys::EVENT_TYPE ] ?? '' ),
			(string) ( $record['priority'] ?? 'normal' ),
			(int) ( $record[ NotificationKeys::ORDER_ID ] ?? 0 ),
			(int) ( $record[ NotificationKeys::CHEF_ID ] ?? 0 ),
			(array) ( $record['metadata'] ?? array() ),
			(string) ( $record[ NotificationKeys::CREATED_AT ] ?? current_time( 'mysql' ) )
		);
	}

	/**
	 * Dispatch through all enabled channels.
	 *
	 * @param Notification $notification Notification object.
	 * @return void
	 */
	private function dispatch( Notification $notification ) : void {
		foreach ( $this->channels->get_active_channels() as $channel ) {
			$this->dispatch_to_channel( $channel, $notification );
		}

		do_action( 'maklaplace_notification_created', $this->notification_to_array( $notification ) );
	}

	/**
	 * Dispatch to a single channel.
	 *
	 * @param NotificationChannelInterface $channel Channel.
	 * @param Notification                 $notification Notification.
	 * @return void
	 */
	private function dispatch_to_channel( NotificationChannelInterface $channel, Notification $notification ) : void {
		try {
			$channel->send( $notification );
		} catch ( \Throwable $exception ) {
			error_log(
				sprintf(
					'MaklaPlace notification channel failure (%s): %s',
					$channel->getChannelName(),
					$exception->getMessage()
				)
			);
		}
	}

	/**
	 * Transform a notification object back to legacy array shape.
	 *
	 * @param Notification $notification Notification.
	 * @return array<string, mixed>
	 */
	private function notification_to_array( Notification $notification ) : array {
		return array(
			NotificationKeys::RECIPIENT_USER_ID => $notification->recipient_user_id,
			NotificationKeys::SENDER_USER_ID    => 0,
			NotificationKeys::EVENT_TYPE        => $notification->type,
			NotificationKeys::MESSAGE           => $notification->message,
			NotificationKeys::ORDER_ID          => $notification->order_id,
			NotificationKeys::CHEF_ID           => $notification->chef_id,
			NotificationKeys::READ_STATUS       => 'unread',
			NotificationKeys::CREATED_AT        => $notification->created_at,
		);
	}

	/**
	 * Build a legacy record and persistable array.
	 *
	 * @param array<string, mixed> $data Data.
	 * @param array<int, array<string, mixed>> $notifications Existing notifications.
	 * @return array<string, mixed>
	 */
	private function build_legacy_record( array $data, array $notifications ) : array {
		$record = array(
			'id'                               => $this->next_id( $notifications ),
			NotificationKeys::RECIPIENT_USER_ID => absint( $data[ NotificationKeys::RECIPIENT_USER_ID ] ?? 0 ),
			NotificationKeys::SENDER_USER_ID    => absint( $data[ NotificationKeys::SENDER_USER_ID ] ?? 0 ),
			NotificationKeys::EVENT_TYPE        => sanitize_text_field( (string) ( $data[ NotificationKeys::EVENT_TYPE ] ?? '' ) ),
			NotificationKeys::MESSAGE           => wp_kses_post( (string) ( $data[ NotificationKeys::MESSAGE ] ?? '' ) ),
			NotificationKeys::ORDER_ID          => absint( $data[ NotificationKeys::ORDER_ID ] ?? 0 ),
			NotificationKeys::CHEF_ID           => absint( $data[ NotificationKeys::CHEF_ID ] ?? 0 ),
			NotificationKeys::READ_STATUS       => ! empty( $data[ NotificationKeys::READ_STATUS ] ) ? 'read' : 'unread',
			NotificationKeys::CREATED_AT        => current_time( 'mysql' ),
			'priority'                          => sanitize_key( (string) ( $data['priority'] ?? 'normal' ) ),
			'metadata'                          => is_array( $data['metadata'] ?? null ) ? $data['metadata'] : array(),
		);

		return $record;
	}

	/**
	 * Build a title for the event.
	 *
	 * @param string $event_type Event type.
	 * @return string
	 */
	private function format_title( string $event_type ) : string {
		return ucwords( str_replace( array( '_', '-' ), ' ', sanitize_key( $event_type ) ) );
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
