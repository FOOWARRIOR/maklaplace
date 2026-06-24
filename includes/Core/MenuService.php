<?php
/**
 * Menu service.
 *
 * @package MaklaPlace\Core
 */

namespace MaklaPlace\Core;

use MaklaPlace\Helpers\ChefProfileKeys;
use MaklaPlace\Helpers\MenuKeys;
use MaklaPlace\Helpers\Validation;
use WP_User;

defined( 'ABSPATH' ) || exit;

/**
 * Handles chef menu items.
 */
final class MenuService {

	/**
	 * Approved menu categories.
	 *
	 * @var array<int, string>
	 */
	private array $allowed_categories = array( 'starter', 'main', 'dessert', 'drink', 'side', 'special' );

	/**
	 * Chef profile service.
	 *
	 * @var ChefProfileService
	 */
	private ChefProfileService $chef_profiles;

	/**
	 * Constructor.
	 *
	 * @param ChefProfileService $chef_profiles Chef profile service.
	 */
	public function __construct( ChefProfileService $chef_profiles ) {
		$this->chef_profiles = $chef_profiles;
	}

	/**
	 * Create a menu item.
	 *
	 * @param int                  $chef_user_id Chef user ID.
	 * @param int                  $chef_profile_id Chef profile ID.
	 * @param array<string, mixed>  $data Menu data.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function create_menu_item( int $chef_user_id, int $chef_profile_id, array $data ) : array|\WP_Error {
		if ( ! $this->can_manage_menu( $chef_user_id ) ) {
			return new \WP_Error( 'maklaplace_menu_forbidden', __( 'Only approved chefs can manage menu items.', 'maklaplace' ) );
		}

		$item = $this->sanitize_item( $data );
		$errors = $this->validate_item( $item );

		if ( ! empty( $errors ) ) {
			return new \WP_Error( 'maklaplace_menu_invalid', __( 'Invalid menu item data.', 'maklaplace' ), $errors );
		}

		$record = $this->build_record( $chef_user_id, $chef_profile_id, $item );
		$menu_items = $this->get_menu_store();
		$menu_id = $this->next_id( $menu_items );
		$record['id'] = $menu_id;
		$menu_items[ $menu_id ] = $record;
		$this->save_menu_store( $menu_items );
		do_action( 'maklaplace_menu_item_created', $record );

		return $record;
	}

	/**
	 * Update a menu item.
	 *
	 * @param int                  $chef_user_id Chef user ID.
	 * @param int                  $menu_id Menu item ID.
	 * @param array<string, mixed>  $data Menu data.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function update_menu_item( int $chef_user_id, int $menu_id, array $data ) : array|\WP_Error {
		$menu_items = $this->get_menu_store();
		$record = $menu_items[ $menu_id ] ?? null;

		if ( ! is_array( $record ) ) {
			return new \WP_Error( 'maklaplace_menu_not_found', __( 'Menu item not found.', 'maklaplace' ) );
		}

		$is_admin_actor = user_can( get_user_by( 'id', $chef_user_id ), 'manage_options' );

		if ( ! $this->can_manage_menu( $chef_user_id ) && ! $is_admin_actor ) {
			return new \WP_Error( 'maklaplace_menu_forbidden', __( 'You cannot update this menu item.', 'maklaplace' ) );
		}

		if ( ! $is_admin_actor && (int) $record[ MenuKeys::CHEF_USER_ID ] !== $chef_user_id ) {
			return new \WP_Error( 'maklaplace_menu_not_owner', __( 'Only the owning chef can update this menu item.', 'maklaplace' ) );
		}

		$item = array_merge( $record, $this->sanitize_item( $data ) );
		$errors = $this->validate_item( $item );

		if ( ! empty( $errors ) ) {
			return new \WP_Error( 'maklaplace_menu_invalid', __( 'Invalid menu item data.', 'maklaplace' ), $errors );
		}

		$item['updated_at'] = current_time( 'mysql' );
		$menu_items[ $menu_id ] = $item;
		$this->save_menu_store( $menu_items );
		do_action( 'maklaplace_menu_item_updated', $item );

		return $item;
	}

	/**
	 * Delete a menu item.
	 *
	 * @param int $chef_user_id Chef user ID.
	 * @param int $menu_id Menu item ID.
	 * @return bool|\WP_Error
	 */
	public function delete_menu_item( int $chef_user_id, int $menu_id ) : bool|\WP_Error {
		$menu_items = $this->get_menu_store();
		$record = $menu_items[ $menu_id ] ?? null;

		if ( ! is_array( $record ) ) {
			return new \WP_Error( 'maklaplace_menu_not_found', __( 'Menu item not found.', 'maklaplace' ) );
		}

		$is_admin_actor = user_can( get_user_by( 'id', $chef_user_id ), 'manage_options' );

		if ( ! $is_admin_actor && (int) $record[ MenuKeys::CHEF_USER_ID ] !== $chef_user_id ) {
			return new \WP_Error( 'maklaplace_menu_not_owner', __( 'Only the owning chef can delete this menu item.', 'maklaplace' ) );
		}

		unset( $menu_items[ $menu_id ] );
		$this->save_menu_store( $menu_items );
		do_action( 'maklaplace_menu_item_deleted', $record );

		return true;
	}

