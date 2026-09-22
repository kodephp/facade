<?php

declare(strict_types=1);

namespace Kode\Facade;

use Closure;
use Kode\Facade\Exception\FacadeException;
use Psr\Container\ContainerInterface;

/**
 * 门面代理管理器
 *
 * 门面实例的**唯一权威缓存**（非上下文安全模式下）。负责：
 * 服务容器持有、门面到服务ID的绑定、实例解析与缓存、测试替身（mock / swap）。
 *
 * 缓存一致性保证：任何会改变"门面 → 实例"映射的操作
 * （setContainer / bind / bindMany / unbind / mock / clearMock）
 * 都会同步失效对应的实例缓存，杜绝拿到过期实例。
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
     * @var array<class-string, object>
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
     * 更换容器会失效所有已解析实例，避免继续返回旧容器创建的对象。
     *
     * @param ContainerInterface $container PSR-11 容器实例
     */
    public static function setContainer(ContainerInterface $container): void
    {
        if (self::$container !== $container) {
            self::$instances = [];
        }

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
     * 绑定发生变化时会失效该门面已缓存的实例。
     *
     * @template T of object
     * @param class-string<T> $facade    门面类名
     * @param string          $serviceId 服务容器中的服务ID
     */
    public static function bind(string $facade, string $serviceId): void
    {
        if ((self::$bindings[$facade] ?? null) !== $serviceId) {
            unset(self::$instances[$facade]);
        }

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
            self::bind($facade, $serviceId);
        }
    }

    /**
     * 解除门面绑定
     *
     * @param string $facade 门面类名
     */
    public static function unbind(string $facade): void
    {
        unset(self::$bindings[$facade], self::$instances[$facade]);
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
     * 用于测试场景，替换门面的实际实例。传入 Closure 时按"工厂"语义处理：
     * 每次取实例都会重新执行闭包。
     *
     * @param string         $facade 门面类名
     * @param object|Closure $mock   模拟实例，或返回实例的闭包（Closure 本身也是 object）
     */
    public static function mock(string $facade, object $mock): void
    {
        self::$mocks[$facade] = $mock;
        unset(self::$instances[$facade]);
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
     * 取门面的模拟实例（不模拟时返回 null）
     *
     * 供上下文安全模式共用同一份 mock 注册表：mock 只在这里登记，
     * 若上下文解析不读它，开启上下文模式后 mock()/unmock() 就会静默失效。
     * Closure 工厂语义与 {@see self::getInstance()} 一致——每次调用都重新执行闭包。
     *
     * @param string $facade 门面类名
     * @return object|null 已解析的模拟实例，未设置模拟时为 null
     * @throws FacadeException 闭包返回值不是对象
     */
    public static function peekMock(string $facade): ?object
    {
        if (!isset(self::$mocks[$facade])) {
            return null;
        }

        return self::resolveMock($facade);
    }

    /**
     * 直接替换门面的已解析实例
     *
     * 与 mock() 的区别：swap() 写入的是正常的实例缓存，不影响 isMocked() 判定，
     * 适合运行时热替换（如切换驱动），调用 clear() 即可回退到容器解析。
     *
     * @param string $facade   门面类名
     * @param object $instance 替换的实例
     */
    public static function swap(string $facade, object $instance): void
    {
        self::$instances[$facade] = $instance;
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

        return self::$instances[$facade] ??= self::resolveFromContainer($facade);
    }

    /**
     * 解析模拟实例
     *
     * @param string $facade 门面类名
     * @return object
     * @throws FacadeException
     */
    private static function resolveMock(string $facade): object
    {
        $mock = self::$mocks[$facade];

        if (!$mock instanceof Closure) {
            return $mock;
        }

        $resolved = $mock();

        if (!is_object($resolved)) {
            throw FacadeException::invalidInstance($facade);
        }

        return $resolved;
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
        $serviceId = self::resolveServiceId($facade);

        if ($serviceId === null) {
            throw FacadeException::unknownFacade($facade);
        }

        if (self::$container === null) {
            throw FacadeException::containerNotSet();
        }

        if (!self::$container->has($serviceId)) {
            throw FacadeException::serviceNotFound($serviceId);
        }

        $instance = self::$container->get($serviceId);

        if (!is_object($instance)) {
            throw FacadeException::invalidInstance($facade);
        }

        return $instance;
    }

    /**
     * 解析门面对应的服务ID
     *
     * 优先使用 bind() 的显式绑定（运行时覆盖），否则回退到门面自身的 id()，
     * 与上下文安全模式保持完全一致的解析来源。
     *
     * @param string $facade 门面类名
     * @return string|null 解析到的服务ID，无法解析时返回 null
     */
    public static function resolveServiceId(string $facade): ?string
    {
        if (isset(self::$bindings[$facade])) {
            return self::$bindings[$facade];
        }

        if (!is_subclass_of($facade, Facade::class)) {
            return null;
        }

        $id = $facade::getServiceId();

        return $id !== '' ? $id : null;
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
     * 获取所有已解析的实例（用于调试）
     *
     * @return array<class-string, object>
     */
    public static function getInstances(): array
    {
        return self::$instances;
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
     * 清除指定门面的模拟实例
     *
     * @param string $facade 门面类名
     */
    public static function clearMock(string $facade): void
    {
        unset(self::$mocks[$facade], self::$instances[$facade]);
    }

    /**
     * 清除所有模拟实例
     */
    public static function clearMocks(): void
    {
        foreach (array_keys(self::$mocks) as $facade) {
            unset(self::$instances[$facade]);
        }

        self::$mocks = [];
    }

    /**
     * 清除所有绑定
     */
    public static function clearBindings(): void
    {
        self::$bindings = [];
        self::$instances = [];
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
}
