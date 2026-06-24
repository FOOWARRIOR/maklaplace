<?php
/**
 * Main plugin class.
 *
 * @package MaklaPlace\Core
 */

namespace MaklaPlace\Core;

use MaklaPlace\Helpers\Logger;
use MaklaPlace\Modules\ChefModule;
use MaklaPlace\Modules\AuthModule;
use MaklaPlace\Modules\AnalyticsModule;
use MaklaPlace\Modules\MenuModule;
use MaklaPlace\Modules\OrdersModule;
use MaklaPlace\Modules\PublicModule;
use MaklaPlace\Modules\WalletModule;
use MaklaPlace\Modules\NotificationModule;
use MaklaPlace\Modules\UserModule;

use MaklaPlace\Admin\AdminModule;

defined( 'ABSPATH' ) || exit;

/**
 * Bootstraps the plugin.
 */
final class Plugin {

	/**
	 * Hook loader.
	 *
	 * @var Loader
	 */
	private Loader $loader;

	/**
	 * Service container.
	 *
	 * @var Container
	 */
	private Container $container;

	/**
	 * Plugin configuration.
	 *
	 * @var Config
	 */
	private Config $config;

	/**
	 * Registered modules.
	 *
	 * @var array<int, Module>
	 */
	private array $modules = array();

	/**
	 * Initialize the plugin.
	 */
	public function __construct() {
		$this->container = new Container();
		$this->config    = new Config(
			array(
				'version' => MAKLAPLACE_VERSION,
				'path'    => MAKLAPLACE_PATH,
				'url'     => MAKLAPLACE_URL,
				'basename'=> MAKLAPLACE_BASENAME,
				'env'     => defined( 'WP_DEBUG' ) && WP_DEBUG ? 'development' : 'production',
			)
		);

		$this->register_services();
		$this->register_modules();
		$this->loader = new Loader();
	}

	/**
	 * Run the plugin bootstrap.
	 *
	 * @return void
	 */
	public function run() : void {
		try {
			$this->boot_modules();
		} catch ( \Throwable $throwable ) {
			$logger = $this->container->has( Logger::class ) ? $this->container->get( Logger::class ) : new Logger();
			$logger->error( $throwable->getMessage() );
		}

		$this->loader->run();
	}

	/**
	 * Get the service container.
	 *
	 * @return Container
	 */
	public function container() : Container {
		return $this->container;
	}

	/**
	 * Get plugin configuration.
	 *
	 * @return Config
	 */
	public function config() : Config {
		return $this->config;
	}

	/**
	 * Register shared services.
	 *
	 * @return void
	 */
	private function register_services() : void {
		$this->container->singleton( Container::class, $this->container );
		$this->container->singleton( Config::class, $this->config );
		$this->container->singleton( Logger::class, new Logger() );
		$this->container->singleton( DatabaseManager::class, static function () {
			global $wpdb;

			return new DatabaseManager( $wpdb );
		} );
	}

	/**
	 * Register modules from one central location.
	 *
	 * @return void
	 */
	private function register_modules() : void {
		$this->modules = array(
			new CoreModule( $this->container, $this->config ),
			new UserModule( $this->container, $this->config ),
			new AuthModule( $this->container, $this->config ),
			new ChefModule( $this->container, $this->config ),
			new AnalyticsModule( $this->container, $this->config ),
			new MenuModule( $this->container, $this->config ),
			new OrdersModule( $this->container, $this->config ),
			new PublicModule( $this->container, $this->config ),
			new WalletModule( $this->container, $this->config ),
			new NotificationModule( $this->container, $this->config ),
			new AdminModule( $this->container, $this->config ),
		);
	}

	/**
	 * Boot registered modules.
	 *
	 * @return void
	 */
	private function boot_modules() : void {
		foreach ( $this->modules as $module ) {
			$module->register_services();
			$module->register_hooks();
			$module->boot();
		}
	}
}


