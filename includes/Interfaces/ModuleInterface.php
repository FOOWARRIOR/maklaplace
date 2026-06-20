<?php
/**
 * Module contract.
 *
 * @package MaklaPlace\Interfaces
 */

namespace MaklaPlace\Interfaces;

defined( 'ABSPATH' ) || exit;

/**
 * Defines the lifecycle for a plugin module.
 */
interface ModuleInterface {

	/**
	 * Register module services.
	 *
	 * @return void
	 */
	public function register_services() : void;

	/**
	 * Register module hooks.
	 *
	 * @return void
	 */
	public function register_hooks() : void;

	/**
	 * Boot the module.
	 *
	 * @return void
	 */
	public function boot() : void;
}
