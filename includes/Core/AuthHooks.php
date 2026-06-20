<?php
/**
 * Authentication hook registration.
 *
 * @package MaklaPlace\Core
 */

namespace MaklaPlace\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Registers auth-related hooks.
 */
final class AuthHooks {

	/**
	 * Register hooks for login/logout and failed logins.
	 *
	 * @return void
	 */
	public function register() : void {
		add_action( 'wp_login', array( $this, 'handle_login' ), 10, 2 );
		add_action( 'wp_logout', array( $this, 'handle_logout' ) );
		add_action( 'wp_login_failed', array( $this, 'handle_failed_login' ) );
	}

	/**
	 * Handle successful login.
	 *
	 * @param string  $user_login Username.
	 * @param \WP_User $user User object.
	 * @return void
	 */
	public function handle_login( string $user_login, \WP_User $user ) : void {
		do_action( 'maklaplace_user_logged_in', $user, $user_login );
	}

	/**
	 * Handle logout.
	 *
	 * @return void
	 */
	public function handle_logout() : void {
		// Reserved for future cleanup and audit logging.
	}

	/**
	 * Handle failed login attempts.
	 *
	 * @param string $username Username.
	 * @return void
	 */
	public function handle_failed_login( string $username ) : void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[MaklaPlace] Failed login attempt for ' . $username ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}
}
