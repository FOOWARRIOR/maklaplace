<?php
/**
 * Plugin Name: MaklaPlace
 * Plugin URI: https://example.com/
 * Description: MaklaPlace transforms WordPress into a marketplace connecting customers with independent chefs.
 * Version: 0.1.0
 * Author: Yazid Bouzifi
 * Text Domain: maklaplace
 * Domain Path: /languages
 *
 * @package MaklaPlace
 */

defined( 'ABSPATH' ) || exit;

define( 'MAKLAPLACE_VERSION', '0.1.0' );
define( 'MAKLAPLACE_FILE', __FILE__ );
define( 'MAKLAPLACE_PATH', plugin_dir_path( __FILE__ ) );
define( 'MAKLAPLACE_URL', plugin_dir_url( __FILE__ ) );
define( 'MAKLAPLACE_BASENAME', plugin_basename( __FILE__ ) );

require_once MAKLAPLACE_PATH . 'includes/autoload.php';

register_activation_hook( __FILE__, array( 'MaklaPlace\\Core\\Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'MaklaPlace\\Core\\Deactivator', 'deactivate' ) );

/**
 * Bootstrap the plugin.
 *
 * @return MaklaPlace\Core\Plugin
 */
function maklaplace() : MaklaPlace\Core\Plugin {
	$plugin = new MaklaPlace\Core\Plugin();
	$plugin->run();

	return $plugin;
}

maklaplace();
