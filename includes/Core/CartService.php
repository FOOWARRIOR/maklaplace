<?php
/**
 * Cart service.
 *
 * @package MaklaPlace\Core
 */

namespace MaklaPlace\Core;

use MaklaPlace\Helpers\MenuKeys;
use MaklaPlace\Helpers\UserMeta;
use MaklaPlace\Helpers\Validation;

defined( 'ABSPATH' ) || exit;

/**
 * Handles customer cart state.
 */
final class CartService {

	/**
	 * Cart meta key.
	 */
	private const META_KEY = 'maklaplace_cart';

	/**
	 * Menu service.
	 *
	 * @var MenuService
	 */
	private MenuService $menus;

	/**
	 * Chef profile service.
	 *
	 * @var ChefProfileService
	 */
	private ChefProfileService $chef_profiles;

	/**
	 * Supported payment methods.
	 *
	 * @var array<int, string>
	 */
	private array $payment_methods = array( 'cash_on_delivery', 'cod' );

	/**
	 * Current cart strategy.
	 *
	 * @var string
	 */
	private string $cart_strategy = 'single_chef';

	/**
	 * Constructor.
	 *
	 * @param MenuService        $menus Menu service.
	 * @param ChefProfileService $chef_profiles Chef profile service.
	 */
	public function __construct( MenuService $menus, ChefProfileService $chef_profiles ) {
		$this->menus          = $menus;
		$this->chef_profiles  = $chef_profiles;
	}

	/**
	 * Add a menu item to the cart.
	 *
	 * @param int   $customer_user_id Customer user ID.
	 * @param int   $menu_item_id Menu item ID.
	 * @param int   $quantity Quantity.
	 * @param array<string, mixed> $meta Optional item metadata.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function add_item( int $customer_user_id, int $menu_item_id, int $quantity = 1, array $meta = array() ) : array|\WP_Error {
		$cart = $this->get_cart( $customer_user_id );
		$menu_item = $this->menus->get_menu_item( $menu_item_id );

		if ( ! is_array( $menu_item ) ) {
			return new \WP_Error( 'maklaplace_cart_item_missing', __( 'Menu item not found.', 'maklaplace' ) );
		}

		$chef_user_id = (int) ( $menu_item[ MenuKeys::CHEF_USER_ID ] ?? 0 );
		if ( $chef_user_id <= 0 || ! $this->chef_profiles->is_approved( $chef_user_id ) ) {
			return new \WP_Error( 'maklaplace_cart_chef_invalid', __( 'This chef is not available for ordering.', 'maklaplace' ) );
		}

		if ( ! empty( $cart['chef_user_id'] ) && (int) $cart['chef_user_id'] !== $chef_user_id ) {
			return new \WP_Error(
				'maklaplace_cart_mixed_chefs',
				__( 'This item is from a different chef. Replace your current cart to continue.', 'maklaplace' ),
				array(
					'warning'             => true,
					'replacement_required' => true,
					'cart_strategy'       => $this->cart_strategy,
					'current_chef_user_id'=> (int) $cart['chef_user_id'],
					'incoming_chef_user_id' => $chef_user_id,
					'current_item_count'   => count( $cart['items'] ),
				)
			);
		}

		if ( (string) ( $menu_item[ MenuKeys::AVAILABILITY ] ?? 'unavailable' ) !== 'available' ) {
			return new \WP_Error( 'maklaplace_cart_item_unavailable', __( 'This menu item is no longer available.', 'maklaplace' ) );
		}

		$quantity = max( 1, $quantity );
		$item_key = $this->item_key( $menu_item_id );
		$existing = $cart['items'][ $item_key ] ?? null;

		if ( is_array( $existing ) ) {
			$existing['quantity'] = (int) $existing['quantity'] + $quantity;
			$existing['line_total'] = round( (float) $existing['price_snapshot'] * (int) $existing['quantity'], 2 );
			$existing['updated_at'] = current_time( 'mysql' );
			$cart['items'][ $item_key ] = $existing;
		} else {
			$cart['items'][ $item_key ] = $this->snapshot_item( $menu_item_id, $menu_item, $quantity, $meta );
		}

		$cart['customer_user_id'] = $customer_user_id;
		$cart['chef_user_id']     = $chef_user_id;
		$cart['chef_profile_id']  = (int) ( $menu_item[ MenuKeys::CHEF_PROFILE_ID ] ?? 0 );
		$cart['totals']           = $this->calculate_totals( $cart['items'] );
		$cart['updated_at']       = current_time( 'mysql' );

		$this->save_cart( $customer_user_id, $cart );

		return $cart;
	}

	/**
	 * Replace the active cart with a new chef item.
	 *
	 * This is the MVP replacement path for single-chef carts. The internal
	 * strategy field keeps the implementation open for multi-chef carts later.
	 *
	 * @param int                  $customer_user_id Customer user ID.
	 * @param int                  $menu_item_id Menu item ID.
	 * @param int                  $quantity Quantity.
	 * @param array<string, mixed>  $meta Optional item metadata.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function replace_cart_with_item( int $customer_user_id, int $menu_item_id, int $quantity = 1, array $meta = array() ) : array|\WP_Error {
		$this->clear_cart( $customer_user_id );

		return $this->add_item( $customer_user_id, $menu_item_id, $quantity, $meta );
	}

	/**
	 * Update an item quantity.
	 *
	 * @param int $customer_user_id Customer user ID.
	 * @param int $menu_item_id Menu item ID.
	 * @param int $quantity Quantity.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function update_quantity( int $customer_user_id, int $menu_item_id, int $quantity ) : array|\WP_Error {
		$cart = $this->get_cart( $customer_user_id );
		$item_key = $this->item_key( $menu_item_id );

		if ( empty( $cart['items'][ $item_key ] ) ) {
			return new \WP_Error( 'maklaplace_cart_item_missing', __( 'Cart item not found.', 'maklaplace' ) );
		}

		$quantity = absint( $quantity );
		if ( $quantity <= 0 ) {
			unset( $cart['items'][ $item_key ] );
		} else {
			$cart['items'][ $item_key ]['quantity']   = $quantity;
			$cart['items'][ $item_key ]['line_total'] = round( (float) $cart['items'][ $item_key ]['price_snapshot'] * $quantity, 2 );
			$cart['items'][ $item_key ]['updated_at'] = current_time( 'mysql' );
		}

		$cart = $this->normalize_cart_after_change( $customer_user_id, $cart );
		$this->save_cart( $customer_user_id, $cart );

		return $cart;
	}

	/**
	 * Remove an item from the cart.
	 *
	 * @param int $customer_user_id Customer user ID.
	 * @param int $menu_item_id Menu item ID.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function remove_item( int $customer_user_id, int $menu_item_id ) : array|\WP_Error {
		$cart = $this->get_cart( $customer_user_id );
		$item_key = $this->item_key( $menu_item_id );

		if ( empty( $cart['items'][ $item_key ] ) ) {
			return new \WP_Error( 'maklaplace_cart_item_missing', __( 'Cart item not found.', 'maklaplace' ) );
		}

		unset( $cart['items'][ $item_key ] );
		$cart = $this->normalize_cart_after_change( $customer_user_id, $cart );
		$this->save_cart( $customer_user_id, $cart );

		return $cart;
	}

	/**
	 * Clear a cart.
	 *
	 * @param int $customer_user_id Customer user ID.
	 * @return array<string, mixed>
	 */
	public function clear_cart( int $customer_user_id ) : array {
		$cart = $this->empty_cart( $customer_user_id );
		$this->save_cart( $customer_user_id, $cart );

		return $cart;
	}

