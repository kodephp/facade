<?php

declare(strict_types=1);

namespace Kode\Facade;

use Closure;
use Kode\Facade\Exception\FacadeException;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionMethod;
use Throwable;

/**
 * 门面抽象基类
 *
 * 提供静态代理功能，将静态方法调用转发到服务容器中的实际服务实例。
 * 支持上下文安全模式，确保在协程环境下实例隔离。
 *
 * 设计要点：
 * - 实例缓存的**唯一权威来源**是 {@see FacadeProxy}（非上下文安全模式）/
 *   {@see ContextualFacadeManager}（上下文安全模式），本类不另设冗余缓存，
 *   杜绝双缓存导致的不一致。
 * - 门面是**透明代理**：服务方法自身抛出的业务异常原样向上传播，绝不被包装。
 * - 上下文安全模式按「每门面」独立开关，避免一个门面开启污染其它门面
 *   （PHP 的 static 属性在整个继承体系中共享，因此必须用独立映射存储）。
 *
 * @package Kode\Facade
 * @author  KodePHP <382601296@qq.com>
 * @license Apache-2.0
 */
abstract class Facade
{
    /**
     * 每门面是否启用上下文安全模式的映射
     *
     * 必须为「每门面」独立存储：PHP 的 static 属性在整个继承体系中共享，
     * 若用单一 static 属性，一个门面开启会导致所有门面被污染。
     *
     * @var array<class-string, bool>
     */
    private static array $contextSafeMap = [];

    /**
     * 方法调用缓存（实例对象 + 可调用闭包）
     *
     * 用 Closure::fromCallable([$instance, $method]) 替代反射 invokeArgs，
     * 调用开销更低；按「门面 + 方法」缓存，并以实例对象的身份（!==）判定失效，
     * 实例一旦变化（mock / swap / clear）即自动重建，兼顾性能与缓存一致性。
     *
     * @var array<class-string, array<string, array{instance: object, callable: \Closure}>>
     */
    private static array $callableCache = [];

    /**
     * 基类自身声明的公共方法名（首次用到时反射计算，之后复用）
     *
     * 这些名字在 PHP 里永远优先于 __callStatic，因此绝不会被转发到服务实例；
     * hasMethod() 必须如实回答「静态调用能不能走到服务」，不能只反射实例。
     *
     * @var array<string, true>|null
     */
    private static ?array $reservedNames = null;

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
        if (self::isContextSafeFor(static::class)) {
            return ContextualFacadeManager::getInstance(static::class);
        }

