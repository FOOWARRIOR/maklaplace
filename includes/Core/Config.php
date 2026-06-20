<?php
/**
 * Plugin configuration.
 *
 * @package MaklaPlace\Core
 */

namespace MaklaPlace\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Holds plugin configuration values.
 */
final class Config {

	/**
	 * Config values.
	 *
	 * @var array<string, mixed>
	 */
	private array $values;

	/**
	 * Constructor.
	 *
	 * @param array<string, mixed> $values Initial configuration values.
	 */
	public function __construct( array $values = array() ) {
		$this->values = $values;
	}

	/**
	 * Get a configuration value.
	 *
	 * @param string $key     Configuration key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public function get( string $key, mixed $default = null ) : mixed {
		return $this->values[ $key ] ?? $default;
	}

	/**
	 * Set a configuration value.
	 *
	 * @param string $key   Configuration key.
	 * @param mixed  $value Configuration value.
	 * @return void
	 */
	public function set( string $key, mixed $value ) : void {
		$this->values[ $key ] = $value;
	}

	/**
	 * Get all configuration values.
	 *
	 * @return array<string, mixed>
	 */
	public function all() : array {
		return $this->values;
	}
}
