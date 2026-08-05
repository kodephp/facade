<?php

declare(strict_types=1);

namespace Kode\Facade\Exception;

use Exception;
use Psr\Container\ContainerExceptionInterface;

/**
 * 门面异常类
 *
 * 提供门面操作相关的异常处理，实现 PSR 容器异常接口。
 *
 * 设计原则：门面是**透明代理**，服务方法自身抛出的业务异常会原样向上传播，
 * 不会被包装成 FacadeException。本类仅描述"门面基础设施层"的失败。
 *
 * 每种失败都有稳定的错误码（见 CODE_* 常量），便于调用方按码分支处理。
 *
 * @package Kode\Facade\Exception
 * @author  KodePHP <382601296@qq.com>
 * @license Apache-2.0
 */
final class FacadeException extends Exception implements ContainerExceptionInterface
{
    /** 门面类未知或无法解析服务ID */
    public const int CODE_UNKNOWN_FACADE = 1001;

    /** 门面实例上不存在可调用的方法 */
    public const int CODE_UNDEFINED_METHOD = 1002;

    /** 服务容器未设置 */
    public const int CODE_CONTAINER_NOT_SET = 1003;

    /** 服务容器中不存在该服务 */
    public const int CODE_SERVICE_NOT_FOUND = 1004;

    /** 容器返回的不是对象 */
    public const int CODE_INVALID_INSTANCE = 1005;

    /**
     * 创建未知门面异常
     *
     * @param string $name 门面名称
     * @return self
     */
    public static function unknownFacade(string $name): self
    {
        return new self("未知的门面: {$name}", self::CODE_UNKNOWN_FACADE);
    }

    /**
     * 创建未定义方法异常
     *
     * @param string $name   门面名称
     * @param string $method 方法名称
     * @return self
     */
    public static function undefinedMethod(string $name, string $method): self
    {
        return new self(
            "门面 {$name} 对应的服务实例上不存在可调用的公共方法 {$method}",
            self::CODE_UNDEFINED_METHOD
        );
    }

    /**
     * 创建容器未设置异常
     *
     * @return self
     */
    public static function containerNotSet(): self
    {
        return new self(
            '服务容器未设置，请先调用 Facade::setContainer() 方法',
            self::CODE_CONTAINER_NOT_SET
        );
    }

    /**
     * 创建服务不存在异常
     *
     * @param string $serviceId 服务ID
     * @return self
     */
    public static function serviceNotFound(string $serviceId): self
    {
        return new self("服务容器中不存在服务: {$serviceId}", self::CODE_SERVICE_NOT_FOUND);
    }

    /**
     * 创建无效实例异常
     *
     * @param string $facade 门面名称
     * @return self
     */
    public static function invalidInstance(string $facade): self
    {
        return new self("门面 {$facade} 解析的实例不是有效对象", self::CODE_INVALID_INSTANCE);
    }
}
