<?php
declare(strict_types=1);
namespace Nova\Contracts;

use Closure;

/**
 * Contract for the dependency injection container.
 *
 * Core capabilities:
 * - bind/singleton/instance registration
 * - make with reflection-based autowiring and attribute-driven singleton
 * - query bindings/instances via has
 */
interface ContainerInterface
{
    /**
     * Bind an abstract to a concrete implementation.
     *
     * @param string $abstract  Identifier or class/interface name
     * @param Closure|callable|string $concrete Factory/callable or class name
     * @param bool $singleton    Whether to register as singleton
     */
    public function bind(string $abstract, Closure|callable|string $concrete, bool $singleton = false): void;

    /**
     * Register a singleton binding.
     *
     * @param string $abstract
     * @param Closure|callable|string $concrete
     */
    public function singleton(string $abstract, Closure|callable|string $concrete): void;

    /**
     * Register an existing instance.
     *
     * @param string $abstract
     * @param object $instance
     */
    public function instance(string $abstract, object $instance): void;

    /**
     * Resolve an abstract into an object.
     *
     * Supports constructor autowiring and factory callable injection.
     * Classes annotated with #[Nova\\Attributes\Singleton] will be cached as singletons.
     *
     * @param string $abstract
     * @param array $parameters Named parameter overrides for autowiring
     * @return object
     */
    public function make(string $abstract, array $parameters = []): object;

    /**
     * Determine whether an abstract is bound or instantiated.
     */
    public function has(string $abstract): bool;
}


