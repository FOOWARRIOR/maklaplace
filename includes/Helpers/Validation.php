<?php
/**
 * Validation helpers.
 *
 * @package MaklaPlace\Helpers
 */

namespace MaklaPlace\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Reusable input validation and sanitization helpers.
 */
final class Validation {

	/**
	 * Validate an email address.
	 *
	 * @param string $email Email address.
	 * @return bool
	 */
	public static function email( string $email ) : bool {
		return (bool) filter_var( $email, FILTER_VALIDATE_EMAIL );
	}

	/**
	 * Validate a password using a basic MVP policy.
	 *
	 * @param string $password Password value.
	 * @return bool
	 */
	public static function password( string $password ) : bool {
		return strlen( $password ) >= 8
			&& preg_match( '/[A-Z]/', $password )
			&& preg_match( '/[a-z]/', $password )
			&& preg_match( '/[0-9]/', $password );
	}

	/**
	 * Check required fields.
	 *
	 * @param array<string, mixed> $fields Fields to inspect.
	 * @return array<int, string>
	 */
	public static function required_fields( array $fields ) : array {
		$missing = array();

		foreach ( $fields as $field => $value ) {
			if ( '' === trim( (string) $value ) ) {
				$missing[] = $field;
			}
		}

		return $missing;
	}

	/**
	 * Sanitize a text field.
	 *
	 * @param mixed $value Value to sanitize.
	 * @return string
	 */
	public static function text( mixed $value ) : string {
		return sanitize_text_field( (string) $value );
	}

	/**
	 * Sanitize an email field.
	 *
	 * @param mixed $value Value to sanitize.
	 * @return string
	 */
	public static function email_field( mixed $value ) : string {
		return sanitize_email( (string) $value );
	}

	/**
	 * Sanitize a password payload.
	 *
	 * @param mixed $value Value to sanitize.
	 * @return string
	 */
	public static function password_field( mixed $value ) : string {
		return is_string( $value ) ? $value : '';
	}
}
