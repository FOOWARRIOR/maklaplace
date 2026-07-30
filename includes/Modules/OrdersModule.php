<?php
/**
 * Orders module placeholder.
 *
 * @package MaklaPlace\Modules
 */

namespace MaklaPlace\Modules;

use MaklaPlace\Core\CartService;
use MaklaPlace\Core\OrderService;
use MaklaPlace\Core\WalletService;
use MaklaPlace\Core\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Registers order management infrastructure.
 */
final class OrdersModule extends Module {

	/**
	 * Register module services.
	 *
	 * @return void
	 */
	public function register_services() : void {
		$this->container->singleton( WalletService::class, WalletService::class );
		$this->container->singleton( CartService::class, static function ( \MaklaPlace\Core\Container $container ) {
			return new CartService(
				$container->get( \MaklaPlace\Core\MenuService::class ),
				$container->get( \MaklaPlace\Core\ChefProfileService::class )
			);
		} );
		$this->container->singleton( OrderService::class, OrderService::class );
	}

	/**
	 * Register module hooks.
	 *
	 * @return void
	 */
	public function register_hooks() : void {
	}

	/**
	 * Boot the module.
	 *
	 * @return void
	 */
	public function boot() : void {
	}
}
