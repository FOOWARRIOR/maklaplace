<?php
/**
 * Notification module.
 *
 * @package MaklaPlace\Modules
 */

namespace MaklaPlace\Modules;

use MaklaPlace\Core\Module;
use MaklaPlace\Core\NotificationHooks;
use MaklaPlace\Core\NotificationService;

defined( 'ABSPATH' ) || exit;

/**
 * Registers notification infrastructure.
 */
final class NotificationModule extends Module {

	/**
	 * Register module services.
	 *
	 * @return void
	 */
	public function register_services() : void {
		$this->container->singleton( NotificationService::class, NotificationService::class );
		$this->container->singleton( NotificationHooks::class, NotificationHooks::class );
	}

	/**
	 * Register module hooks.
	 *
	 * @return void
	 */
	public function register_hooks() : void {
		$this->container->get( NotificationHooks::class )->register();
	}

	/**
	 * Boot the module.
	 *
	 * @return void
	 */
	public function boot() : void {
	}
}
