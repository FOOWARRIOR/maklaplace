<?php
/**
 * Chef profile service.
 *
 * @package MaklaPlace\Core
 */

namespace MaklaPlace\Core;

use MaklaPlace\Helpers\ChefProfileKeys;
use MaklaPlace\Helpers\Validation;
use WP_User;

defined( 'ABSPATH' ) || exit;

/**
 * Handles chef profile data and approval state.
 */
final class ChefProfileService {

	/**
	 * Profile field weights.
	 */
	private const BASIC_WEIGHT = 40;
	private const LOCATION_WEIGHT = 30;
	private const BUSINESS_WEIGHT = 30;

	/**
	 * Create or initialize a chef profile.
	 *
	 * @param int                  $user_id Chef user ID.
	 * @param array<string, mixed>  $data Profile data.
	 * @return bool
	 */
	public function create_profile( int $user_id, array $data ) : bool {
		return $this->update_profile( $user_id, $data );
	}

	/**
	 * Update chef profile data.
	 *
	 * @param int                  $user_id Chef user ID.
	 * @param array<string, mixed>  $data Profile data.
	 * @return bool
	 */
	public function update_profile( int $user_id, array $data ) : bool {
		$profile = $this->sanitize_profile( $data );

		foreach ( $profile as $key => $value ) {
			update_user_meta( $user_id, $key, $value );
		}

		update_user_meta( $user_id, ChefProfileKeys::PROFILE_COMPLETION, $this->calculate_completion( $user_id ) );

		return true;
	}

	/**
	 * Fetch a chef profile safely.
	 *
	 * @param int $user_id Chef user ID.
	 * @return array<string, mixed>|null
	 */
	public function get_profile( int $user_id ) : ?array {
		$user = get_userdata( $user_id );

		if ( ! $user instanceof WP_User || ! in_array( 'maklaplace_chef', (array) $user->roles, true ) ) {
			return null;
		}

		return array(
			ChefProfileKeys::FULL_NAME           => get_user_meta( $user_id, ChefProfileKeys::FULL_NAME, true ),
			ChefProfileKeys::DISPLAY_NAME        => get_user_meta( $user_id, ChefProfileKeys::DISPLAY_NAME, true ),
			ChefProfileKeys::BIO                 => get_user_meta( $user_id, ChefProfileKeys::BIO, true ),
			ChefProfileKeys::PROFILE_PHOTO       => get_user_meta( $user_id, ChefProfileKeys::PROFILE_PHOTO, true ),
			ChefProfileKeys::COVER_IMAGE         => get_user_meta( $user_id, ChefProfileKeys::COVER_IMAGE, true ),
			ChefProfileKeys::CITY                => get_user_meta( $user_id, ChefProfileKeys::CITY, true ),
			ChefProfileKeys::ADDRESS             => get_user_meta( $user_id, ChefProfileKeys::ADDRESS, true ),
			ChefProfileKeys::DELIVERY_RADIUS     => get_user_meta( $user_id, ChefProfileKeys::DELIVERY_RADIUS, true ),
			ChefProfileKeys::CUISINE_TYPES       => get_user_meta( $user_id, ChefProfileKeys::CUISINE_TYPES, true ),
			ChefProfileKeys::WORKING_HOURS       => get_user_meta( $user_id, ChefProfileKeys::WORKING_HOURS, true ),
			ChefProfileKeys::PHONE_NUMBER        => get_user_meta( $user_id, ChefProfileKeys::PHONE_NUMBER, true ),
			ChefProfileKeys::PREPARATION_TIME    => get_user_meta( $user_id, ChefProfileKeys::PREPARATION_TIME, true ),
			ChefProfileKeys::VERIFICATION_STATUS => $this->get_status( $user_id ),
			ChefProfileKeys::PROFILE_COMPLETION  => $this->calculate_completion( $user_id ),
			ChefProfileKeys::APPROVAL_DATE       => get_user_meta( $user_id, ChefProfileKeys::APPROVAL_DATE, true ),
			ChefProfileKeys::REJECTION_REASON    => get_user_meta( $user_id, ChefProfileKeys::REJECTION_REASON, true ),
		);
	}

