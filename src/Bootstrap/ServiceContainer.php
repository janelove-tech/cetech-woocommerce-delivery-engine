<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Bootstrap;

/**
 * Minimal dependency container for plugin services.
 */
final class ServiceContainer {

	/** @var array<string, mixed> */
	private array $bindings = [];

	/** @var array<string, mixed> */
	private array $instances = [];

	/** @var array<string, true> */
	private array $singletons = [];

	/**
	 * @param callable(ServiceContainer): mixed $factory
	 */
	public function bind( string $id, callable $factory ): void {
		$this->bindings[ $id ] = $factory;
		unset( $this->instances[ $id ], $this->singletons[ $id ] );
	}

	/**
	 * @param callable(ServiceContainer): mixed $factory
	 */
	public function singleton( string $id, callable $factory ): void {
		$this->singletons[ $id ] = true;
		$this->bindings[ $id ]    = $factory;
	}

	public function has( string $id ): bool {
		return array_key_exists( $id, $this->bindings ) || array_key_exists( $id, $this->instances );
	}

	public function get( string $id ): mixed {
		if ( array_key_exists( $id, $this->instances ) ) {
			return $this->instances[ $id ];
		}

		if ( ! array_key_exists( $id, $this->bindings ) ) {
			throw new \InvalidArgumentException(
				sprintf( 'Service "%s" is not registered in the container.', $id )
			);
		}

		$factory = $this->bindings[ $id ];
		$value   = $factory( $this );

		if ( isset( $this->singletons[ $id ] ) ) {
			$this->instances[ $id ] = $value;
		}

		return $value;
	}
}
