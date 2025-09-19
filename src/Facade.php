<?php

declare(strict_types=1);

namespace Kode\Facade;

use Closure;
use Kode\Context\Context;
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
        if (static::$contextSafe) {
            // In context-safe mode, we check if the instance exists in current context
            // This is a simplified check - in reality, we might need a more sophisticated approach
            try {
                static::getInstance();
                return true;
            } catch (\Throwable $e) {
                return false;
            }
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
            return method_exists($instance, $method);
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
        
        // If not in context-safe mode, use the original implementation
        if (!static::$contextSafe) {
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
        
        // In context-safe mode, get fresh instance and call method directly
        $instance = static::getInstance();
        
        // Validate method exists
        if (!method_exists($instance, $method)) {
            throw FacadeException::undefinedMethod($facade, $method);
        }
        
        // Create reflection method (could be cached in future)
        $methodReflection = new ReflectionMethod($instance, $method);
        
        // Invoke method with arguments
        try {
            return $methodReflection->invokeArgs($instance, $args);
        } catch (\ReflectionException $e) {
            throw new FacadeException("Error invoking method {$method} on facade {$facade}: " . $e->getMessage(), 0, $e);
        }
    }
}