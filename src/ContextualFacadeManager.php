<?php

declare(strict_types=1);

namespace Kode\Facade;

use Kode\Context\Context;
use Kode\Facade\Exception\FacadeException;
use Psr\Container\ContainerInterface;

/**
 * 上下文安全门面管理器
 *
 * 为每个协程/上下文环境提供独立的门面实例缓存，
 * 确保在 Fiber、Swoole、Swow 等协程环境下门面实例不会相互污染。
 *
 * @package Kode\Facade
 * @author  KodePHP <382601296@qq.com>
 * @license Apache-2.0
 */
final class ContextualFacadeManager
{
    /**
     * 上下文键名前缀
     */
    private const CONTEXT_KEY_PREFIX = '__kode_facade_instances';

    /**
     * 服务容器实例
     */
    private static ?ContainerInterface $container = null;

    /**
     * 私有构造函数，防止实例化
     */
    private function __construct()
    {
    }

    /**
     * 设置服务容器
     *
     * @param ContainerInterface $container PSR-11 容器实例
     */
    public static function setContainer(ContainerInterface $container): void
    {
        self::$container = $container;
    }

    /**
     * 获取服务容器
     *
     * @return ContainerInterface
     * @throws FacadeException 如果容器未设置
     */
    public static function getContainer(): ContainerInterface
    {
        if (self::$container === null) {
            throw FacadeException::containerNotSet();
        }

        return self::$container;
    }

    /**
     * 获取门面实例
     *
     * 从当前上下文获取或创建门面实例。
     *
     * @param string $facadeClass 门面类名
     * @return object 服务实例
     * @throws FacadeException
     */
    public static function getInstance(string $facadeClass): object
    {
        $serviceId = self::getServiceId($facadeClass);
        $contextKey = self::getContextKey($facadeClass);

        $instances = Context::get($contextKey, []);

        if (isset($instances[$serviceId])) {
            return $instances[$serviceId];
        }

        $instance = self::resolveFromContainer($facadeClass, $serviceId);

        $instances[$serviceId] = $instance;
        Context::set($contextKey, $instances);

        return $instance;
    }

    /**
     * 检查门面实例是否存在于当前上下文
     *
     * @param string $facadeClass 门面类名
     * @return bool
     */
    public static function hasInstance(string $facadeClass): bool
    {
        $serviceId = self::getServiceId($facadeClass);
        $contextKey = self::getContextKey($facadeClass);

        $instances = Context::get($contextKey, []);

        return isset($instances[$serviceId]);
    }

    /**
     * 清除当前上下文的所有门面实例
     */
    public static function clearInstances(): void
    {
        Context::delete(self::CONTEXT_KEY_PREFIX);
    }

    /**
     * 清除指定门面在当前上下文的实例
     *
     * @param string $facadeClass 门面类名
     */
    public static function clearInstance(string $facadeClass): void
    {
        $contextKey = self::getContextKey($facadeClass);
        Context::delete($contextKey);
    }

    /**
     * 从容器解析服务实例
     *
     * @param string $facadeClass 门面类名
     * @param string $serviceId   服务ID
     * @return object
     * @throws FacadeException
     */
    private static function resolveFromContainer(string $facadeClass, string $serviceId): object
    {
        $container = self::getContainer();

        if (!$container->has($serviceId)) {
            throw FacadeException::noResolvedInstance($facadeClass);
        }

        $instance = $container->get($serviceId);

        if (!is_object($instance)) {
            throw FacadeException::invalidInstance($facadeClass);
        }

        return $instance;
    }

    /**
     * 获取门面的服务ID
     *
     * @param string $facadeClass 门面类名
     * @return string
     * @throws FacadeException
     */
    private static function getServiceId(string $facadeClass): string
    {
        if (!class_exists($facadeClass)) {
            throw FacadeException::unknownFacade($facadeClass);
        }

        if (!method_exists($facadeClass, 'getServiceId')) {
            throw FacadeException::unknownFacade($facadeClass);
        }

        return $facadeClass::getServiceId();
    }

    /**
     * 获取上下文存储键
     *
     * @param string $facadeClass 门面类名
     * @return string
     */
    private static function getContextKey(string $facadeClass): string
    {
        return self::CONTEXT_KEY_PREFIX . '.' . $facadeClass;
    }

    /**
     * 重置管理器状态
     *
     * @internal 用于测试
     */
    public static function reset(): void
    {
        self::$container = null;
    }
}
