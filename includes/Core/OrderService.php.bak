<?php
/**
 * Order service.
 *
 * @package MaklaPlace\Core
 */

namespace MaklaPlace\Core;

use MaklaPlace\Helpers\MenuKeys;
use MaklaPlace\Helpers\OrderKeys;
use MaklaPlace\Helpers\Validation;
use WP_User;

defined( 'ABSPATH' ) || exit;

/**
 * Handles core order management.
 */
final class OrderService {

	/**
	 * Chef profile service.
	 *
	 * @var ChefProfileService
	 */
	private ChefProfileService $chef_profiles;

	/**
	 * Menu service.
	 *
	 * @var MenuService
	 */
	private MenuService $menus;

	/**
	 * Wallet service.
	 *
	 * @var WalletService
	 */
	private WalletService $wallets;

	/**
	 * Constructor.
	 *
	 * @param ChefProfileService $chef_profiles Chef profile service.
	 * @param MenuService        $menus Menu service.
	 */
	public function __construct( ChefProfileService $chef_profiles, MenuService $menus, WalletService $wallets ) {
		$this->chef_profiles = $chef_profiles;
		$this->menus          = $menus;
		$this->wallets        = $wallets;
	}

	/**
	 * Create an order.
	 *
	 * @param int                  $customer_user_id Customer user ID.
	 * @param array<string, mixed>  $data Order data.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function create_order( int $customer_user_id, array $data ) : array|\WP_Error {
		$payload = $this->sanitize_payload( $customer_user_id, $data );
		$errors   = $this->validate_payload( $payload );

		if ( ! empty( $errors ) ) {
			return new \WP_Error( 'maklaplace_order_invalid', __( 'Invalid order data.', 'maklaplace' ), $errors );
		}

		$hash = $this->build_submission_hash( $payload );
		$orders = $this->get_order_store();

		foreach ( $orders as $existing ) {
			if ( ( $existing[ OrderKeys::SUBMISSION_HASH ] ?? '' ) === $hash ) {
				return new \WP_Error( 'maklaplace_duplicate_order', __( 'Duplicate order submission detected.', 'maklaplace' ) );
			}
		}

		$items = $this->snapshot_items( $payload['items'] );
		$chef_user_id = (int) $payload['chef_user_id'];
		$chef_profile_id = (int) $payload['chef_profile_id'];
		$subtotal = $this->calculate_subtotal( $items );

		$record = array(
			'id'                        => $this->next_id( $orders ),
			OrderKeys::CUSTOMER_USER_ID => $customer_user_id,
			OrderKeys::CUSTOMER_NAME    => $payload['customer_name'],
			OrderKeys::CUSTOMER_PHONE   => $payload['customer_phone'],
			OrderKeys::DELIVERY_ADDRESS => $payload['delivery_address'],
			OrderKeys::CHEF_USER_ID     => $chef_user_id,
			OrderKeys::CHEF_PROFILE_ID  => $chef_profile_id,
			OrderKeys::ITEMS            => $items,
			OrderKeys::SUBTOTAL         => $subtotal,
			OrderKeys::TOTAL_AMOUNT     => $subtotal,
			OrderKeys::CURRENCY         => 'DA',
			OrderKeys::STATUS           => 'pending',
			OrderKeys::CREATED_AT       => current_time( 'mysql' ),
			OrderKeys::UPDATED_AT       => current_time( 'mysql' ),
			OrderKeys::CUSTOMER_NOTES   => Validation::text( $payload['customer_notes'] ?? '' ),
			OrderKeys::CHEF_NOTES       => '',
			OrderKeys::SUBMISSION_HASH  => $hash,
		);

		$orders[ $record['id'] ] = $record;
		$this->save_order_store( $orders );
		do_action( 'maklaplace_order_created', $record );

		return $record;
	}

	/**
	 * Update order status.
	 *
	 * @param int    $actor_user_id Actor user ID.
	 * @param int    $order_id Order ID.
	 * @param string $status New status.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function update_status( int $actor_user_id, int $order_id, string $status ) : array|\WP_Error {
		$orders = $this->get_order_store();
		$order = $orders[ $order_id ] ?? null;

		if ( ! is_array( $order ) ) {
			return new \WP_Error( 'maklaplace_order_not_found', __( 'Order not found.', 'maklaplace' ) );
		}

		if ( in_array( $order[ OrderKeys::STATUS ], array( 'completed', 'cancelled' ), true ) ) {
			return new \WP_Error( 'maklaplace_order_locked', __( 'Completed or cancelled orders cannot be modified.', 'maklaplace' ) );
		}

		$new_status = $this->normalize_status( $status );
		if ( ! $new_status ) {
			return new \WP_Error( 'maklaplace_order_status_invalid', __( 'Invalid order status.', 'maklaplace' ) );
		}

		$is_admin = $this->is_admin( $actor_user_id );
		$is_customer_owner = (int) $order[ OrderKeys::CUSTOMER_USER_ID ] === $actor_user_id;
		$is_chef_owner = (int) $order[ OrderKeys::CHEF_USER_ID ] === $actor_user_id;

		if ( ! $is_admin ) {
			$allowed = $this->allowed_transitions_for_actor( $actor_user_id, $order[ OrderKeys::STATUS ], $new_status, $is_customer_owner, $is_chef_owner );
			if ( is_wp_error( $allowed ) ) {
				return $allowed;
			}
		}

		$old_status = $order[ OrderKeys::STATUS ];
		$order[ OrderKeys::STATUS ] = $new_status;
		$order[ OrderKeys::UPDATED_AT ] = current_time( 'mysql' );
		$orders[ $order_id ] = $order;
		$this->save_order_store( $orders );

		do_action( 'maklaplace_order_status_changed', $order, $old_status, $new_status );
		if ( 'completed' === $new_status ) {
			$this->wallets->add_commission( (int) $order[ OrderKeys::CHEF_USER_ID ], $order_id, (float) $order[ OrderKeys::TOTAL_AMOUNT ] );
			do_action( 'maklaplace_order_completed', $order );
		}
		if ( 'cancelled' === $new_status ) {
			do_action( 'maklaplace_order_cancelled', $order );
		}

		return $order;
	}

	/**
	 * Check whether an order commission has been processed.
	 *
	 * @param int $order_id Order ID.
	 * @return bool
	 */
	public function is_commission_processed( int $order_id ) : bool {
		return $this->wallets->is_commission_processed( $order_id );
	}

