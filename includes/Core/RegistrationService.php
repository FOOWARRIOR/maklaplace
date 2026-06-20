<?php
/**
 * Registration service.
 *
 * @package MaklaPlace\Core
 */

namespace MaklaPlace\Core;

use MaklaPlace\Helpers\Validation;
use MaklaPlace\Helpers\UserMeta;
use WP_Error;
use WP_User;

defined( 'ABSPATH' ) || exit;

/**
 * Handles customer and chef registration.
 */
final class RegistrationService {

	/**
	 * Register a customer account.
	 *
	 * @param array<string, mixed> $data Registration payload.
	 * @return WP_User|WP_Error
	 */
	public function register_customer( array $data ) : WP_User|WP_Error {
		$payload = $this->sanitize_payload( $data );
		$errors  = $this->validate_common( $payload, true );

		if ( ! empty( $errors ) ) {
			return new WP_Error( 'maklaplace_invalid_customer', __( 'Invalid customer registration data.', 'maklaplace' ), $errors );
		}

		$payload['role'] = 'maklaplace_customer';

		return $this->create_user( $payload );
	}

	/**
	 * Register a chef account.
	 *
	 * @param array<string, mixed> $data Registration payload.
	 * @return WP_User|WP_Error
	 */
	public function register_chef( array $data ) : WP_User|WP_Error {
		$payload = $this->sanitize_payload( $data );
		$errors  = $this->validate_common( $payload, true );

		if ( ! empty( $errors ) ) {
			return new WP_Error( 'maklaplace_invalid_chef', __( 'Invalid chef registration data.', 'maklaplace' ), $errors );
		}

		$payload['role'] = 'maklaplace_chef';

		$user = $this->create_user( $payload );

		if ( $user instanceof WP_User ) {
			UserMeta::set( $user->ID, UserMeta::CHEF_VERIFICATION_STATUS, 'pending' );
			UserMeta::set( $user->ID, UserMeta::CHEF_VERIFICATION_DATE, '' );
			UserMeta::set( $user->ID, UserMeta::CHEF_APPROVAL_DATE, '' );
			UserMeta::set( $user->ID, 'maklaplace_chef_registration_date', current_time( 'mysql' ) );
		}

		return $user;
	}

	/**
	 * Check whether an email address already exists.
	 *
	 * @param string $email Email address.
	 * @return bool
	 */
	public function email_exists( string $email ) : bool {
		return email_exists( $email );
	}

	/**
	 * Placeholder for future phone duplicate checks.
	 *
	 * @param string|null $phone Phone number.
	 * @return bool
	 */
	public function phone_exists( ?string $phone = null ) : bool {
		return false;
	}

	/**
	 * Validate the common payload.
	 *
	 * @param array<string, mixed> $payload Payload.
	 * @param bool                 $require_password Whether password is required.
	 * @return array<int, string>
	 */
	private function validate_common( array $payload, bool $require_password ) : array {
		$errors = array();
		$missing = Validation::required_fields(
			array(
				'user_login' => $payload['user_login'] ?? '',
				'user_email' => $payload['user_email'] ?? '',
			)
		);

		if ( ! empty( $missing ) ) {
			$errors[] = 'missing_required_fields';
		}

		if ( isset( $payload['user_email'] ) && ! Validation::email( (string) $payload['user_email'] ) ) {
			$errors[] = 'invalid_email';
		}

		if ( $require_password ) {
			$password = isset( $payload['user_pass'] ) ? (string) $payload['user_pass'] : '';
			if ( ! Validation::password( $password ) ) {
				$errors[] = 'weak_password';
			}
		}

		if ( ! empty( $payload['user_email'] ) && $this->email_exists( (string) $payload['user_email'] ) ) {
			$errors[] = 'duplicate_email';
		}

		if ( ! empty( $payload['phone_number'] ) && $this->phone_exists( (string) $payload['phone_number'] ) ) {
			$errors[] = 'duplicate_phone';
		}

		return array_unique( $errors );
	}

	/**
	 * Sanitize registration payload.
	 *
	 * @param array<string, mixed> $data Raw data.
	 * @return array<string, mixed>
	 */
	private function sanitize_payload( array $data ) : array {
		return array(
			'user_login'   => Validation::text( $data['user_login'] ?? '' ),
			'user_email'   => Validation::email_field( $data['user_email'] ?? '' ),
			'user_pass'    => Validation::password_field( $data['user_pass'] ?? '' ),
			'display_name' => Validation::text( $data['display_name'] ?? '' ),
			'phone_number' => Validation::text( $data['phone_number'] ?? '' ),
		);
	}

	/**
	 * Create a user through WordPress APIs.
	 *
	 * @param array<string, mixed> $payload Sanitized payload.
	 * @return WP_User|WP_Error
	 */
	private function create_user( array $payload ) : WP_User|WP_Error {
		$user_id = wp_insert_user(
			array(
				'user_login'   => $payload['user_login'],
				'user_email'   => $payload['user_email'],
				'user_pass'    => $payload['user_pass'],
				'display_name' => $payload['display_name'],
				'role'         => $payload['role'],
			)
		);

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		if ( ! empty( $payload['phone_number'] ) ) {
			UserMeta::set( (int) $user_id, 'maklaplace_phone_number', $payload['phone_number'] );
		}

		$user = get_user_by( 'id', (int) $user_id );

		if ( ! $user instanceof WP_User ) {
			return new WP_Error( 'maklaplace_user_not_found', __( 'Unable to load the created user.', 'maklaplace' ) );
		}

		do_action( 'maklaplace_user_registered', $user, $payload );

		if ( 'maklaplace_chef' === $payload['role'] ) {
			do_action( 'maklaplace_chef_registered', $user, $payload );
		}

		return $user;
	}
}
