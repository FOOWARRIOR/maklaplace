<?php
/**
 * Public marketplace module.
 *
 * @package MaklaPlace\Modules
 */

namespace MaklaPlace\Modules;

use MaklaPlace\Core\Module;
use MaklaPlace\PublicArea\MarketplaceController;

defined( 'ABSPATH' ) || exit;

/**
 * Registers public-facing marketplace infrastructure.
 */
final class PublicModule extends Module {

	public function register_services() : void {
		$this->container->singleton( MarketplaceController::class, static function ( \MaklaPlace\Core\Container $container ) {
			return new MarketplaceController( $container );
		} );
	}

	public function register_hooks() : void {
		$this->container->get( MarketplaceController::class )->register_hooks();
	}

	public function boot() : void {
	}
}
