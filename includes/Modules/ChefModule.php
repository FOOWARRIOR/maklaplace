<?php
/**
 * Chef module placeholder.
 *
 * @package MaklaPlace\Modules
 */

namespace MaklaPlace\Modules;

use MaklaPlace\Core\ChefProfileService;
use MaklaPlace\Core\Module;
use MaklaPlace\Chef\ChefDashboardController;
use MaklaPlace\Repositories\ChefReviewRepository;

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
		$this->container->singleton( ChefReviewRepository::class, ChefReviewRepository::class );
		$this->container->singleton( ChefDashboardController::class, static function ( \MaklaPlace\Core\Container $container ) {
			return new ChefDashboardController( $container );
		} );
	}

	/**
	 * Register module hooks.
	 *
	 * @return void
	 */
	public function register_hooks() : void {
		$this->container->get( ChefDashboardController::class )->register_hooks();
	}

	/**
	 * Boot the chef module.
	 *
	 * @return void
	 */
	public function boot() : void {
	}
}
