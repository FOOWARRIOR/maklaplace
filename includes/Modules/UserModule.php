<?php
/**
 * User module placeholder.
 *
 * @package MaklaPlace\Modules
 */

namespace MaklaPlace\Modules;

use MaklaPlace\Core\CapabilityManager;
use MaklaPlace\Core\CapabilityService;
use MaklaPlace\Core\MetadataService;
use MaklaPlace\Core\Module;
use MaklaPlace\Core\RoleService;
use MaklaPlace\Core\UserManager;
use MaklaPlace\Core\UserService;
use MaklaPlace\Core\ChefProfileService;

defined( 'ABSPATH' ) || exit;

/**
 * Registers user infrastructure services and hooks.
 */
final class UserModule extends Module {

	/**
	 * Register module services.
	 *
	 * @return void
	 */
	public function register_services() : void {
		$this->container->singleton( CapabilityManager::class, CapabilityManager::class );
		$this->container->singleton( UserService::class, UserService::class );
		$this->container->singleton( RoleService::class, RoleService::class );
		$this->container->singleton( CapabilityService::class, CapabilityService::class );
		$this->container->singleton( MetadataService::class, MetadataService::class );
		$this->container->singleton( ChefProfileService::class, ChefProfileService::class );
		$this->container->singleton(
			UserManager::class,
			static function ( $container ) {
				return new UserManager( $container->get( CapabilityManager::class ) );
			}
		);
	}

	/**
	 * Register module hooks.
	 *
	 * @return void
	 */
	public function register_hooks() : void {
		add_action( 'init', array( $this, 'boot' ) );
	}

	/**
	 * Boot the user module.
	 *
	 * @return void
	 */
	public function boot() : void {
		$this->container->get( UserManager::class )->register();
	}
}
