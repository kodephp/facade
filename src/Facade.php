<?php

declare(strict_types=1);

namespace Kode\Facade;

use Kode\Context\Context;
use Kode\Facade\Exception\FacadeException;
use Psr\Container\ContainerInterface;
use ReflectionMethod;
use Throwable;

/**
 * 门面抽象基类
 *
 * 提供静态代理功能，将静态方法调用转发到服务容器中的实际服务实例。
 * 支持上下文安全模式，确保在协程环境下实例隔离。
 *
 * @package Kode\Facade
 * @author  KodePHP <382601296@qq.com>
 * @license Apache-2.0
 *
 * @method static mixed __callStatic(string $method, array $args)
 */
abstract class Facade
{
    /**
     * 已解析的门面实例缓存（包含方法反射缓存）
     *
     * @var array<class-string, array{0: object, 1: array<string, ReflectionMethod>}>
     */
    protected static array $resolvedInstances = [];

    /**
     * 上下文安全模式开关
     */
    protected static bool $contextSafe = false;

    /**
     * 获取门面对应的服务标识
     *
     * 子类必须实现此方法，返回服务容器中的服务ID。
     *
     * @return string 服务容器中的服务ID
     */
    abstract protected static function id(): string;

    /**
     * 获取门面实例
     *
     * 根据上下文安全模式从不同来源获取实例。
     *
     * @return object 服务实例
     * @throws FacadeException
     */
    public static function getInstance(): object
    {
        if (static::$contextSafe) {
            return ContextualFacadeManager::getInstance(static::class);
        }

        $facade = static::class;

        if (isset(static::$resolvedInstances[$facade])) {
            return static::$resolvedInstances[$facade][0];
        }

        $instance = FacadeProxy::getInstance($facade);
        static::$resolvedInstances[$facade] = [$instance, []];

        return $instance;
    }

    /**
     * 设置服务容器
     *
     * 同时设置到代理管理器和上下文管理器，确保向后兼容。
     *
     * @param ContainerInterface $container PSR-11 容器实例
     */
    public static function setContainer(ContainerInterface $container): void
    {
        FacadeProxy::setContainer($container);
        ContextualFacadeManager::setContainer($container);
    }

    /**
     * 清除当前门面的缓存实例
     */
    public static function clear(): void
    {
        if (static::$contextSafe) {
            ContextualFacadeManager::clearInstance(static::class);
        } else {
            unset(static::$resolvedInstances[static::class]);
            FacadeProxy::clearInstance(static::class);
        }
    }

    /**
     * 清除所有门面的缓存实例
     */
    public static function clearAll(): void
    {
        if (static::$contextSafe) {
            Context::clear();
        } else {
            static::$resolvedInstances = [];
            FacadeProxy::clearInstances();
        }
    }

    /**
     * 获取已解析的实例列表（用于调试）
     *
     * @return array<class-string, array{0: object, 1: array<string, ReflectionMethod>}>
     */
    public static function getResolvedInstances(): array
    {
        return static::$resolvedInstances;
    }

    /**
     * 模拟门面实例
     *
     * 用于测试场景，替换门面的实际实例。
     *
     * @param object $mock 模拟实例
     */
    public static function mock(object $mock): void
    {
        FacadeProxy::mock(static::class, $mock);
    }

    /**
     * 检查门面是否已解析
     *
     * @return bool
     */
    public static function isResolved(): bool
    {
        if (static::$contextSafe) {
            return ContextualFacadeManager::hasInstance(static::class);
        }

        return isset(static::$resolvedInstances[static::class]) || FacadeProxy::hasInstance(static::class);
    }

    /**
     * 获取门面的服务ID
     *
     * @return string
     */
    public static function getServiceId(): string
    {
        return static::id();
    }

    /**
     * 使用参数数组调用门面方法
     *
     * @param string $method 方法名
     * @param array  $args   参数数组
     * @return mixed
     * @throws FacadeException
     */
    public static function call(string $method, array $args = []): mixed
    {
        return static::__callStatic($method, $args);
    }

    /**
     * 检查门面实例上是否存在指定方法
     *
     * @param string $method 方法名
     * @return bool
     */
    public static function hasMethod(string $method): bool
    {
        try {
            $instance = static::getInstance();
            $reflection = new ReflectionMethod($instance, $method);
            return $reflection->isPublic();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * 启用上下文安全模式
     *
     * 在协程环境下，每个协程将拥有独立的实例缓存。
     */
    public static function enableContextSafeMode(): void
    {
        static::$contextSafe = true;
    }

    /**
     * 禁用上下文安全模式
     */
    public static function disableContextSafeMode(): void
    {
        static::$contextSafe = false;
    }

    /**
     * 检查是否启用了上下文安全模式
     *
     * @return bool
     */
    public static function isContextSafeMode(): bool
    {
        return static::$contextSafe;
    }

    /**
     * 处理动态静态方法调用
     *
     * 将静态调用转发到实际的服务实例。
     *
     * @param string $method 方法名
     * @param array  $args   参数数组
     * @return mixed
     * @throws FacadeException
     */
    public static function __callStatic(string $method, array $args): mixed
    {
        $facade = static::class;
        $instance = static::getInstance();

        if (!static::$contextSafe) {
            return static::invokeWithCache($facade, $method, $instance, $args);
        }

        return static::invokeWithoutCache($facade, $method, $instance, $args);
    }

    /**
     * 使用缓存调用方法
     *
     * @param string $facade  门面类名
     * @param string $method  方法名
     * @param object $instance 服务实例
     * @param array  $args    参数数组
     * @return mixed
     * @throws FacadeException
     */
    private static function invokeWithCache(string $facade, string $method, object $instance, array $args): mixed
    {
        if (!isset(static::$resolvedInstances[$facade])) {
            static::$resolvedInstances[$facade] = [$instance, []];
        }

        $methodReflections = static::$resolvedInstances[$facade][1];

        if (isset($methodReflections[$method])) {
            return static::invokeMethod($facade, $method, $methodReflections[$method], $instance, $args);
        }

        $reflection = new ReflectionMethod($instance, $method);
        static::$resolvedInstances[$facade][1][$method] = $reflection;

        return static::invokeMethod($facade, $method, $reflection, $instance, $args);
    }

    /**
     * 不使用缓存调用方法（上下文安全模式）
     *
     * @param string $facade   门面类名
     * @param string $method   方法名
     * @param object $instance 服务实例
     * @param array  $args     参数数组
     * @return mixed
     * @throws FacadeException
     */
    private static function invokeWithoutCache(string $facade, string $method, object $instance, array $args): mixed
    {
        $reflection = new ReflectionMethod($instance, $method);
        return static::invokeMethod($facade, $method, $reflection, $instance, $args);
    }

    /**
     * 执行方法调用
     *
     * @param string           $facade    门面类名
     * @param string           $method    方法名
     * @param ReflectionMethod $reflection 方法反射
     * @param object           $instance  服务实例
     * @param array            $args      参数数组
     * @return mixed
     * @throws FacadeException
     */
    private static function invokeMethod(
        string $facade,
        string $method,
        ReflectionMethod $reflection,
        object $instance,
        array $args
    ): mixed {
        if (!$reflection->isPublic()) {
            throw FacadeException::undefinedMethod($facade, $method);
        }

        try {
            return $reflection->invokeArgs($instance, $args);
        } catch (Throwable $e) {
            throw FacadeException::methodInvocationFailed($facade, $method, $e);
        }
    }
}
