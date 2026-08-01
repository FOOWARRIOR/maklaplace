<?php
/**
 * Analytics module.
 *
 * @package MaklaPlace\Modules
 */

namespace MaklaPlace\Modules;

use MaklaPlace\Core\AnalyticsService;
use MaklaPlace\Core\Events\ListenerRegistry;
use MaklaPlace\Core\Events\Listeners\AnalyticsListener;
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
		$this->container->singleton( AnalyticsListener::class, AnalyticsListener::class );
	}

	/**
	 * Register module hooks.
	 *
	 * @return void
	 */
	public function register_hooks() : void {
		$this->container->get( ListenerRegistry::class )->register_listener( $this->container->get( AnalyticsListener::class ) );
	}

	/**
	 * Boot the module.
	 *
	 * @return void
	 */
	public function boot() : void {
	}
}
