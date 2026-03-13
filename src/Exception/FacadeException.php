<?php

declare(strict_types=1);

namespace Kode\Facade\Exception;

use Exception;
use Psr\Container\ContainerExceptionInterface;
use Throwable;

/**
 * 门面异常类
 *
 * 提供门面操作相关的异常处理，实现 PSR 容器异常接口。
 *
 * @package Kode\Facade\Exception
 * @author  KodePHP <382601296@qq.com>
 * @license Apache-2.0
 */
final class FacadeException extends Exception implements ContainerExceptionInterface
{
    /**
     * 创建未知门面异常
     *
     * @param string $name 门面名称
     * @return static
     */
    public static function unknownFacade(string $name): static
    {
        return new static("未知的门面: {$name}");
    }

    /**
     * 创建未解析实例异常
     *
     * @param string $name 门面名称
     * @return static
     */
    public static function noResolvedInstance(string $name): static
    {
        return new static("门面 {$name} 没有解析的实例");
    }

    /**
     * 创建未定义方法异常
     *
     * @param string $name   门面名称
     * @param string $method 方法名称
     * @return static
     */
    public static function undefinedMethod(string $name, string $method): static
    {
        return new static("门面 {$name} 未定义方法 {$method}");
    }

    /**
     * 创建容器未设置异常
     *
     * @return static
     */
    public static function containerNotSet(): static
    {
        return new static("服务容器未设置，请先调用 Facade::setContainer() 方法");
    }

    /**
     * 创建服务不存在异常
     *
     * @param string $serviceId 服务ID
     * @return static
     */
    public static function serviceNotFound(string $serviceId): static
    {
        return new static("服务容器中不存在服务: {$serviceId}");
    }

    /**
     * 创建无效实例异常
     *
     * @param string $facade 门面名称
     * @return static
     */
    public static function invalidInstance(string $facade): static
    {
        return new static("门面 {$facade} 解析的实例不是有效对象");
    }

    /**
     * 创建方法调用异常
     *
     * @param string         $facade  门面名称
     * @param string         $method  方法名称
     * @param Throwable|null $previous 前一个异常
     * @return static
     */
    public static function methodInvocationFailed(string $facade, string $method, ?Throwable $previous = null): static
    {
        $message = "调用门面 {$facade} 的方法 {$method} 失败";
        if ($previous !== null) {
            $message .= ": " . $previous->getMessage();
        }
        return new static($message, 0, $previous);
    }
}