	/**
	 * Fetch orders by customer.
	 *
	 * @param int $customer_user_id Customer user ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_orders_by_customer( int $customer_user_id ) : array {
		return array_values(
			array_filter(
				$this->get_order_store(),
				static fn( array $order ) : bool => (int) $order[ OrderKeys::CUSTOMER_USER_ID ] === $customer_user_id
			)
		);
	}

	/**
	 * Fetch orders by chef.
	 *
	 * @param int $chef_user_id Chef user ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_orders_by_chef( int $chef_user_id ) : array {
		return array_values(
			array_filter(
				$this->get_order_store(),
				static fn( array $order ) : bool => (int) $order[ OrderKeys::CHEF_USER_ID ] === $chef_user_id
			)
		);
	}

	/**
	 * Fetch a single order safely.
	 *
	 * @param int $order_id Order ID.
	 * @return array<string, mixed>|null
	 */
	public function get_order( int $order_id ) : ?array {
		$orders = $this->get_order_store();

		return isset( $orders[ $order_id ] ) && is_array( $orders[ $order_id ] ) ? $orders[ $order_id ] : null;
	}

	/**
	 * Validate order ownership.
	 *
	 * @param int $actor_user_id Actor user ID.
	 * @param array<string, mixed> $order Order record.
	 * @return bool
	 */
	public function validate_ownership( int $actor_user_id, array $order ) : bool {
		if ( $this->is_admin( $actor_user_id ) ) {
			return true;
		}

		return (int) $order[ OrderKeys::CUSTOMER_USER_ID ] === $actor_user_id || (int) $order[ OrderKeys::CHEF_USER_ID ] === $actor_user_id;
	}

	/**
	 * Sanitize order input.
	 *
	 * @param int                  $customer_user_id Customer user ID.
	 * @param array<string, mixed>  $data Raw data.
	 * @return array<string, mixed>
	 */
	private function sanitize_payload( int $customer_user_id, array $data ) : array {
		return array(
			'customer_user_id'   => $customer_user_id,
			'customer_name'      => Validation::text( $data['customer_name'] ?? '' ),
			'customer_phone'     => Validation::text( $data['customer_phone'] ?? '' ),
			'delivery_address'   => Validation::text( $data['delivery_address'] ?? '' ),
			'chef_user_id'       => absint( $data['chef_user_id'] ?? 0 ),
			'chef_profile_id'    => absint( $data['chef_profile_id'] ?? 0 ),
			'items'              => is_array( $data['items'] ?? null ) ? $data['items'] : array(),
			'customer_notes'     => Validation::text( $data['customer_notes'] ?? '' ),
			'notes_hash'         => Validation::text( $data['notes_hash'] ?? '' ),
		);
	}