	/**
	 * Calculate profile completion percentage.
	 *
	 * @param int $user_id Chef user ID.
	 * @return int
	 */
	public function calculate_completion( int $user_id ) : int {
		$profile = $this->get_raw_profile( $user_id );

		$basic_complete = $this->group_complete(
			array(
				$profile['full_name'],
				$profile['display_name'],
				$profile['bio'],
				$profile['profile_photo'],
				$profile['cover_image'],
			)
		);

		$location_complete = $this->group_complete(
			array(
				$profile['city'],
				$profile['address'],
				$profile['delivery_radius'],
			)
		);

		$business_complete = $this->group_complete(
			array(
				$profile['cuisine_types'],
				$profile['working_hours'],
				$profile['phone_number'],
				$profile['preparation_time'],
			)
		);

		return (int) round(
			( $basic_complete * self::BASIC_WEIGHT ) +
			( $location_complete * self::LOCATION_WEIGHT ) +
			( $business_complete * self::BUSINESS_WEIGHT )
		);
	}

	/**
	 * Check whether the profile is approved.
	 *
	 * @param int $user_id Chef user ID.
	 * @return bool
	 */
	public function is_approved( int $user_id ) : bool {
		return 'approved' === $this->get_status( $user_id );
	}

	/**
	 * Approve a chef profile.
	 *
	 * @param int $user_id Chef user ID.
	 * @return bool
	 */
	public function approve( int $user_id ) : bool {
		update_user_meta( $user_id, ChefProfileKeys::VERIFICATION_STATUS, 'approved' );
		update_user_meta( $user_id, ChefProfileKeys::APPROVAL_DATE, current_time( 'mysql' ) );
		delete_user_meta( $user_id, ChefProfileKeys::REJECTION_REASON );
		do_action( 'maklaplace_chef_approved', $user_id );

		return true;
	}

	/**
	 * Reject a chef profile.
	 *
	 * @param int    $user_id Chef user ID.
	 * @param string $reason Rejection reason.
	 * @return bool
	 */
	public function reject( int $user_id, string $reason ) : bool {
		update_user_meta( $user_id, ChefProfileKeys::VERIFICATION_STATUS, 'rejected' );
		update_user_meta( $user_id, ChefProfileKeys::REJECTION_REASON, Validation::text( $reason ) );
		do_action( 'maklaplace_chef_rejected', $user_id, $reason );

		return true;
	}

	/**
	 * Suspend a chef profile.
	 *
	 * @param int $user_id Chef user ID.
	 * @return bool
	 */
	public function suspend( int $user_id ) : bool {
		update_user_meta( $user_id, ChefProfileKeys::VERIFICATION_STATUS, 'suspended' );
		do_action( 'maklaplace_chef_suspended', $user_id );

		return true;
	}

	/**
	 * Revert chef profile to pending.
	 *
	 * @param int $user_id Chef user ID.
	 * @return bool
	 */
	public function set_pending( int $user_id ) : bool {
		update_user_meta( $user_id, ChefProfileKeys::VERIFICATION_STATUS, 'pending' );
		delete_user_meta( $user_id, ChefProfileKeys::APPROVAL_DATE );
		return true;
	}

	/**
	 * Determine public visibility.
	 *
	 * @param int $user_id Chef user ID.
	 * @return bool
	 */
	public function is_public( int $user_id ) : bool {
		return $this->is_approved( $user_id );
	}

	/**
	 * Get current verification status.
	 *
	 * @param int $user_id Chef user ID.
	 * @return string
	 */
	public function get_status( int $user_id ) : string {
		$status = get_user_meta( $user_id, ChefProfileKeys::VERIFICATION_STATUS, true );

		return is_string( $status ) && '' !== $status ? $status : 'pending';
	}

