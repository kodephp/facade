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
 * 架构要点（基于 kode/context 3.0 的执行单元隔离 + WeakMap 自动回收）：
 * - 每个「门面 + 服务ID」在上下文中拥有**独立的键**，而非共享一个可变数组。
 *   这彻底消除了「读-改-写」共享 map 带来的竞态与类型污染（脏数据/非数组）风险。
 * - 实例解析使用 {@see Context::getOrSet()} 的原子 get-or-compute 语义：
 *   键不存在时才调用工厂解析并写入；若解析失败（容器/服务异常）异常直接向上传播，
 *   **且不会写入任何失败状态**，下一次调用会重新解析，避免缓存坏状态。
 * - 清除按「门面前缀」匹配，因此运行时改绑（服务ID 变化）后旧键也能被正确清理。
 *
 * @package Kode\Facade
 * @author  KodePHP <382601296@qq.com>
 * @license Apache-2.0
 */
final class ContextualFacadeManager
{
    /**
     * 上下文键名前缀（所有门面实例键均以此开头，便于批量清除）
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
     * 获取门面实例（原子解析，失败不缓存）
     *
     * 从当前上下文获取或创建门面实例。每个「门面 + 服务ID」使用独立键，
     * 通过 {@see Context::getOrSet()} 保证解析的原子性与失败安全性。
     *
     * @param string $facadeClass 门面类名
     * @return object 服务实例
     * @throws FacadeException
     */
    public static function getInstance(string $facadeClass): object
    {
        $serviceId = self::getServiceId($facadeClass);
        $key = self::getInstanceKey($facadeClass, $serviceId);

        // 原子 get-or-set：键不存在时解析并写入；解析抛异常则不上写入、直接传播。
        return Context::getOrSet($key, static function () use ($facadeClass, $serviceId): object {
            return self::resolveFromContainer($facadeClass, $serviceId);
        });
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
        $key = self::getInstanceKey($facadeClass, $serviceId);

        return Context::has($key);
    }

    /**
     * 清除当前上下文的所有门面实例
     *
     * 仅删除以本管理器前缀开头的键，绝不触碰上下文中其它无关数据。
     */
    public static function clearInstances(): void
    {
        $prefix = self::CONTEXT_KEY_PREFIX . '.';

        foreach (Context::keys() as $key) {
            if (str_starts_with($key, $prefix)) {
                Context::delete($key);
            }
        }
    }

    /**
     * 清除指定门面在当前上下文的实例
     *
     * 按「门面前缀」匹配，因此运行时改绑（服务ID 变化）产生的旧键也会被一并清除。
     *
     * @param string $facadeClass 门面类名
     */
    public static function clearInstance(string $facadeClass): void
    {
        $prefix = self::getInstancePrefix($facadeClass);

        foreach (Context::keys() as $key) {
            if (str_starts_with($key, $prefix)) {
                Context::delete($key);
            }
        }
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
            throw FacadeException::serviceNotFound($serviceId);
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

        // 优先使用 FacadeProxy 的显式绑定（允许运行时覆盖），
        // 否则回退到门面自身定义的 id()，与 FacadeProxy 的解析逻辑保持一致。
        $bound = FacadeProxy::getServiceId($facadeClass);
        if ($bound !== null) {
            return $bound;
        }

        if (!method_exists($facadeClass, 'getServiceId')) {
            throw FacadeException::unknownFacade($facadeClass);
        }

        return $facadeClass::getServiceId();
    }

    /**
     * 获取「门面 + 服务ID」对应的上下文存储键
     *
     * 每个服务ID对应独立键，避免共享可变数组。
     *
     * @param string $facadeClass 门面类名
     * @param string $serviceId   服务ID
     * @return string
     */
    private static function getInstanceKey(string $facadeClass, string $serviceId): string
    {
        return self::CONTEXT_KEY_PREFIX . '.' . $facadeClass . '.' . $serviceId;
    }

    /**
     * 获取门面实例键的前缀（用于批量清除）
     *
     * @param string $facadeClass 门面类名
     * @return string
     */
    private static function getInstancePrefix(string $facadeClass): string
    {
        return self::CONTEXT_KEY_PREFIX . '.' . $facadeClass . '.';
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
