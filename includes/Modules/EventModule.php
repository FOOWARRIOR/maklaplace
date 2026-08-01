<?php
/**
 * Event module.
 *
 * @package MaklaPlace\Modules
 */

namespace MaklaPlace\Modules;

use MaklaPlace\Core\Events\DomainEventBridge;
use MaklaPlace\Core\Events\EventBus;
use MaklaPlace\Core\Events\ListenerRegistry;
use MaklaPlace\Core\Events\Listeners\AuditListener;
use MaklaPlace\Core\Module;

defined( 'ABSPATH' ) || exit;

final class EventModule extends Module {

	public function register_services() : void {
		$this->container->singleton( EventBus::class, EventBus::class );
		$this->container->singleton( ListenerRegistry::class, ListenerRegistry::class );
		$this->container->singleton( DomainEventBridge::class, DomainEventBridge::class );
		$this->container->singleton( AuditListener::class, AuditListener::class );
	}

	public function register_hooks() : void {
	}

	public function boot() : void {
		$this->container->get( ListenerRegistry::class )->register_listener( $this->container->get( AuditListener::class ) );
		$this->container->get( DomainEventBridge::class )->register();
	}
}
