<?php
/**
 * Notification hook bridge.
 *
 * @package MaklaPlace\Core
 */

namespace MaklaPlace\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Bridges existing domain events to notifications.
 */
final class NotificationHooks {

	/**
	 * Notification service.
	 *
	 * @var NotificationService
	 */
	private NotificationService $notifications;

	/**
	 * Constructor.
	 *
	 * @param NotificationService $notifications Notification service.
	 */
	public function __construct( NotificationService $notifications ) {
		$this->notifications = $notifications;
	}

	/**
	 * Register listeners.
	 *
	 * @return void
	 */
	public function register() : void {
		add_action( 'maklaplace_order_created', array( $this, 'on_order_created' ) );
		add_action( 'maklaplace_order_status_changed', array( $this, 'on_order_status_changed' ), 10, 3 );
		add_action( 'maklaplace_order_completed', array( $this, 'on_order_completed' ) );
		add_action( 'maklaplace_order_cancelled', array( $this, 'on_order_cancelled' ) );
		add_action( 'maklaplace_commission_added', array( $this, 'on_commission_added' ) );
		add_action( 'maklaplace_wallet_threshold_reached', array( $this, 'on_wallet_threshold_reached' ) );
		add_action( 'maklaplace_wallet_updated', array( $this, 'on_wallet_updated' ) );
		add_action( 'maklaplace_chef_approved', array( $this, 'on_chef_approved' ) );
		add_action( 'maklaplace_chef_rejected', array( $this, 'on_chef_rejected' ) );
		add_action( 'maklaplace_chef_suspended', array( $this, 'on_chef_suspended' ) );
	}

	/**
	 * Order created.
	 *
	 * @param array<string, mixed> $order Order data.
	 * @return void
	 */
	public function on_order_created( array $order ) : void {
		$this->notifications->notify_from_event(
			'order_created',
			array(
				'recipient_user_id' => (int) $order['maklaplace_order_customer_user_id'],
				'sender_user_id'    => 0,
				'order_id'          => (int) $order['id'],
				'chef_id'           => (int) $order['maklaplace_order_chef_user_id'],
			)
		);
	}

	/**
	 * Order status changed.
	 *
	 * @param array<string, mixed> $order Order data.
	 * @param string               $old_status Old status.
	 * @param string               $new_status New status.
	 * @return void
	 */
	public function on_order_status_changed( array $order, string $old_status, string $new_status ) : void {
		$this->notifications->notify_from_event(
			'order_status_changed',
			array(
				'recipient_user_id' => (int) $order['maklaplace_order_customer_user_id'],
				'order_id'          => (int) $order['id'],
				'chef_id'           => (int) $order['maklaplace_order_chef_user_id'],
			)
		);
	}

	/**
	 * Order completed.
	 *
	 * @param array<string, mixed> $order Order data.
	 * @return void
	 */
	public function on_order_completed( array $order ) : void {
		$this->notifications->notify_from_event(
			'order_completed',
			array(
				'recipient_user_id' => (int) $order['maklaplace_order_customer_user_id'],
				'order_id'          => (int) $order['id'],
				'chef_id'           => (int) $order['maklaplace_order_chef_user_id'],
			)
		);
	}

	/**
	 * Order cancelled.
	 *
	 * @param array<string, mixed> $order Order data.
	 * @return void
	 */
	public function on_order_cancelled( array $order ) : void {
		$this->notifications->notify_from_event(
			'order_cancelled',
			array(
				'recipient_user_id' => (int) $order['maklaplace_order_customer_user_id'],
				'order_id'          => (int) $order['id'],
				'chef_id'           => (int) $order['maklaplace_order_chef_user_id'],
			)
		);
	}

	/**
	 * Commission added.
	 *
	 * @param array<string, mixed> $payload Payload.
	 * @return void
	 */
	public function on_commission_added( array $payload ) : void {
		$this->notifications->notify_from_event(
			'commission_added',
			array(
				'recipient_user_id' => (int) $payload['chef_user_id'],
				'order_id'          => (int) $payload['order_id'],
				'chef_id'           => (int) $payload['chef_user_id'],
			)
		);
	}

	/**
	 * Wallet threshold reached.
	 *
	 * @param array<string, mixed> $payload Payload.
	 * @return void
	 */
	public function on_wallet_threshold_reached( array $payload ) : void {
		$this->notifications->notify_from_event(
			'wallet_threshold_reached',
			array(
				'recipient_user_id' => (int) $payload['chef_user_id'],
				'chef_id'           => (int) $payload['chef_user_id'],
			)
		);
	}

	/**
	 * Wallet updated.
	 *
	 * @param array<string, mixed> $payload Payload.
	 * @return void
	 */
	public function on_wallet_updated( array $payload ) : void {
		$this->notifications->notify_from_event(
			'wallet_status_changed',
			array(
				'recipient_user_id' => (int) $payload['chef_user_id'],
				'chef_id'           => (int) $payload['chef_user_id'],
			)
		);
	}

	/**
	 * Chef approved.
	 *
	 * @param int $chef_user_id Chef user ID.
	 * @return void
	 */
	public function on_chef_approved( int $chef_user_id ) : void {
		$this->notifications->notify_from_event(
			'chef_approved',
			array(
				'recipient_user_id' => $chef_user_id,
				'chef_id'           => $chef_user_id,
			)
		);
	}

	/**
	 * Chef rejected.
	 *
	 * @param int    $chef_user_id Chef user ID.
	 * @param string $reason Reason.
	 * @return void
	 */
	public function on_chef_rejected( int $chef_user_id, string $reason ) : void {
		$this->notifications->notify_from_event(
			'chef_rejected',
			array(
				'recipient_user_id' => $chef_user_id,
				'chef_id'           => $chef_user_id,
			)
		);
	}

	/**
	 * Chef suspended.
	 *
	 * @param int $chef_user_id Chef user ID.
	 * @return void
	 */
	public function on_chef_suspended( int $chef_user_id ) : void {
		$this->notifications->notify_from_event(
			'chef_suspended',
			array(
				'recipient_user_id' => $chef_user_id,
				'chef_id'           => $chef_user_id,
			)
		);
	}
}
