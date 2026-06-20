<?php
/**
 * Analytics hook bridge.
 *
 * @package MaklaPlace\Core
 */

namespace MaklaPlace\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Bridges marketplace events into analytics updates.
 */
final class AnalyticsHooks {

	/**
	 * Analytics service.
	 *
	 * @var AnalyticsService
	 */
	private AnalyticsService $analytics;

	/**
	 * Constructor.
	 *
	 * @param AnalyticsService $analytics Analytics service.
	 */
	public function __construct( AnalyticsService $analytics ) {
		$this->analytics = $analytics;
	}

	/**
	 * Register hook listeners.
	 *
	 * @return void
	 */
	public function register() : void {
		add_action( 'maklaplace_order_created', array( $this, 'on_order_created' ), 10, 1 );
		add_action( 'maklaplace_order_status_changed', array( $this, 'on_order_status_changed' ), 10, 3 );
		add_action( 'maklaplace_order_completed', array( $this, 'on_order_completed' ), 10, 1 );
		add_action( 'maklaplace_commission_added', array( $this, 'on_commission_added' ), 10, 1 );
	}

	/**
	 * Handle order creation.
	 *
	 * @param array<string, mixed> $order Order record.
	 * @return void
	 */
	public function on_order_created( array $order ) : void {
		$this->analytics->record_order_event(
			array(
				'event'        => 'order_created',
				'order_id'     => absint( $order['id'] ?? 0 ),
				'chef_user_id' => absint( $order['maklaplace_order_chef_user_id'] ?? 0 ),
				'total'        => (float) ( $order['maklaplace_order_total_amount'] ?? 0 ),
				'status'       => (string) ( $order['maklaplace_order_status'] ?? 'pending' ),
			)
		);
	}

	/**
	 * Handle order status changes.
	 *
	 * @param array<string, mixed> $order Order record.
	 * @param string               $old_status Old status.
	 * @param string               $new_status New status.
	 * @return void
	 */
	public function on_order_status_changed( array $order, string $old_status, string $new_status ) : void {
		$this->analytics->record_order_event(
			array(
				'event'        => 'order_status_changed',
				'order_id'     => absint( $order['id'] ?? 0 ),
				'chef_user_id' => absint( $order['maklaplace_order_chef_user_id'] ?? 0 ),
				'total'        => (float) ( $order['maklaplace_order_total_amount'] ?? 0 ),
				'status'       => $new_status,
				'old_status'   => $old_status,
			)
		);
	}

	/**
	 * Handle completed orders.
	 *
	 * @param array<string, mixed> $order Order record.
	 * @return void
	 */
	public function on_order_completed( array $order ) : void {
		$this->analytics->record_order_event(
			array(
				'event'        => 'order_completed',
				'order_id'     => absint( $order['id'] ?? 0 ),
				'chef_user_id' => absint( $order['maklaplace_order_chef_user_id'] ?? 0 ),
				'total'        => (float) ( $order['maklaplace_order_total_amount'] ?? 0 ),
				'status'       => 'completed',
			)
		);
	}

	/**
	 * Handle commission events.
	 *
	 * @param array<string, mixed> $payload Commission payload.
	 * @return void
	 */
	public function on_commission_added( array $payload ) : void {
		$this->analytics->record_commission_event( $payload );
	}
}