	/**
	 * Get the active cart for a customer.
	 *
	 * @param int $customer_user_id Customer user ID.
	 * @return array<string, mixed>
	 */
	public function get_cart( int $customer_user_id ) : array {
		$cart = get_user_meta( $customer_user_id, self::META_KEY, true );
		if ( ! is_array( $cart ) ) {
			return $this->empty_cart( $customer_user_id );
		}

		$cart['items'] = isset( $cart['items'] ) && is_array( $cart['items'] ) ? $cart['items'] : array();
		$cart['totals'] = isset( $cart['totals'] ) && is_array( $cart['totals'] ) ? $cart['totals'] : $this->calculate_totals( $cart['items'] );
		$cart['payment_method'] = $this->normalize_payment_method( (string) ( $cart['payment_method'] ?? 'cod' ) );
		$cart['currency'] = Validation::text( $cart['currency'] ?? 'DA' );

		return $cart;
	}

	/**
	 * Set a payment method for the cart.
	 *
	 * @param int    $customer_user_id Customer user ID.
	 * @param string $payment_method Payment method.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function set_payment_method( int $customer_user_id, string $payment_method ) : array|\WP_Error {
		if ( ! in_array( $payment_method, $this->payment_methods, true ) ) {
			return new \WP_Error( 'maklaplace_cart_payment_invalid', __( 'Unsupported payment method.', 'maklaplace' ) );
		}

		$cart = $this->get_cart( $customer_user_id );
		$cart['payment_method'] = $this->normalize_payment_method( $payment_method );
		$cart['updated_at'] = current_time( 'mysql' );
		$this->save_cart( $customer_user_id, $cart );

		return $cart;
	}

	/**
	 * Store checkout customer details in the cart.
	 *
	 * @param int                  $customer_user_id Customer user ID.
	 * @param array<string, mixed>  $details Checkout details.
	 * @return array<string, mixed>
	 */
	public function set_customer_details( int $customer_user_id, array $details ) : array {
		$cart = $this->get_cart( $customer_user_id );
		$cart['customer_name']    = Validation::text( $details['customer_name'] ?? '' );
		$cart['customer_phone']   = Validation::text( $details['customer_phone'] ?? '' );
		$cart['delivery_address'] = Validation::text( $details['delivery_address'] ?? '' );
		$cart['customer_notes']   = wp_kses_post( (string) ( $details['customer_notes'] ?? '' ) );
		$cart['updated_at']       = current_time( 'mysql' );
		$this->save_cart( $customer_user_id, $cart );

		return $cart;
	}

