<?php

declare(strict_types=1);

namespace Kode\Facade;

use Closure;
use Kode\Facade\Exception\FacadeException;
use Psr\Container\ContainerInterface;
use ReflectionClass;

/**
 * 门面代理管理器
 *
 * 管理门面类与实际服务实例之间的映射关系，提供服务绑定、实例获取、模拟等功能。
 * 支持上下文安全模式，确保在协程环境下实例隔离。
 *
 * @package Kode\Facade
 * @author  KodePHP <382601296@qq.com>
 * @license Apache-2.0
 */
final class FacadeProxy
{
    /**
     * 服务容器实例
     */
    private static ?ContainerInterface $container = null;

    /**
     * 已解析的门面实例缓存
     *
     * @var array<string, object>
     */
    private static array $instances = [];

    /**
     * 门面类到服务ID的映射
     *
     * @var array<class-string, string>
     */
    private static array $bindings = [];

    /**
     * 门面模拟实例映射
     *
     * @var array<class-string, object>
     */
    private static array $mocks = [];

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
     * @return ContainerInterface|null
     */
    public static function getContainer(): ?ContainerInterface
    {
        return self::$container;
    }

    /**
     * 绑定门面到服务ID
     *
     * @template T of object
     * @param class-string<T> $facade    门面类名
     * @param string          $serviceId 服务容器中的服务ID
     */
    public static function bind(string $facade, string $serviceId): void
    {
        self::$bindings[$facade] = $serviceId;
    }

    /**
     * 批量绑定门面
     *
     * @param array<class-string, string> $bindings 门面绑定映射数组
     */
    public static function bindMany(array $bindings): void
    {
        foreach ($bindings as $facade => $serviceId) {
            self::$bindings[$facade] = $serviceId;
        }
    }

    /**
     * 解除门面绑定
     *
     * @param string $facade 门面类名
     */
    public static function unbind(string $facade): void
    {
        unset(self::$bindings[$facade]);
    }

    /**
     * 检查门面是否已绑定
     *
     * @param string $facade 门面类名
     * @return bool
     */
    public static function isBound(string $facade): bool
    {
        return isset(self::$bindings[$facade]);
    }

    /**
     * 获取门面对应的服务ID
     *
     * @param string $facade 门面类名
     * @return string|null 返回服务ID，未绑定则返回 null
     */
    public static function getServiceId(string $facade): ?string
    {
        return self::$bindings[$facade] ?? null;
    }

    /**
     * 获取所有门面绑定
     *
     * @return array<class-string, string>
     */
    public static function getBindings(): array
    {
        return self::$bindings;
    }

    /**
     * 模拟门面实例
     *
     * 用于测试场景，替换门面的实际实例。
     *
     * @param string $facade 门面类名
     * @param object $mock   模拟实例或返回实例的闭包
     */
    public static function mock(string $facade, object $mock): void
    {
        self::$mocks[$facade] = $mock;
    }

    /**
     * 检查门面是否被模拟
     *
     * @param string $facade 门面类名
     * @return bool
     */
    public static function isMocked(string $facade): bool
    {
        return isset(self::$mocks[$facade]);
    }

    /**
     * 获取门面实例
     *
     * 优先返回模拟实例，其次返回缓存的实例，最后从容器解析。
     *
     * @param string $facade 门面类名
     * @return object 服务实例
     * @throws FacadeException
     */
    public static function getInstance(string $facade): object
    {
        if (isset(self::$mocks[$facade])) {
            return self::resolveMock($facade);
        }

        if (isset(self::$instances[$facade])) {
            return self::$instances[$facade];
        }

        return self::resolveFromContainer($facade);
    }

    /**
     * 解析模拟实例
     *
     * @param string $facade 门面类名
     * @return object
     */
    private static function resolveMock(string $facade): object
    {
        $mock = self::$mocks[$facade];
        if ($mock instanceof Closure) {
            return $mock();
        }
        return $mock;
    }

    /**
     * 从容器解析服务实例
     *
     * @param string $facade 门面类名
     * @return object
     * @throws FacadeException
     */
    private static function resolveFromContainer(string $facade): object
    {
        if (!isset(self::$bindings[$facade])) {
            throw FacadeException::unknownFacade($facade);
        }

        if (self::$container === null) {
            throw FacadeException::containerNotSet();
        }

        $serviceId = self::$bindings[$facade];

        if (!self::$container->has($serviceId)) {
            throw FacadeException::serviceNotFound($serviceId);
        }

        $instance = self::$container->get($serviceId);

        if (!is_object($instance)) {
            throw FacadeException::invalidInstance($facade);
        }

        self::$instances[$facade] = $instance;

        return $instance;
    }

    /**
     * 检查门面实例是否已解析
     *
     * @param string $facade 门面类名
     * @return bool
     */
    public static function hasInstance(string $facade): bool
    {
        return isset(self::$instances[$facade]) || isset(self::$mocks[$facade]);
    }

    /**
     * 清除指定门面的缓存实例
     *
     * @param string $facade 门面类名
     */
    public static function clearInstance(string $facade): void
    {
        unset(self::$instances[$facade]);
    }

    /**
     * 清除所有缓存的实例
     */
    public static function clearInstances(): void
    {
        self::$instances = [];
    }

    /**
     * 清除所有模拟实例
     */
    public static function clearMocks(): void
    {
        self::$mocks = [];
    }

    /**
     * 清除所有绑定
     */
    public static function clearBindings(): void
    {
        self::$bindings = [];
    }

    /**
     * 清除所有数据（实例、模拟、绑定）
     */
    public static function clearAll(): void
    {
        self::$instances = [];
        self::$mocks = [];
        self::$bindings = [];
    }

    /**
     * 重置代理管理器状态
     *
     * @internal 用于测试
     */
    public static function reset(): void
    {
        self::$container = null;
        self::$instances = [];
        self::$mocks = [];
        self::$bindings = [];
    }

    /**
     * 检查门面是否启用了上下文安全模式
     *
     * @param string $facade 门面类名
     * @return bool
     */
    public static function isContextSafeMode(string $facade): bool
    {
        if (!class_exists($facade)) {
            return false;
        }

        try {
            $reflection = new ReflectionClass($facade);
            if (!$reflection->hasProperty('contextSafe')) {
                return false;
            }

            $property = $reflection->getProperty('contextSafe');
            $property->setAccessible(true);

            return (bool) $property->getValue(null);
        } catch (\Throwable) {
            return false;
        }
    }
}
