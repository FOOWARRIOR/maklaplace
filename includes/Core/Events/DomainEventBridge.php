<?php
/**
 * Bridges existing WordPress actions into the event bus.
 *
 * @package MaklaPlace\Core\Events
 */

namespace MaklaPlace\Core\Events;

defined( 'ABSPATH' ) || exit;

final class DomainEventBridge {

	public function __construct( private EventBus $bus ) {
	}

	public function register() : void {
		add_action( 'maklaplace_order_created', array( $this, 'on_order_created' ), 10, 1 );
		add_action( 'maklaplace_order_confirmed', array( $this, 'on_order_confirmed' ), 10, 1 );
		add_action( 'maklaplace_order_status_changed', array( $this, 'on_order_status_changed' ), 10, 3 );
		add_action( 'maklaplace_order_completed', array( $this, 'on_order_completed' ), 10, 1 );
		add_action( 'maklaplace_order_cancelled', array( $this, 'on_order_cancelled' ), 10, 1 );
		add_action( 'maklaplace_commission_added', array( $this, 'on_commission_added' ), 10, 1 );
		add_action( 'maklaplace_wallet_threshold_reached', array( $this, 'on_wallet_threshold_reached' ), 10, 1 );
		add_action( 'maklaplace_wallet_deduction_recorded', array( $this, 'on_wallet_deduction_recorded' ), 10, 1 );
		add_action( 'maklaplace_chef_registered', array( $this, 'on_chef_registered' ), 10, 1 );
		add_action( 'maklaplace_chef_approved', array( $this, 'on_chef_approved' ), 10, 1 );
		add_action( 'maklaplace_chef_rejected', array( $this, 'on_chef_rejected' ), 10, 2 );
		add_action( 'maklaplace_chef_suspended', array( $this, 'on_chef_suspended' ), 10, 1 );
		add_action( 'maklaplace_customer_registered', array( $this, 'on_customer_registered' ), 10, 1 );
	}

	public function on_order_created( array $order ) : void {
		$this->bus->publish( $this->event_from_order( 'order.created', $order ) );
	}

	public function on_order_confirmed( array $order ) : void {
		$this->bus->publish( $this->event_from_order( 'order.accepted', $order ) );
	}

	public function on_order_status_changed( array $order, string $old_status, string $new_status ) : void {
		$this->bus->publish( $this->event_from_order( 'order.' . sanitize_key( $new_status ), $order, array( 'old_status' => $old_status, 'new_status' => $new_status ) ) );
	}

	public function on_order_completed( array $order ) : void {
		$this->bus->publish( $this->event_from_order( 'order.completed', $order ) );
	}

	public function on_order_cancelled( array $order ) : void {
		$this->bus->publish( $this->event_from_order( 'order.cancelled', $order ) );
	}

	public function on_commission_added( array $payload ) : void {
		$this->bus->publish(
			new Event(
				'wallet.commission_added',
				current_time( 'mysql' ),
				$payload,
				absint( $payload['chef_user_id'] ?? 0 ),
				absint( $payload['order_id'] ?? 0 ),
				absint( $payload['chef_user_id'] ?? 0 )
			)
		);
	}

	public function on_wallet_threshold_reached( array $payload ) : void {
		$this->bus->publish(
			new Event(
				'wallet.threshold_reached',
				current_time( 'mysql' ),
				$payload,
				absint( $payload['chef_user_id'] ?? 0 ),
				0,
				absint( $payload['chef_user_id'] ?? 0 )
			)
		);
	}

	public function on_wallet_deduction_recorded( array $payload ) : void {
		$this->bus->publish(
			new Event(
				'wallet.deduction_recorded',
				current_time( 'mysql' ),
				$payload,
				absint( $payload['chef_user_id'] ?? 0 ),
				0,
				absint( $payload['chef_user_id'] ?? 0 )
			)
		);
	}

	public function on_chef_registered( array $chef ) : void {
		$this->bus->publish( $this->event_from_chef( 'chef.registered', $chef ) );
	}

	public function on_chef_approved( int $chef_user_id ) : void {
		$this->bus->publish( $this->event_from_chef_id( 'chef.approved', $chef_user_id ) );
	}

	public function on_chef_rejected( int $chef_user_id, string $reason ) : void {
		$this->bus->publish( $this->event_from_chef_id( 'chef.rejected', $chef_user_id, array( 'reason' => $reason ) ) );
	}

	public function on_chef_suspended( int $chef_user_id ) : void {
		$this->bus->publish( $this->event_from_chef_id( 'chef.suspended', $chef_user_id ) );
	}

	public function on_customer_registered( array $customer ) : void {
		$this->bus->publish(
			new Event(
				'customer.registered',
				current_time( 'mysql' ),
				$customer,
				absint( $customer['user_id'] ?? 0 ),
				0,
				0
			)
		);
	}

	private function event_from_order( string $name, array $order, array $payload = array() ) : Event {
		return new Event(
			$name,
			current_time( 'mysql' ),
			array_merge( $order, $payload ),
			absint( $order['maklaplace_order_customer_user_id'] ?? 0 ),
			absint( $order['id'] ?? 0 ),
			absint( $order['maklaplace_order_chef_user_id'] ?? 0 )
		);
	}

	private function event_from_chef( string $name, array $chef ) : Event {
		return new Event(
			$name,
			current_time( 'mysql' ),
			$chef,
			absint( $chef['user_id'] ?? 0 ),
			0,
			absint( $chef['user_id'] ?? 0 )
		);
	}

	private function event_from_chef_id( string $name, int $chef_user_id, array $payload = array() ) : Event {
		return new Event(
			$name,
			current_time( 'mysql' ),
			$payload,
			$chef_user_id,
			0,
			$chef_user_id
		);
	}
}
