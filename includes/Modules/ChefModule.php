<?php
/**
 * Chef module placeholder.
 *
 * @package MaklaPlace\Modules
 */

namespace MaklaPlace\Modules;

use MaklaPlace\Core\ChefProfileService;
use MaklaPlace\Core\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Registers chef profile infrastructure.
 */
final class ChefModule extends Module {

	/**
	 * Register module services.
	 *
	 * @return void
	 */
	public function register_services() : void {
		$this->container->singleton( ChefProfileService::class, ChefProfileService::class );
	}

	/**
	 * Register module hooks.
	 *
	 * @return void
	 */
	public function register_hooks() : void {
	}

	/**
	 * Boot the chef module.
	 *
	 * @return void
	 */
	public function boot() : void {
	}
}
