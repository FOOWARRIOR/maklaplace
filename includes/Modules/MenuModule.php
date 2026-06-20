<?php
/**
 * Menu module placeholder.
 *
 * @package MaklaPlace\Modules
 */

namespace MaklaPlace\Modules;

use MaklaPlace\Core\MenuService;
use MaklaPlace\Core\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Registers menu management infrastructure.
 */
final class MenuModule extends Module {

	/**
	 * Register module services.
	 *
	 * @return void
	 */
	public function register_services() : void {
		$this->container->singleton( MenuService::class, MenuService::class );
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
