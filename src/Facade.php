<?php
declare(strict_types=1);
namespace Nova;

use Nova\Context\ContextStorage;
use Nova\Contracts\ContainerInterface;

/**
 * Base Facade class providing static proxy to services resolved from Container.
 * - Context-aware: resolved instances are cached per coroutine/fiber/process via ContextStorage
 * - Safe fallback: works in CLI/FPM when advanced runtimes are absent
 */
abstract class Facade
{
    /**
     * Return the accessor (abstract ID or class name) to resolve from container.
     */
    protected static function getFacadeAccessor(): string
    {
        throw new \RuntimeException('Facade accessor not defined.');
    }

    /**
     * Set the global container instance used by facades.
     */
    public static function setContainer(?ContainerInterface $container): void
    {
        Container::setInstance($container);
    }

    /**
     * Get the global container instance used by facades.
     */
    public static function getContainer(): ContainerInterface
    {
        return Container::getInstance();
    }

    /**
     * Build the context key for current facade accessor.
     */
    protected static function resolvedKey(?string $accessor = null): string
    {
        $acc = $accessor ?? static::getFacadeAccessor();
        return 'facade.resolved.' . $acc;
    }

    /**
     * Resolve instance from container and cache in current context.
     */
    protected static function resolveInstance(): object
    {
        $accessor = static::getFacadeAccessor();

        $key = static::resolvedKey($accessor);
        $instance = ContextStorage::get($key);
        if ($instance) {
            return $instance;
        }

        $container = static::getContainer();
        $instance = $container->make($accessor);
        ContextStorage::set($key, $instance);
        return $instance;
    }

    /**
     * Forget the resolved instance for current facade (or a specific accessor) in current context only.
     */
    public static function clearResolved(?string $accessor = null): void
    {
        ContextStorage::delete(static::resolvedKey($accessor));
    }

    /**
     * Forget all resolved facade instances in current context only.
     */
    public static function clearResolvedAll(): void
    {
        ContextStorage::clearByPrefix('facade.resolved.');
    }

    /**
     * Proxy static calls to the resolved instance.
     * @throws \BadMethodCallException when the target method does not exist
     */
    public static function __callStatic(string $method, array $args): mixed
    {
        $instance = static::resolveInstance();
        if (!method_exists($instance, $method)) {
            throw new \BadMethodCallException(sprintf('%s::%s does not exist', static::class, $method));
        }
        return $instance->$method(...$args);
    }
}



