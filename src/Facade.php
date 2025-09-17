<?php

declare(strict_types=1);

namespace Kode\Facade;

use Closure;
use Kode\Facade\Exception\FacadeException;
use Psr\Container\ContainerInterface;
use ReflectionMethod;

/**
 * Facade Abstract Class
 *
 * This class provides the static proxy functionality for services in the container.
 *
 * @method static mixed __callStatic(string $method, array $args)
 */
abstract class Facade
{
    /**
     * The resolved facade instances with method reflection cache
     *
     * @var array<string, array{object, array<string, ReflectionMethod>}>
     */
    protected static array $resolvedInstances = [];

    /**
     * Get the facade identifier
     *
     * @return string
     */
    abstract protected static function id(): string;

    /**
     * Get the instance of the facade
     *
     * @return object
     * @throws FacadeException
     */
    public static function getInstance(): object
    {
        $facade = static::class;
        $id = static::id();
        
        // Return cached instance if exists
        if (isset(static::$resolvedInstances[$facade])) {
            return static::$resolvedInstances[$facade][0];
        }
        
        // Get instance from proxy
        $instance = FacadeProxy::getInstance($facade);
        
        // Initialize method reflections array
        $methodReflections = [];
        
        // Store resolved instance with empty method reflections
        static::$resolvedInstances[$facade] = [$instance, $methodReflections];
        
        return $instance;
    }

    /**
     * Set the container instance
     *
     * @param ContainerInterface $container
     * @return void
     */
    public static function setContainer(ContainerInterface $container): void
    {
        FacadeProxy::setContainer($container);
    }

    /**
     * Clear the resolved instance
     *
     * @return void
     */
    public static function clear(): void
    {
        unset(static::$resolvedInstances[static::class]);
        FacadeProxy::clearInstances();
    }

    /**
     * Clear all resolved instances
     *
     * @return void
     */
    public static function clearAll(): void
    {
        static::$resolvedInstances = [];
        FacadeProxy::clearAll();
    }
    
    /**
     * Get resolved instances for debugging
     *
     * @return array
     */
    public static function getResolvedInstances(): array
    {
        return static::$resolvedInstances;
    }

    /**
     * Mock the facade with an instance or closure
     *
     * @param Closure|object $mock
     * @return void
     */
    public static function mock(object $mock): void
    {
        FacadeProxy::mock(static::class, $mock);
    }

    /**
     * Check if the facade has been resolved
     *
     * @return bool
     */
    public static function isResolved(): bool
    {
        return isset(static::$resolvedInstances[static::class]);
    }

    /**
     * Get the service ID for this facade
     *
     * @return string
     */
    public static function getServiceId(): string
    {
        return static::id();
    }

    /**
     * Call a method on the facade instance with an array of arguments
     *
     * @param string $method
     * @param array $args
     * @return mixed
     * @throws FacadeException
     */
    public static function call(string $method, array $args = [])
    {
        return static::__callStatic($method, $args);
    }

    /**
     * Check if a method exists on the facade instance
     *
     * @param string $method
     * @return bool
     */
    public static function hasMethod(string $method): bool
    {
        try {
            $instance = static::getInstance();
            return method_exists($instance, $method);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Handle dynamic static calls
     *
     * @param string $method
     * @param array $args
     * @return mixed
     * @throws FacadeException
     */
    public static function __callStatic(string $method, array $args)
    {
        $facade = static::class;
        
        // Ensure instance is resolved
        if (!isset(static::$resolvedInstances[$facade])) {
            static::getInstance();
        }
        
        [$instance, $methodReflections] = static::$resolvedInstances[$facade];
        
        // Check if method reflection is cached
        if (!isset($methodReflections[$method])) {
            // Validate method exists
            if (!method_exists($instance, $method)) {
                throw FacadeException::undefinedMethod($facade, $method);
            }
            
            // Cache method reflection
            $methodReflections[$method] = new ReflectionMethod($instance, $method);
            static::$resolvedInstances[$facade][1] = $methodReflections;
        }
        
        // Invoke method with arguments
        try {
            return $methodReflections[$method]->invokeArgs($instance, $args);
        } catch (\ReflectionException $e) {
            throw new FacadeException("Error invoking method {$method} on facade {$facade}: " . $e->getMessage(), 0, $e);
        }
    }
}