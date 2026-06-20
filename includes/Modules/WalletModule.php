<?php
/**
 * Wallet module placeholder.
 *
 * @package MaklaPlace\Modules
 */

namespace MaklaPlace\Modules;

use MaklaPlace\Core\Module;
use MaklaPlace\Core\WalletService;

defined( 'ABSPATH' ) || exit;

/**
 * Registers wallet accounting infrastructure.
 */
final class WalletModule extends Module {

	/**
	 * Register module services.
	 *
	 * @return void
	 */
	public function register_services() : void {
		$this->container->singleton( WalletService::class, WalletService::class );
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