	/**
	 * Validate payload.
	 *
	 * @param array<string, mixed> $payload Payload.
	 * @return array<int, string>
	 */
	private function validate_payload( array $payload ) : array {
		$errors = array();

		if ( empty( $payload['chef_user_id'] ) || ! $this->chef_profiles->is_approved( (int) $payload['chef_user_id'] ) ) {
			$errors[] = 'invalid_chef';
		}

		if ( '' === trim( (string) $payload['customer_name'] ) ) {
			$errors[] = 'missing_customer_name';
		}

		if ( '' === trim( (string) $payload['delivery_address'] ) ) {
			$errors[] = 'missing_delivery_address';
		}

		if ( empty( $payload['items'] ) ) {
			$errors[] = 'missing_items';
		}

		return $errors;
	}

	/**
	 * Snapshot menu items at order time.
	 *
	 * @param array<int, array<string, mixed>> $items Items.
	 * @return array<int, array<string, mixed>>
	 */
	private function snapshot_items( array $items ) : array {
		$snapshots = array();

		foreach ( $items as $item ) {
			$menu_id = absint( $item['menu_item_id'] ?? 0 );
			$quantity = max( 1, absint( $item['quantity'] ?? 1 ) );
			$menu_item = $this->menus->get_menu_item( $menu_id );

			if ( ! is_array( $menu_item ) ) {
				continue;
			}

			$price = (float) ( $menu_item[ MenuKeys::PRICE ] ?? 0 );
			$total = $price * $quantity;

			$snapshots[] = array(
				'menu_item_id'   => $menu_id,
				'item_name'      => (string) ( $menu_item[ MenuKeys::TITLE ] ?? '' ),
				'quantity'       => $quantity,
				'price_snapshot' => $price,
				'total'          => $total,
				'category'       => $menu_item[ MenuKeys::CATEGORY ] ?? '',
				'cuisine_type'   => $menu_item[ MenuKeys::CUISINE_TYPE ] ?? '',
			);
		}

		return $snapshots;
	}

	/**
	 * Calculate subtotal.
	 *
	 * @param array<int, array<string, mixed>> $items Snapshot items.
	 * @return float
	 */
	private function calculate_subtotal( array $items ) : float {
		return array_reduce(
			$items,
			static fn( float $carry, array $item ) : float => $carry + (float) ( $item['total'] ?? 0 ),
			0.0
		);
	}

	/**
	 * Build a submission hash.
	 *
	 * @param array<string, mixed> $payload Payload.
	 * @return string
	 */
	private function build_submission_hash( array $payload ) : string {
		return hash( 'sha256', wp_json_encode( $payload ) . '|' . $payload['customer_user_id'] );
	}

	/**
	 * Normalize status.
	 *
	 * @param string $status Status value.
	 * @return string|null
	 */
	private function normalize_status( string $status ) : ?string {
		$allowed = array( 'pending', 'accepted', 'preparing', 'ready', 'on_the_way', 'completed', 'cancelled' );

		return in_array( $status, $allowed, true ) ? $status : null;
	}

	/**
	 * Determine if the actor can transition status.
	 *
	 * @param int    $actor_user_id Actor user ID.
	 * @param string $current Current status.
	 * @param string $new New status.
	 * @param bool   $is_customer_owner Is customer owner.
	 * @param bool   $is_chef_owner Is chef owner.
	 * @return true|\WP_Error
	 */
	private function allowed_transitions_for_actor( int $actor_user_id, string $current, string $new, bool $is_customer_owner, bool $is_chef_owner ) : true|\WP_Error {
		$customer_allowed = array( 'cancelled' );
		$chef_allowed = array( 'accepted', 'preparing', 'ready', 'completed', 'cancelled' );

		if ( $is_customer_owner && in_array( $new, $customer_allowed, true ) ) {
			return true;
		}

		if ( $is_chef_owner && in_array( $new, $chef_allowed, true ) ) {
			return true;
		}

		return new \WP_Error( 'maklaplace_order_forbidden', __( 'You are not allowed to change this order status.', 'maklaplace' ) );
	}

	/**
	 * Check admin capability.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	private function is_admin( int $user_id ) : bool {
		$user = get_user_by( 'id', $user_id );

		return $user instanceof WP_User && user_can( $user, 'manage_options' );
	}

	/**
	 * Get orders from storage.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function get_order_store() : array {
		$items = get_option( 'maklaplace_orders', array() );
		return is_array( $items ) ? $items : array();
	}

	/**
	 * Save orders.
	 *
	 * @param array<int, array<string, mixed>> $items Orders.
	 * @return void
	 */
	private function save_order_store( array $items ) : void {
		update_option( 'maklaplace_orders', $items, false );
	}

	/**
	 * Get next order ID.
	 *
	 * @param array<int, array<string, mixed>> $items Existing orders.
	 * @return int
	 */
	private function next_id( array $items ) : int {
		return empty( $items ) ? 1 : ( max( array_keys( $items ) ) + 1 );
	}

	/**
	 * Get all orders.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_orders() : array {
		return $this->get_order_store();
	}
}
