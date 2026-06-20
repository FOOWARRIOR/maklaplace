<?php
namespace MaklaPlace\Admin;

/**
 * Admin module.
 *
 * @package MaklaPlace\Admin
 */

use MaklaPlace\Core\Module;
use MaklaPlace\Core\Container;
use MaklaPlace\Core\Config;

defined( 'ABSPATH' ) || exit;

/**
 * Admin module for MaklaPlace.
 */
class AdminModule extends Module {

	/**
	 * Register module hooks.
	 *
	 * @return void
	 */
	public function register_hooks() : void {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
	}

	/**
	 * Add admin menu and submenu pages.
	 *
	 * @return void
	 */
	public function add_admin_menu() : void {
		add_menu_page(
			__( 'MaklaPlace', 'maklaplace' ),
			__( 'MaklaPlace', 'maklaplace' ),
			'manage_options',
			'maklaplace',
			array( $this, 'dashboard_page' ),
			'dashicons-cart',
			6
		);

		add_submenu_page( 'maklaplace', __( 'Dashboard', 'maklaplace' ), __( 'Dashboard', 'maklaplace' ), 'manage_options', 'maklaplace', array( $this, 'dashboard_page' ) );
		add_submenu_page( 'maklaplace', __( 'Orders', 'maklaplace' ), __( 'Orders', 'maklaplace' ), 'manage_options', 'maklaplace-orders', array( $this, 'orders_page' ) );
		add_submenu_page( 'maklaplace', __( 'Chefs', 'maklaplace' ), __( 'Chefs', 'maklaplace' ), 'manage_options', 'maklaplace-chefs', array( $this, 'chefs_page' ) );
		add_submenu_page( 'maklaplace', __( 'Customers', 'maklaplace' ), __( 'Customers', 'maklaplace' ), 'manage_options', 'maklaplace-customers', array( $this, 'customers_page' ) );
		add_submenu_page( 'maklaplace', __( 'Menus', 'maklaplace' ), __( 'Menus', 'maklaplace' ), 'manage_options', 'maklaplace-menus', array( $this, 'menus_page' ) );
		add_submenu_page( 'maklaplace', __( 'Wallets', 'maklaplace' ), __( 'Wallets', 'maklaplace' ), 'manage_options', 'maklaplace-wallets', array( $this, 'wallets_page' ) );
		add_submenu_page( 'maklaplace', __( 'Notifications', 'maklaplace' ), __( 'Notifications', 'maklaplace' ), 'manage_options', 'maklaplace-notifications', array( $this, 'notifications_page' ) );
		add_submenu_page( 'maklaplace', __( 'Analytics', 'maklaplace' ), __( 'Analytics', 'maklaplace' ), 'manage_options', 'maklaplace-analytics', array( $this, 'analytics_page' ) );
		add_submenu_page( 'maklaplace', __( 'Settings', 'maklaplace' ), __( 'Settings', 'maklaplace' ), 'manage_options', 'maklaplace-settings', array( $this, 'settings_page' ) );
		add_submenu_page( 'maklaplace', __( 'Tools', 'maklaplace' ), __( 'Tools', 'maklaplace' ), 'manage_options', 'maklaplace-tools', array( $this, 'tools_page' ) );
	}

	/**
	 * Dashboard page callback.
	 *
	 * @return void
	 */
	public function dashboard_page() : void {
		require_once MAKLAPLACE_PATH . 'includes/Admin/Pages/dashboard.php';
	}

	/**
	 * Orders page callback.
	 *
	 * @return void
	 */
	public function orders_page() : void {
		require_once MAKLAPLACE_PATH . 'includes/Admin/Pages/orders.php';
	}

	/**
	 * Chefs page callback.
	 *
	 * @return void
	 */
	public function chefs_page() : void {
		require_once MAKLAPLACE_PATH . 'includes/Admin/Pages/chefs.php';
	}

	/**
	 * Customers page callback.
	 *
	 * @return void
	 */
	public function customers_page() : void {
		require_once MAKLAPLACE_PATH . 'includes/Admin/Pages/customers.php';
	}

	/**
	 * Menus page callback.
	 *
	 * @return void
	 */
	public function menus_page() : void {
		require_once MAKLAPLACE_PATH . 'includes/Admin/Pages/menus.php';
	}

	/**
	 * Wallets page callback.
	 *
	 * @return value
	 */
	public function wallets_page() : void {
		require_once MAKLAPLACE_PATH . 'includes/Admin/Pages/wallets.php';
	}

	/**
	 * Notifications page callback.
	 *
	 * @return value
	 */
	public function notifications_page() : void {
		require_once MAKLAPLACE_PATH . 'includes/Admin/Pages/notifications.php';
	}

	/**
	 * Analytics page callback.
	 *
	 * @return value
	 */
	public function analytics_page() : void {
		require_once MAKLAPLACE_PATH . 'includes/Admin/Pages/analytics.php';
	}

	/**
	 * Settings page callback.
	 *
	 * @return value
	 */
	public function settings_page() : void {
		require_once MAKLAPLACE_PATH . 'includes/Admin/Pages/settings.php';
	}

	/**
	 * Tools page callback.
	 *
	 * @return value
	 */
	public function tools_page() : void {
		require_once MAKLAPLACE_PATH . 'includes/Admin/Pages/tools.php';
	}
}