	/**
	 * Get prefilled checkout details.
	 *
	 * @param int $customer_user_id Customer user ID.
	 * @return array<string, string>
	 */
	public function get_checkout_details( int $customer_user_id ) : array {
		$cart = $this->get_cart( $customer_user_id );
		$user = get_userdata( $customer_user_id );

		$name = (string) ( $cart['customer_name'] ?? '' );
		if ( '' === $name && $user instanceof \WP_User ) {
			$name = trim( $user->display_name );
		}

		$phone = (string) ( $cart['customer_phone'] ?? '' );
		if ( '' === $phone ) {
			$phone = (string) UserMeta::get( $customer_user_id, UserMeta::CUSTOMER_PHONE_NUMBER, '' );
		}

		$address = (string) ( $cart['delivery_address'] ?? '' );
		if ( '' === $address ) {
			$address = (string) UserMeta::get( $customer_user_id, UserMeta::CUSTOMER_DEFAULT_ADDRESS, '' );
		}

		return array(
			'customer_name'    => Validation::text( $name ),
			'customer_phone'   => Validation::text( $phone ),
			'delivery_address' => Validation::text( $address ),
			'customer_notes'   => wp_kses_post( (string) ( $cart['customer_notes'] ?? '' ) ),
		);
	}

	/**
	 * Build an order payload from the cart.
	 *
	 * @param int $customer_user_id Customer user ID.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function build_order_payload( int $customer_user_id ) : array|\WP_Error {
		$cart = $this->get_cart( $customer_user_id );

		if ( empty( $cart['items'] ) ) {
			return new \WP_Error( 'maklaplace_cart_empty', __( 'Your cart is empty.', 'maklaplace' ) );
		}

		return array(
			'chef_user_id'      => (int) ( $cart['chef_user_id'] ?? 0 ),
			'chef_profile_id'   => (int) ( $cart['chef_profile_id'] ?? 0 ),
			'items'             => array_values( $cart['items'] ),
			'payment_method'    => $this->normalize_payment_method( (string) ( $cart['payment_method'] ?? 'cod' ) ),
			'subtotal'          => (float) ( $cart['totals']['subtotal'] ?? 0 ),
			'total'             => (float) ( $cart['totals']['total'] ?? 0 ),
			'currency'          => (string) ( $cart['currency'] ?? 'DA' ),
			'cart_snapshot'     => $cart,
		);
	}

	/**
	 * Calculate cart totals from line items.
	 *
	 * @param array<string, array<string, mixed>> $items Cart items.
	 * @return array<string, float|int>
	 */
	public function calculate_totals( array $items ) : array {
		$subtotal = 0.0;
		$quantity = 0;

		foreach ( $items as $item ) {
			$subtotal += (float) ( $item['line_total'] ?? 0 );
			$quantity += (int) ( $item['quantity'] ?? 0 );
		}

		return array(
			'quantity' => $quantity,
			'subtotal' => round( $subtotal, 2 ),
			'total'    => round( $subtotal, 2 ),
		);
	}

	/**
	 * Store a cart record.
	 *
	 * @param int                  $customer_user_id Customer user ID.
	 * @param array<string, mixed> $cart Cart data.
	 * @return void
	 */
	private function save_cart( int $customer_user_id, array $cart ) : void {
		update_user_meta( $customer_user_id, self::META_KEY, $cart );
	}

