<?php
namespace MaklaPlace\Admin;

/**
 * Admin module.
 *
 * @package MaklaPlace\Admin
 */

use MaklaPlace\Core\Module;
use MaklaPlace\Core\Container;
use MaklaPlace\Admin\AdminController;

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
		$this->container->singleton( AdminController::class, static function ( Container $container ) {
			return new AdminController( $container );
		} );
		$this->container->get( AdminController::class )->register_hooks();
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
			'read',
			'maklaplace',
			array( $this, 'dashboard_page' ),
			'dashicons-cart',
			6
		);

		add_submenu_page( 'maklaplace', __( 'Dashboard', 'maklaplace' ), __( 'Dashboard', 'maklaplace' ), 'read', 'maklaplace', array( $this, 'dashboard_page' ) );
		add_submenu_page( 'maklaplace', __( 'Orders', 'maklaplace' ), __( 'Orders', 'maklaplace' ), 'read', 'maklaplace-orders', array( $this, 'orders_page' ) );
		add_submenu_page( 'maklaplace', __( 'Chefs', 'maklaplace' ), __( 'Chefs', 'maklaplace' ), 'read', 'maklaplace-chefs', array( $this, 'chefs_page' ) );
		add_submenu_page( 'maklaplace', __( 'Customers', 'maklaplace' ), __( 'Customers', 'maklaplace' ), 'read', 'maklaplace-customers', array( $this, 'customers_page' ) );
		add_submenu_page( 'maklaplace', __( 'Menus', 'maklaplace' ), __( 'Menus', 'maklaplace' ), 'read', 'maklaplace-menus', array( $this, 'menus_page' ) );
		add_submenu_page( 'maklaplace', __( 'Wallets', 'maklaplace' ), __( 'Wallets', 'maklaplace' ), 'read', 'maklaplace-wallets', array( $this, 'wallets_page' ) );
		add_submenu_page( 'maklaplace', __( 'Notifications', 'maklaplace' ), __( 'Notifications', 'maklaplace' ), 'read', 'maklaplace-notifications', array( $this, 'notifications_page' ) );
		add_submenu_page( 'maklaplace', __( 'Analytics', 'maklaplace' ), __( 'Analytics', 'maklaplace' ), 'read', 'maklaplace-analytics', array( $this, 'analytics_page' ) );
		add_submenu_page( 'maklaplace', __( 'Settings', 'maklaplace' ), __( 'Settings', 'maklaplace' ), 'read', 'maklaplace-settings', array( $this, 'settings_page' ) );
		add_submenu_page( 'maklaplace', __( 'Tools', 'maklaplace' ), __( 'Tools', 'maklaplace' ), 'read', 'maklaplace-tools', array( $this, 'tools_page' ) );
	}

	/**
	 * Dashboard page callback.
	 *
	 * @return void
	 */
	public function dashboard_page() : void {
		$this->container->get( AdminController::class )->dashboard_page();
}

	/**
	 * Orders page callback.
	 *
	 * @return void
	 */
	public function orders_page() : void {
		$this->container->get( AdminController::class )->orders_page();
	}

	/**
	 * Chefs page callback.
	 *
	 * @return void
	 */
	public function chefs_page() : void {
		$this->container->get( AdminController::class )->chefs_page();
	}

	/**
	 * Customers page callback.
	 *
	 * @return void
	 */
	public function customers_page() : void {
		$this->container->get( AdminController::class )->customers_page();
	}

	/**
	 * Menus page callback.
	 *
	 * @return void
	 */
	public function menus_page() : void {
		$this->container->get( AdminController::class )->menus_page();
	}

	/**
	 * Wallets page callback.
	 *
	 * @return value
	 */
	public function wallets_page() : void {
		$this->container->get( AdminController::class )->wallets_page();
	}

	/**
	 * Notifications page callback.
	 *
	 * @return value
	 */
	public function notifications_page() : void {
		$this->container->get( AdminController::class )->notifications_page();
	}

	/**
	 * Analytics page callback.
	 *
	 * @return value
	 */
	public function analytics_page() : void {
		$this->container->get( AdminController::class )->analytics_page();
	}

	/**
	 * Settings page callback.
	 *
	 * @return value
	 */
	public function settings_page() : void {
		$this->container->get( AdminController::class )->settings_page();
	}

	/**
	 * Tools page callback.
	 *
	 * @return value
	 */
	public function tools_page() : void {
		$this->container->get( AdminController::class )->tools_page();
	}
}

