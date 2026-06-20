<?php
/**
 * Base module implementation.
 *
 * @package MaklaPlace\Core
 */

namespace MaklaPlace\Core;

use MaklaPlace\Interfaces\ModuleInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Shared base class for modules.
 */
abstract class Module implements ModuleInterface {

	/**
	 * Plugin container.
	 *
	 * @var Container
	 */
	protected Container $container;

	/**
	 * Module config.
	 *
	 * @var Config
	 */
	protected Config $config;

	/**
	 * Constructor.
	 *
	 * @param Container $container Service container.
	 * @param Config    $config    Plugin configuration.
	 */
	public function __construct( Container $container, Config $config ) {
		$this->container = $container;
		$this->config    = $config;
	}

	/**
	 * Register module services.
	 *
	 * @return void
	 */
	public function register_services() : void {
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
