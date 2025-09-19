<?php

declare(strict_types=1);

namespace Kode\Facade;

use Kode\Context\Context;
use Psr\Container\ContainerInterface;

/**
 * 上下文安全的门面管理器
 * 
 * 该类为每个协程/上下文环境提供独立的门面实例缓存，
 * 确保在fiber、swoole等协程环境下门面实例不会相互污染。
 */
class ContextualFacadeManager
{
    /**
     * 上下文键名
     */
    private const CONTEXT_KEY = '__kode_facade_instances';

    /**
     * 容器实例
     */
    private static ?ContainerInterface $container = null;

    /**
     * 设置服务容器
     *
     * @param ContainerInterface $container
     * @return void
     */
    public static function setContainer(ContainerInterface $container): void
    {
        self::$container = $container;
    }

    /**
     * 获取服务容器
     *
     * @return ContainerInterface
     * @throws \RuntimeException
     */
    public static function getContainer(): ContainerInterface
    {
        if (self::$container === null) {
            throw new \RuntimeException('Facade container has not been set.');
        }

        return self::$container;
    }

    /**
     * 获取门面实例
     *
     * @param string $facadeClass
     * @return object
     */
    public static function getInstance(string $facadeClass): object
    {
        // 获取当前上下文的实例缓存
        $instances = self::getContextInstances($facadeClass);
        
        $serviceId = $facadeClass::getServiceId();
        
        // 如果当前上下文没有缓存该实例，则从容器获取并缓存
        if (!isset($instances[$serviceId])) {
            $instances[$serviceId] = self::getContainer()->get($serviceId);
            self::setContextInstances($instances, $facadeClass);
        }

        return $instances[$serviceId];
    }

    /**
     * 清除当前上下文的所有门面实例
     *
     * @return void
     */
    public static function clearInstances(): void
    {
        // We can't easily clear instances for all facade classes
        // In a real implementation, we might want to keep track of all facade classes
        // For now, we'll just clear the context entirely
        Context::clear();
    }

    /**
     * 获取当前上下文的实例缓存
     *
     * @param string $facadeClass
     * @return array
     */
    private static function getContextInstances(string $facadeClass): array
    {
        $contextKey = self::CONTEXT_KEY . '.' . $facadeClass;
        return Context::get($contextKey, []);
    }

    /**
     * 设置当前上下文的实例缓存
     *
     * @param array $instances
     * @param string $facadeClass
     * @return void
     */
    private static function setContextInstances(array $instances, string $facadeClass): void
    {
        $contextKey = self::CONTEXT_KEY . '.' . $facadeClass;
        Context::set($contextKey, $instances);
    }
}