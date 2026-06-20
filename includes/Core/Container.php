<?php
/**
 * Lightweight service container.
 *
 * @package MaklaPlace\Core
 */

namespace MaklaPlace\Core;

use Closure;
use InvalidArgumentException;
use ReflectionClass;
use RuntimeException;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and resolves services.
 */
final class Container {

	/**
	 * Service bindings.
	 *
	 * @var array<string, callable|string|object>
	 */
	private array $bindings = array();

	/**
	 * Shared service instances.
	 *
	 * @var array<string, object>
	 */
	private array $instances = array();

	/**
	 * Bind a service factory.
	 *
	 * @param string               $id      Service identifier.
	 * @param callable|string|object $concrete Service definition.
	 * @param bool                 $shared  Whether to share the resolved instance.
	 * @return void
	 */
	public function bind( string $id, callable|string|object $concrete, bool $shared = false ) : void {
		$this->bindings[ $id ] = array(
			'concrete' => $concrete,
			'shared'   => $shared,
		);
	}

	/**
	 * Register a shared service.
	 *
	 * @param string               $id      Service identifier.
	 * @param callable|string|object $concrete Service definition.
	 * @return void
	 */
	public function singleton( string $id, callable|string|object $concrete ) : void {
		$this->bind( $id, $concrete, true );
	}

	/**
	 * Check whether a service is registered.
	 *
	 * @param string $id Service identifier.
	 * @return bool
	 */
	public function has( string $id ) : bool {
		return isset( $this->bindings[ $id ] ) || isset( $this->instances[ $id ] );
	}

	/**
	 * Resolve a service from the container.
	 *
	 * @param string $id Service identifier.
	 * @return mixed
	 */
	public function get( string $id ) {
		if ( isset( $this->instances[ $id ] ) ) {
			return $this->instances[ $id ];
		}

		if ( ! isset( $this->bindings[ $id ] ) ) {
			if ( class_exists( $id ) ) {
				return $this->build( $id );
			}

			throw new InvalidArgumentException( sprintf( 'Service "%s" is not registered.', $id ) );
		}

		$binding = $this->bindings[ $id ];
		$concrete = $binding['concrete'];

		if ( is_object( $concrete ) && ! $concrete instanceof Closure ) {
			$service = $concrete;
		} elseif ( is_callable( $concrete ) ) {
			$service = $concrete( $this );
		} elseif ( is_string( $concrete ) ) {
			$service = $this->build( $concrete );
		} else {
			throw new RuntimeException( sprintf( 'Unable to resolve service "%s".', $id ) );
		}

		if ( $binding['shared'] && is_object( $service ) ) {
			$this->instances[ $id ] = $service;
		}

		return $service;
	}

	/**
	 * Build a class instance with constructor injection when possible.
	 *
	 * @param string $class Class name.
	 * @return object
	 */
	public function build( string $class ) : object {
		if ( ! class_exists( $class ) ) {
			throw new InvalidArgumentException( sprintf( 'Class "%s" does not exist.', $class ) );
		}

		$reflection = new ReflectionClass( $class );

		if ( ! $reflection->isInstantiable() ) {
			throw new RuntimeException( sprintf( 'Class "%s" is not instantiable.', $class ) );
		}

		$constructor = $reflection->getConstructor();

		if ( null === $constructor || 0 === $constructor->getNumberOfParameters() ) {
			return $reflection->newInstance();
		}

		$dependencies = array();

		foreach ( $constructor->getParameters() as $parameter ) {
			$type = $parameter->getType();

			if ( null !== $type && ! $type->isBuiltin() ) {
				$dependencies[] = $this->get( $type->getName() );
				continue;
			}

			if ( $parameter->isDefaultValueAvailable() ) {
				$dependencies[] = $parameter->getDefaultValue();
				continue;
			}

			throw new RuntimeException(
				sprintf(
					'Unable to resolve parameter "$%s" for class "%s".',
					$parameter->getName(),
					$class
				)
			);
		}

		return $reflection->newInstanceArgs( $dependencies );
	}
}
