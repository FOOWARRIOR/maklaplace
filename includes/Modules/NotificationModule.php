<?php
/**
 * Notification module.
 *
 * @package MaklaPlace\Modules
 */

namespace MaklaPlace\Modules;

use MaklaPlace\Core\Module;
use MaklaPlace\Core\Events\ListenerRegistry;
use MaklaPlace\Core\Events\Listeners\NotificationListener;
use MaklaPlace\Core\Notifications\ChannelRegistry;
use MaklaPlace\Core\Notifications\NotificationTemplateRegistry;
use MaklaPlace\Core\Notifications\WhatsAppProviderFactory;
use MaklaPlace\Core\Notifications\WhatsAppChannel;
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
		$this->container->singleton( ChannelRegistry::class, ChannelRegistry::class );
		$this->container->singleton( NotificationTemplateRegistry::class, NotificationTemplateRegistry::class );
		$this->container->singleton( WhatsAppProviderFactory::class, WhatsAppProviderFactory::class );
		$this->container->singleton( NotificationService::class, NotificationService::class );
		$this->container->singleton( NotificationListener::class, NotificationListener::class );
	}

	/**
	 * Register module hooks.
	 *
	 * @return void
	 */
	public function register_hooks() : void {
		$this->container->get( ListenerRegistry::class )->register_listener( $this->container->get( NotificationListener::class ) );
	}

	/**
	 * Boot the module.
	 *
	 * @return void
	 */
	public function boot() : void {
		$registry = $this->container->get( ChannelRegistry::class );
		$registry->register_channel( $this->container->get( WhatsAppChannel::class ) );
	}
}
