<?php

declare(strict_types=1);

namespace Kode\Facade;

use Kode\Context\Context;
use Kode\Facade\Exception\FacadeException;
use Psr\Container\ContainerInterface;
use ReflectionMethod;
use Throwable;

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
     * Whether to use context-safe mode
     *
     * @var bool
     */
    protected static bool $contextSafe = false;

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
        // If context-safe mode is enabled, use ContextualFacadeManager
        if (static::$contextSafe) {
            return ContextualFacadeManager::getInstance(static::class);
        }
        
        $facade = static::class;
        
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
        // Set container in both proxy and context manager for backward compatibility
        FacadeProxy::setContainer($container);
        ContextualFacadeManager::setContainer($container);
    }

    /**
     * Clear the resolved instance
     *
     * @return void
     */
    public static function clear(): void
    {
        if (static::$contextSafe) {
            ContextualFacadeManager::clearInstances();
        } else {
            unset(static::$resolvedInstances[static::class]);
            FacadeProxy::clearInstances();
        }
    }

    /**
     * Clear all resolved instances
     *
     * @return void
     */
    public static function clearAll(): void
    {
        if (static::$contextSafe) {
            Context::clear();
        } else {
            static::$resolvedInstances = [];
            FacadeProxy::clearAll();
        }
    }
    
    /**
     * Get resolved instances for debugging
     *
     * @return array
     */
    public static function getResolvedInstances(): array
    {
        if (static::$contextSafe) {
            // In context-safe mode, we don't have a global cache to return
            return [];
        }
        
        return static::$resolvedInstances;
    }

    /**
     * Mock the facade with an instance or closure
     *
     * @param \Closure|object $mock
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
        if (static::$contextSafe) {
            return ContextualFacadeManager::hasInstance(static::class);
        }
        
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
            $methodReflection = new ReflectionMethod($instance, $method);
            return $methodReflection->isPublic();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Enable context-safe mode
     *
     * @return void
     */
    public static function enableContextSafeMode(): void
    {
        static::$contextSafe = true;
    }

    /**
     * Disable context-safe mode
     *
     * @return void
     */
    public static function disableContextSafeMode(): void
    {
        static::$contextSafe = false;
    }

    /**
     * Check if context-safe mode is enabled
     *
     * @return bool
     */
    public static function isContextSafeMode(): bool
    {
        return static::$contextSafe;
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

        $instance = static::getInstance();

        if (!static::$contextSafe) {
            if (!isset(static::$resolvedInstances[$facade])) {
                static::$resolvedInstances[$facade] = [$instance, []];
            }

            $methodReflections = static::$resolvedInstances[$facade][1];
            if (isset($methodReflections[$method])) {
                return static::invokeMethod($facade, $method, $methodReflections[$method], $instance, $args);
            }

            $methodReflection = new ReflectionMethod($instance, $method);
            static::$resolvedInstances[$facade][1][$method] = $methodReflection;

            return static::invokeMethod($facade, $method, $methodReflection, $instance, $args);
        }

        $methodReflection = new ReflectionMethod($instance, $method);
        return static::invokeMethod($facade, $method, $methodReflection, $instance, $args);
    }

    private static function invokeMethod(
        string $facade,
        string $method,
        ReflectionMethod $methodReflection,
        object $instance,
        array $args
    ) {
        if (!$methodReflection->isPublic()) {
            throw FacadeException::undefinedMethod($facade, $method);
        }

        try {
            return $methodReflection->invokeArgs($instance, $args);
        } catch (Throwable $e) {
            throw new FacadeException("Error invoking method {$method} on facade {$facade}: " . $e->getMessage(), 0, $e);
        }
    }
}
