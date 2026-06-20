<?php
/**
 * Service contract.
 *
 * @package MaklaPlace\Interfaces
 */

namespace MaklaPlace\Interfaces;

defined( 'ABSPATH' ) || exit;

/**
 * Basic service lifecycle contract.
 */
interface ServiceInterface {

	/**
	 * Boot the service.
	 *
	 * @return void
	 */
	public function boot() : void;
}