        return FacadeProxy::getInstance(static::class);
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
        // 换容器后旧实例可能整体失效，缓存的可调用闭包必须一起作废
        self::$callableCache = [];
        FacadeProxy::setContainer($container);
        ContextualFacadeManager::setContainer($container);
    }

    /**
     * 清除当前门面的缓存实例
     *
     * 两条缓存都清：swap() 在非上下文模式下只写 FacadeProxy，上下文模式下双写，
     * 若 clear() 只清单边，另一侧的替换实例会一直驻留（切换模式后即复活）。
     */
    public static function clear(): void
    {
        self::forgetCallables(static::class);
        FacadeProxy::clearInstance(static::class);
        ContextualFacadeManager::clearInstance(static::class);
    }

    /**
     * 清除所有门面的缓存实例
     */
    public static function clearAll(): void
    {
        self::$callableCache = [];
        FacadeProxy::clearInstances();
        ContextualFacadeManager::clearInstances();
    }

    /**
     * 模拟门面实例
     *
     * 用于测试场景，替换门面的实际实例。传入 Closure 时按"工厂"语义处理：
     * 每次取实例都会重新执行闭包。
     *
     * @param object|Closure $mock 模拟实例，或返回实例的闭包（Closure 本身也是 object）
     */
    public static function mock(object $mock): void
    {
        self::forgetCallables(static::class);
        FacadeProxy::mock(static::class, $mock);
    }

    /**
     * 撤销当前门面的模拟，恢复容器解析
     */
    public static function unmock(): void
    {
        self::forgetCallables(static::class);
        FacadeProxy::clearMock(static::class);
    }

    /**
     * 检查当前门面是否被模拟
     *
     * @return bool
     */
    public static function isMocked(): bool
    {
        return FacadeProxy::isMocked(static::class);
    }

    /**
     * 绑定当前门面到服务ID（门面自绑定）
     *
     * 等价于 FacadeProxy::bind(static::class, $serviceId)，
     * 让门面在业务代码中即可完成绑定，无需额外引用 FacadeProxy。
     *
     * @param string $serviceId 服务容器中的服务ID
     */
    public static function bind(string $serviceId): void
    {
        self::forgetCallables(static::class);
        FacadeProxy::bind(static::class, $serviceId);
    }

    /**
     * 解除当前门面的绑定
     */
    public static function unbind(): void
    {
        self::forgetCallables(static::class);
        FacadeProxy::unbind(static::class);
    }

    /**
     * 运行时热替换当前门面的已解析实例
     *
     * 等价于 FacadeProxy::swap(static::class, $instance)：写入正常实例缓存，
     * 不影响 isMocked() 判定；调用 clear() 即可回退到容器解析。
     * 适用于运行时切换驱动等场景。
     *
     * @param object $instance 替换的实例
     */
    public static function swap(object $instance): void
    {
        self::forgetCallables(static::class);
        FacadeProxy::swap(static::class, $instance);

        // 上下文安全模式下实例缓存住在执行单元里，只写代理缓存的话本次调用看不到新实例。
        if (self::isContextSafeFor(static::class)) {
            ContextualFacadeManager::setInstance(static::class, $instance);
        }
    }

    /**
     * 检查门面是否已解析
     *
     * @return bool
     */
    public static function isResolved(): bool
    {
        if (self::isContextSafeFor(static::class)) {
            return ContextualFacadeManager::hasInstance(static::class);
        }

        return FacadeProxy::hasInstance(static::class);
    }

    /**
     * 获取门面实际生效的服务ID
     *
     * 与实例解析逻辑保持一致：显式绑定（FacadeProxy::bind）优先于门面自身 id()，
     * 因此返回的是「真正用于解析容器服务的ID」，而非总是 id()。
     *
     * @return string
     */
    public static function getServiceId(): string
    {
        return FacadeProxy::getServiceId(static::class) ?? static::id();
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
        if (self::isReservedName($method)) {
            return false;
        }

        try {
            return is_callable([static::getInstance(), $method]);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * 启用上下文安全模式（仅对当前门面生效）
     *
     * 在协程环境下，每个协程将拥有独立的实例缓存。
     */
    public static function enableContextSafeMode(): void
    {
        self::$contextSafeMap[static::class] = true;
    }

    /**
     * 禁用上下文安全模式（仅对当前门面生效）
     */
    public static function disableContextSafeMode(): void
    {
        self::$contextSafeMap[static::class] = false;
    }

    /**
     * 检查当前门面是否启用了上下文安全模式
     *
     * @return bool
     */
    public static function isContextSafeMode(): bool
    {
        return self::isContextSafeFor(static::class);
    }

    /**
     * 处理动态实例方法调用
     *
     * 允许以「实例方式」使用门面，将实例方法调用转发到服务实例。
     *
     * @param string $method 方法名
     * @param array  $args   参数数组
     * @return mixed
     * @throws FacadeException
     */
    public function __call(string $method, array $args): mixed
    {
        return static::__callStatic($method, $args);
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
        $instance = static::getInstance();

        return self::callInstance($method, $instance, $args);
    }

    /**
     * 转发调用到实例方法（透明代理 + 可调用性缓存）
     *
     * 用 {@see Closure::fromCallable()} 直接调用，比反射 invokeArgs 更快；
     * 闭包绑定到具体实例对象，一旦实例变化（mock / swap / clear）即自动失效重建，
     * 因此在上下文安全模式下跨协程也天然安全。
     *
     * 仅对「方法不可调用（非 public 且无 __call 兜底）」这一基础设施问题抛出
     * FacadeException；服务方法自身的业务异常原样向上传播，不做任何包装。
     *
     * @param string $method   方法名
     * @param object $instance 服务实例
     * @param array  $args     参数数组
     * @return mixed
     * @throws FacadeException
     */
    private static function callInstance(string $method, object $instance, array $args): mixed
    {
        $facade = static::class;

        // PHP 把 `__` 前缀保留给魔术方法。服务实例上的 __construct / __clone /
        // __destruct / __serialize 都能被 Closure::fromCallable 取到并带上任意参数，
        // 等于让调用方在代理之外重跑构造函数（绕过初始化约束、改写内部状态）。
        // 门面只转发业务方法，魔术名一律视为不可达。
        if (str_starts_with($method, '__')) {
            throw FacadeException::magicMethod($facade, $method);
        }

        $entry = self::$callableCache[$facade][$method] ?? null;

        if ($entry === null || $entry['instance'] !== $instance) {
            if (!is_callable([$instance, $method])) {
                throw FacadeException::undefinedMethod($facade, $method);
            }

            $entry = [
                'instance' => $instance,
                'callable' => Closure::fromCallable([$instance, $method]),
            ];
            self::$callableCache[$facade][$method] = $entry;
        }

        return $entry['callable'](...$args);
    }

    /**
     * 丢弃某门面的可调用缓存
     *
     * 缓存条目按「门面 + 方法」持有实例对象的强引用，且只在同门面下次解析时才会被
     * 覆盖；公开 API（clear / swap / mock / bind / setContainer）改绑或替换实例后
     * 若不主动剪枝，旧服务对象会被永久钉住无法回收。
     *
     * @param string $facade 门面类名
     */
    private static function forgetCallables(string $facade): void
    {
        unset(self::$callableCache[$facade]);
    }

    /**
     * 名字是否被门面基类自身占用（永远不会转发到服务实例）
     *
     * @param string $method 方法名
     * @return bool
     */
    private static function isReservedName(string $method): bool
    {
        if (str_starts_with($method, '__')) {
            return true;
        }

        if (self::$reservedNames === null) {
            $names = [];

            foreach ((new ReflectionClass(self::class))->getMethods(ReflectionMethod::IS_PUBLIC) as $reflection) {
                $names[$reflection->getName()] = true;
            }

            self::$reservedNames = $names;
        }

        return isset(self::$reservedNames[$method]);
    }

    /**
     * 当前门面是否处于上下文安全模式
     *
     * @param string $facade 门面类名
     * @return bool
     */
    private static function isContextSafeFor(string $facade): bool
    {
        return self::$contextSafeMap[$facade] ?? false;
    }

    /**
     * 重置门面状态（清空上下文安全模式映射）
     *
     * @internal 用于测试隔离
     */
    public static function resetState(): void
    {
        self::$contextSafeMap = [];
        self::$callableCache = [];
    }
}