	/**
	 * Create an empty cart.
	 *
	 * @param int $customer_user_id Customer user ID.
	 * @return array<string, mixed>
	 */
	private function empty_cart( int $customer_user_id ) : array {
		return array(
			'customer_user_id' => $customer_user_id,
			'chef_user_id'     => 0,
			'chef_profile_id'  => 0,
			'cart_strategy'    => $this->cart_strategy,
			'payment_method'   => 'cod',
			'currency'         => 'DA',
			'customer_name'    => '',
			'customer_phone'   => '',
			'delivery_address' => '',
			'customer_notes'   => '',
			'items'            => array(),
			'totals'           => array(
				'quantity' => 0,
				'subtotal' => 0.0,
				'total'    => 0.0,
			),
			'updated_at'       => current_time( 'mysql' ),
		);
	}

	/**
	 * Snapshot a menu item for cart storage.
	 *
	 * @param int                  $menu_item_id Menu item ID.
	 * @param array<string, mixed>  $menu_item Menu item record.
	 * @param int                  $quantity Quantity.
	 * @param array<string, mixed>  $meta Item metadata.
	 * @return array<string, mixed>
	 */
	private function snapshot_item( int $menu_item_id, array $menu_item, int $quantity, array $meta ) : array {
		$price = (float) ( $menu_item[ MenuKeys::PRICE ] ?? 0 );

		return array_merge(
			array(
				'menu_item_id'            => $menu_item_id,
				'chef_user_id'            => (int) ( $menu_item[ MenuKeys::CHEF_USER_ID ] ?? 0 ),
				'chef_profile_id'         => (int) ( $menu_item[ MenuKeys::CHEF_PROFILE_ID ] ?? 0 ),
				'title_snapshot'          => (string) ( $menu_item[ MenuKeys::TITLE ] ?? '' ),
				'description_snapshot'    => (string) ( $menu_item[ MenuKeys::DESCRIPTION ] ?? '' ),
				'price_snapshot'          => $price,
				'quantity'                => $quantity,
				'line_total'              => round( $price * $quantity, 2 ),
				'category_snapshot'       => (string) ( $menu_item[ MenuKeys::CATEGORY ] ?? '' ),
				'cuisine_type_snapshot'   => (string) ( $menu_item[ MenuKeys::CUISINE_TYPE ] ?? '' ),
				'image_snapshot'          => $menu_item[ MenuKeys::IMAGE ] ?? '',
				'preparation_time_snapshot' => (int) ( $menu_item[ MenuKeys::PREPARATION_TIME ] ?? 0 ),
				'availability_snapshot'   => (string) ( $menu_item[ MenuKeys::AVAILABILITY ] ?? 'unavailable' ),
				'meta'                    => $meta,
				'created_at'              => current_time( 'mysql' ),
				'updated_at'              => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Generate a stable item key.
	 *
	 * @param int $menu_item_id Menu item ID.
	 * @return string
	 */
	private function item_key( int $menu_item_id ) : string {
		return 'menu_item_' . $menu_item_id;
	}

	/**
	 * Normalize the cart after item changes.
	 *
	 * @param int                  $customer_user_id Customer user ID.
	 * @param array<string, mixed>  $cart Cart data.
	 * @return array<string, mixed>
	 */
	private function normalize_cart_after_change( int $customer_user_id, array $cart ) : array {
		$cart['items'] = isset( $cart['items'] ) && is_array( $cart['items'] ) ? $cart['items'] : array();
		$cart['totals'] = $this->calculate_totals( $cart['items'] );
		$cart['customer_user_id'] = $customer_user_id;
		$cart['cart_strategy'] = $this->cart_strategy;
		$cart['payment_method'] = $this->normalize_payment_method( (string) ( $cart['payment_method'] ?? 'cod' ) );
		$cart['currency'] = 'DA';
		$cart['updated_at'] = current_time( 'mysql' );

		if ( empty( $cart['items'] ) ) {
			return $this->empty_cart( $customer_user_id );
		}

		$first_item = reset( $cart['items'] );
		if ( is_array( $first_item ) ) {
			$cart['chef_user_id']    = (int) ( $first_item['chef_user_id'] ?? 0 );
			$cart['chef_profile_id'] = (int) ( $first_item['chef_profile_id'] ?? 0 );
		}

		return $cart;
	}

	/**
	 * Determine whether a cart is restricted to a single chef.
	 *
	 * @return bool
	 */
	public function is_single_chef_mode() : bool {
		return 'single_chef' === $this->cart_strategy;
	}

	/**
	 * Normalize a payment method value.
	 *
	 * @param string $payment_method Payment method.
	 * @return string
	 */
	private function normalize_payment_method( string $payment_method ) : string {
		return in_array( $payment_method, $this->payment_methods, true ) ? $payment_method : 'cod';
	}
}
