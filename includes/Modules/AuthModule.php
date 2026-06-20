<?php
/**
 * Auth module placeholder.
 *
 * @package MaklaPlace\Modules
 */

namespace MaklaPlace\Modules;

use MaklaPlace\Core\AuthHooks;
use MaklaPlace\Core\RegistrationService;
use MaklaPlace\Core\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Registers authentication and registration infrastructure.
 */
final class AuthModule extends Module {

	/**
	 * Register module services.
	 *
	 * @return void
	 */
	public function register_services() : void {
		$this->container->singleton( RegistrationService::class, RegistrationService::class );
		$this->container->singleton( AuthHooks::class, AuthHooks::class );
	}

	/**
	 * Register module hooks.
	 *
	 * @return void
	 */
	public function register_hooks() : void {
		$this->container->get( AuthHooks::class )->register();
	}

	/**
	 * Boot the auth module.
	 *
	 * @return void
	 */
	public function boot() : void {
	}
}