	/**
	 * Sanitize and normalize profile payload.
	 *
	 * @param array<string, mixed> $data Raw profile data.
	 * @return array<string, mixed>
	 */
	private function sanitize_profile( array $data ) : array {
		return array(
			ChefProfileKeys::FULL_NAME       => Validation::text( $data['full_name'] ?? '' ),
			ChefProfileKeys::DISPLAY_NAME    => Validation::text( $data['display_name'] ?? '' ),
			ChefProfileKeys::BIO             => wp_kses_post( (string) ( $data['bio'] ?? '' ) ),
			ChefProfileKeys::PROFILE_PHOTO   => esc_url_raw( (string) ( $data['profile_photo'] ?? '' ) ),
			ChefProfileKeys::COVER_IMAGE     => esc_url_raw( (string) ( $data['cover_image'] ?? '' ) ),
			ChefProfileKeys::CITY            => Validation::text( $data['city'] ?? '' ),
			ChefProfileKeys::ADDRESS         => Validation::text( $data['address'] ?? '' ),
			ChefProfileKeys::DELIVERY_RADIUS => absint( $data['delivery_radius'] ?? 0 ),
			ChefProfileKeys::CUISINE_TYPES   => array_values( array_map( 'sanitize_text_field', (array) ( $data['cuisine_types'] ?? array() ) ) ),
			ChefProfileKeys::WORKING_HOURS   => is_array( $data['working_hours'] ?? null ) ? array_map( 'sanitize_text_field', (array) $data['working_hours'] ) : Validation::text( $data['working_hours'] ?? '' ),
			ChefProfileKeys::PHONE_NUMBER    => Validation::text( $data['phone_number'] ?? '' ),
			ChefProfileKeys::PREPARATION_TIME=> absint( $data['preparation_time'] ?? 0 ),
		);
	}

	/**
	 * Get raw profile data.
	 *
	 * @param int $user_id Chef user ID.
	 * @return array<string, mixed>
	 */
	private function get_raw_profile( int $user_id ) : array {
		return array(
			'full_name'       => (string) get_user_meta( $user_id, ChefProfileKeys::FULL_NAME, true ),
			'display_name'    => (string) get_user_meta( $user_id, ChefProfileKeys::DISPLAY_NAME, true ),
			'bio'             => (string) get_user_meta( $user_id, ChefProfileKeys::BIO, true ),
			'profile_photo'   => (string) get_user_meta( $user_id, ChefProfileKeys::PROFILE_PHOTO, true ),
			'cover_image'     => (string) get_user_meta( $user_id, ChefProfileKeys::COVER_IMAGE, true ),
			'city'            => (string) get_user_meta( $user_id, ChefProfileKeys::CITY, true ),
			'address'         => (string) get_user_meta( $user_id, ChefProfileKeys::ADDRESS, true ),
			'delivery_radius' => (string) get_user_meta( $user_id, ChefProfileKeys::DELIVERY_RADIUS, true ),
			'cuisine_types'   => (array) get_user_meta( $user_id, ChefProfileKeys::CUISINE_TYPES, true ),
			'working_hours'   => get_user_meta( $user_id, ChefProfileKeys::WORKING_HOURS, true ),
			'phone_number'    => (string) get_user_meta( $user_id, ChefProfileKeys::PHONE_NUMBER, true ),
			'preparation_time'=> (string) get_user_meta( $user_id, ChefProfileKeys::PREPARATION_TIME, true ),
		);
	}

	/**
	 * Determine whether a group is complete.
	 *
	 * @param array<int, mixed> $values Values to check.
	 * @return float
	 */
	private function group_complete( array $values ) : float {
		$filled = 0;
		$total  = count( $values );

		foreach ( $values as $value ) {
			if ( is_array( $value ) ) {
				if ( ! empty( $value ) ) {
					$filled++;
				}
				continue;
			}

			if ( '' !== trim( (string) $value ) ) {
				$filled++;
			}
		}

		return 0 === $total ? 0.0 : $filled / $total;
	}
}