	/**
	 * Fetch menu items by chef.
	 *
	 * @param int $chef_user_id Chef user ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_menu_items_by_chef( int $chef_user_id ) : array {
		return array_values(
			array_filter(
				$this->get_menu_store(),
				static fn( array $item ) : bool => (int) $item[ MenuKeys::CHEF_USER_ID ] === $chef_user_id
			)
		);
	}

	/**
	 * Fetch a single menu item safely.
	 *
	 * @param int $menu_id Menu item ID.
	 * @return array<string, mixed>|null
	 */
	public function get_menu_item( int $menu_id ) : ?array {
		$menu_items = $this->get_menu_store();
		return isset( $menu_items[ $menu_id ] ) && is_array( $menu_items[ $menu_id ] ) ? $menu_items[ $menu_id ] : null;
	}

	/**
	 * Get all stored menu items.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_menu_items() : array {
		return array_values( $this->get_menu_store() );
	}

	/**
	 * Toggle menu item availability.
	 *
	 * @param int  $menu_id Menu item ID.
	 * @param bool $enabled Whether the item is enabled.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function set_availability( int $menu_id, bool $enabled ) : array|\WP_Error {
		$menu_items = $this->get_menu_store();
		$record     = $menu_items[ $menu_id ] ?? null;

		if ( ! is_array( $record ) ) {
			return new \WP_Error( 'maklaplace_menu_not_found', __( 'Menu item not found.', 'maklaplace' ) );
		}

		$record[ MenuKeys::AVAILABILITY ] = $enabled ? 'available' : 'unavailable';
		$record[ MenuKeys::UPDATED_AT ]   = current_time( 'mysql' );
		$menu_items[ $menu_id ]           = $record;
		$this->save_menu_store( $menu_items );

		do_action( 'maklaplace_menu_item_updated', $record );

		return $record;
	}

	/**
	 * Toggle menu item featured state.
	 *
	 * @param int  $menu_id Menu item ID.
	 * @param bool $featured Whether the item is featured.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function set_featured( int $menu_id, bool $featured ) : array|\WP_Error {
		$menu_items = $this->get_menu_store();
		$record     = $menu_items[ $menu_id ] ?? null;

		if ( ! is_array( $record ) ) {
			return new \WP_Error( 'maklaplace_menu_not_found', __( 'Menu item not found.', 'maklaplace' ) );
		}

		$record[ MenuKeys::FEATURED ] = $featured ? 1 : 0;
		$record[ MenuKeys::UPDATED_AT ] = current_time( 'mysql' );
		$menu_items[ $menu_id ] = $record;
		$this->save_menu_store( $menu_items );

		do_action( 'maklaplace_menu_item_updated', $record );

		return $record;
	}

	/**
	 * Filter menu items by availability.
	 *
	 * @param string $availability Availability status.
	 * @return array<int, array<string, mixed>>
	 */
	public function filter_by_availability( string $availability ) : array {
		$availability = in_array( $availability, array( 'available', 'unavailable' ), true ) ? $availability : 'available';

		return array_values(
			array_filter(
				$this->get_menu_store(),
				static fn( array $item ) : bool => ( $item[ MenuKeys::AVAILABILITY ] ?? 'unavailable' ) === $availability
			)
		);
	}

	/**
	 * Determine if a chef can manage menu items.
	 *
	 * @param int $chef_user_id Chef user ID.
	 * @return bool
	 */
	public function can_manage_menu( int $chef_user_id ) : bool {
		return $this->chef_profiles->is_approved( $chef_user_id ) || $this->is_admin_actor( $chef_user_id );
	}

