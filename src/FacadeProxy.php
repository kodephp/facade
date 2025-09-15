<?php

declare(strict_types=1);

namespace Kode\Facade;

use Closure;
use Kode\Facade\Exception\FacadeException;
use Psr\Container\ContainerInterface;

/**
 * Facade Proxy Manager
 *
 * This class manages the mapping between facades and their actual instances.
 */
class FacadeProxy
{
    /**
     * The container instance
     *
     * @var ContainerInterface|null
     */
    protected static ?ContainerInterface $container = null;

    /**
     * The resolved facade instances
     *
     * @var array<string, object>
     */
    protected static array $instances = [];

    /**
     * The facade to service ID mappings
     *
     * @var array<string, string>
     */
    protected static array $ids = [];

    /**
     * The facade to mock instance mappings
     *
     * @var array<string, object|Closure>
     */
    protected static array $mocks = [];

    /**
     * Set the container instance
     *
     * @param ContainerInterface $container
     * @return void
     */
    public static function setContainer(ContainerInterface $container): void
    {
        static::$container = $container;
    }

    /**
     * Get the container instance
     *
     * @return ContainerInterface|null
     */
    public static function getContainer(): ?ContainerInterface
    {
        return static::$container;
    }

    /**
     * Bind a facade to a service ID
     *
     * @template T of object
     * @param class-string<T> $facade
     * @param string $serviceId
     * @return void
     */
    public static function bind(string $facade, string $serviceId): void
    {
        static::$ids[$facade] = $serviceId;
    }

    /**
     * Mock a facade with an instance or closure
     *
     * @param string $facade
     * @param Closure|object $mock
     * @return void
     */
    public static function mock(string $facade, object $mock): void
    {
        static::$mocks[$facade] = $mock;
    }

    /**
     * Get the facade instance
     *
     * @param string $facade
     * @return object
     * @throws FacadeException
     */
    public static function getInstance(string $facade): object
    {
        // Return mock if exists
        if (isset(static::$mocks[$facade])) {
            $mock = static::$mocks[$facade];
            
            if ($mock instanceof Closure) {
                return $mock();
            }
            
            return $mock;
        }

        // Return cached instance if exists
        if (isset(static::$instances[$facade])) {
            return static::$instances[$facade];
        }

        // Check if facade is bound
        if (!isset(static::$ids[$facade])) {
            throw FacadeException::unknownFacade($facade);
        }

        // Check if container is set
        if (static::$container === null) {
            throw new FacadeException('Container not set');
        }

        // Resolve instance from container
        $serviceId = static::$ids[$facade];
        
        if (!static::$container->has($serviceId)) {
            throw FacadeException::noResolvedInstance($facade);
        }

        $instance = static::$container->get($serviceId);
        
        if (!is_object($instance)) {
            throw new FacadeException("Resolved instance for {$facade} is not an object");
        }

        // Cache the instance
        return static::$instances[$facade] = $instance;
    }

    /**
     * Clear all resolved instances
     *
     * @return void
     */
    public static function clearInstances(): void
    {
        static::$instances = [];
    }

    /**
     * Clear all mocks
     *
     * @return void
     */
    public static function clearMocks(): void
    {
        static::$mocks = [];
    }

    /**
     * Clear all bindings
     *
     * @return void
     */
    public static function clearBindings(): void
    {
        static::$ids = [];
    }

    /**
     * Clear all cached data
     *
     * @return void
     */
    public static function clearAll(): void
    {
        static::clearInstances();
        static::clearMocks();
        static::clearBindings();
    }
}