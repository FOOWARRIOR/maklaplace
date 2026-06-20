<?php
/**
 * Analytics module.
 *
 * @package MaklaPlace\Modules
 */

namespace MaklaPlace\Modules;

use MaklaPlace\Core\AnalyticsHooks;
use MaklaPlace\Core\AnalyticsService;
use MaklaPlace\Core\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Registers analytics infrastructure.
 */
final class AnalyticsModule extends Module {

	/**
	 * Register module services.
	 *
	 * @return void
	 */
	public function register_services() : void {
		$this->container->singleton( AnalyticsService::class, AnalyticsService::class );
		$this->container->singleton( AnalyticsHooks::class, AnalyticsHooks::class );
	}

	/**
	 * Register module hooks.
	 *
	 * @return void
	 */
	public function register_hooks() : void {
		$this->container->get( AnalyticsHooks::class )->register();
	}

	/**
	 * Boot the module.
	 *
	 * @return void
	 */
	public function boot() : void {
	}
}