	/**
	 * Check whether the actor is an administrator.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	private function is_admin_actor( int $user_id ) : bool {
		$user = get_user_by( 'id', $user_id );

		return $user instanceof WP_User && user_can( $user, 'manage_options' );
	}

	/**
	 * Sanitize item data.
	 *
	 * @param array<string, mixed> $data Raw data.
	 * @return array<string, mixed>
	 */
	private function sanitize_item( array $data ) : array {
		$cuisine = $data['cuisine_type'] ?? '';
		$image   = $data['image'] ?? '';
		$image   = is_numeric( $image ) ? (int) $image : esc_url_raw( (string) $image );

		return array(
			MenuKeys::TITLE            => Validation::text( $data['title'] ?? '' ),
			MenuKeys::DESCRIPTION      => wp_kses_post( (string) ( $data['description'] ?? '' ) ),
			MenuKeys::PRICE            => is_numeric( $data['price'] ?? null ) ? (float) $data['price'] : 0.0,
			MenuKeys::PREPARATION_TIME => absint( $data['preparation_time'] ?? 0 ),
			MenuKeys::CATEGORY         => Validation::text( $data['category'] ?? '' ),
			MenuKeys::CUISINE_TYPE     => Validation::text( is_array( $cuisine ) ? (string) reset( $cuisine ) : (string) $cuisine ),
			MenuKeys::FEATURED         => ! empty( $data['featured'] ) ? 1 : 0,
			MenuKeys::IMAGE            => $image,
			MenuKeys::AVAILABILITY     => in_array( (string) ( $data['availability'] ?? 'unavailable' ), array( 'available', 'unavailable' ), true ) ? (string) $data['availability'] : 'unavailable',
		);
	}

	/**
	 * Validate a menu item payload.
	 *
	 * @param array<string, mixed> $item Menu data.
	 * @return array<int, string>
	 */
	private function validate_item( array $item ) : array {
		$errors = array();

		if ( '' === trim( (string) $item[ MenuKeys::TITLE ] ) ) {
			$errors[] = 'missing_title';
		}

		if ( ! is_numeric( $item[ MenuKeys::PRICE ] ) || (float) $item[ MenuKeys::PRICE ] <= 0 ) {
			$errors[] = 'invalid_price';
		}

		if ( ! in_array( $item[ MenuKeys::CATEGORY ], $this->allowed_categories, true ) ) {
			$errors[] = 'invalid_category';
		}

		$image = $item[ MenuKeys::IMAGE ];

		if ( is_int( $image ) && $image > 0 ) {
			$mime = (string) get_post_mime_type( $image );
			if ( 0 !== strpos( $mime, 'image/' ) ) {
				$errors[] = 'invalid_image';
			}
		} elseif ( is_string( $image ) && '' !== trim( $image ) ) {
			$attachment_id = attachment_url_to_postid( $image );
			if ( $attachment_id <= 0 ) {
				$errors[] = 'invalid_image';
			}
		} else {
			$errors[] = 'invalid_image';
		}

		return $errors;
	}

	/**
	 * Build a record for storage.
	 *
	 * @param int                  $chef_user_id Chef user ID.
	 * @param int                  $chef_profile_id Chef profile ID.
	 * @param array<string, mixed>  $item Sanitized item.
	 * @return array<string, mixed>
	 */
	private function build_record( int $chef_user_id, int $chef_profile_id, array $item ) : array {
		return array_merge(
			array(
				'id'                => 0,
				MenuKeys::CHEF_USER_ID   => $chef_user_id,
				MenuKeys::CHEF_PROFILE_ID => $chef_profile_id,
				MenuKeys::CREATED_AT     => current_time( 'mysql' ),
				MenuKeys::UPDATED_AT     => current_time( 'mysql' ),
			),
			array(
				MenuKeys::TITLE           => $item[ MenuKeys::TITLE ],
				MenuKeys::DESCRIPTION     => $item[ MenuKeys::DESCRIPTION ],
				MenuKeys::PRICE           => $item[ MenuKeys::PRICE ],
				MenuKeys::PREPARATION_TIME => $item[ MenuKeys::PREPARATION_TIME ],
				MenuKeys::CATEGORY        => $item[ MenuKeys::CATEGORY ],
				MenuKeys::CUISINE_TYPE    => $item[ MenuKeys::CUISINE_TYPE ],
				MenuKeys::FEATURED        => ! empty( $item[ MenuKeys::FEATURED ] ) ? 1 : 0,
				MenuKeys::IMAGE           => $item[ MenuKeys::IMAGE ],
				MenuKeys::AVAILABILITY    => $item[ MenuKeys::AVAILABILITY ],
			)
		);
	}

	/**
	 * Get stored menu items.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function get_menu_store() : array {
		$items = get_option( 'maklaplace_menu_items', array() );
		return is_array( $items ) ? $items : array();
	}

	/**
	 * Save menu items.
	 *
	 * @param array<int, array<string, mixed>> $items Items.
	 * @return void
	 */
	private function save_menu_store( array $items ) : void {
		update_option( 'maklaplace_menu_items', $items, false );
	}

	/**
	 * Get the next numeric ID.
	 *
	 * @param array<int, array<string, mixed>> $items Stored items.
	 * @return int
	 */
	private function next_id( array $items ) : int {
		return empty( $items ) ? 1 : ( max( array_keys( $items ) ) + 1 );
	}
}
